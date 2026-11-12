<?php

namespace Tests\Feature\Restaurant;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantOrderTest extends TestCase
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
            'name' => 'Restaurante El Peñol',
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
            'name' => 'Mesa Terraza 1',
            'capacity' => 6,
            'status' => 'available',
        ]);

        $category = Category::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Especialidades',
        ]);

        $this->dish = Product::create([
            'business_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'name' => 'Bandeja Paisa',
            'sku' => 'BP-01',
            'sale_price' => 32000,
            'stock' => 20,
            'product_type' => 'dish',
            'is_active' => true,
        ]);
    }

    public function test_can_open_table_order_persisting_guest_count(): void
    {
        $this->actingAs($this->employee)
            ->withSession(['current_business_id' => $this->restaurant->id]);

        $response = $this->post(route('restaurant.orders.store'), [
            'table_id' => $this->table->id,
            'customer_name' => 'Mesero Andrés',
            'guest_count' => 4,
            'notes' => 'Mesa junto al ventanal',
            'items' => [
                [
                    'product_id' => $this->dish->id,
                    'quantity' => 2,
                    'notes' => 'Uno sin aguacate',
                ],
            ],
        ]);

        $order = RestaurantOrder::where('business_id', $this->restaurant->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals(4, $order->guest_count);
        $this->assertEquals('Mesero Andrés', $order->customer_name);
        $this->assertEquals('occupied', $this->table->fresh()->status);

        $response->assertRedirect(route('restaurant.orders.show', $order));

        $this->assertDatabaseHas('restaurant_orders', [
            'id' => $order->id,
            'table_id' => $this->table->id,
            'guest_count' => 4,
            'customer_name' => 'Mesero Andrés',
        ]);
    }

    public function test_can_open_table_order_with_null_guest_count(): void
    {
        $this->actingAs($this->employee)
            ->withSession(['current_business_id' => $this->restaurant->id]);

        $response = $this->post(route('restaurant.orders.store'), [
            'table_id' => $this->table->id,
            'customer_name' => 'Cliente Rápido',
            // guest_count omitido
        ]);

        $order = RestaurantOrder::where('business_id', $this->restaurant->id)->first();
        $this->assertNotNull($order);
        $this->assertNull($order->guest_count);
        $response->assertRedirect(route('restaurant.orders.show', $order));

        $this->assertDatabaseHas('restaurant_orders', [
            'id' => $order->id,
            'table_id' => $this->table->id,
            'guest_count' => null,
        ]);
    }

    public function test_guest_count_is_rendered_on_order_show_and_tickets(): void
    {
        $this->actingAs($this->employee)
            ->withSession(['current_business_id' => $this->restaurant->id]);

        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->employee->id,
            'order_number' => 'ORD-0099',
            'order_type' => 'table',
            'status' => 'open',
            'customer_name' => 'Mesero Diego',
            'guest_count' => 5,
            'subtotal' => 32000,
            'total' => 32000,
        ]);

        $order->items()->create([
            'business_id' => $this->restaurant->id,
            'product_id' => $this->dish->id,
            'quantity' => 1,
            'unit_price' => 32000,
            'subtotal' => 32000,
            'printed_to_kitchen' => false,
        ]);

        // 1. Show view
        $showResponse = $this->get(route('restaurant.orders.show', $order));
        $showResponse->assertOk();
        $showResponse->assertSee('Personas:');
        $showResponse->assertSee('5');

        // 2. Kitchen ticket
        $kitchenResponse = $this->get(route('restaurant.orders.kitchen-ticket', $order));
        $kitchenResponse->assertOk();
        $kitchenResponse->assertSee('Personas:');
        $kitchenResponse->assertSee('5');

        // 3. Pre-bill ticket
        $preBillResponse = $this->get(route('restaurant.orders.prebill', $order));
        $preBillResponse->assertOk();
        $preBillResponse->assertSee('PERSONAS:');
        $preBillResponse->assertSee('5');
    }

    public function test_guest_count_validation_rejects_invalid_values(): void
    {
        $this->actingAs($this->employee)
            ->withSession(['current_business_id' => $this->restaurant->id]);

        // Valor 0 (debe ser al menos 1)
        $respZero = $this->post(route('restaurant.orders.store'), [
            'table_id' => $this->table->id,
            'guest_count' => 0,
        ]);
        $respZero->assertSessionHasErrors(['guest_count']);

        // Valor 100 (máximo 99)
        $respMax = $this->post(route('restaurant.orders.store'), [
            'table_id' => $this->table->id,
            'guest_count' => 100,
        ]);
        $respMax->assertSessionHasErrors(['guest_count']);
    }

    public function test_salon_orders_create_exposes_dishes_and_merchandise_with_available_stock(): void
    {
        $merchandise = Product::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Proteína Whey Tarro 2lb',
            'sku' => 'SUPP-WHEY-01',
            'cost_price' => 80000,
            'sale_price' => 120000,
            'stock' => 15,
            'min_stock' => 3,
            'product_type' => 'standard',
            'base_unit' => 'unit',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->employee)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.create'));

        $response->assertOk();
        $response->assertSee($this->dish->name);
        $response->assertSee($merchandise->name);
        $response->assertSee('15 disp.');
    }
}

