<?php

namespace Tests\Feature\Sales;

use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosKeyboardAndErgonomicsTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Business $retailBusiness;
    protected Business $restaurantBusiness;
    protected User $retailAdmin;
    protected User $restaurantAdmin;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create([
            'name' => 'Administrador',
            'slug' => Role::ROLE_ADMIN,
        ]);

        $this->retailBusiness = Business::create([
            'name' => 'Comercio Retail Cali',
            'business_type' => 'retail',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->restaurantBusiness = Business::create([
            'name' => 'Restaurante Gourmet Cali',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->retailAdmin = User::factory()->create([
            'name' => 'Andrés Felipe Morales',
        ]);
        $this->retailBusiness->users()->attach($this->retailAdmin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->restaurantAdmin = User::factory()->create([
            'name' => 'Mariana Restrepo López',
        ]);
        $this->restaurantBusiness->users()->attach($this->restaurantAdmin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        RestaurantTable::create([
            'business_id' => $this->restaurantBusiness->id,
            'name' => 'Mesa 1',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'business_id' => $this->restaurantBusiness->id,
            'name' => 'Cliente Frecuente Cali',
            'document' => '1144123456',
            'phone' => '3157778899',
            'address' => 'Avenida Colombia # 10-20',
        ]);
    }

    public function test_customer_name_starts_empty_and_authenticated_user_is_shown_as_badge(): void
    {
        // En restaurante, el usuario Mariana Restrepo López debe verse como indicador de usuario/mesero
        // y el campo de cliente o referencia debe iniciar limpio
        $response = $this->actingAs($this->restaurantAdmin)
            ->withSession(['current_business_id' => $this->restaurantBusiness->id])
            ->get(route('restaurant.orders.create'));

        $response->assertStatus(200);
        $response->assertSee('Mariana Restrepo López');
        $response->assertSee('Cliente / Referencia (Opcional)');
        $response->assertDontSee('value="Mariana"', false);
    }

    public function test_customer_search_in_delivery_and_pos_has_keyboard_listeners_and_prevents_submit(): void
    {
        // 1. En pedidos de domicilio de restaurante
        $responseDelivery = $this->actingAs($this->restaurantAdmin)
            ->withSession(['current_business_id' => $this->restaurantBusiness->id])
            ->get(route('restaurant.orders.create-delivery'));

        $responseDelivery->assertStatus(200);
        $responseDelivery->assertSee('customer-result-item');
        $responseDelivery->assertSee("e.key === 'Enter'", false);
        $responseDelivery->assertSee("e.preventDefault()", false);
        $responseDelivery->assertSee("deliveryAddress", false);

        // 2. En venta directa POS (Retail)
        $responsePos = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->get(route('sales.create'));

        $responsePos->assertStatus(200);
        $responsePos->assertSee('customer-result-item');
        $responsePos->assertSee("e.key === 'Enter'", false);
        $responsePos->assertSee("e.preventDefault()", false);
        $responsePos->assertSee("productSearch", false);
    }

    public function test_discount_input_in_pos_has_auto_select_and_clamping_sanitizer_preventing_010(): void
    {
        $response = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->get(route('sales.create'));

        $response->assertStatus(200);

        // Auto-selección en foco para prevenir concatenación '010'
        $response->assertSee("this.select()", false);

        // Sanitización reactiva (parseInt, 0 a 100)
        $response->assertSee("parseInt(this.value, 10)", false);
        $response->assertSee("if (val > 100) val = 100", false);

        // Tecla Enter ejecuta blur y previene submit
        $response->assertSee("this.blur()", false);
        $response->assertSee("updateTotals()", false);

        // Botones ergonómicos steppers (+ y -)
        $response->assertSee('btnDiscountMinus');
        $response->assertSee('btnDiscountPlus');
    }

    public function test_inventory_movement_create_has_dual_product_selector_with_enter_key(): void
    {
        $category = Category::create([
            'business_id' => $this->retailBusiness->id,
            'name' => 'Calzado Deportivo',
        ]);

        Product::create([
            'business_id' => $this->retailBusiness->id,
            'category_id' => $category->id,
            'name' => 'Tenis Running Pro',
            'sku' => 'CAL-TENIS-01',
            'cost_price' => 120000,
            'sale_price' => 210000,
            'stock' => 25,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->get(route('inventory.create'));

        $response->assertStatus(200);

        // Dual selector: campo de texto rápido + <select> tradicional
        $response->assertSee('product_search_input');
        $response->assertSee('product_search_results');
        $response->assertSee('<select class="form-select', false);

        // Soporte teclado Enter y foco automático a cantidad
        $response->assertSee("quantityInput.focus()", false);
        $response->assertSee("quantityInput.select()", false);

        // Steppers en campo de cantidad
        $response->assertSee('btnQtyMinus');
        $response->assertSee('btnQtyPlus');
    }

    public function test_global_css_enforces_permanent_spinners_on_numeric_inputs(): void
    {
        $response = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->get(route('dashboard'));

        $response->assertStatus(200);

        // Estilos CSS incrustados en layout app para visibilidad permanente
        $response->assertSee('input[type=number]::-webkit-inner-spin-button', false);
        $response->assertSee('opacity: 1 !important', false);
        $response->assertSee('-moz-appearance: number-input', false);
    }

    public function test_search_inputs_suppress_emoji_and_use_standard_bi_search_icon(): void
    {
        // 1. Movimientos de inventario
        $responseInv = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->get(route('inventory.create'));
        $responseInv->assertOk();
        $responseInv->assertDontSee('🔍');
        $responseInv->assertSee('bi-search text-muted', false);
        $responseInv->assertSee('placeholder="Escribe para buscar producto por nombre o SKU..."', false);

        // 2. Comandas de salón
        $responseOrder = $this->actingAs($this->restaurantAdmin)
            ->withSession(['current_business_id' => $this->restaurantBusiness->id])
            ->get(route('restaurant.orders.create'));
        $responseOrder->assertOk();
        $responseOrder->assertDontSee('🔍');
        $responseOrder->assertSee('bi-search text-muted', false);
        $responseOrder->assertSee('placeholder="Escribe para buscar plato o bebida..."', false);
        $responseOrder->assertDontSee('Ej. Juan, Mesa ventana, etc.');

        // 3. Pedidos de domicilio
        $responseDelivery = $this->actingAs($this->restaurantAdmin)
            ->withSession(['current_business_id' => $this->restaurantBusiness->id])
            ->get(route('restaurant.orders.create-delivery'));
        $responseDelivery->assertOk();
        $responseDelivery->assertDontSee('🔍');
        $responseDelivery->assertSee('bi-search text-muted', false);
        $responseDelivery->assertSee('placeholder="Escribe para buscar plato o bebida..."', false);
    }

    public function test_stepper_inputs_suppress_native_browser_spinners_via_css_and_classes(): void
    {
        $response = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('.input-stepper::-webkit-outer-spin-button', false);
        $response->assertSee('.input-group input[type=number]::-webkit-outer-spin-button', false);
        $response->assertSee('-webkit-appearance: none !important', false);
        $response->assertSee('-moz-appearance: textfield !important', false);
    }

    public function test_number_inputs_have_auto_select_and_zero_suppression_ergonomics(): void
    {
        $response = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->get(route('products.create'));

        $response->assertOk();
        $response->assertSee('setupNumberInputErgonomics', false);
        $response->assertSee('/^0+[1-9]/', false);
        $response->assertSee("target.value = val.replace(/^0+/, '');", false);
    }

    public function test_restaurant_tenant_has_direct_counter_sale_access_in_sidebar_and_views(): void
    {
        // 1. Sidebar de restaurante muestra 'Ventas de Mostrador'
        $responseDashboard = $this->actingAs($this->restaurantAdmin)
            ->withSession(['current_business_id' => $this->restaurantBusiness->id])
            ->get(route('dashboard'));

        $responseDashboard->assertOk();
        $responseDashboard->assertSee('Ventas de Mostrador');
        $responseDashboard->assertSee(route('sales.index'));

        // 2. Restaurante puede acceder a sales.index y sales.create
        $responseIndex = $this->actingAs($this->restaurantAdmin)
            ->withSession(['current_business_id' => $this->restaurantBusiness->id])
            ->get(route('sales.index'));
        $responseIndex->assertOk();

        $responseCreate = $this->actingAs($this->restaurantAdmin)
            ->withSession(['current_business_id' => $this->restaurantBusiness->id])
            ->get(route('sales.create'));
        $responseCreate->assertOk();

        // 3. Cuadre de Caja contiene '+ Nueva Venta' e 'Historial Ventas' y elimina artefactos SQL
        $responseCash = $this->actingAs($this->restaurantAdmin)
            ->withSession(['current_business_id' => $this->restaurantBusiness->id])
            ->get(route('reports.cash-register'));
        $responseCash->assertOk();
        $responseCash->assertSee('+ Nueva Venta');
        $responseCash->assertSee('Historial Ventas');
        $responseCash->assertDontSee('SUM(delivery_fee)');

        // 4. Catálogo de productos en pestaña mercancía muestra botón 'Vender Mercancía'
        $responseProducts = $this->actingAs($this->restaurantAdmin)
            ->withSession(['current_business_id' => $this->restaurantBusiness->id])
            ->get(route('products.index', ['tab' => 'merchandise']));
        $responseProducts->assertOk();
        $responseProducts->assertSee('Vender Mercancía');
    }

    public function test_mobile_top_app_bar_collapsible_filters_and_table_to_card_pattern(): void
    {
        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->retailBusiness->id,
            'user_id' => $this->retailAdmin->id,
            'invoice_number' => 'VTA-202610-0001',
            'sale_date' => now(),
            'total' => 25000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->get(route('sales.index'));

        $response->assertOk();

        // A. Top Navbar unificado en móvil (< 768px) y completo en escritorio
        $response->assertSee('d-none d-md-flex align-items-center gap-2 gap-md-3', false);
        $response->assertSee('d-md-none dropdown', false);
        $response->assertSee('id="mobileUserMenu"', false);
        $response->assertSee('Negocio Activo');

        // B. Botones de acción ergonómicos en móvil (mínimo 44px)
        $response->assertSee('style="min-height: 44px;"', false);

        // C. Filtros colapsables progresivos en móvil
        $response->assertSee('id="mobileFiltersCollapse"', false);
        $response->assertSee('data-bs-target="#mobileFiltersCollapse"', false);
        $response->assertSee('Filtros avanzados', false);

        // D. Patrón Table-to-Card en móvil conservando tabla de escritorio
        $response->assertSee('card card-custom d-none d-md-block', false);
        $response->assertSee('d-md-none d-flex flex-column gap-3', false);
        $response->assertSee('VTA-202610-0001', false);
        $response->assertSee('Ver Comprobante', false);
    }

    public function test_sales_create_renders_dual_product_selector_with_search_and_dropdown(): void
    {
        $category = Category::withoutGlobalScopes()->create([
            'business_id' => $this->retailBusiness->id,
            'name' => 'Vestuario',
        ]);

        $product = Product::withoutGlobalScopes()->create([
            'business_id' => $this->retailBusiness->id,
            'category_id' => $category->id,
            'name' => 'Camisa Lino Cali',
            'sku' => 'CAM-001',
            'cost_price' => 25000,
            'sale_price' => 60000,
            'stock' => 12,
            'min_stock' => 2,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->get(route('sales.create'));

        $response->assertOk();

        // 1. Renderiza el input de búsqueda rápida
        $response->assertSee('id="productSearch"', false);
        $response->assertSee('placeholder="Buscar por nombre o SKU..."', false);

        // 2. Renderiza el select desplegable con la opción por defecto
        $response->assertSee('id="product_dropdown_pos"', false);
        $response->assertSee('-- O selecciona un producto del catálogo --');

        // 3. Renderiza las opciones del producto con stock y precio
        $response->assertSee('Camisa Lino Cali');
        $response->assertSee('$60.000');
        $response->assertSee('Stock: 12');

        // 4. Contiene la sincronización reactiva en JS
        $response->assertSee('productDropdownPos.addEventListener', false);
        $response->assertSee('window.addProductToSale = addToCart;', false);
    }

    /**
     * Test de interfaz: sales.create renderiza el botón estandarizado de creación rápida
     * de cliente con ícono bi-person-plus y clases corporativas.
     */
    public function test_sales_create_renders_standardized_quick_customer_button_with_person_plus_icon(): void
    {
        $response = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->get(route('sales.create'));

        $response->assertOk();

        // 1. Botón disparador con id, clase y atributo modal
        $response->assertSee('id="btnOpenQuickCustomer"', false);
        $response->assertSee('btn-quick-customer-trigger');
        $response->assertSee('btn-open-quick-customer');
        $response->assertSee('data-bs-target="#quickCustomerModal"', false);
        $response->assertSee('title="Crear Cliente Rápido"', false);

        // 2. Ícono delineado bi-person-plus (no bi-plus-lg)
        $response->assertSee('bi-person-plus');
        $response->assertDontSee('data-bs-target="#quickCustomerModal" title="Crear cliente rápido"><i class="bi bi-plus-lg"', false);

        // 3. Modal estandarizado presente
        $response->assertSee('id="quickCustomerModal"', false);
    }

    public function test_api_products_search_returns_all_required_fields_and_sales_create_scripts_are_intact(): void
    {
        $category = Category::withoutGlobalScopes()->create([
            'business_id' => $this->retailBusiness->id,
            'name' => 'Bebidas Retail',
        ]);

        $product = Product::withoutGlobalScopes()->create([
            'business_id' => $this->retailBusiness->id,
            'category_id' => $category->id,
            'name' => 'Jugo Natural Mango Cali',
            'sku' => 'JUG-MANG-01',
            'cost_price' => 3000,
            'sale_price' => 7500,
            'stock' => 20,
            'min_stock' => 5,
            'product_type' => 'standard',
            'is_active' => true,
        ]);

        // 1. Probar endpoint api.products.search
        $apiResponse = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->getJson(route('api.products.search', ['q' => 'Mango']));

        $apiResponse->assertOk();
        $data = $apiResponse->json();
        $this->assertNotEmpty($data);
        $first = $data[0];

        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('price', $first);
        $this->assertArrayHasKey('stock', $first);
        $this->assertArrayHasKey('available_stock', $first);
        $this->assertArrayHasKey('product_type', $first);
        $this->assertArrayHasKey('sku', $first);

        $this->assertEquals('Jugo Natural Mango Cali', $first['name']);
        $this->assertEquals(7500, $first['price']);
        $this->assertEquals(20, $first['stock']);
        $this->assertEquals('standard', $first['product_type']);
        $this->assertEquals('JUG-MANG-01', $first['sku']);

        // 2. Verificar vista sales.create
        $viewResponse = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->get(route('sales.create'));

        $viewResponse->assertOk();
        $viewResponse->assertSee('localCatalog');
        $viewResponse->assertSee('searchLocalProducts');
        $viewResponse->assertSee('renderProductResults');
        $viewResponse->assertSee('executeProductSearch');
        $viewResponse->assertSee('addToCart');
        $viewResponse->assertSee('showStockErrorBanner');
        $viewResponse->assertSee('id="stock-error-container"', false);
    }
}


