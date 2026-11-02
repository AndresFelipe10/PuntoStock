<?php

namespace Tests\Feature\Restaurant;

use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdersFilterAndQuickCustomerTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $restaurant;
    protected User $admin;
    protected User $employee;
    protected RestaurantTable $table;
    protected Product $dish;

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

        $this->restaurant = Business::create([
            'name' => 'Restaurante Sabor Criollo',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create();
        $this->restaurant->users()->attach($this->admin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->employee = User::factory()->create();
        $this->restaurant->users()->attach($this->employee->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);

        $this->table = RestaurantTable::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Mesa 10',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $category = Category::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Almuerzos',
        ]);

        $this->dish = Product::create([
            'business_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'name' => 'Sancocho de Gallina',
            'sku' => 'SAN-01',
            'sale_price' => 25000,
            'stock' => 50,
            'product_type' => 'dish',
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: La vista de nuevo pedido domicilio/para llevar renderiza el botón estandarizado y el modal rápido.
     */
    public function test_create_delivery_order_view_renders_quick_customer_button_and_modal(): void
    {
        $response = $this->actingAs($this->employee)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.create-delivery'));

        $response->assertOk();
        $response->assertSee('btnOpenQuickCustomer');
        $response->assertSee('btn-open-quick-customer');
        $response->assertSee('bi-person-plus');
        $response->assertSee('quickCustomerModal');
        $response->assertSee('btn_save_quick_customer');
        $response->assertSee('quick_customer_document');
        $response->assertSee('quick_customer_name');
        $response->assertSee('quick_customer_phone');
        $response->assertSee('quick_customer_address');
    }

    /**
     * Test 2: La creación AJAX rápida de clientes retorna la carga completa con id, documento, nombre, teléfono y dirección.
     */
    public function test_ajax_quick_customer_creation_stores_customer_and_returns_complete_payload_including_address(): void
    {
        $payload = [
            'document' => '1144009988',
            'name' => 'Carlos Alberto Mendoza',
            'phone' => '3123456789',
            'address' => 'Calle 5 # 12-34 Barrio Granada',
            'email' => 'carlos.mendoza@test.com',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->employee)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->postJson(route('customers.store'), $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'name',
            'document',
            'phone',
            'address',
            'message',
        ]);

        $response->assertJson([
            'name' => 'Carlos Alberto Mendoza',
            'document' => '1144009988',
            'phone' => '3123456789',
            'address' => 'Calle 5 # 12-34 Barrio Granada',
        ]);

        $this->assertDatabaseHas('customers', [
            'business_id' => $this->restaurant->id,
            'name' => 'Carlos Alberto Mendoza',
            'document' => '1144009988',
            'address' => 'Calle 5 # 12-34 Barrio Granada',
        ]);
    }

    /**
     * Test 3: Filtro por estado 'precount' (En Cobro) en historial incluye exclusivamente comandas activas
     * con pre-cuenta impresa y excluye tajantemente comandas facturadas ('billed').
     */
    public function test_orders_history_filter_by_status_precount_only_includes_active_orders_with_precount_and_excludes_billed_orders(): void
    {
        $pastDate = Carbon::now('America/Bogota')->subDays(2)->setTime(12, 0, 0);

        // 1. Comanda anterior ACTIVA con pre-cuenta impresa (En Cobro)
        $activePrecountOrder = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->employee->id,
            'order_number' => 'ORD-PAST-001',
            'order_type' => 'table',
            'status' => 'open',
            'precount_printed_at' => $pastDate,
            'subtotal' => 50000,
            'total' => 50000,
        ]);
        \Illuminate\Support\Facades\DB::table('restaurant_orders')
            ->where('id', $activePrecountOrder->id)
            ->update(['created_at' => $pastDate, 'updated_at' => $pastDate]);

        // 2. Comanda anterior FACTURADA / CERRADA que tuvo pre-cuenta impresa en su momento
        $billedPastOrder = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->employee->id,
            'order_number' => 'ORD-PAST-002',
            'order_type' => 'table',
            'status' => 'billed',
            'precount_printed_at' => $pastDate,
            'closed_at' => $pastDate->copy()->addMinutes(15),
            'subtotal' => 80000,
            'total' => 80000,
        ]);
        \Illuminate\Support\Facades\DB::table('restaurant_orders')
            ->where('id', $billedPastOrder->id)
            ->update(['created_at' => $pastDate, 'updated_at' => $pastDate]);

        // Consultar con filtro status = 'precount'
        $response = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.index', [
                'tab' => 'history',
                'status' => 'precount',
            ]));

        $response->assertOk();

        /** @var \Illuminate\Pagination\LengthAwarePaginator $pastOrders */
        $pastOrders = $response->viewData('pastOrders');

        $orderIds = $pastOrders->pluck('id')->all();

        $this->assertContains($activePrecountOrder->id, $orderIds, 'La orden activa en cobro debe aparecer.');
        $this->assertNotContains($billedPastOrder->id, $orderIds, 'La orden ya facturada (billed) no debe aparecer en precount.');
    }

    /**
     * Test 4: Filtros de estado 'open', 'billed' y 'cancelled' retornan exactamente las comandas esperadas.
     */
    public function test_orders_history_filter_by_status_open_and_billed_and_cancelled(): void
    {
        $pastDate = Carbon::now('America/Bogota')->subDays(3)->setTime(14, 0, 0);

        // 1. Orden abierta simple
        $openOrder = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->employee->id,
            'order_number' => 'ORD-TEST-OPEN',
            'order_type' => 'table',
            'status' => 'open',
            'precount_printed_at' => null,
            'subtotal' => 20000,
            'total' => 20000,
        ]);
        \Illuminate\Support\Facades\DB::table('restaurant_orders')
            ->where('id', $openOrder->id)
            ->update(['created_at' => $pastDate, 'updated_at' => $pastDate]);

        // 2. Orden facturada
        $billedOrder = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->employee->id,
            'order_number' => 'ORD-TEST-BILLED',
            'order_type' => 'table',
            'status' => 'billed',
            'closed_at' => $pastDate,
            'subtotal' => 45000,
            'total' => 45000,
        ]);
        \Illuminate\Support\Facades\DB::table('restaurant_orders')
            ->where('id', $billedOrder->id)
            ->update(['created_at' => $pastDate, 'updated_at' => $pastDate]);

        // 3. Orden cancelada
        $cancelledOrder = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->employee->id,
            'order_number' => 'ORD-TEST-CANCELLED',
            'order_type' => 'table',
            'status' => 'cancelled',
            'cancelled_at' => $pastDate,
            'subtotal' => 15000,
            'total' => 15000,
        ]);
        \Illuminate\Support\Facades\DB::table('restaurant_orders')
            ->where('id', $cancelledOrder->id)
            ->update(['created_at' => $pastDate, 'updated_at' => $pastDate]);

        // Probar filtro 'open'
        $responseOpen = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.index', ['tab' => 'history', 'status' => 'open']));
        $openIds = $responseOpen->viewData('pastOrders')->pluck('id')->all();
        $this->assertContains($openOrder->id, $openIds);
        $this->assertNotContains($billedOrder->id, $openIds);
        $this->assertNotContains($cancelledOrder->id, $openIds);

        // Probar filtro 'billed'
        $responseBilled = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.index', ['tab' => 'history', 'status' => 'billed']));
        $billedIds = $responseBilled->viewData('pastOrders')->pluck('id')->all();
        $this->assertContains($billedOrder->id, $billedIds);
        $this->assertNotContains($openOrder->id, $billedIds);
        $this->assertNotContains($cancelledOrder->id, $billedIds);

        // Probar filtro 'cancelled'
        $responseCancelled = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.index', ['tab' => 'history', 'status' => 'cancelled']));
        $cancelledIds = $responseCancelled->viewData('pastOrders')->pluck('id')->all();
        $this->assertContains($cancelledOrder->id, $cancelledIds);
        $this->assertNotContains($openOrder->id, $cancelledIds);
        $this->assertNotContains($billedOrder->id, $cancelledIds);
    }

    /**
     * Test 5: Persistencia de la pestaña activa 'Historial Anterior' al filtrar o enviar tab=history.
     */
    public function test_orders_index_maintains_active_history_tab_when_filtering_or_using_tab_parameter(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.index', [
                'tab' => 'history',
                'status' => 'billed',
            ]));

        $response->assertOk();
        // Verificar que el tab activo sea 'past-orders-pane'
        $response->assertSee('id="past-orders-pane" role="tabpanel" aria-labelledby="history-orders-tab" tabindex="0"', false);
        $response->assertSee('class="tab-pane fade show active" id="past-orders-pane"', false);
    }

    /**
     * Test 6: Búsqueda textual amplia y filtrado por tipo de pedido (Salón, Domicilio y Para Llevar).
     */
    public function test_orders_search_and_filter_by_order_type_salon_delivery_takeaway(): void
    {
        $pastDate = Carbon::now('America/Bogota')->subDays(2)->setTime(10, 0, 0);

        $table2 = RestaurantTable::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Mesa 2',
            'capacity' => 2,
            'status' => 'available',
        ]);

        // 1. Orden de salón en Mesa 2
        $tableOrder = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'table_id' => $table2->id,
            'user_id' => $this->employee->id,
            'order_number' => 'ORD-TYPE-SALON',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 30000,
            'total' => 30000,
        ]);
        \Illuminate\Support\Facades\DB::table('restaurant_orders')
            ->where('id', $tableOrder->id)
            ->update(['created_at' => $pastDate, 'updated_at' => $pastDate]);

        // 2. Orden de domicilio
        $deliveryOrder = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->employee->id,
            'order_number' => 'ORD-TYPE-DELIVERY',
            'order_type' => 'delivery',
            'status' => 'open',
            'customer_name' => 'Cliente Domicilio',
            'delivery_phone' => '3111111111',
            'delivery_address' => 'Avenida 6N # 20-30',
            'subtotal' => 45000,
            'total' => 48000,
        ]);
        \Illuminate\Support\Facades\DB::table('restaurant_orders')
            ->where('id', $deliveryOrder->id)
            ->update(['created_at' => $pastDate, 'updated_at' => $pastDate]);

        // 3. Orden para llevar
        $takeoutOrder = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->employee->id,
            'order_number' => 'ORD-TYPE-TAKEOUT',
            'order_type' => 'takeout',
            'status' => 'open',
            'customer_name' => 'Cliente Para Llevar',
            'subtotal' => 25000,
            'total' => 25000,
        ]);
        \Illuminate\Support\Facades\DB::table('restaurant_orders')
            ->where('id', $takeoutOrder->id)
            ->update(['created_at' => $pastDate, 'updated_at' => $pastDate]);

        // A. Búsqueda por "Domicilio"
        $respSearchDelivery = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.index', ['search' => 'Domicilio']));
        $ids = $respSearchDelivery->viewData('pastOrders')->pluck('id')->all();
        $this->assertContains($deliveryOrder->id, $ids);
        $this->assertNotContains($tableOrder->id, $ids);
        $this->assertNotContains($takeoutOrder->id, $ids);

        // B. Filtro por order_type = 'delivery'
        $respFilterDelivery = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.index', ['order_type' => 'delivery']));
        $ids = $respFilterDelivery->viewData('pastOrders')->pluck('id')->all();
        $this->assertContains($deliveryOrder->id, $ids);
        $this->assertNotContains($tableOrder->id, $ids);
        $this->assertNotContains($takeoutOrder->id, $ids);

        // C. Búsqueda por "Para llevar"
        $respSearchTakeout = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.index', ['search' => 'Para llevar']));
        $ids = $respSearchTakeout->viewData('pastOrders')->pluck('id')->all();
        $this->assertContains($takeoutOrder->id, $ids);
        $this->assertNotContains($tableOrder->id, $ids);
        $this->assertNotContains($deliveryOrder->id, $ids);

        // D. Filtro por order_type = 'takeaway'
        $respFilterTakeout = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.index', ['order_type' => 'takeaway']));
        $ids = $respFilterTakeout->viewData('pastOrders')->pluck('id')->all();
        $this->assertContains($takeoutOrder->id, $ids);
        $this->assertNotContains($tableOrder->id, $ids);
        $this->assertNotContains($deliveryOrder->id, $ids);

        // E. Búsqueda por "Salón"
        $respSearchSalon = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.index', ['search' => 'Salón']));
        $ids = $respSearchSalon->viewData('pastOrders')->pluck('id')->all();
        $this->assertContains($tableOrder->id, $ids);
        $this->assertNotContains($deliveryOrder->id, $ids);
        $this->assertNotContains($takeoutOrder->id, $ids);

        // F. Búsqueda por "Mesa 2"
        $respSearchMesa = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.index', ['search' => 'Mesa 2']));
        $ids = $respSearchMesa->viewData('pastOrders')->pluck('id')->all();
        $this->assertContains($tableOrder->id, $ids);
        $this->assertNotContains($deliveryOrder->id, $ids);
        $this->assertNotContains($takeoutOrder->id, $ids);

        // G. Filtro por order_type = 'dine_in'
        $respFilterDineIn = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.index', ['order_type' => 'dine_in']));
        $ids = $respFilterDineIn->viewData('pastOrders')->pluck('id')->all();
        $this->assertContains($tableOrder->id, $ids);
        $this->assertNotContains($deliveryOrder->id, $ids);
        $this->assertNotContains($takeoutOrder->id, $ids);
    }
}
