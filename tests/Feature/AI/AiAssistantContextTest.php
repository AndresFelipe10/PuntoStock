<?php

namespace Tests\Feature\AI;

use App\AI\Contracts\AiProviderInterface;
use App\AI\Providers\FakeAiProvider;
use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAssistantContextTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Business $retailBusiness;
    protected Business $restaurantBusiness;
    protected Business $otherBusiness;
    protected User $retailAdmin;
    protected User $restaurantAdmin;
    protected FakeAiProvider $fakeProvider;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.enabled' => true]);

        $this->fakeProvider = new FakeAiProvider();
        $this->app->instance(AiProviderInterface::class, $this->fakeProvider);

        $this->adminRole = Role::create([
            'name' => 'Administrador',
            'slug' => Role::ROLE_ADMIN,
        ]);

        // Retail business
        $this->retailBusiness = Business::create([
            'name' => 'Boutique Cali Retail',
            'business_type' => 'retail',
        ]);
        $this->retailAdmin = User::factory()->create();
        $this->retailBusiness->users()->attach($this->retailAdmin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        // Restaurant business
        $this->restaurantBusiness = Business::create([
            'name' => 'Restaurante Sabor Valluno',
            'business_type' => 'restaurant',
        ]);
        $this->restaurantAdmin = User::factory()->create();
        $this->restaurantBusiness->users()->attach($this->restaurantAdmin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        // Isolated third-party business
        $this->otherBusiness = Business::create([
            'name' => 'Negocio Externo Invasor',
            'business_type' => 'retail',
        ]);
    }

    /**
     * Test 1: Flujo exacto reportado en producción:
     * Usuario pregunta "¿Cuántos clientes activos tenemos registrados?" -> responde 2.
     * Luego pregunta "cuales son?" -> lista a Juan Pérez y María Gómez, NO productos de catálogo.
     */
    public function test_customer_count_followed_by_anaphoric_cuales_son_resolves_to_customer_list(): void
    {
        // Crear 2 clientes activos en el negocio retail
        Customer::create([
            'business_id' => $this->retailBusiness->id,
            'name' => 'Juan Pérez',
            'document' => '1144123456',
            'phone' => '3151234567',
            'email' => 'juan.perez@example.com',
            'is_active' => true,
        ]);

        Customer::create([
            'business_id' => $this->retailBusiness->id,
            'name' => 'María Gómez',
            'document' => '1144987654',
            'phone' => '3169876543',
            'email' => 'maria.gomez@example.com',
            'is_active' => true,
        ]);

        // Crear productos en catálogo para asegurar que no se ejecute fallback erróneo
        Category::create([
            'business_id' => $this->retailBusiness->id,
            'name' => 'Calzado',
        ]);
        Product::create([
            'business_id' => $this->retailBusiness->id,
            'name' => 'Zapatos Deportivos Pro',
            'sku' => 'ZAP-001',
            'cost_price' => 50000,
            'sale_price' => 90000,
            'stock' => 15,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        // 1. Primer turno: Conteo de clientes
        $turn1 = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->postJson('/ai/ask', [
                'query' => '¿Cuántos clientes activos tenemos registrados?',
            ]);

        $turn1->assertOk();
        $this->assertTrue($turn1->json('success'));
        $this->assertStringContainsString('2', $turn1->json('summary'));
        $this->assertEquals('count_customers', $turn1->json('intent'));

        // 2. Segundo turno: Pregunta anafórica de seguimiento "cuales son?"
        $turn2 = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->postJson('/ai/ask', [
                'query' => 'cuales son?',
            ]);

        $turn2->assertOk();
        $this->assertTrue($turn2->json('success'));
        $this->assertEquals('list_customers', $turn2->json('intent'));

        // Verificar que los datos son clientes y NO productos
        $customerNames = collect($turn2->json('data'))->pluck('name')->toArray();
        $this->assertContains('Juan Pérez', $customerNames);
        $this->assertContains('María Gómez', $customerNames);
        $this->assertNotContains('Zapatos Deportivos Pro', $customerNames);
        $this->assertStringNotContainsString('catálogo', strtolower($turn2->json('summary')));
    }

    /**
     * Test 2: Flujo de seguimiento en Stock / Inventario:
     * Preguntar por productos agotados y luego "cuales son?" lista productos con stock 0.
     */
    public function test_inventory_out_of_stock_followed_by_cuales_son_lists_out_of_stock_products(): void
    {
        $category = Category::create([
            'business_id' => $this->retailBusiness->id,
            'name' => 'Ropa',
        ]);

        Product::create([
            'business_id' => $this->retailBusiness->id,
            'category_id' => $category->id,
            'name' => 'Camisa Agotada Negra',
            'sku' => 'CAM-AGOTADA',
            'cost_price' => 20000,
            'sale_price' => 45000,
            'stock' => 0,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        Product::create([
            'business_id' => $this->retailBusiness->id,
            'category_id' => $category->id,
            'name' => 'Pantalón Con Stock',
            'sku' => 'PAN-CON-STOCK',
            'cost_price' => 40000,
            'sale_price' => 80000,
            'stock' => 20,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        // Turno 1: Productos agotados
        $turn1 = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->postJson('/ai/ask', [
                'query' => '¿Qué productos están completamente agotados?',
            ]);

        $turn1->assertOk();
        $this->assertEquals('out_of_stock_products', $turn1->json('intent'));

        // Turno 2: Seguimiento "¿cuáles son?"
        $turn2 = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->postJson('/ai/ask', [
                'query' => '¿cuáles son?',
            ]);

        $turn2->assertOk();
        $this->assertEquals('out_of_stock_products', $turn2->json('intent'));

        $productNames = collect($turn2->json('data'))->pluck('name')->toArray();
        $this->assertContains('Camisa Agotada Negra', $productNames);
        $this->assertNotContains('Pantalón Con Stock', $productNames);
    }

    /**
     * Test 3: Aislamiento Multi-Tenant estricto y sin filtración de datos de otros tenants.
     */
    public function test_tenant_data_isolation_in_contextual_queries(): void
    {
        // Cliente en tenant retail
        Customer::create([
            'business_id' => $this->retailBusiness->id,
            'name' => 'Cliente Legítimo Retail',
            'document' => '11111111',
            'is_active' => true,
        ]);

        // Cliente en otro tenant ajeno
        Customer::create([
            'business_id' => $this->otherBusiness->id,
            'name' => 'Cliente Espía Negocio B',
            'document' => '99999999',
            'is_active' => true,
        ]);

        // Consulta en retailBusiness
        $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->postJson('/ai/ask', ['query' => '¿Cuántos clientes tenemos?']);

        $followUp = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->postJson('/ai/ask', ['query' => 'muéstramelos']);

        $followUp->assertOk();
        $names = collect($followUp->json('data'))->pluck('name')->toArray();
        $this->assertContains('Cliente Legítimo Retail', $names);
        $this->assertNotContains('Cliente Espía Negocio B', $names);
    }

    /**
     * Test 4: Compatibilidad y comportamiento diferenciado según tipo de negocio (Retail vs Restaurante):
     * En restaurante responde sobre mesas y comandas.
     * En consulta desconocida, ofrece guía personalizada sin inventar catálogo.
     */
    public function test_business_type_specific_guidance_and_restaurant_intents(): void
    {
        // Crear mesas en restaurante
        RestaurantTable::create([
            'business_id' => $this->restaurantBusiness->id,
            'name' => 'Mesa 1',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);
        RestaurantTable::create([
            'business_id' => $this->restaurantBusiness->id,
            'name' => 'Mesa 2',
            'capacity' => 2,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        // 1. Consulta de mesas en restaurante
        $tableQuery = $this->actingAs($this->restaurantAdmin)
            ->withSession(['current_business_id' => $this->restaurantBusiness->id])
            ->postJson('/ai/ask', ['query' => '¿Cuál es el estado de las mesas?']);

        $tableQuery->assertOk();
        $this->assertEquals('restaurant_tables_status', $tableQuery->json('intent'));
        $this->assertStringContainsString('mesas', strtolower($tableQuery->json('summary')));

        // 2. Consulta no reconocida en Restaurante: no lista catálogo y da guía de restaurante
        $unknownRestaurant = $this->actingAs($this->restaurantAdmin)
            ->withSession(['current_business_id' => $this->restaurantBusiness->id])
            ->postJson('/ai/ask', ['query' => 'quiero saber algo totalmente desconocido']);

        $unknownRestaurant->assertOk();
        $this->assertEquals('unrecognized', $unknownRestaurant->json('intent'));
        $this->assertStringContainsString('restaurante', strtolower($unknownRestaurant->json('summary')));
        $this->assertEmpty($unknownRestaurant->json('data'));

        // 3. Consulta no reconocida en Retail: da guía de comercio retail
        $unknownRetail = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->postJson('/ai/ask', ['query' => 'consulta completamente incomprensible']);

        $unknownRetail->assertOk();
        $this->assertEquals('unrecognized', $unknownRetail->json('intent'));
        $this->assertStringContainsString('comercio', strtolower($unknownRetail->json('summary')));
        $this->assertEmpty($unknownRetail->json('data'));
    }

    /**
     * Test 5: Reiniciar contexto conversacional con endpoint /ai/clear-context.
     */
    public function test_clear_context_endpoint_resets_session_memory(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('ai.clear-context'));
        $this->assertEquals(url('/ai/clear-context'), route('ai.clear-context'));

        // GET /ai redirige a /ai/assistant
        $redirectResponse = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->get('/ai');
        $redirectResponse->assertRedirect('/ai/assistant');

        // GET /ai/assistant renderiza vista con referencia a ai.clear-context
        $viewResponse = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->get(route('ai.index'));
        $viewResponse->assertOk();
        $viewResponse->assertSee(route('ai.clear-context'), false);

        Customer::create([
            'business_id' => $this->retailBusiness->id,
            'name' => 'Cliente Activo',
            'is_active' => true,
        ]);

        // Turno 1: pregunta sobre clientes
        $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->postJson('/ai/ask', ['query' => 'cuantos clientes tenemos']);

        // Limpiar contexto via JSON
        $clearResponse = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->postJson(route('ai.clear-context'));

        $clearResponse->assertOk();
        $clearResponse->assertJson(['success' => true]);

        // Preguntar "cuales son?" sin contexto previo debe devolver unrecognized y no list_customers
        $followUp = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->postJson('/ai/ask', ['query' => 'cuales son?']);

        $followUp->assertOk();
        $this->assertEquals('unrecognized', $followUp->json('intent'));

        // Limpiar contexto via solicitud web tradicional (redirect back)
        $webClearResponse = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->from(route('ai.index'))
            ->post(route('ai.clear-context'));

        $webClearResponse->assertRedirect(route('ai.index'));
        $webClearResponse->assertSessionHas('status', 'Contexto del asistente reiniciado exitosamente.');
    }

    /**
     * Test 6: Vistas de órdenes de restaurante y ventas no muestran el sufijo técnico '(80mm)'.
     */
    public function test_views_render_without_80mm_suffix_in_buttons(): void
    {
        $table = RestaurantTable::create([
            'business_id' => $this->restaurantBusiness->id,
            'name' => 'Mesa Terraza',
            'status' => 'occupied',
            'is_active' => true,
        ]);

        // 1. Orden abierta -> botón 'Pre-cuenta'
        $openOrder = RestaurantOrder::create([
            'business_id' => $this->restaurantBusiness->id,
            'table_id' => $table->id,
            'user_id' => $this->restaurantAdmin->id,
            'order_number' => 'ORD-TEST-001',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 30000,
            'total' => 30000,
        ]);

        $openResponse = $this->actingAs($this->restaurantAdmin)
            ->withSession(['current_business_id' => $this->restaurantBusiness->id])
            ->get(route('restaurant.orders.show', $openOrder));

        $openResponse->assertOk();
        $openResponse->assertSee('Pre-cuenta');
        $openResponse->assertDontSee('(80mm)');

        // 2. Orden cerrada con venta facturada -> botones 'Ticket Fiscal' e 'Imprimir Ticket Fiscal'
        $sale = Sale::create([
            'business_id' => $this->restaurantBusiness->id,
            'user_id' => $this->restaurantAdmin->id,
            'invoice_number' => 'FAC-TEST-001',
            'sale_date' => Carbon::now(),
            'total' => 30000,
            'status' => 'completed',
        ]);

        $closedOrder = RestaurantOrder::create([
            'business_id' => $this->restaurantBusiness->id,
            'table_id' => $table->id,
            'user_id' => $this->restaurantAdmin->id,
            'sale_id' => $sale->id,
            'order_number' => 'ORD-TEST-002',
            'order_type' => 'table',
            'status' => 'closed',
            'subtotal' => 30000,
            'total' => 30000,
        ]);

        $closedResponse = $this->actingAs($this->restaurantAdmin)
            ->withSession(['current_business_id' => $this->restaurantBusiness->id])
            ->get(route('restaurant.orders.show', $closedOrder));

        $closedResponse->assertOk();
        $closedResponse->assertSee('Ticket Fiscal');
        $closedResponse->assertSee('Imprimir Ticket Fiscal');
        $closedResponse->assertDontSee('(80mm)');
    }
}
