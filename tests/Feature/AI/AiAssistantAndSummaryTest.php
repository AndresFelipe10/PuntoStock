<?php

namespace Tests\Feature\AI;

use App\AI\Contracts\AiProviderInterface;
use App\AI\Enums\AiIntent;
use App\AI\Providers\FakeAiProvider;
use App\AI\Services\DeterministicAiResolver;
use App\Enums\PaymentMethod;
use App\Mail\DailyBusinessSummaryMail;
use App\Models\Business;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SalePayment;
use App\Models\User;
use App\Services\DailySummaryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AiAssistantAndSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Business $businessA;
    protected Business $businessB;
    protected User $adminUserA;
    protected FakeAiProvider $fakeProvider;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.enabled' => true]);

        $this->fakeProvider = new FakeAiProvider();
        $this->app->instance(AiProviderInterface::class, $this->fakeProvider);

        $this->adminRole = Role::firstOrCreate(
            ['slug' => Role::ROLE_ADMIN],
            ['name' => 'Administrador']
        );

        $this->businessA = Business::create([
            'name' => 'Restaurante Gourmet A',
            'business_type' => 'restaurant',
            'email' => 'admin@gourmet-a.com',
            'is_active' => true,
        ]);

        $this->businessB = Business::create([
            'name' => 'Restaurante B',
            'business_type' => 'restaurant',
            'email' => 'admin@gourmet-b.com',
            'is_active' => true,
        ]);

        $this->adminUserA = User::factory()->create([
            'email' => 'propietario@gourmet-a.com',
        ]);
        $this->businessA->users()->attach($this->adminUserA->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);
    }

    public function test_shift_and_relative_date_sales_comparison_with_tenant_isolation(): void
    {
        $tz = 'America/Bogota';
        $today = Carbon::now($tz);
        $lastTuesday = $today->copy()->previous(Carbon::TUESDAY);

        // Ventas Negocio A: Hoy en el turno de la tarde (14:30)
        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUserA->id,
            'invoice_number' => 'FAC-A-001',
            'status' => 'completed',
            'total' => 150000,
            'subtotal' => 150000,
            'payment_method' => 'cash',
            'sale_date' => $today->copy()->setTime(14, 30, 0),
        ]);

        // Venta Negocio A: Hoy en turno mañana (10:00) - No debe contarse en turno tarde
        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUserA->id,
            'invoice_number' => 'FAC-A-002',
            'status' => 'completed',
            'total' => 80000,
            'subtotal' => 80000,
            'payment_method' => 'cash',
            'sale_date' => $today->copy()->setTime(10, 0, 0),
        ]);

        // Venta Negocio A: Martes pasado en turno de la tarde (15:00)
        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUserA->id,
            'invoice_number' => 'FAC-A-003',
            'status' => 'completed',
            'total' => 100000,
            'subtotal' => 100000,
            'payment_method' => 'card',
            'sale_date' => $lastTuesday->copy()->setTime(15, 0, 0),
        ]);

        // Venta Negocio B: Hoy en turno tarde (14:30) - Aislamiento estricto
        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'user_id' => $this->adminUserA->id,
            'invoice_number' => 'FAC-B-001',
            'status' => 'completed',
            'total' => 999000,
            'subtotal' => 999000,
            'payment_method' => 'cash',
            'sale_date' => $today->copy()->setTime(14, 30, 0),
        ]);

        $this->fakeProvider->setNextIntent(AiIntent::SALES_COMPARISON, [
            'shift' => 'afternoon',
            'target_date' => 'today',
            'compare_to' => 'previous_tuesday',
        ]);

        $response = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->postJson('/ai/ask', [
                'query' => '¿Cuánto vendimos en el turno de la tarde comparado con el martes pasado?',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'intent' => 'sales_comparison',
            'success' => true,
        ]);

        $meta = $response->json('meta');
        $this->assertEquals(150000, $meta['current_total']);
        $this->assertEquals(100000, $meta['compare_total']);
        $this->assertEquals(50000, $meta['difference_total']);
        $this->assertEquals('+50,0%', $meta['percentage_variation']);

        // Verificar que no se contamina con datos de Negocio B
        $this->assertStringNotContainsString('999.000', $response->getContent());
    }

    public function test_product_margins_analysis_with_strict_limit_enforcement(): void
    {
        // Crear 5 platos en Negocio A con márgenes ascendentes
        $dishes = [
            ['name' => 'Sopa del Día', 'cost' => 8000, 'sale' => 10000],   // Margen: 2.000 (20%)
            ['name' => 'Empanada Criolla', 'cost' => 2500, 'sale' => 5000], // Margen: 2.500 (50%)
            ['name' => 'Bandeja Paisa', 'cost' => 18000, 'sale' => 25000],  // Margen: 7.000 (28%)
            ['name' => 'Churrasco 300g', 'cost' => 25000, 'sale' => 45000], // Margen: 20.000 (44.4%)
            ['name' => 'Salmón Grillé', 'cost' => 30000, 'sale' => 60000],  // Margen: 30.000 (50%)
        ];

        foreach ($dishes as $idx => $d) {
            Product::withoutGlobalScopes()->create([
                'business_id' => $this->businessA->id,
                'name' => $d['name'],
                'sku' => 'PLT-' . str_pad($idx + 1, 3, '0', STR_PAD_LEFT),
                'product_type' => 'dish',
                'cost_price' => $d['cost'],
                'sale_price' => $d['sale'],
                'stock' => 50,
                'min_stock' => 10,
                'is_active' => true,
            ]);
        }

        $this->fakeProvider->setNextIntent(AiIntent::PRODUCT_MARGINS, [
            'order' => 'asc',
            'limit' => 3,
            'product_type' => 'dish',
        ]);

        $response = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->postJson('/ai/ask', [
                'query' => '¿Cuáles son los 3 platos con menor margen de ganancia?',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'intent' => 'product_margins',
            'success' => true,
        ]);

        $data = $response->json('data');
        $this->assertCount(3, $data, 'Debe devolver estrictamente 3 platos y no el catálogo completo');

        // El primero debe ser 'Sopa del Día' (margen unitario $2.000)
        $this->assertEquals('Sopa del Día', $data[0]['name']);
        $this->assertEquals('$2.000', $data[0]['margin_amount']);
    }

    public function test_product_margins_identifies_zero_cost_items_with_disclaimer(): void
    {
        Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Limonada de la Casa',
            'sku' => 'PLT-LIM',
            'product_type' => 'dish',
            'cost_price' => 0.00,
            'sale_price' => 4000,
            'stock' => 100,
            'min_stock' => 10,
            'is_active' => true,
        ]);

        $this->fakeProvider->setNextIntent(AiIntent::PRODUCT_MARGINS, [
            'order' => 'asc',
            'limit' => 1,
            'product_type' => 'dish',
        ]);

        $response = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->postJson('/ai/ask', [
                'query' => 'platos menor margen',
            ]);

        $response->assertStatus(200);
        $summary = $response->json('summary');
        $this->assertStringContainsString('costo en $0', $summary);
    }

    public function test_deterministic_resolver_autonomously_resolves_key_intents(): void
    {
        $resolver = new DeterministicAiResolver();

        // 1. Comparativa de turnos y fechas relativas
        $intent1 = $resolver->resolve('¿Cuánto vendimos en el turno de la tarde comparado con el martes pasado?');
        $this->assertNotNull($intent1);
        $this->assertEquals(AiIntent::SALES_COMPARISON, $intent1->intent);
        $this->assertEquals('afternoon', $intent1->filters['shift']);
        $this->assertEquals('previous_tuesday', $intent1->filters['compare_to']);

        // 2. Ranking de márgenes con límite 3
        $intent2 = $resolver->resolve('¿Cuáles son los 3 platos con menor margen de ganancia?');
        $this->assertNotNull($intent2);
        $this->assertEquals(AiIntent::PRODUCT_MARGINS, $intent2->intent);
        $this->assertEquals(3, $intent2->filters['limit']);
        $this->assertEquals('asc', $intent2->filters['order']);
        $this->assertEquals('dish', $intent2->filters['product_type']);

        // 3. Top 5 más vendidos
        $intent3 = $resolver->resolve('¿Cuáles son los 5 productos más vendidos?');
        $this->assertNotNull($intent3);
        $this->assertEquals(AiIntent::TOP_SELLING_PRODUCTS, $intent3->intent);
        $this->assertEquals(5, $intent3->filters['limit']);

        // 4. Stock crítico proveedor
        $intent4 = $resolver->resolve('¿Qué productos tienen bajo stock o requieren reposición?');
        $this->assertNotNull($intent4);
        $this->assertEquals(AiIntent::LOW_STOCK_PRODUCTS, $intent4->intent);

        // 5. Ventas de hoy vs ayer
        $intent5 = $resolver->resolve('Ventas de hoy vs ayer');
        $this->assertNotNull($intent5);
        $this->assertEquals(AiIntent::SALES_COMPARISON, $intent5->intent);
    }

    public function test_daily_summary_service_generates_accurate_financial_and_operational_data(): void
    {
        $tz = 'America/Bogota';
        $today = Carbon::now($tz);
        $yesterday = $today->copy()->subDay();

        // Producto estrella
        $product = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Arepa Especial',
            'sku' => 'ARP-001',
            'cost_price' => 2000,
            'sale_price' => 5000,
            'stock' => 2,
            'min_stock' => 10, // En stock crítico
            'is_active' => true,
        ]);

        // Venta de hoy
        $saleToday = Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUserA->id,
            'invoice_number' => 'FAC-001',
            'status' => 'completed',
            'total' => 25000,
            'subtotal' => 25000,
            'payment_method' => 'cash',
            'sale_date' => $today->copy()->setTime(13, 0, 0),
        ]);

        SaleDetail::withoutGlobalScopes()->create([
            'sale_id' => $saleToday->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 5000,
            'subtotal' => 25000,
        ]);

        SalePayment::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'sale_id' => $saleToday->id,
            'method' => PaymentMethod::Cash,
            'amount' => 25000,
        ]);

        // Venta de ayer
        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUserA->id,
            'invoice_number' => 'FAC-000',
            'status' => 'completed',
            'total' => 20000,
            'subtotal' => 20000,
            'payment_method' => 'cash',
            'sale_date' => $yesterday->copy()->setTime(13, 0, 0),
        ]);

        $service = app(DailySummaryService::class);
        $summary = $service->generateSummary($this->businessA, $today);

        $this->assertEquals(25000, $summary['revenue_today']);
        $this->assertEquals(20000, $summary['revenue_yesterday']);
        $this->assertEquals('+25,0%', $summary['percentage_variation']);
        $this->assertEquals(1, $summary['sales_count']);
        $this->assertEquals(25000, $summary['average_ticket']);
        $this->assertNotEmpty($summary['payment_breakdown']);
        $this->assertEquals('Efectivo', $summary['payment_breakdown'][0]['label']);
        $this->assertEquals(1, $summary['critical_stock_count']);
        $this->assertStringContainsString('Arepa Especial', $summary['ai_conclusion']);
    }

    public function test_send_daily_summary_command_sends_email_with_mail_fake(): void
    {
        Mail::fake();

        $this->artisan('pos:send-daily-summary', ['--business_id' => $this->businessA->id])
            ->expectsOutputToContain('PuntoStock POS - Envío Automatizado de Resumen Diario')
            ->expectsOutputToContain('Resumen diario enviado exitosamente')
            ->assertExitCode(0);

        Mail::assertSent(DailyBusinessSummaryMail::class, function ($mail) {
            return $mail->hasTo($this->adminUserA->email)
                && $mail->business->id === $this->businessA->id
                && isset($mail->summary['revenue_today']);
        });
    }
}
