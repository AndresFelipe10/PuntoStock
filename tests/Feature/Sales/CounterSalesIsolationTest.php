<?php

namespace Tests\Feature\Sales;

use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SalePayment;
use App\Models\User;
use App\Services\Restaurant\RestaurantOrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounterSalesIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $restaurant;
    protected User $admin;
    protected User $employee;
    protected RestaurantTable $table;
    protected Product $product;
    protected Customer $customer;

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
            'name' => 'Restaurante y Cafetería Colonial',
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
            'name' => 'Mesa 5',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $category = Category::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Menú Ejecutivo',
        ]);

        $this->product = Product::create([
            'business_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'name' => 'Almuerzo Campestre',
            'sku' => 'ALM-01',
            'sale_price' => 20000,
            'purchase_price' => 12000,
            'stock' => 100,
            'product_type' => 'dish',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Felipe Gómez',
            'document' => '1144005566',
            'phone' => '3123456789',
            'is_active' => true,
        ]);
    }

    /**
     * Test: El listado en Ventas de Mostrador (/sales) incluye exclusivamente ventas directas
     * y excluye tajantemente las facturas originadas en comandas de restaurante.
     */
    public function test_counter_sales_index_lists_direct_sales_and_excludes_restaurant_orders(): void
    {
        $saleDate = Carbon::now('America/Bogota');

        // 1. Venta directa de mostrador (restaurant_order_id = null)
        $directSale = Sale::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->admin->id,
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'customer_document' => $this->customer->document,
            'restaurant_order_id' => null,
            'order_type' => 'retail',
            'invoice_number' => 'VTA-MOSTRADOR-001',
            'sale_date' => $saleDate,
            'subtotal' => 20000,
            'discount' => 0,
            'discount_percentage' => 0,
            'delivery_fee' => 0,
            'service_fee' => 0,
            'tax_inc' => 0,
            'total' => 20000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'notes' => 'Venta rápida de mostrador',
        ]);

        SaleDetail::create([
            'sale_id' => $directSale->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 20000,
            'subtotal' => 20000,
            'total' => 20000,
        ]);

        SalePayment::create([
            'business_id' => $this->restaurant->id,
            'sale_id' => $directSale->id,
            'method' => 'cash',
            'amount' => 20000,
            'cash_received' => 20000,
            'change_given' => 0,
        ]);

        // 2. Comanda de restaurante y su venta asociada (restaurant_order_id vinculada)
        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->employee->id,
            'order_number' => 'ORD-0099',
            'order_type' => 'table',
            'status' => 'billed',
            'subtotal' => 40000,
            'total' => 40000,
            'closed_at' => $saleDate,
        ]);

        $restaurantSale = Sale::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->employee->id,
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'customer_document' => $this->customer->document,
            'restaurant_order_id' => $order->id,
            'order_type' => 'table',
            'invoice_number' => 'VTA-REST-0099',
            'sale_date' => $saleDate,
            'subtotal' => 40000,
            'discount' => 0,
            'discount_percentage' => 0,
            'delivery_fee' => 0,
            'service_fee' => 0,
            'tax_inc' => 0,
            'total' => 40000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'notes' => 'Cobro final de comanda ORD-0099',
        ]);

        SaleDetail::create([
            'sale_id' => $restaurantSale->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 20000,
            'subtotal' => 40000,
            'total' => 40000,
        ]);

        SalePayment::create([
            'business_id' => $this->restaurant->id,
            'sale_id' => $restaurantSale->id,
            'method' => 'cash',
            'amount' => 40000,
            'cash_received' => 50000,
            'change_given' => 10000,
        ]);

        // Consultar Ventas de Mostrador
        $response = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('sales.index'));

        $response->assertOk();

        /** @var \Illuminate\Pagination\LengthAwarePaginator $sales */
        $sales = $response->viewData('sales');
        $saleIds = $sales->pluck('id')->all();

        // Certificar que la venta de mostrador SÍ está
        $this->assertContains($directSale->id, $saleIds, 'La venta directa de mostrador debe aparecer en /sales');
        $response->assertSee('VTA-MOSTRADOR-001');

        // Certificar que la venta proveniente de comanda NO está
        $this->assertNotContains($restaurantSale->id, $saleIds, 'La venta proveniente de restaurante NO debe aparecer en /sales');
        $response->assertDontSee('VTA-REST-0099');
    }

    /**
     * Test: El botón "Volver" en sales.show redirige a la comanda si proviene de restaurante,
     * o a sales.index si es una venta directa de mostrador.
     */
    public function test_sale_show_view_renders_correct_back_button_depending_on_restaurant_order_origin(): void
    {
        $saleDate = Carbon::now('America/Bogota');

        // Venta de mostrador
        $directSale = Sale::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->admin->id,
            'restaurant_order_id' => null,
            'invoice_number' => 'VTA-DIRECT-01',
            'sale_date' => $saleDate,
            'subtotal' => 20000,
            'total' => 20000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        // Comanda y su venta asociada
        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->employee->id,
            'order_number' => 'ORD-BACK-01',
            'order_type' => 'table',
            'status' => 'billed',
            'subtotal' => 20000,
            'total' => 20000,
        ]);

        $restaurantSale = Sale::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->employee->id,
            'restaurant_order_id' => $order->id,
            'invoice_number' => 'VTA-BACK-REST',
            'sale_date' => $saleDate,
            'subtotal' => 20000,
            'total' => 20000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        // 1. Verificación para venta directa de mostrador
        $respDirect = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('sales.show', $directSale));

        $respDirect->assertOk();
        $respDirect->assertSee(route('sales.index'));
        $respDirect->assertDontSee('Volver a Comanda');

        // 2. Verificación para venta proveniente de comanda
        $respRest = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('sales.show', $restaurantSale));

        $respRest->assertOk();
        $respRest->assertSee(route('restaurant.orders.show', $order->id));
        $respRest->assertSee('Volver a Comanda');
    }

    /**
     * Test: El Cuadre de Caja (reports.cash-register) sigue agregando tanto ventas de mostrador
     * como ventas de comanda de restaurante de forma íntegra.
     */
    public function test_cash_register_report_aggregates_both_counter_sales_and_restaurant_sales(): void
    {
        $today = Carbon::now('America/Bogota');

        // Venta mostrador: 25.000
        $sale1 = Sale::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->admin->id,
            'restaurant_order_id' => null,
            'invoice_number' => 'VTA-CASH-01',
            'sale_date' => $today,
            'subtotal' => 25000,
            'total' => 25000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);
        SalePayment::create([
            'business_id' => $this->restaurant->id,
            'sale_id' => $sale1->id,
            'method' => 'cash',
            'amount' => 25000,
            'cash_received' => 25000,
            'change_given' => 0,
        ]);

        // Venta comanda restaurante: 35.000
        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->employee->id,
            'order_number' => 'ORD-CASH-02',
            'order_type' => 'table',
            'status' => 'billed',
            'subtotal' => 35000,
            'total' => 35000,
            'closed_at' => $today,
        ]);
        $sale2 = Sale::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->employee->id,
            'restaurant_order_id' => $order->id,
            'invoice_number' => 'VTA-CASH-02',
            'sale_date' => $today,
            'subtotal' => 35000,
            'total' => 35000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);
        SalePayment::create([
            'business_id' => $this->restaurant->id,
            'sale_id' => $sale2->id,
            'method' => 'cash',
            'amount' => 35000,
            'cash_received' => 40000,
            'change_given' => 5000,
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('reports.cash-register'));

        $response->assertOk();

        // Ambas ventas deben estar sumadas en el reporte: 25.000 + 35.000 = 60.000
        $response->assertSee('$60.000');
    }
}
