<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\AddOrderItemsRequest;
use App\Http\Requests\Restaurant\CancelRestaurantOrderRequest;
use App\Http\Requests\Restaurant\ChangeOrderTableRequest;
use App\Http\Requests\Restaurant\RemoveOrderItemRequest;
use App\Http\Requests\Restaurant\SplitOrderPaymentRequest;
use App\Http\Requests\Restaurant\StoreDeliveryOrderRequest;
use App\Http\Requests\Restaurant\StoreRestaurantOrderRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Services\Restaurant\RestaurantOrderService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RestaurantOrderController extends Controller
{
    /**
     * Display a list of orders separated by temporal hierarchy (today vs past).
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', RestaurantOrder::class);

        $todayBogota = now('America/Bogota');
        $todayDate = $todayBogota->toDateString();
        $todayStart = (clone $todayBogota)->startOfDay();
        $todayEnd = (clone $todayBogota)->endOfDay();

        // 1. Comandas del día (Hoy en zona horaria America/Bogota)
        $todayQuery = RestaurantOrder::with(['table', 'user', 'items', 'customer'])
            ->whereBetween('created_at', [$todayStart, $todayEnd]);

        // 2. Comandas anteriores a hoy con filtros opcionales
        $pastQuery = RestaurantOrder::with(['table', 'user', 'items', 'customer'])
            ->where('created_at', '<', $todayStart);

        if ($search = trim((string) $request->input('search', ''))) {
            $pastQuery->searchOrders($search);
            $todayQuery->searchOrders($search);
        }

        if ($status = $request->input('status')) {
            $pastQuery->byStatus($status);
            $todayQuery->byStatus($status);
        }

        if ($orderType = $request->input('order_type')) {
            $pastQuery->byOrderType($orderType);
            $todayQuery->byOrderType($orderType);
        }

        if ($dateFrom = $request->input('date_from')) {
            $dateFromBogota = Carbon::parse($dateFrom, 'America/Bogota')->startOfDay();
            $pastQuery->where('created_at', '>=', $dateFromBogota);
        }

        if ($dateTo = $request->input('date_to')) {
            $dateToBogota = Carbon::parse($dateTo, 'America/Bogota')->endOfDay();
            $pastQuery->where('created_at', '<=', $dateToBogota);
        }

        $todayOrders = $todayQuery->latest()->get();
        $pastOrders = $pastQuery->latest()->paginate(15, ['*'], 'past_page')->withQueryString();

        // Compatibilidad hacia atrás
        $orders = $pastOrders;

        return view('restaurant.orders.index', compact('todayOrders', 'pastOrders', 'todayDate', 'orders'));
    }

    /**
     * Show form to open a new order.
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', RestaurantOrder::class);

        $tables = RestaurantTable::where('status', 'available')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $products = Product::where('is_active', true)
            ->where('product_type', 'dish')
            ->with('category')
            ->orderBy('name')
            ->get();

        $selectedTableId = $request->query('table_id');

        return view('restaurant.orders.create', compact('tables', 'products', 'selectedTableId'));
    }

    /**
     * Open a new order on a table.
     */
    public function store(StoreRestaurantOrderRequest $request, RestaurantOrderService $orderService): RedirectResponse
    {
        $userId = (int) (auth()->id() ?? $request->user()->id);
        $order = $orderService->openTableOrder(
            (int) session('current_business_id'),
            $userId,
            $request->validated()
        );

        $response = redirect()->route('restaurant.orders.show', $order)
            ->with('success', "Comanda {$order->order_number} abierta exitosamente.");

        if ($order->items()->exists()) {
            $orderService->sendToKitchen($order);
            $response->with('print_kitchen_ticket_id', $order->id)
                ->with('print_kitchen_batch', 1);
        }

        return $response;
    }

    /**
     * Display the order details and order management view.
     */
    public function show(RestaurantOrder $order): View
    {
        Gate::authorize('view', $order);

        $order->load(['table', 'user', 'items.product', 'customer', 'sales']);

        $productsQuery = Product::where('is_active', true);
        if ($order->order_type === 'table') {
            $productsQuery->where('product_type', 'dish');
        }
        $products = $productsQuery
            ->with('category')
            ->orderBy('name')
            ->get();

        $groupedProducts = $products->groupBy(fn($p) => $p->category?->name ?? 'General / Sin Categoría');

        $batches = $order->items->groupBy('batch_number');

        $customers = Customer::where('is_active', true)->orderBy('name')->get();

        $availableTables = RestaurantTable::where('status', 'available')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('restaurant.orders.show', compact('order', 'products', 'groupedProducts', 'batches', 'customers', 'availableTables'));
    }

    /**
     * Add dishes or items to an active order.
     */
    public function addItems(AddOrderItemsRequest $request, RestaurantOrder $order, RestaurantOrderService $orderService): RedirectResponse
    {
        Gate::authorize('update', $order);

        if (! $order->canBeModified()) {
            return redirect()->route('restaurant.orders.show', $order)
                ->with('error', 'No se pueden agregar platos a una comanda que ya ha sido cerrada y facturada.');
        }

        $createdItems = $orderService->addItemsToOrder($order, $request->validated('items'));
        $batchNumber = $createdItems->first()?->batch_number;

        return redirect()->route('restaurant.orders.show', $order)
            ->with('success', 'Platos agregados correctamente a la comanda.')
            ->with('print_kitchen_ticket_id', $order->id)
            ->with('print_kitchen_batch', $batchNumber);
    }

    /**
     * Send pending items to kitchen and render the 80mm thermal ticket.
     */
    public function kitchenTicket(Request $request, RestaurantOrder $order, RestaurantOrderService $orderService): View
    {
        Gate::authorize('view', $order);

        $batch = $request->query('batch') ?? $request->input('batch');
        $isReprint = $request->boolean('reprint');

        if ($batch) {
            $itemsToPrint = $order->items()
                ->where('batch_number', $batch)
                ->where('status', '!=', 'cancelled')
                ->with('product')
                ->get();
        } else {
            $itemsToPrint = $orderService->sendToKitchen($order);

            // Si todos los ítems ya estaban impresos (se recuperan los de la última tanda enviada)
            if ($itemsToPrint->isEmpty()) {
                $maxBatch = (int) ($order->items()->where('status', '!=', 'cancelled')->max('batch_number') ?? 1);
                $itemsToPrint = $order->items()
                    ->where('batch_number', $maxBatch)
                    ->where('status', '!=', 'cancelled')
                    ->with('product')
                    ->get();
            }
        }

        return view('restaurant.orders.kitchen-ticket', [
            'order' => $order,
            'itemsToPrint' => $itemsToPrint,
            'isReprint' => $isReprint,
        ]);
    }

    /**
     * Show form to create a delivery or takeout order.
     */
    public function createDelivery(Request $request): View
    {
        Gate::authorize('create', RestaurantOrder::class);

        $businessId = session('current_business_id') ?? auth()->user()?->business_id ?? $request->user()?->business_id;

        $products = Product::where('is_active', true)
            ->with('category')
            ->orderBy('name')
            ->get();

        $customers = Customer::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'phone', 'document', 'address']);

        return view('restaurant.orders.create-delivery', compact('products', 'customers'));
    }

    /**
     * Store a newly created delivery or takeout order.
     */
    public function storeDelivery(StoreDeliveryOrderRequest $request, RestaurantOrderService $orderService): RedirectResponse
    {
        $userId = (int) (auth()->id() ?? $request->user()->id);
        $order = $orderService->openDeliveryOrTakeoutOrder(
            (int) session('current_business_id'),
            $userId,
            $request->validated()
        );

        // Enviar automáticamente a cocina los platos agregados al registrar el pedido
        $orderService->sendToKitchen($order);

        return redirect()->route('restaurant.orders.deliveries')
            ->with('success', "Pedido {$order->order_number} registrado exitosamente.")
            ->with('print_kitchen_ticket_id', $order->id)
            ->with('print_kitchen_batch', 1);
    }

    /**
     * Display the operational board for deliveries and dispatches.
     */
    public function deliveries(Request $request): View
    {
        Gate::authorize('viewAny', RestaurantOrder::class);

        $orders = RestaurantOrder::whereIn('order_type', ['delivery', 'takeout'])
            ->whereIn('status', ['open', 'in_kitchen', 'dispatched', 'delivered'])
            ->with(['items.product', 'user', 'customer'])
            ->latest()
            ->get();

        $kitchenOrders = $orders->whereIn('status', ['open', 'in_kitchen']);
        $dispatchedOrders = $orders->where('status', 'dispatched')->where('order_type', 'delivery');
        $deliveredOrders = $orders->where('status', 'delivered');

        return view('restaurant.orders.deliveries', compact('kitchenOrders', 'dispatchedOrders', 'deliveredOrders'));
    }

    /**
     * Mark a takeout order as directly picked up by customer.
     */
    public function pickup(Request $request, RestaurantOrder $order, RestaurantOrderService $orderService): RedirectResponse
    {
        Gate::authorize('update', $order);
        abort_if($order->business_id !== (int) session('current_business_id'), 403);

        $orderService->pickupOrder($order);

        return back()->with('success', "Pedido {$order->order_number} marcado como recogido exitosamente.");
    }

    /**
     * Update order delivery operational status.
     */
    public function updateStatus(Request $request, RestaurantOrder $order, RestaurantOrderService $orderService): RedirectResponse
    {
        Gate::authorize('update', $order);

        $request->validate([
            'status' => ['required', 'string', 'in:open,in_kitchen,dispatched,delivered,cancelled'],
        ]);

        $orderService->updateDeliveryStatus($order, $request->input('status'));

        return back()->with('success', 'Estado del pedido actualizado correctamente.');
    }

    /**
     * Render the 80mm thermal dispatch ticket for delivery orders.
     */
    public function dispatchTicket(RestaurantOrder $order): View
    {
        Gate::authorize('view', $order);

        $order->load(['items.product', 'user', 'customer', 'business']);

        return view('restaurant.orders.dispatch-ticket', compact('order'));
    }

    /**
     * Issue pre-bill informational ticket (80mm) and mark order/table as billed.
     */
    public function preBill(Request $request, RestaurantOrder $order): View
    {
        Gate::authorize('view', $order);

        DB::transaction(function () use ($order) {
            $lockedOrder = RestaurantOrder::where('business_id', $order->business_id)
                ->lockForUpdate()
                ->findOrFail($order->id);

            if (! $lockedOrder->isClosedOrBilled() && ! $lockedOrder->isCancelled()) {
                $lockedOrder->update([
                    'precount_printed_at' => now(),
                ]);

                if ($lockedOrder->table_id) {
                    $table = RestaurantTable::where('business_id', $lockedOrder->business_id)
                        ->lockForUpdate()
                        ->find($lockedOrder->table_id);
                    if ($table && $table->status !== 'billed') {
                        $table->update(['status' => 'billed']);
                    }
                }
            }
        });

        $order->refresh()->load(['items.product', 'user', 'table', 'business']);

        return view('restaurant.orders.pre-bill-ticket', compact('order'));
    }

    /**
     * Cancel an empty order and immediately release its table.
     */
    public function cancelEmpty(RestaurantOrder $order, RestaurantOrderService $orderService): RedirectResponse
    {
        Gate::authorize('cancelEmpty', $order);

        if ((int) $order->business_id !== (int) session('current_business_id')) {
            abort(403);
        }

        if ($order->status !== 'open') {
            return back()->with('error', 'Solo se pueden cancelar comandas en estado abierto.');
        }

        if ($order->items()->count() > 0) {
            return back()->with('error', 'No se puede cancelar una comanda que contiene platos comandados. Anule o elimine los consumos primero.');
        }

        $orderService->cancelEmptyOrder($order);

        return redirect()->route('restaurant.orders.index')
            ->with('status', "Comanda {$order->order_number} cancelada exitosamente y mesa liberada.");
    }

    /**
     * Remove an individual item or reduce its quantity from an active order and recalculate totals.
     */
    public function removeItem(
        RemoveOrderItemRequest $request,
        RestaurantOrder $order,
        RestaurantOrderItem $item,
        RestaurantOrderService $orderService
    ): RedirectResponse {
        Gate::authorize('deleteItem', $order);

        if ((int) $order->business_id !== (int) session('current_business_id')) {
            abort(403);
        }

        if (! $order->canBeModified()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'order' => 'No se pueden eliminar platos de una comanda que ya fue cobrada, facturada o cancelada.',
            ]);
        }

        $previousQty = (float) $item->quantity;
        $qtyToRemove = $request->validated('quantity_to_remove') !== null
            ? (float) $request->validated('quantity_to_remove')
            : $previousQty;

        $orderService->removeItemFromOrder(
            $order,
            $item,
            $request->validated('reason'),
            $request->user(),
            $qtyToRemove
        );

        $msg = $qtyToRemove < $previousQty
            ? "Se retiró(eron) {$qtyToRemove} unidad(es) de {$item->product?->name} y se recalculó la comanda."
            : "Plato {$item->product?->name} eliminado exitosamente de la comanda.";

        return redirect()->route('restaurant.orders.show', $order)
            ->with('success', $msg);
    }

    /**
     * Annul an entire order, free the assigned table, and restore stock.
     */
    public function cancel(
        CancelRestaurantOrderRequest $request,
        RestaurantOrder $order,
        RestaurantOrderService $orderService
    ): RedirectResponse {
        Gate::authorize('cancel', $order);

        if ((int) $order->business_id !== (int) session('current_business_id')) {
            abort(403);
        }

        if (! $order->canBeModified()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'order' => 'No se puede anular una comanda que ya fue cobrada, facturada o cancelada.',
            ]);
        }

        $orderService->cancelOrder(
            $order,
            $request->validated('reason'),
            $request->user()
        );

        return redirect()->route('restaurant.orders.show', $order)
            ->with('success', "Comanda {$order->order_number} anulada exitosamente y mesa liberada.");
    }

    /**
     * Change the assigned table of an active order.
     */
    public function changeTable(
        ChangeOrderTableRequest $request,
        RestaurantOrder $order,
        RestaurantOrderService $orderService
    ): RedirectResponse {
        Gate::authorize('update', $order);

        if ((int) $order->business_id !== (int) session('current_business_id')) {
            abort(403);
        }

        if (! $order->canBeModified()) {
            return back()->withErrors([
                'order' => 'No se puede cambiar de mesa una comanda que ya fue facturada, cobrada o anulada.',
            ]);
        }

        $newTable = RestaurantTable::where('business_id', (int) session('current_business_id'))
            ->findOrFail($request->validated('table_id'));

        $orderService->changeTable($order, $newTable, $request->user());

        return redirect()->route('restaurant.orders.show', $order)
            ->with('success', "Mesa cambiada exitosamente a: {$newTable->name}");
    }

    /**
     * Process split payment for selected items in an active table order.
     */
    public function splitPayment(
        SplitOrderPaymentRequest $request,
        RestaurantOrder $order,
        RestaurantOrderService $orderService
    ): RedirectResponse {
        Gate::authorize('update', $order);

        $result = $orderService->paySplitItems(
            order: $order,
            data: $request->validated(),
            user: $request->user()
        );

        $msg = $result['is_fully_paid']
            ? "Comanda {$order->order_number} saldada en su totalidad por división de cuenta. Mesa liberada."
            : "Pago parcial de {$result['items_count']} plato(s) por $" . number_format($result['split_total'], 0, ',', '.') . " registrado exitosamente.";

        return redirect()->route('restaurant.orders.show', $order)
            ->with('success', $msg)
            ->with('print_split_ticket_id', $result['payment_id']);
    }

    /**
     * Render 80mm thermal ticket for split pre-count (informational partial pre-bill).
     */
    public function printSplitPrecount(Request $request, RestaurantOrder $order): View
    {
        Gate::authorize('view', $order);

        $itemIds = $request->query('items', []);
        if (is_string($itemIds)) {
            $itemIds = explode(',', $itemIds);
        }

        $itemsQuery = $order->items()
            ->where('status', '!=', 'cancelled')
            ->where('payment_status', 'pending')
            ->with('product');

        if (! empty($itemIds)) {
            $itemsQuery->whereIn('id', (array) $itemIds);
        }

        $items = $itemsQuery->get();
        $splitTotal = (float) $items->sum('subtotal');

        $customerId = $request->query('customer_id');
        $customer = null;
        if ($customerId) {
            $customer = Customer::where('business_id', $order->business_id)->find($customerId);
        }

        $discountPct = max(0, min(100, (float) $request->query('discount_percentage', 0)));
        $includeTip = filter_var($request->query('include_tip', false), FILTER_VALIDATE_BOOLEAN);
        $includeTax = filter_var($request->query('include_tax', false), FILTER_VALIDATE_BOOLEAN);

        $discountAmount = round($splitTotal * ($discountPct / 100), 2);
        $base = max(0, round($splitTotal - $discountAmount, 2));
        $tipAmount = $includeTip ? round($base * 0.10, 2) : 0;
        $taxAmount = $includeTax ? round($base * 0.08, 2) : 0;
        $finalTotal = round($base + $tipAmount + $taxAmount, 2);

        $order->load(['table', 'user', 'customer', 'business']);

        return view('restaurant.orders.split-precount-ticket', [
            'order' => $order,
            'items' => $items,
            'splitTotal' => $splitTotal,
            'customer' => $customer,
            'discountPct' => $discountPct,
            'discountAmount' => $discountAmount,
            'includeTip' => $includeTip,
            'tipAmount' => $tipAmount,
            'includeTax' => $includeTax,
            'taxAmount' => $taxAmount,
            'finalTotal' => $finalTotal,
        ]);
    }

    /**
     * Render 80mm thermal receipt/ticket for a settled split payment.
     */
    public function printSplitTicket(RestaurantOrder $order, Sale $payment): View
    {
        Gate::authorize('view', $order);

        if ((int) $payment->restaurant_order_id !== (int) $order->id || (int) $payment->business_id !== (int) $order->business_id) {
            abort(404);
        }

        $order->load(['table', 'business', 'items']);
        $payment->load(['details.product', 'payments', 'user', 'customer', 'business']);

        return view('restaurant.orders.split-ticket', [
            'order' => $order,
            'payment' => $payment,
        ]);
    }

    /**
     * Bill and settle remaining balance of an order.
     */
    public function charge(
        Request $request,
        RestaurantOrder $order,
        RestaurantOrderService $orderService
    ): RedirectResponse {
        Gate::authorize('update', $order);

        if ((int) $order->business_id !== (int) session('current_business_id')) {
            abort(403);
        }

        $sale = $orderService->billOrder($order, $request->all(), $request->user());

        return redirect()->route('restaurant.orders.show', $order)
            ->with('success', "Comanda {$order->order_number} cobrada y liquidada exitosamente.")
            ->with('print_sale_ticket_id', $sale->id);
    }

    /**
     * Alias for charge method.
     */
    public function bill(
        Request $request,
        RestaurantOrder $order,
        RestaurantOrderService $orderService
    ): RedirectResponse {
        return $this->charge($request, $order, $orderService);
    }
}
