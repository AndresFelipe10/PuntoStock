<?php

namespace Tests\Feature\Restaurant;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $businessA;
    protected Business $businessB;
    protected User $adminA;
    protected User $adminB;
    protected RestaurantTable $tableA;
    protected Product $dishPasta;
    protected Product $dishLomo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create([
            'name' => 'Administrador',
            'slug' => Role::ROLE_ADMIN,
        ]);

        $this->employeeRole = Role::create([
            'name' => 'Empleado',
            'slug' => Role::ROLE_EMPLOYEE,
        ]);

        $this->businessA = Business::create([
            'name' => 'Restaurante Cali Sabor A',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->businessB = Business::create([
            'name' => 'Restaurante Cali Sabor B',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->adminA = User::factory()->create(['name' => 'Admin Restaurante A']);
        $this->businessA->users()->attach($this->adminA->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $this->adminB = User::factory()->create(['name' => 'Admin Restaurante B']);
        $this->businessB->users()->attach($this->adminB->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $this->tableA = RestaurantTable::create([
            'business_id' => $this->businessA->id,
            'name' => 'Mesa 4',
            'capacity' => 4,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $categoryA = Category::create([
            'business_id' => $this->businessA->id,
            'name' => 'Platos Principales',
        ]);

        // 2 platos de $25.000 cada uno
        $this->dishPasta = Product::create([
            'business_id' => $this->businessA->id,
            'category_id' => $categoryA->id,
            'name' => 'Pasta Carbonara Gourmet',
            'sku' => 'PLT-PASTA-01',
            'product_type' => 'dish',
            'cost_price' => 10000,
            'sale_price' => 25000,
            'stock' => 15,
            'is_active' => true,
        ]);

        $this->dishLomo = Product::create([
            'business_id' => $this->businessA->id,
            'category_id' => $categoryA->id,
            'name' => 'Lomo al Trapo Tradicional',
            'sku' => 'PLT-LOMO-01',
            'product_type' => 'dish',
            'cost_price' => 12000,
            'sale_price' => 25000,
            'stock' => 15,
            'is_active' => true,
        ]);
    }

    /**
     * Caso 1 (El bug reportado):
     * Comanda con 2 platos de $25.000 ($50.000 total).
     * Se paga 1 plato mediante paySplitItems() ($25.000). Comanda queda open.
     * Se invoca el endpoint de cobro regular (orders.charge / orders.bill) pagando $25.000.
     * Aserción: Petición exitosa, comanda pasa a billed, mesa pasa a available, y ventas acumuladas suman $50.000.
     */
    public function test_case_1_successful_mixed_flow_settlement_after_partial_split_bill(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminA->id,
            'table_id' => $this->tableA->id,
            'order_number' => 'ORD-0031',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 50000,
            'total' => 50000,
        ]);

        $item1 = RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->dishPasta->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
            'status' => 'served',
            'payment_status' => 'pending',
            'batch_number' => 1,
        ]);

        $item2 = RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->dishLomo->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
            'status' => 'served',
            'payment_status' => 'pending',
            'batch_number' => 1,
        ]);

        // Verificaciones del estado inicial
        $this->assertEquals(50000.0, $order->totalAmount());
        $this->assertEquals(0.0, $order->paidAmount());
        $this->assertEquals(50000.0, $order->pendingBalance());
        $this->assertFalse($order->hasPartialPayments());

        // Paso 1: Liquidar plato 1 ($25.000) por división de cuenta (Split Bill)
        $orderService = app(RestaurantOrderService::class);
        $splitResult = $orderService->paySplitItems(
            order: $order,
            selectedItemIds: [$item1->id],
            paymentMethod: 'cash',
            amountReceived: 25000,
            reference: null,
            user: $this->adminA
        );

        $this->assertFalse($splitResult['is_fully_paid']);
        $this->assertEquals(25000.0, $splitResult['remaining_balance']);

        $order->refresh();
        $this->assertEquals('open', $order->status);
        $this->assertEquals(50000.0, $order->totalAmount());
        $this->assertEquals(25000.0, $order->paidAmount());
        $this->assertEquals(25000.0, $order->pendingBalance());
        $this->assertTrue($order->hasPartialPayments());

        // Paso 2: Invocar el endpoint de cobro regular (orders.charge) pagando los $25.000 restantes
        $payload = [
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 25000,
                    'cash_received' => 25000,
                    'change_given' => 0,
                ],
            ],
            'notes' => 'Cobro de saldo restante de la comanda',
        ];

        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('restaurant.orders.charge', $order), $payload);

        $response->assertRedirect(route('restaurant.orders.show', $order));
        $response->assertSessionHas('success');

        // Paso 3: Aserciones críticas
        $order->refresh();
        $this->assertEquals('billed', $order->status);
        $this->assertNotNull($order->closed_at);
        $this->assertTrue($order->isFullyPaid());
        $this->assertEquals(0.0, $order->pendingBalance());

        // Mesa liberada a 'available'
        $this->tableA->refresh();
        $this->assertEquals('available', $this->tableA->status);

        // Ítems marcados como 'paid'
        $item1->refresh();
        $item2->refresh();
        $this->assertEquals('paid', $item1->payment_status);
        $this->assertEquals('paid', $item2->payment_status);

        // Ventas acumuladas de la comanda suman $50.000 (exactamente dos ventas de $25.000 cada una)
        $sales = Sale::where('restaurant_order_id', $order->id)->get();
        $this->assertCount(2, $sales);
        $this->assertEquals(50000.0, (float) $sales->sum('total'));
        $this->assertStringContainsString('Comanda cerrada y liquidada en su totalidad', $order->notes);
    }

    /**
     * Caso 2: Rechazo de sobrepago ($50.000) o pago insuficiente ($10.000) cuando el saldo pendiente es $25.000.
     */
    public function test_case_2_rejection_of_overpayment_or_underpayment_against_pending_balance(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminA->id,
            'table_id' => $this->tableA->id,
            'order_number' => 'ORD-0032',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 50000,
            'total' => 50000,
        ]);

        $item1 = RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->dishPasta->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
            'status' => 'served',
            'payment_status' => 'pending',
            'batch_number' => 1,
        ]);

        RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->dishLomo->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
            'status' => 'served',
            'payment_status' => 'pending',
            'batch_number' => 1,
        ]);

        // Pagar 1 plato ($25.000) por división de cuenta
        $orderService = app(RestaurantOrderService::class);
        $orderService->paySplitItems(
            order: $order,
            selectedItemIds: [$item1->id],
            paymentMethod: 'cash',
            amountReceived: 25000,
            reference: null,
            user: $this->adminA
        );

        $order->refresh();
        $this->assertEquals(25000.0, $order->pendingBalance());

        // Intento 1: Enviar $50.000 (el total histórico bruto de la orden) -> DEBE RECHAZARSE
        $responseOverpay = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('restaurant.orders.charge', $order), [
                'payments' => [
                    ['method' => 'cash', 'amount' => 50000, 'cash_received' => 50000],
                ],
            ]);

        $responseOverpay->assertSessionHasErrors(['payments']);

        // Intento 2: Enviar $10.000 (pago insuficiente) -> DEBE RECHAZARSE
        $responseUnderpay = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('restaurant.orders.charge', $order), [
                'payments' => [
                    ['method' => 'cash', 'amount' => 10000, 'cash_received' => 10000],
                ],
            ]);

        $responseUnderpay->assertSessionHasErrors(['payments']);

        // Confirmar que la orden sigue abierta y no se cerró
        $this->assertEquals('open', $order->fresh()->status);
    }

    /**
     * Caso 3: Protección de ítems pagados:
     * Verificar que no se permita eliminar o reducir un plato que ya tiene payment_status === 'paid',
     * ni anular una comanda que tiene pagos parciales activos.
     */
    public function test_case_3_protection_of_paid_items_and_partially_paid_orders(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminA->id,
            'table_id' => $this->tableA->id,
            'order_number' => 'ORD-0033',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 50000,
            'total' => 50000,
        ]);

        $item1 = RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->dishPasta->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
            'status' => 'served',
            'payment_status' => 'pending',
            'batch_number' => 1,
        ]);

        $item2 = RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->dishLomo->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
            'status' => 'served',
            'payment_status' => 'pending',
            'batch_number' => 1,
        ]);

        // Pagar item 1
        $orderService = app(RestaurantOrderService::class);
        $orderService->paySplitItems(
            order: $order,
            selectedItemIds: [$item1->id],
            paymentMethod: 'cash',
            amountReceived: 25000,
            reference: null,
            user: $this->adminA
        );

        $item1->refresh();
        $this->assertTrue($item1->isPaid());

        // Intento 1: Eliminar el ítem 1 que ya está pagado
        $responseDelete = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->delete(route('restaurant.orders.items.destroy', [$order, $item1]), [
                'reason' => 'Cliente pidió devolución',
                'quantity_to_remove' => 1,
            ]);

        $responseDelete->assertSessionHasErrors(['item']);
        $this->assertEquals('served', $item1->fresh()->status);

        // Intento 2: Anular la comanda completa que ya contiene ítems pagados
        $responseCancelOrder = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('restaurant.orders.cancel', $order), [
                'reason' => 'Anular mesa completa',
            ]);

        $responseCancelOrder->assertSessionHasErrors(['order']);
        $this->assertEquals('open', $order->fresh()->status);
    }

    /**
     * Caso 4: Cobro exitoso utilizando el alias de ruta 'orders.bill'.
     */
    public function test_case_4_order_billing_via_alias_orders_bill(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminA->id,
            'table_id' => $this->tableA->id,
            'order_number' => 'ORD-0034',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 25000,
            'total' => 25000,
        ]);

        RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->dishPasta->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
            'status' => 'served',
            'payment_status' => 'pending',
            'batch_number' => 1,
        ]);

        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('restaurant.orders.bill', $order), [
                'payments' => [
                    ['method' => 'card', 'amount' => 25000, 'reference' => 'VOUCHER-12345'],
                ],
            ]);

        $response->assertRedirect(route('restaurant.orders.show', $order));
        $this->assertEquals('billed', $order->fresh()->status);
        $this->assertEquals('available', $this->tableA->fresh()->status);
    }

    /**
     * Caso 5: Aislamiento multi-tenant (Usuario de Empresa B no puede cobrar orden de Empresa A).
     */
    public function test_case_5_multi_tenant_isolation_on_order_charging(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminA->id,
            'table_id' => $this->tableA->id,
            'order_number' => 'ORD-0035',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 25000,
            'total' => 25000,
        ]);

        RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->dishPasta->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
            'status' => 'served',
            'payment_status' => 'pending',
            'batch_number' => 1,
        ]);

        // Usuario de Negocio B intenta cobrar comanda de Negocio A
        $response = $this->actingAs($this->adminB)
            ->withSession(['current_business_id' => $this->businessB->id])
            ->post(route('restaurant.orders.charge', $order), [
                'payments' => [
                    ['method' => 'cash', 'amount' => 25000, 'cash_received' => 25000],
                ],
            ]);

        // Debe ser bloqueado por autorización / multi-tenancy (403 o 404)
        $this->assertTrue(in_array($response->status(), [403, 404]));
        $this->assertEquals('open', $order->fresh()->status);
    }

    /**
     * Caso 6: Verificación de métodos y accesores computados en RestaurantOrder.
     */
    public function test_case_6_model_financial_attributes_and_accessors_integrity(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminA->id,
            'table_id' => $this->tableA->id,
            'order_number' => 'ORD-0036',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 50000,
            'total' => 50000,
        ]);

        RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->dishPasta->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
            'status' => 'served',
            'payment_status' => 'paid',
            'batch_number' => 1,
        ]);

        RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->dishLomo->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
            'status' => 'served',
            'payment_status' => 'pending',
            'batch_number' => 1,
        ]);

        $order->load('items');

        $this->assertEquals(50000.0, $order->total_amount);
        $this->assertEquals(25000.0, $order->paid_amount);
        $this->assertEquals(25000.0, $order->pending_balance);
        $this->assertTrue($order->has_partial_payments);
        $this->assertFalse($order->isFullyPaid());
        $this->assertCount(1, $order->unpaidItems());
    }

    /**
     * Test 1: Jerarquía de Badges en index: Una comanda facturada/cerrada (status: billed)
     * que tuvo pre-cuenta emitida (precount_printed_at != null) debe mostrarse como
     * "Cerrada / Pagada" (bg-success) y NO como "En Cobro / Pre-cuenta emitida".
     */
    public function test_status_badge_hierarchy_in_orders_index_prioritizes_billed_over_precount(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminA->id,
            'table_id' => $this->tableA->id,
            'order_number' => 'ORD-0099',
            'order_type' => 'table',
            'status' => 'billed',
            'precount_printed_at' => now()->subMinutes(10),
            'closed_at' => now(),
            'subtotal' => 50000,
            'total' => 50000,
        ]);

        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('restaurant.orders.index'));

        $response->assertOk();
        $response->assertSee('ORD-0099');
        $response->assertSee('Cerrada / Pagada');
        $response->assertDontSee('En Cobro / Pre-cuenta emitida');
    }

    /**
     * Test 2: Bloqueo Visual en show: Al visitar una comanda liquidada/facturada (status: billed),
     * el formulario inferior y el botón "Confirmar y Enviar a Cocina" NO deben existir en el HTML,
     * y en su lugar debe renderizarse el bloque ergonómico con "Volver al Salón y Mesas".
     */
    public function test_visual_lock_in_show_view_hides_add_items_form_and_renders_return_links_when_order_billed(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminA->id,
            'table_id' => $this->tableA->id,
            'order_number' => 'ORD-0100',
            'order_type' => 'table',
            'status' => 'billed',
            'precount_printed_at' => now()->subMinutes(15),
            'closed_at' => now(),
            'subtotal' => 50000,
            'total' => 50000,
        ]);

        RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->dishPasta->id,
            'quantity' => 2,
            'unit_price' => 25000,
            'subtotal' => 50000,
            'status' => 'served',
            'payment_status' => 'paid',
            'batch_number' => 1,
        ]);

        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('restaurant.orders.show', $order));

        $response->assertOk();
        // El botón de enviar más platos a cocina no debe existir
        $response->assertDontSee('Confirmar y Enviar a Cocina');
        // Debe mostrar el panel ergonómico de comanda cerrada con sus botones de navegación
        $response->assertSee('Volver al Salón y Mesas');
        $response->assertSee('Ir a Comandas de Hoy');
        $response->assertSee('Comanda Cerrada y Facturada');
        $response->assertSee('Esta comanda ha sido liquidada en su totalidad');
    }

    /**
     * Test 3: Rechazo Defensivo en Backend: Intentar hacer un POST a agregar ítem
     * en una orden con status = 'billed' debe ser rechazado impidiendo la modificación.
     */
    public function test_backend_rejection_when_attempting_to_add_items_to_billed_order(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminA->id,
            'table_id' => $this->tableA->id,
            'order_number' => 'ORD-0101',
            'order_type' => 'table',
            'status' => 'billed',
            'closed_at' => now(),
            'subtotal' => 25000,
            'total' => 25000,
        ]);

        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('restaurant.orders.items.store', $order), [
                'items' => [
                    [
                        'product_id' => $this->dishPasta->id,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response->assertRedirect(route('restaurant.orders.show', $order));
        $response->assertSessionHas('error', 'No se pueden agregar platos a una comanda que ya ha sido cerrada y facturada.');

        // Verificar que no se creó ningún ítem nuevo
        $this->assertEquals(0, RestaurantOrderItem::where('order_id', $order->id)->count());
    }

    /**
     * Test: Corrección de raíz del doble cobro de domicilio (ORD-0398).
     * 1 plato de $20.000 + delivery_fee de $3.000 = total $23.000.
     * El HTML no debe renderizar $26.000, el subtotal de consumo debe ser $20.000,
     * el flete $3.000 y el total a liquidar $23.000.
     * Al cobrar con $23.000 en efectivo, la orden pasa a 'billed' y la venta almacena total = 23000, subtotal = 20000, delivery_fee = 3000.
     */
    public function test_delivery_order_settlement_avoids_double_charging_delivery_fee(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminA->id,
            'order_number' => 'ORD-0398',
            'order_type' => 'delivery',
            'status' => 'open',
            'customer_name' => 'Cliente Domicilio',
            'delivery_address' => 'Calle 10 # 5-20, Cali',
            'delivery_phone' => '3123456789',
            'delivery_fee' => 3000.00,
            'subtotal' => 20000.00,
            'total' => 23000.00,
        ]);

        RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->dishPasta->id,
            'quantity' => 1,
            'unit_price' => 20000.00,
            'subtotal' => 20000.00,
            'status' => 'served',
            'payment_status' => 'pending',
            'batch_number' => 1,
        ]);

        $order->load(['items', 'sales']);

        // 1. Verificación en el modelo
        $this->assertEquals(20000.00, $order->itemsSubtotal());
        $this->assertEquals(3000.00, $order->deliveryFeeAmount());
        $this->assertEquals(23000.00, $order->totalAmount());
        $this->assertEquals(20000.00, $order->pendingItemsSubtotal());
        $this->assertEquals(3000.00, $order->pendingDeliveryFee());
        $this->assertEquals(23000.00, $order->pendingBalance());

        // 2. Verificación de la vista show
        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('restaurant.orders.show', $order));

        $response->assertOk();
        $html = $response->getContent();

        // No debe mostrar $26.000 ni $26,000 en ningún lugar
        $this->assertStringNotContainsString('26.000', $html);
        $this->assertStringNotContainsString('26,000', $html);

        // Subtotal de consumo en $20,000.00, flete en $3,000.00
        $response->assertSee('20,000.00');
        $response->assertSee('3,000.00');

        // 3. POST de cobro/facturación enviando exactamente $23.000
        $chargePayload = [
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 23000.00,
                    'cash_received' => 23000.00,
                    'change_given' => 0.00,
                ],
            ],
            'discount_percentage' => 0,
        ];

        $postResponse = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('restaurant.orders.charge', $order), $chargePayload);

        $postResponse->assertRedirect(route('restaurant.orders.show', $order));
        $postResponse->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertEquals('billed', $order->status);
        $this->assertEquals(0.00, $order->pendingBalance());

        $sale = Sale::where('restaurant_order_id', $order->id)->first();
        $this->assertNotNull($sale);
        $this->assertEquals(20000.00, (float) $sale->subtotal);
        $this->assertEquals(3000.00, (float) $sale->delivery_fee);
        $this->assertEquals(23000.00, (float) $sale->total);
        $this->assertEquals('completed', $sale->status);
    }

    /**
     * Test: Domicilio con descuento e impuesto INC.
     * Platos: $50.000, Flete: $5.000.
     * Descuento: 10% (-$5.000 sobre platos -> netItems = $45.000).
     * INC 8%: (+$3.600 sobre netItems).
     * Domicilio: (+$5.000).
     * Total esperado: $45.000 + $3.600 + $5.000 = $53.600.
     */
    public function test_delivery_order_settlement_with_discount_and_tax_inc(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminA->id,
            'order_number' => 'ORD-0399',
            'order_type' => 'delivery',
            'status' => 'open',
            'customer_name' => 'Cliente Gourmet',
            'delivery_address' => 'Av Colombia # 12-34',
            'delivery_phone' => '3159998877',
            'delivery_fee' => 5000.00,
            'subtotal' => 50000.00,
            'total' => 55000.00,
        ]);

        RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->dishPasta->id,
            'quantity' => 2,
            'unit_price' => 25000.00,
            'subtotal' => 50000.00,
            'status' => 'served',
            'payment_status' => 'pending',
            'batch_number' => 1,
        ]);

        $order->load(['items', 'sales']);

        // Base items = 50.000, Delivery fee = 5.000
        $this->assertEquals(50000.00, $order->itemsSubtotal());
        $this->assertEquals(5000.00, $order->deliveryFeeAmount());

        $chargePayload = [
            'discount_percentage' => 10,
            'include_tax' => true,
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 53600.00,
                    'cash_received' => 53600.00,
                    'change_given' => 0.00,
                ],
            ],
        ];

        $postResponse = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('restaurant.orders.charge', $order), $chargePayload);

        $postResponse->assertRedirect(route('restaurant.orders.show', $order));
        $postResponse->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertEquals('billed', $order->status);

        $sale = Sale::where('restaurant_order_id', $order->id)->first();
        $this->assertNotNull($sale);
        $this->assertEquals(50000.00, (float) $sale->subtotal);
        $this->assertEquals(5000.00, (float) $sale->discount);
        $this->assertEquals(10.0, (float) $sale->discount_percentage);
        $this->assertEquals(3600.00, (float) $sale->tax_inc);
        $this->assertEquals(5000.00, (float) $sale->delivery_fee);
        $this->assertEquals(53600.00, (float) $sale->total);
    }

    /**
     * Test: La notificación flash de cobro exitoso se renderiza exactamente 1 sola vez en show (sin duplicidad).
     */
    public function test_settlement_success_flash_message_renders_exactly_once_without_duplicates(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminA->id,
            'table_id' => $this->tableA->id,
            'order_number' => 'ORD-0099',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 25000,
            'total' => 25000,
        ]);

        RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->dishPasta->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
            'status' => 'served',
            'payment_status' => 'pending',
            'batch_number' => 1,
        ]);

        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('restaurant.orders.charge', $order), [
                'payments' => [
                    ['method' => 'cash', 'amount' => 25000, 'cash_received' => 25000, 'change_given' => 0],
                ],
            ]);

        $response->assertRedirect(route('restaurant.orders.show', $order));

        $followResponse = $this->followRedirects($response);
        $followResponse->assertOk();

        $content = $followResponse->getContent();
        $this->assertSame(
            1,
            substr_count($content, 'cobrada y liquidada exitosamente'),
            'El mensaje de éxito de comanda cobrada debe renderizarse exactamente 1 sola vez en el HTML.'
        );
    }
}

