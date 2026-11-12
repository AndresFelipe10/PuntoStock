<?php

namespace Tests\Feature\Restaurant;

use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPrecountAndBatchLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $business;
    protected User $admin;
    protected User $waiter;
    protected RestaurantTable $table;
    protected Product $dishBurger;
    protected Product $dishPizza;
    protected Product $dishSoda;

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

        $this->business = Business::create([
            'name' => 'Restaurante Cali Stress Test',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create(['name' => 'Admin Restaurante']);
        $this->business->users()->attach($this->admin->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $this->waiter = User::factory()->create(['name' => 'Mesero Restaurante']);
        $this->business->users()->attach($this->waiter->id, ['role_id' => $this->employeeRole->id, 'is_active' => true]);

        $this->table = RestaurantTable::create([
            'business_id' => $this->business->id,
            'name' => 'Mesa 1',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        $category = Category::create([
            'business_id' => $this->business->id,
            'name' => 'Comidas Rápidas',
        ]);

        $this->dishBurger = Product::create([
            'business_id' => $this->business->id,
            'category_id' => $category->id,
            'name' => 'Hamburguesa Especial',
            'sku' => 'PLT-HMB-01',
            'product_type' => 'dish',
            'cost_price' => 12000,
            'sale_price' => 25000,
            'stock' => 50,
            'is_active' => true,
        ]);

        $this->dishPizza = Product::create([
            'business_id' => $this->business->id,
            'category_id' => $category->id,
            'name' => 'Pizza Artesanal',
            'sku' => 'PLT-PZA-01',
            'product_type' => 'dish',
            'cost_price' => 15000,
            'sale_price' => 30000,
            'stock' => 50,
            'is_active' => true,
        ]);

        $this->dishSoda = Product::create([
            'business_id' => $this->business->id,
            'category_id' => $category->id,
            'name' => 'Gaseosa Colombiana',
            'sku' => 'PLT-GAS-01',
            'product_type' => 'dish',
            'cost_price' => 2000,
            'sale_price' => 5000,
            'stock' => 100,
            'is_active' => true,
        ]);
    }

    /**
     * Test de estrés del ciclo completo:
     * Abrir comanda -> Emitir Pre-cuenta -> Verificar canBeModified y modals ->
     * Agregar Tanda #2 -> Verificar Enviado a Cocina inmediato ->
     * Re-emitir Pre-cuenta -> Agregar Tanda #3 -> Verificar respuesta 200 limpia ->
     * Cobro parcial Split Bill -> Cobro saldo restante final -> Cierre total.
     */
    public function test_complete_precount_and_batch_lifecycle_flow(): void
    {
        $orderService = app(RestaurantOrderService::class);

        // 1. Abrir comanda en Mesa 1 con Tanda #1
        $order = $orderService->openTableOrder($this->business->id, $this->waiter->id, [
            'table_id' => $this->table->id,
            'customer_name' => 'Comensal Mesa 1',
            'items' => [
                [
                    'product_id' => $this->dishBurger->id,
                    'quantity' => 1,
                    'notes' => 'Término 3/4',
                ],
            ],
        ]);

        $this->assertEquals('occupied', $this->table->fresh()->status);
        $this->assertEquals('in_kitchen', $order->fresh()->status);
        $this->assertTrue($order->fresh()->canBeModified());

        // 2. Emitir Pre-cuenta
        $responsePrebill = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->business->id])
            ->get(route('restaurant.orders.prebill', $order));

        $responsePrebill->assertOk();
        $this->assertNotNull($order->fresh()->precount_printed_at);
        $this->assertEquals('billed', $this->table->fresh()->status);
        $this->assertTrue($order->fresh()->canBeModified());
        $this->assertFalse($order->fresh()->isClosedOrBilled());

        // Verificar que la vista show NO muestra cerrada y SÍ tiene modales y formulario de agregar platos
        $responseShow1 = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->business->id])
            ->get(route('restaurant.orders.show', $order));

        $responseShow1->assertOk();
        $responseShow1->assertDontSee('Comanda Cerrada y Facturada');
        $responseShow1->assertDontSee('Esta comanda ha sido liquidada en su totalidad');
        $responseShow1->assertSee('id="settlementModal"', false);
        $responseShow1->assertSee('Agregar Platos a la Comanda');

        // 3. Agregar Tanda #2 (Pizza)
        $responseAddBatch2 = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('restaurant.orders.items.store', $order), [
                'items' => [
                    [
                        'product_id' => $this->dishPizza->id,
                        'quantity' => 1,
                        'notes' => 'Crujiente',
                    ],
                ],
            ]);

        $responseAddBatch2->assertRedirect(route('restaurant.orders.show', $order));
        $responseAddBatch2->assertSessionHas('print_kitchen_ticket_id', $order->id);
        $responseAddBatch2->assertSessionHas('print_kitchen_batch', 2);

        // La tanda 2 debe quedar inmediatamente como cocina/impresa
        $itemBatch2 = $order->fresh()->items()->where('batch_number', 2)->first();
        $this->assertNotNull($itemBatch2);
        $this->assertTrue((bool) $itemBatch2->printed_to_kitchen);
        $this->assertEquals('kitchen', $itemBatch2->status);

        // Precount debe haberse limpiado y mesa devuelta a occupied
        $this->assertNull($order->fresh()->precount_printed_at);
        $this->assertEquals('occupied', $this->table->fresh()->status);
        $this->assertEquals('in_kitchen', $order->fresh()->status);

        // Vista show muestra la tanda 2 inmediatamente enviada a cocina
        $responseShow2 = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->business->id])
            ->get(route('restaurant.orders.show', $order));

        $responseShow2->assertOk();
        $responseShow2->assertSee('Enviado a Cocina');
        $responseShow2->assertDontSee('Sin imprimir');

        // 4. Volver a emitir Pre-cuenta
        $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->business->id])
            ->get(route('restaurant.orders.prebill', $order))
            ->assertOk();

        $this->assertNotNull($order->fresh()->precount_printed_at);
        $this->assertEquals('billed', $this->table->fresh()->status);

        // 5. Agregar Tanda #3 (Gaseosa)
        $responseAddBatch3 = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('restaurant.orders.items.store', $order), [
                'items' => [
                    [
                        'product_id' => $this->dishSoda->id,
                        'quantity' => 2,
                        'notes' => null,
                    ],
                ],
            ]);

        $responseAddBatch3->assertRedirect(route('restaurant.orders.show', $order));
        $responseAddBatch3->assertSessionHas('print_kitchen_batch', 3);

        $itemBatch3 = $order->fresh()->items()->where('batch_number', 3)->first();
        $this->assertNotNull($itemBatch3);
        $this->assertTrue((bool) $itemBatch3->printed_to_kitchen);

        // Verificar que GET /restaurant/orders/{id} responde HTTP 200 limpio sin 500
        $responseShow3 = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->business->id])
            ->get(route('restaurant.orders.show', $order));

        $responseShow3->assertOk();
        $responseShow3->assertDontSee('Comanda Cerrada y Facturada');

        // Total esperado: 25.000 (burger) + 30.000 (pizza) + 10.000 (2 gaseosas) = 65.000
        $this->assertEquals(65000, $order->fresh()->totalAmount());
        $this->assertEquals(65000, $order->fresh()->pendingBalance());

        // 6. Cobrar parcialmente con Split Bill (Hamburguesa = $25.000)
        $burgerItem = $order->fresh()->items()->where('product_id', $this->dishBurger->id)->first();
        $responseSplit = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('restaurant.orders.split-payment', $order), [
                'items' => [$burgerItem->id],
                'payments' => [
                    [
                        'method' => 'cash',
                        'amount' => 25000,
                        'cash_received' => 25000,
                    ],
                ],
            ]);

        $responseSplit->assertRedirect(route('restaurant.orders.show', $order));
        $this->assertEquals(40000, $order->fresh()->pendingBalance());
        $this->assertEquals(25000, $order->fresh()->paidAmount());
        $this->assertFalse($order->fresh()->isClosedOrBilled());
        $this->assertTrue($order->fresh()->canBeModified());
        $this->assertEquals('occupied', $this->table->fresh()->status);

        // 7. Liquidar saldo restante ($40.000) con Cobrar / Facturar
        $responseFinalCharge = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('restaurant.orders.charge', $order), [
                'payments' => [
                    [
                        'method' => 'cash',
                        'amount' => 40000,
                        'cash_received' => 50000,
                    ],
                ],
            ]);

        $responseFinalCharge->assertRedirect();
        $this->assertTrue($order->fresh()->isClosedOrBilled());
        $this->assertFalse($order->fresh()->canBeModified());
        $this->assertEquals(0, $order->fresh()->pendingBalance());
        $this->assertEquals('available', $this->table->fresh()->status);

        // La vista show ahora sí debe mostrar Comanda Cerrada y Facturada
        $responseShowFinal = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->business->id])
            ->get(route('restaurant.orders.show', $order));

        $responseShowFinal->assertOk();
        $responseShowFinal->assertSee('Comanda Cerrada y Facturada');
        $responseShowFinal->assertSee('Esta comanda ha sido liquidada en su totalidad');
        $responseShowFinal->assertDontSee('id="settlementForm"', false);
    }

    /**
     * Test de creación rápida de cliente desde Split Bill:
     * Creación AJAX con JSON -> Inyección multi-tenant -> Asociación al pago parcial.
     */
    public function test_quick_customer_creation_from_split_bill(): void
    {
        $orderService = app(RestaurantOrderService::class);

        $order = $orderService->openTableOrder($this->business->id, $this->waiter->id, [
            'table_id' => $this->table->id,
            'items' => [
                [
                    'product_id' => $this->dishBurger->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        // 1. Enviar petición JSON para crear cliente rápido
        $customerData = [
            'name' => 'Comensal Rápido VIP',
            'document' => '1098765432',
            'phone' => '3159998877',
        ];

        $responseCustomer = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->business->id])
            ->postJson(route('customers.store'), $customerData);

        $responseCustomer->assertStatus(201);
        $responseCustomer->assertJsonFragment([
            'name' => 'Comensal Rápido VIP',
            'document' => '1098765432',
        ]);

        $createdCustomerId = $responseCustomer->json('id');
        $this->assertNotNull($createdCustomerId);

        // Verificar aislamiento multi-tenant
        $dbCustomer = Customer::find($createdCustomerId);
        $this->assertEquals($this->business->id, $dbCustomer->business_id);

        // 2. Procesar pago en splitPayment asociando el nuevo customer_id
        $item = $order->items->first();
        $responseSplit = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('restaurant.orders.split-payment', $order), [
                'items' => [$item->id],
                'customer_id' => $createdCustomerId,
                'payments' => [
                    [
                        'method' => 'cash',
                        'amount' => 25000,
                        'cash_received' => 25000,
                    ],
                ],
            ]);

        $responseSplit->assertRedirect(route('restaurant.orders.show', $order));

        // Verificar que la venta parcial quedó a nombre del nuevo cliente
        $sale = $order->fresh()->sales()->latest('id')->first();
        $this->assertNotNull($sale);
        $this->assertEquals($createdCustomerId, $sale->customer_id);
        $this->assertEquals('Comensal Rápido VIP', $sale->customer_name);
        $this->assertEquals('1098765432', $sale->customer_document);
    }

    /**
     * Test del comando Artisan restaurant:sync-tables para saneamiento automático.
     */
    public function test_artisan_sync_tables_command_repairs_orphans(): void
    {
        // 1. Mesa con comanda huérfana en falso 'billed'
        $corruptedOrder = RestaurantOrder::create([
            'business_id' => $this->business->id,
            'user_id' => $this->waiter->id,
            'table_id' => $this->table->id,
            'order_number' => 'ORD-TEST-999',
            'order_type' => 'table',
            'status' => 'billed',
            'sale_id' => null,
            'subtotal' => 25000,
            'total' => 25000,
        ]);

        RestaurantOrderItem::create([
            'business_id' => $this->business->id,
            'order_id' => $corruptedOrder->id,
            'product_id' => $this->dishBurger->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
            'status' => 'kitchen',
            'printed_to_kitchen' => true,
            'payment_status' => 'pending',
            'batch_number' => 1,
        ]);

        $this->table->update(['status' => 'billed']);

        // 2. Mesa 2 huérfana en 'billed' sin comanda
        $table2 = RestaurantTable::create([
            'business_id' => $this->business->id,
            'name' => 'Mesa Huérfana',
            'capacity' => 2,
            'status' => 'billed',
            'is_active' => true,
        ]);

        // Ejecutar comando de sincronización
        $this->artisan('restaurant:sync-tables', ['--business' => $this->business->id])
            ->assertSuccessful();

        // La comanda corrupta debió restaurarse a in_kitchen
        $this->assertEquals('in_kitchen', $corruptedOrder->fresh()->status);

        // La mesa huérfana debió liberarse a available
        $this->assertEquals('available', $table2->fresh()->status);
    }

    /**
     * Test de interfaz: Verificar que show.blade.php renderice el botón [+]
     * en ambos modales y el modal estandarizado #quickCustomerModal con los 4 campos.
     */
    public function test_show_view_renders_quick_customer_buttons_and_standardized_modal(): void
    {
        $orderService = app(RestaurantOrderService::class);

        $order = $orderService->openTableOrder($this->business->id, $this->waiter->id, [
            'table_id' => $this->table->id,
            'items' => [
                [
                    'product_id' => $this->dishBurger->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->business->id])
            ->get(route('restaurant.orders.show', $order));

        $response->assertOk();

        // 1. Botones [bi-person-plus] para crear cliente rápido en ambos modales
        $response->assertSee('data-target-select="#settlement_customer_id"', false);
        $response->assertSee('data-target-select="#split_customer_id"', false);
        $response->assertSee('btn-open-quick-customer');
        $response->assertSee('btn-quick-customer-trigger');
        $response->assertSee('bi-person-plus');

        // 2. Modal estandarizado #quickCustomerModal
        $response->assertSee('id="quickCustomerModal"', false);
        $response->assertSee('Crear Cliente Rápido');

        // 3. Los 4 campos requeridos
        $response->assertSee('id="quick_customer_document"', false);
        $response->assertSee('Cédula o NIT');
        $response->assertSee('id="quick_customer_name"', false);
        $response->assertSee('Nombre o Razón Social');
        $response->assertSee('id="quick_customer_phone"', false);
        $response->assertSee('Teléfono');
        $response->assertSee('id="quick_customer_email"', false);
        $response->assertSee('Correo Electrónico');
        $response->assertSee('id="btn_save_quick_customer"', false);
    }

    /**
     * Test de interfaz: Salón y Mapa de Mesas NO renderiza botón "Configuración",
     * pero SÍ renderiza "Ver Comandas" y "Nueva Mesa".
     */
    public function test_tables_index_does_not_render_configuration_button_but_renders_responsive_actions(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->business->id])
            ->get(route('restaurant.tables.index'));

        $response->assertOk();

        // Acciones esperadas
        $response->assertSee('Salón y Mapa de Mesas');
        $response->assertSee('Ver Comandas');
        $response->assertSee('Nueva Mesa');

        // El botón y modal de Configuración NO deben estar presentes en esta vista
        $response->assertDontSee('Configuración de Comandas');
        $response->assertDontSee('kitchenConfigModal');
    }

    /**
     * Test de persistencia: Creación rápida de cliente con los 4 campos bajo el business_id activo.
     */
    public function test_quick_customer_creation_saves_all_four_fields_under_active_business_id(): void
    {
        $payload = [
            'document' => '900123456-1',
            'name' => 'Inversiones Gastronómicas Cali SAS',
            'phone' => '3157778899',
            'email' => 'facturacion@gastronomicascali.com',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->business->id])
            ->postJson(route('customers.store'), $payload);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'document' => '900123456-1',
            'name' => 'Inversiones Gastronómicas Cali SAS',
            'phone' => '3157778899',
        ]);

        $this->assertDatabaseHas('customers', [
            'business_id' => $this->business->id,
            'document' => '900123456-1',
            'name' => 'Inversiones Gastronómicas Cali SAS',
            'phone' => '3157778899',
            'email' => 'facturacion@gastronomicascali.com',
            'is_active' => true,
        ]);
    }

    /**
     * Test de interfaz: Los botones de agregar platos renderizan etiquetas limpias sin doble signo +.
     */
    public function test_order_create_and_show_buttons_render_clean_labels_without_double_plus(): void
    {
        // 1. GET /restaurant/orders/create
        $responseCreate = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->business->id])
            ->get(route('restaurant.orders.create'));

        $responseCreate->assertOk();
        $responseCreate->assertSee('Agregar plato');
        $responseCreate->assertSee('bi-plus-circle');
        $responseCreate->assertDontSee('+ Agregar plato');

        // Crear una orden para probar la vista show
        $order = RestaurantOrder::create([
            'business_id' => $this->business->id,
            'table_id' => $this->table->id,
            'user_id' => $this->waiter->id,
            'order_type' => 'table',
            'status' => 'open',
            'order_number' => 'ORD-BTN-001',
            'subtotal' => 0,
            'total' => 0,
        ]);

        // 2. GET /restaurant/orders/{order}
        $responseShow = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->business->id])
            ->get(route('restaurant.orders.show', $order));

        $responseShow->assertOk();
        $responseShow->assertSee('Agregar otro plato');
        $responseShow->assertSee('bi-plus-circle');
        $responseShow->assertDontSee('+ Agregar otro plato');
    }
}

