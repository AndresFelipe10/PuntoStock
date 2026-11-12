<?php

namespace App\Services\Restaurant;

use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SalePayment;
use App\Models\User;
use App\Http\Requests\Concerns\NormalizesCopMonetaryFields;
use App\Services\Inventory\InventoryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestaurantOrderService
{
    use NormalizesCopMonetaryFields;

    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Open a new order for a table.
     */
    public function openTableOrder(int $businessId, int $userId, array $data): RestaurantOrder
    {
        return DB::transaction(function () use ($businessId, $userId, $data) {
            $table = RestaurantTable::where('business_id', $businessId)
                ->lockForUpdate()
                ->findOrFail($data['table_id']);

            if ($table->status !== 'available') {
                throw ValidationException::withMessages([
                    'table_id' => 'La mesa seleccionada ya se encuentra ocupada.',
                ]);
            }

            $orderNumber = $this->generateOrderNumber($businessId);

            if (! empty($data['items']) && is_array($data['items'])) {
                $this->validateStandardProductsStock($businessId, $data['items']);
            }

            $order = RestaurantOrder::create([
                'business_id' => $businessId,
                'table_id' => $table->id,
                'user_id' => $userId,
                'order_number' => $orderNumber,
                'order_type' => 'table',
                'status' => 'open',
                'customer_name' => $data['customer_name'] ?? null,
                'guest_count' => ! empty($data['guest_count']) ? (int) $data['guest_count'] : null,
                'notes' => $data['notes'] ?? null,
                'subtotal' => 0.00,
                'total' => 0.00,
            ]);

            $table->update(['status' => 'occupied']);

            if (! empty($data['items']) && is_array($data['items'])) {
                $this->addItemsToOrder($order, $data['items']);
            }

            return $order->fresh(['table', 'items.product', 'user']);
        });
    }

    /**
     * Add dishes or products to an active order.
     *
     * @return Collection<int, RestaurantOrderItem>
     */
    public function addItemsToOrder(RestaurantOrder $order, array $itemsData): Collection
    {
        if (! $order->canBeModified()) {
            throw ValidationException::withMessages([
                'order' => 'No se pueden agregar platos a una comanda que ya ha sido cerrada y facturada.',
            ]);
        }

        return DB::transaction(function () use ($order, $itemsData) {
            $lockedOrder = RestaurantOrder::where('business_id', $order->business_id)
                ->lockForUpdate()
                ->findOrFail($order->id);

            if (! $lockedOrder->canBeModified()) {
                throw ValidationException::withMessages([
                    'order' => 'No se pueden agregar platos a una comanda que ya ha sido cerrada y facturada.',
                ]);
            }

            $this->validateStandardProductsStock($order->business_id, $itemsData, $lockedOrder);

            // Determinar tanda (batch_number)
            $hasPrintedItems = $lockedOrder->items()
                ->where('printed_to_kitchen', true)
                ->where('status', '!=', 'cancelled')
                ->exists();

            $maxBatch = (int) ($lockedOrder->items()->max('batch_number') ?? 0);

            if ($hasPrintedItems) {
                $batchNumber = max(1, $maxBatch + 1);
            } else {
                $batchNumber = max(1, $maxBatch);
            }

            $createdItems = new Collection();

            foreach ($itemsData as $itemData) {
                $product = Product::where('business_id', $order->business_id)
                    ->findOrFail($itemData['product_id']);

                $quantity = round((float) $itemData['quantity'], 3);
                $unitPrice = round((float) $product->sale_price, 2);
                $subtotal = round($quantity * $unitPrice, 2);

                $orderItem = RestaurantOrderItem::create([
                    'business_id' => $order->business_id,
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'notes' => ! empty($itemData['notes']) ? trim($itemData['notes']) : null,
                    'status' => 'kitchen',
                    'printed_to_kitchen' => true,
                    'batch_number' => $batchNumber,
                ]);

                $createdItems->push($orderItem);
            }

            // Si la comanda tenía pre-cuenta emitida, limpiar marcador y reactivar en cocina
            $orderUpdates = [];
            if (! empty($lockedOrder->precount_printed_at)) {
                $orderUpdates['precount_printed_at'] = null;
            }
            if (in_array($lockedOrder->status, ['open', 'precount'], true) || empty($lockedOrder->status)) {
                $orderUpdates['status'] = 'in_kitchen';
            }
            if (! empty($orderUpdates)) {
                $lockedOrder->update($orderUpdates);
            }

            if ($lockedOrder->table_id) {
                $table = RestaurantTable::where('business_id', $lockedOrder->business_id)
                    ->lockForUpdate()
                    ->find($lockedOrder->table_id);
                if ($table && $table->status !== 'occupied') {
                    $table->update(['status' => 'occupied']);
                }
            }

            $lockedOrder->recalculateTotals();
            $order->refresh();

            return $createdItems;
        });
    }

    /**
     * Mark pending items as printed to kitchen and advance status.
     *
     * @return Collection<int, RestaurantOrderItem>
     */
    public function sendToKitchen(RestaurantOrder $order): Collection
    {
        return DB::transaction(function () use ($order) {
            $pendingItems = $order->items()
                ->where('printed_to_kitchen', false)
                ->where('status', '!=', 'cancelled')
                ->with('product')
                ->lockForUpdate()
                ->get();

            if ($pendingItems->isNotEmpty()) {
                foreach ($pendingItems as $item) {
                    $item->update([
                        'printed_to_kitchen' => true,
                        'status' => 'kitchen',
                    ]);
                }

                if ($order->status === 'open') {
                    $order->update(['status' => 'in_kitchen']);
                }
            }

            return $pendingItems;
        });
    }

    /**
     * Open a new delivery or takeout order.
     */
    public function openDeliveryOrTakeoutOrder(int $businessId, int $userId, array $data): RestaurantOrder
    {
        return DB::transaction(function () use ($businessId, $userId, $data) {
            $orderType = $data['order_type'] ?? 'delivery';
            $deliveryFee = $orderType === 'takeout' ? 0.00 : round((float) ($data['delivery_fee'] ?? 0.00), 2);

            if ($deliveryFee < 0) {
                throw ValidationException::withMessages([
                    'delivery_fee' => 'El costo de envío no puede ser negativo.',
                ]);
            }

            $customerName = $data['customer_name'] ?? null;
            $deliveryPhone = $data['delivery_phone'] ?? null;
            $deliveryAddress = $data['delivery_address'] ?? null;

            if (! empty($data['customer_id'])) {
                $customer = \App\Models\Customer::where('business_id', $businessId)->find($data['customer_id']);
                if ($customer) {
                    $customerName = $customerName ?: $customer->name;
                    $deliveryPhone = $deliveryPhone ?: $customer->phone;
                    $deliveryAddress = $deliveryAddress ?: $customer->address;
                }
            }

            $orderNumber = $this->generateOrderNumber($businessId);

            if (! empty($data['items']) && is_array($data['items'])) {
                $this->validateStandardProductsStock($businessId, $data['items']);
            }

            $order = RestaurantOrder::create([
                'business_id' => $businessId,
                'table_id' => null,
                'user_id' => $userId,
                'order_number' => $orderNumber,
                'order_type' => $orderType,
                'status' => 'open',
                'customer_id' => $data['customer_id'] ?? null,
                'customer_name' => $customerName,
                'delivery_phone' => $deliveryPhone,
                'delivery_address' => $deliveryAddress,
                'delivery_notes' => $data['delivery_notes'] ?? null,
                'delivery_fee' => $deliveryFee,
                'notes' => $data['notes'] ?? null,
                'subtotal' => 0.00,
                'total' => $deliveryFee,
            ]);

            if (! empty($data['items']) && is_array($data['items'])) {
                $this->addItemsToOrder($order, $data['items']);
            }

            return $order->fresh(['items.product', 'user', 'customer']);
        });
    }

    /**
     * Validate that standard merchandise products have enough stock available before committing items.
     * Non-standard products (dish, menu, plate) are completely ignored.
     * Committed stock in active pending orders is accounted for.
     *
     * @param int $businessId
     * @param array $itemsData
     * @param RestaurantOrder|null $order
     * @throws InsufficientStockException
     */
    public function validateStandardProductsStock(int $businessId, array $itemsData, ?RestaurantOrder $order = null): void
    {
        if (empty($itemsData)) {
            return;
        }

        // 1. Agrupar y sumar las cantidades solicitadas por product_id
        $groupedQuantities = [];
        foreach ($itemsData as $item) {
            if (empty($item['product_id'])) {
                continue;
            }
            $productId = (int) $item['product_id'];
            $qty = (float) ($item['quantity'] ?? 0);
            if ($qty > 0) {
                $groupedQuantities[$productId] = ($groupedQuantities[$productId] ?? 0) + $qty;
            }
        }

        if (empty($groupedQuantities)) {
            return;
        }

        $standardProducts = Product::where('business_id', $businessId)
            ->whereIn('id', array_keys($groupedQuantities))
            ->get()
            ->filter(fn($p) => $p->isStandard())
            ->keyBy('id');

        if ($standardProducts->isEmpty()) {
            return;
        }

        Product::attachAvailableStock($standardProducts, $businessId);

        foreach ($standardProducts as $productId => $product) {
            $requestedQty = (float) ($groupedQuantities[$productId] ?? 0);
            $availableStock = (float) $product->available_stock;

            if ($requestedQty > $availableStock) {
                throw new InsufficientStockException(
                    availableStock: $availableStock,
                    requestedQuantity: $requestedQty,
                    productName: $product->name
                );
            }
        }
    }

    /**
     * Update delivery operational status (in_kitchen, dispatched, delivered, cancelled).
     */
    public function updateDeliveryStatus(RestaurantOrder $order, string $newStatus): RestaurantOrder
    {
        return DB::transaction(function () use ($order, $newStatus) {
            $lockedOrder = RestaurantOrder::where('business_id', $order->business_id)
                ->lockForUpdate()
                ->findOrFail($order->id);

            $validStatuses = ['open', 'in_kitchen', 'dispatched', 'delivered', 'cancelled'];
            if (! in_array($newStatus, $validStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => 'Estado de pedido no válido.',
                ]);
            }

            if (! $lockedOrder->canBeModified()) {
                throw ValidationException::withMessages([
                    'order' => 'No se puede cambiar el estado de un pedido que ya fue facturado, cerrado o anulado.',
                ]);
            }

            if ($newStatus === 'cancelled') {
                if ($lockedOrder->hasPartialPayments() || $lockedOrder->paidAmount() > 0) {
                    throw ValidationException::withMessages([
                        'order' => 'No se puede anular un pedido con pagos registrados.',
                    ]);
                }

                if ($lockedOrder->table_id) {
                    $table = RestaurantTable::where('business_id', $lockedOrder->business_id)
                        ->lockForUpdate()
                        ->find($lockedOrder->table_id);
                    if ($table) {
                        $table->update(['status' => 'available']);
                    }
                }
            }

            $updateData = ['status' => $newStatus];
            if ($newStatus === 'delivered' && empty($lockedOrder->delivered_at)) {
                $updateData['delivered_at'] = now();
            }

            $lockedOrder->update($updateData);

            return $lockedOrder->fresh();
        });
    }

    /**
     * Mark a takeout order as directly picked up by customer (status: delivered).
     */
    public function pickupOrder(RestaurantOrder $order): RestaurantOrder
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = RestaurantOrder::where('business_id', $order->business_id)
                ->lockForUpdate()
                ->findOrFail($order->id);

            if (! in_array($lockedOrder->order_type, ['takeout', 'takeaway'], true)) {
                throw ValidationException::withMessages([
                    'order' => 'Solo los pedidos para llevar pueden marcarse como recogidos.',
                ]);
            }

            if (! $lockedOrder->canBeModified()) {
                throw ValidationException::withMessages([
                    'order' => 'No se puede cambiar el estado de un pedido que ya fue facturado, cerrado o anulado.',
                ]);
            }

            $lockedOrder->update([
                'status' => 'delivered',
                'delivered_at' => now(),
            ]);

            return $lockedOrder->fresh();
        });
    }

    /**
     * Cancel an empty order and release its assigned table.
     */
    public function cancelEmptyOrder(RestaurantOrder $order): RestaurantOrder
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = RestaurantOrder::where('business_id', $order->business_id)
                ->lockForUpdate()
                ->findOrFail($order->id);

            $lockedTable = null;
            if ($lockedOrder->table_id) {
                $lockedTable = RestaurantTable::where('business_id', $lockedOrder->business_id)
                    ->lockForUpdate()
                    ->find($lockedOrder->table_id);
            }

            if ($lockedOrder->status !== 'open') {
                throw ValidationException::withMessages([
                    'order' => 'Solo se pueden cancelar comandas en estado abierto.',
                ]);
            }

            if ($lockedOrder->items()->count() > 0) {
                throw ValidationException::withMessages([
                    'order' => 'No se puede cancelar una comanda que contiene platos registrados. Anule o elimine los consumos primero.',
                ]);
            }

            $lockedOrder->update(['status' => 'cancelled']);

            if ($lockedTable) {
                $lockedTable->update(['status' => 'available']);
            }

            return $lockedOrder->fresh();
        });
    }

    /**
     * Remove an individual item or reduce its quantity in an active order, restore stock, and recalculate totals.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function removeItemFromOrder(
        RestaurantOrder $order,
        RestaurantOrderItem $item,
        string $reason,
        User $admin,
        float|int|null $quantityToRemove = null
    ): RestaurantOrder {
        if (! $admin->isCurrentAdmin()) {
            throw new AuthorizationException('Esta acción es de acceso exclusivo para administradores.');
        }

        if (! $order->canBeModified()) {
            throw ValidationException::withMessages([
                'order' => 'No se pueden eliminar platos de una comanda que ya fue cobrada, facturada o anulada.',
            ]);
        }

        if ((int) $item->order_id !== (int) $order->id) {
            throw ValidationException::withMessages([
                'item' => 'El plato no pertenece a la comanda especificada.',
            ]);
        }

        if ($item->isCancelled()) {
            throw ValidationException::withMessages([
                'item' => 'Este ítem ya ha sido cancelado previamente.',
            ]);
        }

        if ($item->isPaid()) {
            throw ValidationException::withMessages([
                'item' => 'No se puede eliminar ni reducir un plato que ya ha sido pagado y facturado.',
            ]);
        }

        return DB::transaction(function () use ($order, $item, $reason, $admin, $quantityToRemove) {
            $lockedItem = RestaurantOrderItem::lockForUpdate()->findOrFail($item->id);
            $lockedOrder = RestaurantOrder::lockForUpdate()->findOrFail($order->id);

            if (! $lockedOrder->canBeModified()) {
                throw ValidationException::withMessages([
                    'order' => 'No se pueden eliminar platos de una comanda que ya fue cobrada, facturada o anulada.',
                ]);
            }

            if ($lockedItem->isCancelled()) {
                throw ValidationException::withMessages([
                    'item' => 'Este ítem ya ha sido cancelado previamente.',
                ]);
            }

            if ($lockedItem->isPaid()) {
                throw ValidationException::withMessages([
                    'item' => 'No se puede eliminar ni reducir un plato que ya ha sido pagado y facturado.',
                ]);
            }

            $currentQty = (float) $lockedItem->quantity;
            $qtyToRemove = $quantityToRemove !== null ? (float) $quantityToRemove : $currentQty;

            if ($qtyToRemove <= 0) {
                throw ValidationException::withMessages([
                    'quantity_to_remove' => 'La cantidad a retirar debe ser mayor a cero.',
                ]);
            }

            if ($qtyToRemove > $currentQty) {
                throw ValidationException::withMessages([
                    'quantity_to_remove' => "La cantidad a retirar ({$qtyToRemove}) no puede ser mayor a la cantidad actual del plato ({$currentQty}).",
                ]);
            }

            // 1. Revertir inventario ÚNICAMENTE de la cantidad retirada con trazabilidad
            $product = Product::lockForUpdate()->find($lockedItem->product_id);
            if ($product) {
                $movementReason = $qtyToRemove >= $currentQty
                    ? "Cancelación total de ítem en comanda #{$lockedOrder->order_number}: {$reason}"
                    : "Reducción parcial de ítem (-{$qtyToRemove} un.) en comanda #{$lockedOrder->order_number}: {$reason}";

                $this->inventoryService->registerMovement(
                    product: $product,
                    type: InventoryMovement::TYPE_ENTRY,
                    quantity: $qtyToRemove,
                    reason: $movementReason,
                    userId: $admin->id,
                );
            }

            // 2. Eliminación Total vs Reducción Parcial
            if ($qtyToRemove >= $currentQty) {
                $lockedItem->update([
                    'status' => 'cancelled',
                    'cancelled_by' => $admin->id,
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                ]);
            } else {
                $newQty = round($currentQty - $qtyToRemove, 3);
                $newSubtotal = round($newQty * (float) $lockedItem->unit_price, 2);

                $auditNote = "Reducción de {$qtyToRemove} un. por {$admin->name}: {$reason}";
                $existingNotes = $lockedItem->notes ? trim($lockedItem->notes) . " | " : '';
                $updatedNotes = mb_substr($existingNotes . $auditNote, 0, 255);

                $lockedItem->update([
                    'quantity' => $newQty,
                    'subtotal' => $newSubtotal,
                    'notes' => $updatedNotes,
                ]);
            }

            // 3. Recalcular la comanda
            $lockedOrder->recalculateTotals();

            return $lockedOrder->fresh(['items.product', 'table', 'user', 'customer']);
        });
    }

    /**
     * Annul an active order, release its table, restore stock for all active items, and log audit.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function cancelOrder(RestaurantOrder $order, string $reason, User $admin): RestaurantOrder
    {
        if (! $admin->isCurrentAdmin()) {
            throw new AuthorizationException('Esta acción es de acceso exclusivo para administradores.');
        }

        if (! $order->canBeModified()) {
            throw ValidationException::withMessages([
                'order' => 'No se puede anular una comanda que ya fue cobrada, facturada o anulada.',
            ]);
        }

        if ($order->hasPartialPayments() || $order->paidAmount() > 0) {
            throw ValidationException::withMessages([
                'order' => 'No se puede anular una comanda que tiene platos ya pagados y facturados.',
            ]);
        }

        return DB::transaction(function () use ($order, $reason, $admin) {
            $lockedOrder = RestaurantOrder::lockForUpdate()->findOrFail($order->id);

            if (! $lockedOrder->canBeModified()) {
                throw ValidationException::withMessages([
                    'order' => 'No se puede anular una comanda que ya fue cobrada, facturada o anulada.',
                ]);
            }

            if ($lockedOrder->hasPartialPayments() || $lockedOrder->paidAmount() > 0) {
                throw ValidationException::withMessages([
                    'order' => 'No se puede anular una comanda que tiene platos ya pagados y facturados.',
                ]);
            }

            // Revertir inventario de todos los ítems activos y marcarlos como anulados
            $activeItems = $lockedOrder->items()
                ->where('status', '!=', 'cancelled')
                ->lockForUpdate()
                ->get();

            foreach ($activeItems as $activeItem) {
                $product = Product::lockForUpdate()->find($activeItem->product_id);
                if ($product) {
                    $this->inventoryService->registerMovement(
                        product: $product,
                        type: InventoryMovement::TYPE_ENTRY,
                        quantity: $activeItem->quantity,
                        reason: "Anulación de comanda #{$lockedOrder->order_number}: {$reason}",
                        userId: $admin->id,
                    );
                }

                $activeItem->update([
                    'status' => 'cancelled',
                    'cancelled_by' => $admin->id,
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                ]);
            }

            // Liberar la mesa asociada si existe
            if ($lockedOrder->table_id) {
                $table = RestaurantTable::where('business_id', $lockedOrder->business_id)
                    ->lockForUpdate()
                    ->find($lockedOrder->table_id);

                if ($table) {
                    $table->update(['status' => 'available']);
                }
            }

            $lockedOrder->update([
                'status' => 'cancelled',
                'cancelled_by' => $admin->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            return $lockedOrder->fresh(['items.product', 'table', 'user', 'customer']);
        });
    }

    /**
     * Change the assigned table of an active order atomically.
     *
     * @throws ValidationException
     */
    public function changeTable(RestaurantOrder $order, RestaurantTable $newTable, User $user): RestaurantOrder
    {
        if ((int) $order->business_id !== (int) $newTable->business_id) {
            throw ValidationException::withMessages([
                'table_id' => 'La mesa seleccionada no pertenece al mismo establecimiento que la comanda.',
            ]);
        }

        if (! $order->canBeModified()) {
            throw ValidationException::withMessages([
                'order' => 'No se puede cambiar de mesa una comanda que ya fue cobrada, facturada o anulada.',
            ]);
        }

        if ((int) $newTable->id === (int) $order->table_id) {
            throw ValidationException::withMessages([
                'table_id' => 'La comanda ya se encuentra asignada a esta mesa.',
            ]);
        }

        if ($newTable->status !== 'available') {
            throw ValidationException::withMessages([
                'table_id' => 'La mesa seleccionada ya se encuentra ocupada.',
            ]);
        }

        return DB::transaction(function () use ($order, $newTable, $user) {
            $lockedOrder = RestaurantOrder::where('business_id', $order->business_id)
                ->lockForUpdate()
                ->findOrFail($order->id);

            if (! $lockedOrder->canBeModified()) {
                throw ValidationException::withMessages([
                    'order' => 'No se puede cambiar de mesa una comanda que ya fue cobrada, facturada o anulada.',
                ]);
            }

            if ((int) $lockedOrder->table_id === (int) $newTable->id) {
                throw ValidationException::withMessages([
                    'table_id' => 'La comanda ya se encuentra asignada a esta mesa.',
                ]);
            }

            $oldTableId = $lockedOrder->table_id;
            $newTableId = (int) $newTable->id;

            $tableIds = array_values(array_filter(array_unique([$oldTableId, $newTableId])));
            sort($tableIds);

            $lockedTables = RestaurantTable::where('business_id', $lockedOrder->business_id)
                ->whereIn('id', $tableIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lockedNewTable = $lockedTables->get($newTableId);

            if (! $lockedNewTable) {
                throw ValidationException::withMessages([
                    'table_id' => 'La mesa seleccionada no existe o no pertenece al negocio.',
                ]);
            }

            if ($lockedNewTable->status !== 'available') {
                throw ValidationException::withMessages([
                    'table_id' => 'La mesa seleccionada ya se encuentra ocupada.',
                ]);
            }

            $oldTable = $oldTableId ? $lockedTables->get($oldTableId) : null;

            // 1. Liberar la mesa previa
            if ($oldTable) {
                $oldTable->update(['status' => 'available']);
            }

            // 2. Ocupar la mesa destino
            $lockedNewTable->update(['status' => 'occupied']);

            // 3. Trazabilidad y auditoría
            $oldTableName = $oldTable ? $oldTable->name : 'Sin mesa previa';
            $timestamp = now('America/Bogota')->format('d/m/Y H:i');
            $auditNote = "Cambio de mesa realizado por {$user->name} el {$timestamp}: de '{$oldTableName}' a '{$lockedNewTable->name}'.";
            $existingNotes = $lockedOrder->notes ? trim($lockedOrder->notes) . "\n" : '';
            $updatedNotes = $existingNotes . "[AUDITORÍA] " . $auditNote;

            // 4. Actualizar la orden
            $lockedOrder->update([
                'table_id' => $lockedNewTable->id,
                'notes' => $updatedNotes,
            ]);

            return $lockedOrder->fresh(['table', 'items.product', 'user', 'customer']);
        });
    }

    /**
     * Generate sequential order number per business.
     * Format: ORD-XXXX (e.g. ORD-0001)
     */
    protected function generateOrderNumber(int $businessId): string
    {
        $prefix = 'ORD-';

        $lastOrder = RestaurantOrder::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('order_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($lastOrder && preg_match('/ORD-(\d+)/', $lastOrder->order_number, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Settle individual items ordered at a table (Split Bill), generate partial receipt/invoice, and evaluate table closure.
     *
     * @param RestaurantOrder $order
     * @param array<int> $selectedItemIds
     * @param string $paymentMethod
     * @param float $amountReceived
     * @param string|null $reference
     * @param User $user
     * @return array
     *
     * @throws ValidationException
     */
    /**
     * Settle individual items ordered at a table (Split Bill), generate partial receipt/invoice, and evaluate table closure.
     * Full parity with standard billing (customer, discounts, tip 10%, INC 8%, multi-payments, and notes).
     *
     * @param RestaurantOrder $order
     * @param array $selectedItemIds
     * @param string|array|null $paymentMethod
     * @param float $amountReceived
     * @param string|null $reference
     * @param User|null $user
     * @param array $data
     * @return array
     *
     * @throws ValidationException
     */
    public function paySplitItems(
        RestaurantOrder $order,
        array $selectedItemIds = [],
        string|array|null $paymentMethod = null,
        float $amountReceived = 0.0,
        ?string $reference = null,
        ?User $user = null,
        array $data = []
    ): array {
        // Normalización de argumentos para compatibilidad dual (antiguo vs nuevo contrato)
        if (! empty($data)) {
            $input = $data;
        } elseif (! empty($selectedItemIds) && isset($selectedItemIds['items'])) {
            $input = $selectedItemIds;
        } else {
            $input = [
                'items' => $selectedItemIds,
                'payment_method' => is_string($paymentMethod) ? $paymentMethod : 'cash',
                'cash_received' => $amountReceived,
                'reference' => $reference,
            ];
        }

        if (empty($input['items']) && ! empty($selectedItemIds) && is_array($selectedItemIds) && array_is_list($selectedItemIds)) {
            $input['items'] = $selectedItemIds;
        }

        $user = $user ?? auth()->user();
        if (! $user) {
            throw new \RuntimeException('No hay un usuario autenticado para registrar el cobro.');
        }

        $rawItemIds = (array) ($input['items'] ?? []);
        if (empty($rawItemIds)) {
            throw ValidationException::withMessages([
                'items' => 'Debe seleccionar al menos un plato para liquidar.',
            ]);
        }

        return DB::transaction(function () use ($order, $input, $rawItemIds, $user) {
            // 1. Multi-Tenant y Bloqueo Pesimista de la Orden
            $lockedOrder = RestaurantOrder::where('business_id', $order->business_id)
                ->lockForUpdate()
                ->findOrFail($order->id);

            if (! in_array($lockedOrder->status, ['open', 'in_kitchen', 'dispatched', 'delivered', 'billed'], true)) {
                throw ValidationException::withMessages([
                    'order' => 'La comanda ya se encuentra cerrada o anulada.',
                ]);
            }

            // 2. Bloquear y validar los ítems seleccionados
            $itemIds = array_values(array_unique($rawItemIds));
            $lockedItems = RestaurantOrderItem::where('business_id', $lockedOrder->business_id)
                ->where('order_id', $lockedOrder->id)
                ->whereIn('id', $itemIds)
                ->lockForUpdate()
                ->get();

            if ($lockedItems->count() !== count($itemIds)) {
                throw ValidationException::withMessages([
                    'items' => 'Uno o más platos seleccionados no pertenecen a esta comanda o al negocio.',
                ]);
            }

            foreach ($lockedItems as $item) {
                if ($item->payment_status !== 'pending') {
                    throw ValidationException::withMessages([
                        'items' => "El plato '{$item->product?->name}' ya fue pagado previamente.",
                    ]);
                }
                if ($item->status === 'cancelled') {
                    throw ValidationException::withMessages([
                        'items' => "El plato '{$item->product?->name}' se encuentra anulado y no puede cobrarse.",
                    ]);
                }
            }

            // 3. Cálculo Financiero Autoritativo con Descuento, Propina (10%) e INC (8%)
            $subtotal = round((float) $lockedItems->sum('subtotal'), 2);

            if ($subtotal <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'El total de los consumos seleccionados debe ser mayor a cero.',
                ]);
            }

            $discountPercentage = max(0, min(100, (float) ($input['discount_percentage'] ?? 0)));
            $discountAmount = round($subtotal * ($discountPercentage / 100), 2);
            $baseAjustada = max(0, round($subtotal - $discountAmount, 2));

            $includeTip = filter_var($input['include_tip'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $includeTax = filter_var($input['include_tax'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $tipAmount = $includeTip ? round($baseAjustada * 0.10, 2) : 0.00;
            $taxAmount = $includeTax ? round($baseAjustada * 0.08, 2) : 0.00;

            $totalToSettle = round($baseAjustada + $tipAmount + $taxAmount, 2);

            // 4. Normalización y Validación de Medios de Pago (Soporte Mixto)
            $payments = [];
            if (! empty($input['payments']) && is_array($input['payments'])) {
                foreach ($input['payments'] as $p) {
                    $rawMethod = strtolower($p['method'] ?? 'cash');
                    $method = in_array($rawMethod, ['nequi', 'daviplata'], true) ? 'transfer' : $rawMethod;
                    $ref = ! empty($p['reference']) ? strip_tags(trim((string) $p['reference'])) : null;
                    if (in_array($rawMethod, ['nequi', 'daviplata'], true) && empty($ref)) {
                        $ref = ucfirst($rawMethod);
                    }
                    if ($ref !== null) {
                        $ref = mb_substr($ref, 0, 60);
                    }

                    $rawAmt = $p['amount'] ?? null;
                    $normalizedAmt = $rawAmt !== null && $rawAmt !== '' ? $this->normalizeCopMoney($rawAmt) : null;
                    $amount = $normalizedAmt !== null
                        ? round((float) $normalizedAmt, 2)
                        : $totalToSettle;

                    if ($amount <= 0) {
                        continue;
                    }

                    $isCash = ($method === 'cash');
                    $rawReceived = $p['received'] ?? $p['cash_received'] ?? null;
                    $normalizedReceived = $rawReceived !== null && $rawReceived !== '' ? $this->normalizeCopMoney($rawReceived) : null;
                    $cashReceived = $isCash
                        ? ($normalizedReceived !== null ? round((float) $normalizedReceived, 2) : $amount)
                        : $amount;
                    $changeGiven = $isCash ? max(0.00, round($cashReceived - $amount, 2)) : 0.00;

                    if ($isCash && $cashReceived < $amount) {
                        throw ValidationException::withMessages([
                            'payments' => 'El efectivo recibido no puede ser inferior al monto asignado en este método de pago.',
                        ]);
                    }

                    $payments[] = [
                        'raw_method' => $rawMethod,
                        'method' => $method,
                        'amount' => $amount,
                        'reference' => $ref,
                        'cash_received' => $cashReceived,
                        'change_given' => $changeGiven,
                    ];
                }
            } elseif (! empty($input['payment_method'])) {
                $rawMethod = strtolower($input['payment_method']);
                $method = in_array($rawMethod, ['nequi', 'daviplata'], true) ? 'transfer' : $rawMethod;
                $ref = ! empty($input['reference']) ? strip_tags(trim((string) $input['reference'])) : null;
                if (in_array($rawMethod, ['nequi', 'daviplata'], true) && empty($ref)) {
                    $ref = ucfirst($rawMethod);
                }
                if ($ref !== null) {
                    $ref = mb_substr($ref, 0, 60);
                }

                $rawAmt = $input['amount'] ?? null;
                $normalizedAmt = $rawAmt !== null && $rawAmt !== '' ? $this->normalizeCopMoney($rawAmt) : null;
                $amount = $normalizedAmt !== null ? round((float) $normalizedAmt, 2) : $totalToSettle;

                $rawReceived = $input['cash_received'] ?? $input['received'] ?? null;
                $normalizedReceived = $rawReceived !== null && $rawReceived !== '' ? $this->normalizeCopMoney($rawReceived) : null;
                $cashReceived = $method === 'cash'
                    ? ($normalizedReceived !== null ? round((float) $normalizedReceived, 2) : $amount)
                    : $amount;
                $changeGiven = $method === 'cash' ? max(0.00, round($cashReceived - $amount, 2)) : 0.00;

                if ($method === 'cash' && $cashReceived < $amount) {
                    throw ValidationException::withMessages([
                        'payments' => 'El efectivo recibido no puede ser inferior al monto asignado.',
                    ]);
                }

                $payments[] = [
                    'raw_method' => $rawMethod,
                    'method' => $method,
                    'amount' => $amount,
                    'reference' => $ref,
                    'cash_received' => $cashReceived,
                    'change_given' => $changeGiven,
                ];
            }

            if (empty($payments)) {
                throw ValidationException::withMessages([
                    'payments' => 'Debe registrar al menos un método de pago válido.',
                ]);
            }

            $sumPayments = round(array_sum(array_column($payments, 'amount')), 2);
            if (abs($sumPayments - $totalToSettle) > 0.05) {
                throw ValidationException::withMessages([
                    'payments' => 'El total de los métodos de pago ($' . number_format($sumPayments, 0, ',', '.') . ') debe ser igual al total a liquidar ($' . number_format($totalToSettle, 0, ',', '.') . ').',
                ]);
            }

            // 5. Resolución de Cliente y Datos de Facturación
            $defaultName = config('sales.default_customer_name', 'CONSUMIDOR FINAL');
            $defaultDoc = config('sales.default_customer_document', '222222222222');

            $customerId = $input['customer_id'] ?? $lockedOrder->customer_id;
            $customerName = null;
            $customerDocument = null;

            if ($customerId) {
                $customer = Customer::where('business_id', $lockedOrder->business_id)->find($customerId);
                if ($customer) {
                    $rawDoc = $customer->document ?? $customer->identification_number;
                    $normName = strtoupper(trim((string) $customer->name));
                    if ($rawDoc === '222222222222' && ($normName === '' || $normName === 'CONSUMIDOR FINAL')) {
                        $customerId = null;
                        $customerName = $defaultName;
                        $customerDocument = $defaultDoc;
                    } else {
                        $customerId = $customer->id;
                        $customerName = $customer->name;
                        $customerDocument = $rawDoc ?? $defaultDoc;
                    }
                } else {
                    $customerId = null;
                }
            }

            if (! $customerName) {
                $candidate = ! empty($input['customer_name'])
                    ? strip_tags(trim((string) $input['customer_name']))
                    : ($lockedOrder->customer_name ?: null);

                $sellerName = $user?->name ?? '';
                $sellerFirst = explode(' ', trim($sellerName))[0] ?? '';
                $isSeller = false;
                if ($candidate && $sellerName) {
                    $cLower = mb_strtolower($candidate);
                    $sLower = mb_strtolower($sellerName);
                    $fLower = mb_strtolower($sellerFirst);
                    if ($cLower === $sLower || ($fLower !== '' && $cLower === $fLower)) {
                        $isSeller = true;
                    }
                }

                if ($candidate && ! $isSeller && mb_strtolower($candidate) !== 'consumidor final') {
                    $customerName = $candidate;
                    $customerDocument = $lockedOrder->customer_document ?? $defaultDoc;
                } else {
                    $customerName = $defaultName;
                    $customerDocument = $defaultDoc;
                    $customerId = null;
                }
            }

            // 6. Registro de Venta (Sale)
            $invoiceNumber = $this->generateInvoiceNumber($lockedOrder->business_id);
            $derivedPaymentMethod = count($payments) > 1 ? 'mixed' : $payments[0]['method'];

            $baseNotes = ! empty($input['notes'])
                ? strip_tags(trim((string) $input['notes']))
                : "Pago parcial de platos en comanda {$lockedOrder->order_number}";

            $cleanOrderNotes = $lockedOrder->clean_notes;
            if ($cleanOrderNotes) {
                if (! empty($input['notes'])) {
                    $notes = ! str_contains($baseNotes, $cleanOrderNotes)
                        ? "{$baseNotes} — Nota: {$cleanOrderNotes}"
                        : $baseNotes;
                } else {
                    $notes = "Pago parcial de platos en comanda {$lockedOrder->order_number} — Nota: {$cleanOrderNotes}";
                }
            } else {
                $notes = $baseNotes;
            }

            $sale = Sale::create([
                'business_id' => $lockedOrder->business_id,
                'user_id' => $user->id,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'customer_document' => $customerDocument,
                'restaurant_order_id' => $lockedOrder->id,
                'order_type' => $lockedOrder->order_type ?? 'table',
                'invoice_number' => $invoiceNumber,
                'sale_date' => now('America/Bogota'),
                'subtotal' => $subtotal,
                'discount' => $discountAmount,
                'discount_percentage' => $discountPercentage,
                'delivery_fee' => 0.00,
                'service_fee' => $tipAmount,
                'tax_inc' => $taxAmount,
                'total' => $totalToSettle,
                'payment_method' => $derivedPaymentMethod,
                'status' => 'completed',
                'notes' => $notes,
            ]);

            // 7. Registro de Medios de Pago (SalePayment)
            foreach ($payments as $p) {
                SalePayment::create([
                    'business_id' => $lockedOrder->business_id,
                    'sale_id' => $sale->id,
                    'method' => $p['method'],
                    'amount' => $p['amount'],
                    'reference' => ! empty($p['reference']) ? mb_substr((string) $p['reference'], 0, 60) : null,
                    'cash_received' => $p['cash_received'],
                    'change_given' => $p['change_given'],
                ]);
            }

            // 8. Crear detalles de venta y descontar stock si es producto estándar
            foreach ($lockedItems as $item) {
                SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                ]);

                if ($item->product && $item->product->isStandard()) {
                    $this->inventoryService->registerMovement(
                        product: $item->product,
                        type: InventoryMovement::TYPE_EXIT,
                        quantity: $item->quantity,
                        reason: "Venta división de cuenta #{$invoiceNumber} (Comanda {$lockedOrder->order_number})",
                        userId: $user->id,
                        saleId: $sale->id,
                    );
                }
            }

            // 9. Transición Atómica de Ítems a 'paid'
            $now = now();
            foreach ($lockedItems as $item) {
                $item->update([
                    'payment_status' => 'paid',
                    'paid_at' => $now,
                    'payment_id' => $sale->id,
                ]);
            }

            // 10. Evaluación de Cierre de Comanda y Mesa
            $pendingCount = $lockedOrder->items()
                ->where('payment_status', 'pending')
                ->where('status', '!=', 'cancelled')
                ->count();

            $isFullyPaid = ($pendingCount === 0);
            $existingNotes = $lockedOrder->notes ? trim($lockedOrder->notes) . "\n" : '';
            $timestamp = now('America/Bogota')->format('d/m/Y H:i');

            if ($isFullyPaid) {
                // Comanda liquidada en su totalidad
                $auditNote = "[AUDITORÍA] {$timestamp}: Comanda liquidada en su totalidad mediante división de cuenta por platos.";
                $lockedOrder->update([
                    'status' => 'billed',
                    'sale_id' => $sale->id,
                    'closed_at' => $now,
                    'notes' => mb_substr($existingNotes . $auditNote, 0, 500),
                ]);

                if ($lockedOrder->table_id) {
                    $table = RestaurantTable::where('business_id', $lockedOrder->business_id)
                        ->lockForUpdate()
                        ->find($lockedOrder->table_id);

                    if ($table) {
                        $table->update(['status' => 'available']);
                    }
                }
            } else {
                // Aún quedan ítems pendientes: la orden se mantiene activa (open) y la mesa ocupada (occupied)
                $remainingBalance = (float) $lockedOrder->items()
                    ->where('payment_status', 'pending')
                    ->where('status', '!=', 'cancelled')
                    ->sum('subtotal');

                $itemNames = $lockedItems->map(fn ($i) => $i->product?->name ?? 'Ítem')->implode(', ');
                $auditNote = "[AUDITORÍA] {$timestamp}: Pago parcial por {$user->name}. Platos cobrados: [{$itemNames}]. Saldo restante comanda: $" . number_format($remainingBalance, 0, ',', '.') . ".";

                $lockedOrder->update([
                    'status' => 'open',
                    'notes' => mb_substr($existingNotes . $auditNote, 0, 500),
                ]);

                if ($lockedOrder->table_id) {
                    $table = RestaurantTable::where('business_id', $lockedOrder->business_id)
                        ->lockForUpdate()
                        ->find($lockedOrder->table_id);

                    if ($table && $table->status !== 'occupied') {
                        $table->update(['status' => 'occupied']);
                    }
                }
            }

            $remainingBalance = (float) $lockedOrder->items()
                ->where('payment_status', 'pending')
                ->where('status', '!=', 'cancelled')
                ->sum('subtotal');

            $totalCashReceived = array_sum(array_column($payments, 'cash_received'));
            $totalChange = array_sum(array_column($payments, 'change_given'));

            return [
                'payment_id' => $sale->id,
                'sale' => $sale->load(['details.product', 'payments']),
                'split_total' => $totalToSettle,
                'subtotal' => $subtotal,
                'discount' => $discountAmount,
                'tip' => $tipAmount,
                'tax' => $taxAmount,
                'amount_received' => $totalCashReceived,
                'change' => $totalChange,
                'payment_method' => $derivedPaymentMethod,
                'items_count' => $lockedItems->count(),
                'is_fully_paid' => $isFullyPaid,
                'remaining_balance' => round($remainingBalance, 2),
                'order' => $lockedOrder->fresh(['table', 'items.product', 'user', 'customer']),
            ];
        });
    }

    /**
     * Generate sequential invoice number per business.
     * Format: VTA-YYYYMM-XXXX (e.g. VTA-202610-0001)
     */
    protected function generateInvoiceNumber(int $businessId): string
    {
        $prefix = 'VTA-' . now()->format('Ym') . '-';

        $lastSale = Sale::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('invoice_number', 'like', $prefix . '%')
            ->orderByRaw('LENGTH(invoice_number) DESC, invoice_number DESC')
            ->lockForUpdate()
            ->first();

        if ($lastSale) {
            $lastNumber = (int) str_replace($prefix, '', $lastSale->invoice_number);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Bill and settle the remaining pending balance of an active restaurant order, create a Sale and SalePayment(s),
     * mark remaining pending items as paid, transition order to 'billed', and free the assigned table.
     *
     * @param RestaurantOrder $order
     * @param array $data
     * @param User $user
     * @return Sale
     *
     * @throws ValidationException
     */
    public function billOrder(RestaurantOrder $order, array $data, User $user): Sale
    {
        return DB::transaction(function () use ($order, $data, $user) {
            // 1. Multi-Tenant y Bloqueo Pesimista de la Orden
            $lockedOrder = RestaurantOrder::where('business_id', $order->business_id)
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->isClosed() || $lockedOrder->isCancelled()) {
                throw ValidationException::withMessages([
                    'order' => 'La comanda ya se encuentra cerrada o anulada.',
                ]);
            }

            $pendingBalance = $lockedOrder->pendingBalance();
            if ($pendingBalance <= 0) {
                throw ValidationException::withMessages([
                    'order' => 'La comanda no tiene saldo pendiente por cobrar.',
                ]);
            }

            // Bloquear ítems pendientes activos
            $lockedPendingItems = RestaurantOrderItem::where('business_id', $lockedOrder->business_id)
                ->where('order_id', $lockedOrder->id)
                ->where('payment_status', 'pending')
                ->where('status', '!=', 'cancelled')
                ->lockForUpdate()
                ->get();

            if ($lockedPendingItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'order' => 'No existen consumos pendientes por liquidar en esta comanda.',
                ]);
            }

            // 2. Normalización de Pagos
            $payments = [];
            if (! empty($data['payments']) && is_array($data['payments'])) {
                foreach ($data['payments'] as $p) {
                    $rawMethod = strtolower($p['method'] ?? 'cash');
                    $method = in_array($rawMethod, ['nequi', 'daviplata'], true) ? 'transfer' : $rawMethod;
                    $ref = ! empty($p['reference']) ? strip_tags(trim((string) $p['reference'])) : null;
                    if (in_array($rawMethod, ['nequi', 'daviplata'], true) && empty($ref)) {
                        $ref = ucfirst($rawMethod);
                    }
                    if ($ref !== null) {
                        $ref = mb_substr($ref, 0, 60);
                    }

                    $rawAmt = $p['amount'] ?? 0;
                    $normalizedAmt = $rawAmt !== null && $rawAmt !== '' ? $this->normalizeCopMoney($rawAmt) : 0;
                    $amount = round((float) $normalizedAmt, 2);
                    if ($amount <= 0) {
                        continue;
                    }
                    $isCash = ($method === 'cash');
                    $rawReceived = $p['cash_received'] ?? $p['received'] ?? null;
                    $normalizedReceived = $rawReceived !== null && $rawReceived !== '' ? $this->normalizeCopMoney($rawReceived) : null;
                    $cashReceived = $isCash
                        ? ($normalizedReceived !== null ? round((float) $normalizedReceived, 2) : $amount)
                        : $amount;
                    $changeGiven = $isCash ? max(0.00, round($cashReceived - $amount, 2)) : 0.00;

                    if ($isCash && $cashReceived < $amount) {
                        throw ValidationException::withMessages([
                            'payments' => 'El efectivo recibido no puede ser inferior al monto asignado.',
                        ]);
                    }

                    $payments[] = [
                        'method' => $method,
                        'amount' => $amount,
                        'reference' => $ref,
                        'cash_received' => $cashReceived,
                        'change_given' => $changeGiven,
                    ];
                }
            } elseif (! empty($data['payment_method'])) {
                $rawMethod = strtolower($data['payment_method']);
                $method = in_array($rawMethod, ['nequi', 'daviplata'], true) ? 'transfer' : $rawMethod;
                $ref = ! empty($data['reference']) ? strip_tags(trim((string) $data['reference'])) : null;
                if (in_array($rawMethod, ['nequi', 'daviplata'], true) && empty($ref)) {
                    $ref = ucfirst($rawMethod);
                }
                if ($ref !== null) {
                    $ref = mb_substr($ref, 0, 60);
                }

                $rawAmt = $data['amount'] ?? null;
                $normalizedAmt = $rawAmt !== null && $rawAmt !== '' ? $this->normalizeCopMoney($rawAmt) : null;
                $amount = $normalizedAmt !== null ? round((float) $normalizedAmt, 2) : $pendingBalance;

                $rawReceived = $data['cash_received'] ?? $data['received'] ?? null;
                $normalizedReceived = $rawReceived !== null && $rawReceived !== '' ? $this->normalizeCopMoney($rawReceived) : null;
                $cashReceived = $method === 'cash'
                    ? ($normalizedReceived !== null ? round((float) $normalizedReceived, 2) : $amount)
                    : $amount;
                $changeGiven = $method === 'cash' ? max(0.00, round($cashReceived - $amount, 2)) : 0.00;

                if ($method === 'cash' && $cashReceived < $amount) {
                    throw ValidationException::withMessages([
                        'payments' => 'El efectivo recibido no puede ser inferior al monto asignado.',
                    ]);
                }

                $payments[] = [
                    'method' => $method,
                    'amount' => $amount,
                    'reference' => $ref,
                    'cash_received' => $cashReceived,
                    'change_given' => $changeGiven,
                ];
            }

            if (empty($payments)) {
                throw ValidationException::withMessages([
                    'payments' => 'Debe registrar al menos un método de pago válido.',
                ]);
            }

            // 3. Validación Financiera Estricta del Saldo a Cubrir
            $baseItems = round((float) $lockedOrder->pendingItemsSubtotal(), 2);
            $deliveryFee = round((float) $lockedOrder->pendingDeliveryFee(), 2);

            $discountPercentage = max(0, min(100, (float) ($data['discount_percentage'] ?? 0)));
            $discountAmount = round($baseItems * ($discountPercentage / 100), 2);
            $netItems = max(0, round($baseItems - $discountAmount, 2));

            $tipAmount = ! empty($data['include_tip'])
                ? round($netItems * 0.10, 2)
                : (isset($data['service_fee']) && $data['service_fee'] !== '' ? max(0, round((float) $this->normalizeCopMoney($data['service_fee']), 2)) : 0.00);

            $taxAmount = ! empty($data['include_tax'])
                ? round($netItems * 0.08, 2)
                : (isset($data['tax_inc']) && $data['tax_inc'] !== '' ? max(0, round((float) $this->normalizeCopMoney($data['tax_inc']), 2)) : 0.00);

            $expectedTotalToSettle = round($netItems + $tipAmount + $taxAmount + $deliveryFee, 2);

            $sumPayments = round(array_sum(array_column($payments, 'amount')), 2);

            if (abs($sumPayments - $expectedTotalToSettle) > 0.05) {
                throw ValidationException::withMessages([
                    'payments' => 'El saldo a cubrir es de $' . number_format($expectedTotalToSettle, 0, ',', '.') . ' (Total recibido: $' . number_format($sumPayments, 0, ',', '.') . ').',
                ]);
            }

            // 4. Determinar método principal
            $derivedPaymentMethod = count($payments) > 1 ? 'mixed' : $payments[0]['method'];

            // 5. Creación de la Venta (Sale)
            $invoiceNumber = $this->generateInvoiceNumber($lockedOrder->business_id);

            $defaultName = config('sales.default_customer_name', 'CONSUMIDOR FINAL');
            $defaultDoc = config('sales.default_customer_document', '222222222222');

            $customerId = $data['customer_id'] ?? $lockedOrder->customer_id;
            $customerName = null;
            $customerDocument = null;

            if ($customerId) {
                $customer = Customer::where('business_id', $lockedOrder->business_id)->find($customerId);
                if ($customer) {
                    $rawDoc = $customer->document ?? $customer->identification_number;
                    $normName = strtoupper(trim((string) $customer->name));
                    if ($rawDoc === '222222222222' && ($normName === '' || $normName === 'CONSUMIDOR FINAL')) {
                        $customerId = null;
                        $customerName = $defaultName;
                        $customerDocument = $defaultDoc;
                    } else {
                        $customerId = $customer->id;
                        $customerName = $customer->name;
                        $customerDocument = $rawDoc ?? $defaultDoc;
                    }
                } else {
                    $customerId = null;
                }
            }

            if (! $customerName) {
                $candidate = ! empty($data['customer_name'])
                    ? strip_tags(trim((string) $data['customer_name']))
                    : ($lockedOrder->customer_name ?: null);

                $sellerName = $user?->name ?? '';
                $sellerFirst = explode(' ', trim($sellerName))[0] ?? '';
                $isSeller = false;
                if ($candidate && $sellerName) {
                    $cLower = mb_strtolower($candidate);
                    $sLower = mb_strtolower($sellerName);
                    $fLower = mb_strtolower($sellerFirst);
                    if ($cLower === $sLower || ($fLower !== '' && $cLower === $fLower)) {
                        $isSeller = true;
                    }
                }

                if ($candidate && ! $isSeller && mb_strtolower($candidate) !== 'consumidor final') {
                    $customerName = $candidate;
                    $customerDocument = $lockedOrder->customer_document ?? $defaultDoc;
                } else {
                    $customerName = $defaultName;
                    $customerDocument = $defaultDoc;
                    $customerId = null;
                }
            }

            $baseNotes = ! empty($data['notes'])
                ? strip_tags(trim((string) $data['notes']))
                : "Cobro final de comanda {$lockedOrder->order_number}";

            $cleanOrderNotes = $lockedOrder->clean_notes;
            if ($cleanOrderNotes) {
                if (! empty($data['notes'])) {
                    $saleNotes = ! str_contains($baseNotes, $cleanOrderNotes)
                        ? "{$baseNotes} — Nota: {$cleanOrderNotes}"
                        : $baseNotes;
                } else {
                    $saleNotes = "Cobro final de comanda {$lockedOrder->order_number} — Nota: {$cleanOrderNotes}";
                }
            } else {
                $saleNotes = $baseNotes;
            }

            $sale = Sale::create([
                'business_id' => $lockedOrder->business_id,
                'user_id' => $user->id,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'customer_document' => $customerDocument,
                'restaurant_order_id' => $lockedOrder->id,
                'order_type' => $lockedOrder->order_type ?? 'table',
                'invoice_number' => $invoiceNumber,
                'sale_date' => now('America/Bogota'),
                'subtotal' => $baseItems,
                'discount' => $discountAmount,
                'discount_percentage' => $discountPercentage,
                'delivery_fee' => $deliveryFee,
                'service_fee' => $tipAmount,
                'tax_inc' => $taxAmount,
                'total' => $expectedTotalToSettle,
                'payment_method' => $derivedPaymentMethod,
                'status' => 'completed',
                'notes' => $saleNotes,
            ]);

            // 6. Registro de SalePayments
            foreach ($payments as $p) {
                SalePayment::create([
                    'business_id' => $lockedOrder->business_id,
                    'sale_id' => $sale->id,
                    'method' => $p['method'],
                    'amount' => $p['amount'],
                    'reference' => ! empty($p['reference']) ? mb_substr((string) $p['reference'], 0, 60) : null,
                    'cash_received' => $p['cash_received'],
                    'change_given' => $p['change_given'],
                ]);
            }

            // 7. Detalles de Venta e Inventario
            foreach ($lockedPendingItems as $item) {
                SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                ]);

                if ($item->product && $item->product->isStandard()) {
                    $this->inventoryService->registerMovement(
                        product: $item->product,
                        type: InventoryMovement::TYPE_EXIT,
                        quantity: $item->quantity,
                        reason: "Venta comanda #{$invoiceNumber} ({$lockedOrder->order_number})",
                        userId: $user->id,
                        saleId: $sale->id,
                    );
                }
            }

            // 8. Transición Atómica de Ítems a 'paid'
            $now = now();
            foreach ($lockedPendingItems as $item) {
                $item->update([
                    'payment_status' => 'paid',
                    'paid_at' => $now,
                    'payment_id' => $sale->id,
                ]);
            }

            // 9. Cierre y Liberación Atómica de la Comanda y Mesa
            $timestamp = now('America/Bogota')->format('d/m/Y H:i');
            $auditNote = "[AUDITORÍA] {$timestamp}: Comanda cerrada y liquidada en su totalidad por {$user->name}. Cobro de saldo pendiente: $" . number_format($expectedTotalToSettle, 0, ',', '.') . ".";
            $existingNotes = $lockedOrder->notes ? trim($lockedOrder->notes) . "\n" : '';

            $lockedOrder->update([
                'status' => 'billed',
                'sale_id' => $sale->id,
                'closed_at' => $now,
                'notes' => mb_substr($existingNotes . $auditNote, 0, 500),
            ]);

            if ($lockedOrder->table_id) {
                $table = RestaurantTable::where('business_id', $lockedOrder->business_id)
                    ->lockForUpdate()
                    ->find($lockedOrder->table_id);

                if ($table) {
                    $table->update(['status' => 'available']);
                }
            }

            return $sale->load(['details.product', 'payments', 'restaurantOrder']);
        });
    }

    /**
     * Alias for billOrder to satisfy 'charge' naming convention.
     */
    public function charge(RestaurantOrder $order, array $data, User $user): Sale
    {
        return $this->billOrder($order, $data, $user);
    }
}

