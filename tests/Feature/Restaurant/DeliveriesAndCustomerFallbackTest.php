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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveriesAndCustomerFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $restaurant;
    protected Business $otherRestaurant;
    protected User $admin;
    protected User $employee;
    protected User $pedritoWaiter;
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
            'name' => 'Restaurante Cali Sabor',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->otherRestaurant = Business::create([
            'name' => 'Otro Restaurante Tenant',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create(['name' => 'Admin Restaurante']);
        $this->restaurant->users()->attach($this->admin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->employee = User::factory()->create(['name' => 'Empleado Restaurante']);
        $this->restaurant->users()->attach($this->employee->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);

        $this->pedritoWaiter = User::factory()->create(['name' => 'Pedrito Gómez']);
        $this->restaurant->users()->attach($this->pedritoWaiter->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);

        $this->table = RestaurantTable::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Mesa 5',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        $category = Category::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Platos Fuertes',
        ]);

        $this->dish = Product::create([
            'business_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'name' => 'Bandeja Paisa Criolla',
            'sku' => 'BAN-01',
            'product_type' => 'dish',
            'sale_price' => 32000,
            'cost_price' => 15000,
            'stock' => 10,
            'min_stock' => 1,
            'track_inventory' => false,
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Cliente por defecto 'Consumidor Final' en Comandas.
     * Certificar que en la comanda de salón atendida por el mesero Pedrito Gómez,
     * si no se asocia ningún cliente, la columna Cliente muestra Consumidor Final y no Pedrito.
     */
    public function test_salon_order_without_customer_displays_consumidor_final_and_not_waiter_name(): void
    {
        // 1. Crear comanda de salón atendida por Pedrito Gómez sin cliente asociado
        $orderWithoutName = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->pedritoWaiter->id,
            'order_number' => 'ORD-TEST-001',
            'order_type' => 'table',
            'status' => 'open',
            'customer_id' => null,
            'customer_name' => null,
            'subtotal' => 32000,
            'total' => 32000,
        ]);

        // 2. Crear comanda donde customer_name se guardó con el nombre del mesero (Pedrito)
        $orderWithWaiterAsCustomerName = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->pedritoWaiter->id,
            'order_number' => 'ORD-TEST-002',
            'order_type' => 'table',
            'status' => 'open',
            'customer_id' => null,
            'customer_name' => 'Pedrito',
            'subtotal' => 32000,
            'total' => 32000,
        ]);

        // Verificación en modelo
        $this->assertSame('Consumidor Final', $orderWithoutName->client_display_name);
        $this->assertSame('Consumidor Final', $orderWithWaiterAsCustomerName->client_display_name);

        // Verificación en vista Comandas (index)
        $response = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.index'));

        $response->assertStatus(200);
        $response->assertSee('ORD-TEST-001');
        $response->assertSee('ORD-TEST-002');
        $response->assertSee('Consumidor Final');

        // Verificación en vista de detalle (show)
        $responseShow = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.show', $orderWithWaiterAsCustomerName));

        $responseShow->assertStatus(200);
        $responseShow->assertSee('Cliente: <strong>Consumidor Final</strong>', false);
    }

    /**
     * Test 2: Botón 'Recogido' y salto directo a 'Entregados' para pedidos Para Llevar.
     */
    public function test_takeout_order_pickup_button_and_direct_transition_to_delivered(): void
    {
        // 1. Pedido Para Llevar en preparación
        $takeoutOrder = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->employee->id,
            'order_number' => 'ORD-TAKEOUT-10',
            'order_type' => 'takeout',
            'status' => 'in_kitchen',
            'customer_name' => 'Carlos Mendoza',
            'subtotal' => 32000,
            'total' => 32000,
        ]);

        // 2. Pedido Domicilio en preparación (para verificar diferenciación de botones)
        $deliveryOrder = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->employee->id,
            'order_number' => 'ORD-DELIVERY-20',
            'order_type' => 'delivery',
            'status' => 'in_kitchen',
            'customer_name' => 'Laura Restrepo',
            'delivery_address' => 'Carrera 1 # 2-3',
            'subtotal' => 32000,
            'total' => 35000,
            'delivery_fee' => 3000,
        ]);

        // 3. Verificación en el tablero de despacho
        $responseBoard = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.deliveries'));

        $responseBoard->assertStatus(200);

        // El pedido para llevar debe tener botón 'Recogido' y NO 'Despachar'
        $responseBoard->assertSee('btn-mark-picked-up');
        $responseBoard->assertSee('Recogido');
        $responseBoard->assertSee(route('restaurant.deliveries.pickup', $takeoutOrder));

        // El pedido a domicilio debe tener botón 'Despachar'
        $responseBoard->assertSee('Despachar');

        // 4. Ejecutar el POST hacia la ruta de pickup
        $pickupUrl = route('restaurant.deliveries.pickup', $takeoutOrder);
        $responsePickup = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->post($pickupUrl);

        $responsePickup->assertSessionHas('success');

        // 5. Certificar estado 'delivered' y 'delivered_at' NOT NULL
        $takeoutOrder->refresh();
        $this->assertSame('delivered', $takeoutOrder->status);
        $this->assertNotNull($takeoutOrder->delivered_at);

        // 6. Certificar que ahora se ubica en la columna '3. Entregados' con botón 'Cuadrar Pedido'
        $responseBoardAfter = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.deliveries'));

        $responseBoardAfter->assertStatus(200);
        $responseBoardAfter->assertSee('Cuadrar Pedido');
        $responseBoardAfter->assertSee('btn-settle-order');
    }

    /**
     * Test 3: Tirilla de despacho con 'Subtotal Consumo' y nunca 'Subtotal Comida'.
     */
    public function test_dispatch_ticket_displays_subtotal_consumo_and_never_subtotal_comida(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->employee->id,
            'order_number' => 'ORD-TICKET-99',
            'order_type' => 'delivery',
            'status' => 'dispatched',
            'customer_name' => 'Felipe Gómez',
            'delivery_phone' => '3123456789',
            'delivery_address' => 'Calle 10 # 5-20',
            'delivery_fee' => 4000,
            'subtotal' => 32000,
            'total' => 36000,
        ]);

        RestaurantOrderItem::create([
            'business_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->dish->id,
            'quantity' => 1,
            'unit_price' => 32000,
            'subtotal' => 32000,
            'status' => 'kitchen',
        ]);

        $responseTicket = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.dispatch-ticket', $order));

        $responseTicket->assertStatus(200);
        $responseTicket->assertSee('Subtotal Consumo:');
        $responseTicket->assertDontSee('Subtotal Comida:');
        $responseTicket->assertSee('Costo de Domicilio:');
        $responseTicket->assertSee('TOTAL A COBRAR EN DESTINO:');
    }

    /**
     * Test 4: Aislamiento multi-tenant al marcar como recogido.
     */
    public function test_cross_tenant_pickup_is_forbidden(): void
    {
        $takeoutOrder = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->employee->id,
            'order_number' => 'ORD-TAKEOUT-CROSS',
            'order_type' => 'takeout',
            'status' => 'in_kitchen',
            'customer_name' => 'Cliente Protegido',
            'subtotal' => 20000,
            'total' => 20000,
        ]);

        $otherAdmin = User::factory()->create();
        $this->otherRestaurant->users()->attach($otherAdmin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($otherAdmin)
            ->withSession(['current_business_id' => $this->otherRestaurant->id])
            ->post(route('restaurant.deliveries.pickup', $takeoutOrder));

        $this->assertTrue(in_array($response->status(), [403, 404]));
    }
}
