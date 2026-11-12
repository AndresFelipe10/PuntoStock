<?php

namespace App\Services\Sales;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\Sales\InvalidSaleItemException;
use App\Exceptions\Sales\InvalidSalePaymentException;
use App\Models\Business;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SalePayment;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Process a complete sale within a single database transaction.
     *
     * Resolves authoritative prices directly from products.sale_price under
     * pessimistic locking (lockForUpdate). Client-sent unit prices are strictly ignored.
     * Creates the sale header, detail lines, and decrements stock for each product
     * via InventoryService. If any product lacks stock, InsufficientStockException
     * propagates and the entire transaction rolls back.
     *
     * @param  array  $data       Validated request data
     * @param  int    $businessId Active tenant ID
     * @param  int    $userId     Authenticated user ID
     * @return Sale               The created sale with details loaded
     *
     * @throws InsufficientStockException
     * @throws InvalidSaleItemException
     */
    public function processSale(array $data, int $businessId, int $userId): Sale
    {
        return DB::transaction(function () use ($data, $businessId, $userId) {
            $restaurantOrder = null;
            if (! empty($data['restaurant_order_id'])) {
                $restaurantOrder = RestaurantOrder::withoutGlobalScopes()
                    ->where('business_id', $businessId)
                    ->lockForUpdate()
                    ->find($data['restaurant_order_id']);

                if (! $restaurantOrder || $restaurantOrder->status === 'closed' || $restaurantOrder->status === 'cancelled' || $restaurantOrder->sale_id !== null) {
                    throw new InvalidSaleItemException('La comanda seleccionada ya fue cobrada, facturada o anulada.');
                }
            }

            $business = Business::withoutGlobalScopes()->find($businessId);
            $isRestaurantSale = ($business && $business->isRestaurant())
                || !empty($data['restaurant_order_id'])
                || (isset($data['order_type']) && $data['order_type'] !== 'direct');

            $subtotal = 0;
            $resolvedItems = [];

            // Lock products and resolve authoritative pricing from the database
            foreach ($data['items'] as $item) {
                $product = Product::withoutGlobalScopes()
                    ->where('business_id', $businessId)
                    ->lockForUpdate()
                    ->find($item['product_id']);

                if ($product === null) {
                    throw new InvalidSaleItemException(
                        'El producto seleccionado no está disponible en este negocio o ya no existe.'
                    );
                }

                $unitPrice = (float) $product->sale_price;
                $quantity = round((float) $item['quantity'], 3);
                $lineSubtotal = round($quantity * $unitPrice, 2);
                $subtotal += $lineSubtotal;

                $resolvedItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $lineSubtotal,
                ];
            }

            // Blindaje normativo colombiano de liquidación (Gastronomía y Restaurantes):
            // 1. Base Gravable / Consumo neto = Subtotal - Descuento (Art. 512-1 del Estatuto Tributario).
            // 2. Propina Voluntaria / Servicio (Ley 1935 de 2018 y Circular Única SIC):
            //    Liberalidad voluntaria del consumidor (sugerida hasta el 10%) con destino exclusivo a los trabajadores.
            //    Regla inmutable: La propina NO hace parte de la base gravable del INC ni del IVA. La propina no causa impuestos.
            // 3. Impuesto Nacional al Consumo - INC 8% (Art. 512-1 E.T.):
            //    Grava exclusivamente el expendio de comidas y bebidas (Base Gravable = Subtotal - Descuento).
            //    Regla inmutable: El INC NO se calcula sobre la propina ni sobre el flete/costo de domicilio (delivery_fee).
            // 4. Independencia de bases: ni la propina grava el impuesto, ni el impuesto grava la propina.
            // Total a Liquidar = Base Gravable (Subtotal - Descuento) + Domicilio + Servicio + INC.
            $discountPercentage = (float) ($data['discount_percentage'] ?? 0);
            $discount = round($subtotal * ($discountPercentage / 100), 2);
            $netFoodBase = max(0, round($subtotal - $discount, 2));

            // Cálculo discriminado de base para Impuesto Nacional al Consumo (INC 8% Art. 512-1 E.T.):
            // Grava exclusivamente el expendio de comidas y bebidas preparadas del menú ($dishSubtotal).
            // La mercancía de mostrador ($standardSubtotal), propinas y domicilios están exentos de INC.
            $dishSubtotal = 0.00;
            $standardSubtotal = 0.00;
            foreach ($resolvedItems as $resolved) {
                if ($resolved['product']->isDish()) {
                    $dishSubtotal += $resolved['subtotal'];
                } else {
                    $standardSubtotal += $resolved['subtotal'];
                }
            }

            $dishDiscount = $subtotal > 0 ? round($dishSubtotal * ($discountPercentage / 100), 2) : 0.00;
            $netDishBase = max(0, round($dishSubtotal - $dishDiscount, 2));

            $deliveryFee = isset($data['delivery_fee']) ? max(0, round($this->parseAmount($data['delivery_fee']), 2)) : 0.00;
            $serviceFee = isset($data['service_fee']) ? max(0, round($this->parseAmount($data['service_fee']), 2)) : 0.00;
            $taxInc = isset($data['tax_inc']) ? max(0, round($this->parseAmount($data['tax_inc']), 2)) : 0.00;

            // Validación matemática estricta y autoritativa de tributos y propinas (Art. 512-1 E.T. y Ley 1935/2018):
            if ($netFoodBase <= 0) {
                $serviceFee = 0.00;
                $taxInc = 0.00;
            } else {
                if ($taxInc > 0) {
                    $expectedInc = round($netDishBase * 0.08, 2);
                    if (abs($taxInc - $expectedInc) > 1.00) {
                        throw new InvalidSaleItemException(
                            "El valor del Impuesto Nacional al Consumo (INC) debe ser del 8% sobre la base neta (\${$expectedInc})."
                        );
                    }
                    $taxInc = $expectedInc;
                }

                if ($serviceFee > 0 && $serviceFee > max(1000000, $netFoodBase * 2)) {
                    throw new InvalidSaleItemException('El valor de la propina voluntaria excede el límite permitido para la venta.');
                }
            }

            $total = round($netFoodBase + $deliveryFee + $serviceFee + $taxInc, 2);

            // Generate unique invoice number for this business
            $invoiceNumber = $this->generateInvoiceNumber($businessId);

            // Resolve customer snapshot (or fallback to Consumidor Final)
            $defaultName = config('sales.default_customer_name', 'CONSUMIDOR FINAL');
            $defaultDoc = config('sales.default_customer_document', '222222222222');

            $customerId = $data['customer_id'] ?? null;
            $customerName = null;
            $customerDocument = null;

            if ($customerId !== null) {
                $customer = \App\Models\Customer::withoutGlobalScopes()
                    ->where('business_id', $businessId)
                    ->find($customerId);

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
                    : null;

                $seller = User::find($userId);
                $sellerName = $seller?->name ?? '';
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
                    $customerDocument = ! empty($data['customer_document'])
                        ? trim((string) $data['customer_document'])
                        : $defaultDoc;
                } else {
                    $customerName = $defaultName;
                    $customerDocument = $defaultDoc;
                    $customerId = null;
                }
            }

            // Normalize payments (support both 'payments' array and legacy 'payment_method')
            $payments = [];
            if (!empty($data['payments']) && is_array($data['payments'])) {
                $payments = array_values($data['payments']);
            } elseif (!empty($data['payment_method'])) {
                $payments = [
                    [
                        'method' => $data['payment_method'],
                        'amount' => $total,
                        'reference' => null,
                        'cash_received' => $data['payment_method'] === 'cash' ? $total : null,
                        'change_given' => $data['payment_method'] === 'cash' ? 0.00 : null,
                    ],
                ];
            } else {
                $payments = [
                    [
                        'method' => 'cash',
                        'amount' => $total,
                        'reference' => null,
                        'cash_received' => $total,
                        'change_given' => 0.00,
                    ],
                ];
            }

            // Normalizar billeteras electrónicas hacia transfer preservando referencia
            foreach ($payments as &$p) {
                $rawMethod = strtolower($p['method'] ?? 'cash');
                if (in_array($rawMethod, ['nequi', 'daviplata'], true)) {
                    $p['method'] = 'transfer';
                    if (empty($p['reference'])) {
                        $p['reference'] = ucfirst($rawMethod);
                    }
                }
            }
            unset($p);

            // Authoritative payments validation when total > 0
            if ($total > 0) {
                if (count($payments) < 1 || count($payments) > 5) {
                    throw new InvalidSalePaymentException('Debe especificar entre 1 y 5 líneas de pago.');
                }

                $cashLines = array_filter($payments, fn($p) => ($p['method'] ?? '') === 'cash');
                if (count($cashLines) > 1) {
                    throw new InvalidSalePaymentException('Solo se permite una línea de pago en efectivo.');
                }

                $totalEsperado = round((float) $total, 2);
                $totalRecibido = 0.0;

                foreach ($payments as $p) {
                    $amt = $this->parseAmount($p['amount'] ?? 0);
                    if ($amt <= 0) {
                        throw new InvalidSalePaymentException('Cada línea de pago debe tener un monto mayor a cero.');
                    }
                    if (!in_array($p['method'] ?? '', ['cash', 'card', 'transfer', 'other'])) {
                        throw new InvalidSalePaymentException("El método de pago '{$p['method']}' es inválido.");
                    }
                    $totalRecibido += $amt;
                }

                $totalRecibido = round($totalRecibido, 2);

                if (abs($totalRecibido - $totalEsperado) > 0.01) {
                    throw new InvalidSalePaymentException('La suma de los métodos de pago no coincide con el total de la venta.');
                }

                // Validar efectivo recibido con tolerancia decimal
                foreach ($cashLines as $cashP) {
                    $cashAmt = $this->parseAmount($cashP['amount'] ?? 0);
                    if (isset($cashP['cash_received']) && $cashP['cash_received'] !== '' && $cashP['cash_received'] !== null) {
                        $cashRec = $this->parseAmount($cashP['cash_received']);
                        if (($cashAmt - $cashRec) > 0.01) {
                            throw new InvalidSalePaymentException('El efectivo recibido no puede ser menor al monto asignado en efectivo.');
                        }
                    }
                }
            }

            // Derivar payment_method para la cabecera de la venta
            if (count($payments) === 1) {
                $derivedPaymentMethod = $payments[0]['method'];
            } else {
                $derivedPaymentMethod = 'mixed';
            }

            // Create sale header
            $sale = Sale::create([
                'business_id' => $businessId,
                'user_id' => $userId,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'customer_document' => $customerDocument,
                'restaurant_order_id' => $data['restaurant_order_id'] ?? null,
                'order_type' => $data['order_type'] ?? null,
                'invoice_number' => $invoiceNumber,
                'sale_date' => $data['sale_date'],
                'subtotal' => $subtotal,
                'discount' => $discount,
                'discount_percentage' => $discountPercentage,
                'delivery_fee' => $deliveryFee,
                'service_fee' => $serviceFee,
                'tax_inc' => $taxInc,
                'total' => $total,
                'payment_method' => $derivedPaymentMethod,
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
            ]);

            // Create sale_payments rows
            if ($total > 0) {
                foreach ($payments as $p) {
                    $method = $p['method'];
                    $amount = round($this->parseAmount($p['amount'] ?? 0), 2);
                    $isCash = ($method === 'cash');

                    if ($isCash) {
                        $cashReceived = (isset($p['cash_received']) && $p['cash_received'] !== '' && $p['cash_received'] !== null)
                            ? round($this->parseAmount($p['cash_received']), 2)
                            : $amount;
                        $changeGiven = round(max(0, $cashReceived - $amount), 2);
                    } else {
                        $cashReceived = null;
                        $changeGiven = null;
                    }

                    $cleanRef = !empty($p['reference']) ? strip_tags(trim((string)$p['reference'])) : null;
                    if ($cleanRef !== null && mb_strlen($cleanRef) > 60) {
                        $cleanRef = mb_substr($cleanRef, 0, 60);
                    }

                    SalePayment::create([
                        'business_id' => $businessId,
                        'sale_id' => $sale->id,
                        'method' => $method,
                        'amount' => $amount,
                        'reference' => $cleanRef,
                        'cash_received' => $cashReceived,
                        'change_given' => $changeGiven,
                    ]);
                }
            }

            // Create detail lines and decrement stock (only for retail sales)
            foreach ($resolvedItems as $resolved) {
                SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $resolved['product']->id,
                    'quantity' => $resolved['quantity'],
                    'unit_price' => $resolved['unit_price'],
                    'subtotal' => $resolved['subtotal'],
                ]);

                // En restaurantes los platos preparados no descuentan inventario físico estricto.
                // En mercancía de mostrador (standard) y en comercios Retail, se descuenta con bloqueo pesimista y verificación de existencias.
                $shouldDecrementStock = ! $isRestaurantSale || $resolved['product']->isStandard();
                if ($shouldDecrementStock) {
                    $this->inventoryService->registerMovement(
                        product: $resolved['product'],
                        type: InventoryMovement::TYPE_EXIT,
                        quantity: $resolved['quantity'],
                        reason: "Venta #{$invoiceNumber}",
                        userId: $userId,
                        saleId: $sale->id,
                    );
                }
            }

            // Close linked restaurant order and free table if applicable
            if ($restaurantOrder) {
                $restaurantOrder->items()
                    ->where('payment_status', 'pending')
                    ->where('status', '!=', 'cancelled')
                    ->update([
                        'payment_status' => 'paid',
                        'paid_at' => now(),
                        'payment_id' => $sale->id,
                    ]);

                $restaurantOrder->update([
                    'status' => 'closed',
                    'sale_id' => $sale->id,
                    'closed_at' => now(),
                ]);

                if ($restaurantOrder->table_id) {
                    $table = RestaurantTable::withoutGlobalScopes()
                        ->where('business_id', $businessId)
                        ->lockForUpdate()
                        ->find($restaurantOrder->table_id);

                    if ($table) {
                        $table->update(['status' => 'available']);
                    }
                }
            }

            return $sale->load('details.product', 'customer', 'user', 'payments', 'restaurantOrder');
        });
    }

    /**
     * Cancel a completed sale and restore stock for each line item.
     *
     * @param  Sale  $sale  The sale to cancel
     * @param  int   $userId  The user performing the cancellation
     * @return Sale  The updated sale
     */
    public function cancelSale(Sale $sale, int $userId): Sale
    {
        return DB::transaction(function () use ($sale, $userId) {
            $lockedSale = Sale::withoutGlobalScopes()
                ->where('business_id', $sale->business_id)
                ->lockForUpdate()
                ->findOrFail($sale->id);

            if ($lockedSale->status === 'cancelled') {
                return $lockedSale->load(['details.product', 'customer', 'user']);
            }

            $lockedSale->load('details.product');

            $business = Business::withoutGlobalScopes()->find($lockedSale->business_id);
            $isRestaurantSale = ($business && $business->isRestaurant())
                || !empty($lockedSale->restaurant_order_id)
                || ($lockedSale->order_type !== null && $lockedSale->order_type !== 'direct');

            // Restaurar existencias para retail y para productos de mercancía (standard) en restaurantes
            foreach ($lockedSale->details as $detail) {
                $product = $detail->product ?? Product::withoutGlobalScopes()->find($detail->product_id);
                $shouldRestoreStock = ! $isRestaurantSale || ($product && $product->isStandard());
                if ($shouldRestoreStock) {
                    $this->inventoryService->registerMovement(
                        product: $detail->product_id,
                        type: InventoryMovement::TYPE_ENTRY,
                        quantity: $detail->quantity,
                        reason: "Anulación venta #{$lockedSale->invoice_number}",
                        userId: $userId,
                        saleId: $lockedSale->id,
                    );
                }
            }

            $lockedSale->update(['status' => 'cancelled']);

            return $lockedSale->fresh(['details.product', 'customer', 'user']);
        });
    }

    /**
     * Generate a sequential invoice number for the business.
     * Format: VTA-YYYYMM-XXXX (e.g. VTA-202608-0001)
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

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Parsear y sanitizar strings numéricos con formato de moneda a float canónico.
     * Soporta "$650,000.00", "650000,00", "650.000", "1.250.000,50", floats y números enteros.
     */
    protected function parseAmount(mixed $value): float
    {
        if (is_numeric($value)) {
            if (is_string($value) && preg_match('/^\d+\.\d{3}$/', trim($value))) {
                return (float) str_replace('.', '', trim($value));
            }
            return (float) $value;
        }

        if (!is_string($value)) {
            return (float) ($value ?? 0);
        }

        $str = trim($value);
        $str = preg_replace('/[^\d,\.]/', '', $str);

        if ($str === '') {
            return 0.0;
        }

        if (str_contains($str, '.') && str_contains($str, ',')) {
            $lastDot = strrpos($str, '.');
            $lastComma = strrpos($str, ',');
            if ($lastComma > $lastDot) {
                $str = str_replace('.', '', $str);
                $str = str_replace(',', '.', $str);
            } else {
                $str = str_replace(',', '', $str);
            }
        } elseif (str_contains($str, ',')) {
            if (substr_count($str, ',') > 1 || preg_match('/,\d{3}$/', $str)) {
                $str = str_replace(',', '', $str);
            } else {
                $str = str_replace(',', '.', $str);
            }
        } elseif (str_contains($str, '.')) {
            if (substr_count($str, '.') > 1 || preg_match('/\.\d{3}$/', $str)) {
                $str = str_replace('.', '', $str);
            }
        }

        return (float) $str;
    }
}
