<?php

namespace Tests\Feature\Superadmin;

use App\Mail\DailyBusinessSummaryMail;
use App\Mail\WelcomeTenantMail;
use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use App\Services\DailySummaryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SuperadminBusinessCreateWelcomeMailTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::firstOrCreate(
            ['slug' => Role::ROLE_ADMIN],
            ['name' => 'Administrador', 'description' => 'Administrador']
        );

        $this->superadmin = User::factory()->create([
            'is_superadmin' => true,
        ]);
    }

    public function test_superadmin_business_creation_sends_welcome_email_with_credentials(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->superadmin)->post('/superadmin/businesses', [
            'name' => 'Comercializadora Pacífico',
            'business_type' => 'retail',
            'nit' => '900987654-2',
            'phone' => '3157894561',
            'email' => 'contacto@pacifico.com',
            'address' => 'Carrera 1 # 12-34, Cali',
            'admin_name' => 'Carlos Gerente',
            'admin_email' => 'carlos@pacifico.com',
            'nightly_report_enabled' => '1',
        ]);

        $response->assertRedirect('/superadmin/businesses');
        $response->assertSessionHas('status');
        $response->assertSessionHas('generated_password');

        $business = Business::where('name', 'Comercializadora Pacífico')->first();
        $this->assertNotNull($business);
        $this->assertTrue($business->nightly_report_enabled);

        $admin = User::where('email', 'carlos@pacifico.com')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->must_change_password);

        Mail::assertSent(WelcomeTenantMail::class, function ($mail) use ($business, $admin) {
            return $mail->hasTo('carlos@pacifico.com')
                && $mail->business->id === $business->id
                && $mail->adminUser->id === $admin->id
                && !empty($mail->plainPassword);
        });
    }

    public function test_superadmin_can_create_business_with_nightly_report_disabled(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->superadmin)->post('/superadmin/businesses', [
            'name' => 'Boutique Sin Reportes',
            'business_type' => 'retail',
            'nit' => '900111222-3',
            'phone' => '3161234567',
            'email' => 'info@sinreportes.com',
            'admin_name' => 'Laura Admin',
            'admin_email' => 'laura@sinreportes.com',
            'nightly_report_enabled' => '0',
        ]);

        $response->assertRedirect('/superadmin/businesses');

        $business = Business::where('name', 'Boutique Sin Reportes')->first();
        $this->assertNotNull($business);
        $this->assertFalse($business->nightly_report_enabled);
    }

    public function test_superadmin_can_update_nightly_report_enabled_status(): void
    {
        $business = Business::create([
            'name' => 'Panadería Central',
            'business_type' => 'retail',
            'status' => 'active',
            'is_active' => true,
            'nightly_report_enabled' => true,
        ]);

        $response = $this->actingAs($this->superadmin)->put("/superadmin/businesses/{$business->id}", [
            'name' => 'Panadería Central Editada',
            'business_type' => 'retail',
            'nightly_report_enabled' => '0',
        ]);

        $response->assertRedirect('/superadmin/businesses');
        $business->refresh();
        $this->assertFalse($business->nightly_report_enabled);
        $this->assertSame('Panadería Central Editada', $business->name);
    }

    public function test_tenant_admin_can_toggle_nightly_report_in_my_business_settings(): void
    {
        $business = Business::create([
            'name' => 'Restaurante Mi Tierra',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
            'nightly_report_enabled' => true,
        ]);

        $admin = User::factory()->create([
            'is_superadmin' => false,
            'must_change_password' => false,
        ]);

        $business->users()->attach($admin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        // Simular sesión del tenant admin
        $response = $this->actingAs($admin)
            ->withSession(['current_business_id' => $business->id])
            ->put('/my-business', [
                'name' => 'Restaurante Mi Tierra Renovado',
                'nightly_report_enabled' => '0',
            ]);

        $response->assertRedirect(route('businesses.edit'));
        $business->refresh();
        $this->assertFalse($business->nightly_report_enabled);

        // Reactivar
        $response2 = $this->actingAs($admin)
            ->withSession(['current_business_id' => $business->id])
            ->put('/my-business', [
                'name' => 'Restaurante Mi Tierra Renovado',
                'nightly_report_enabled' => '1',
            ]);

        $response2->assertRedirect(route('businesses.edit'));
        $business->refresh();
        $this->assertTrue($business->nightly_report_enabled);
    }

    public function test_pos_send_daily_summary_only_delivers_to_enabled_businesses(): void
    {
        Mail::fake();

        // Negocio 1 con reporte activo
        $business1 = Business::create([
            'name' => 'Negocio Activo Notif',
            'business_type' => 'retail',
            'email' => 'activo@negocio.com',
            'status' => 'active',
            'is_active' => true,
            'nightly_report_enabled' => true,
        ]);

        $admin1 = User::factory()->create([
            'email' => 'admin1@activo.com',
            'is_superadmin' => false,
        ]);
        $business1->users()->attach($admin1->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        // Negocio 2 con reporte desactivado (opt-out)
        $business2 = Business::create([
            'name' => 'Negocio Desactivado Notif',
            'business_type' => 'retail',
            'email' => 'desactivado@negocio.com',
            'status' => 'active',
            'is_active' => true,
            'nightly_report_enabled' => false,
        ]);

        $admin2 = User::factory()->create([
            'email' => 'admin2@desactivado.com',
            'is_superadmin' => false,
        ]);
        $business2->users()->attach($admin2->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        // Ejecutar comando sin especificar --business_id
        $this->artisan('pos:send-daily-summary')
            ->assertExitCode(0);

        // Solo debe enviarse al negocio 1
        Mail::assertSent(DailyBusinessSummaryMail::class, function ($mail) use ($business1) {
            return $mail->business->id === $business1->id;
        });

        Mail::assertNotSent(DailyBusinessSummaryMail::class, function ($mail) use ($business2) {
            return $mail->business->id === $business2->id;
        });
    }

    public function test_welcome_and_daily_summary_mailables_render_properly(): void
    {
        $business = Business::create([
            'name' => 'Supermercado Central',
            'business_type' => 'retail',
            'status' => 'active',
            'is_active' => true,
            'nightly_report_enabled' => true,
        ]);

        $admin = User::factory()->create([
            'name' => 'Laura Pérez',
            'email' => 'laura@central.com',
        ]);

        // 1. Renderizar WelcomeTenantMail
        $welcomeMail = new WelcomeTenantMail($business, $admin, 'Temporal123*');
        $welcomeHtml = $welcomeMail->render();

        $this->assertStringContainsString('PuntoStock', $welcomeHtml);
        $this->assertStringContainsString('Laura Pérez', $welcomeHtml);
        $this->assertStringContainsString('Supermercado Central', $welcomeHtml);
        $this->assertStringContainsString('Temporal123*', $welcomeHtml);
        $this->assertStringContainsString('laura@central.com', $welcomeHtml);
        $this->assertStringContainsString('puntostockpos.com', $welcomeHtml);

        // 2. Renderizar DailyBusinessSummaryMail
        $summary = [
            'date' => '2026-11-09',
            'date_formatted' => '09/11/2026',
            'date_human' => '09 de noviembre de 2026',
            'revenue_today' => 150000,
            'revenue_today_formatted' => '$150.000',
            'revenue_yesterday' => 100000,
            'percentage_variation' => '+50,0%',
            'sales_count' => 10,
            'average_ticket' => 15000,
            'average_ticket_formatted' => '$15.000',
            'payment_breakdown' => [
                [
                    'method' => 'cash',
                    'label' => 'Efectivo',
                    'amount' => 100000,
                    'amount_formatted' => '$100.000',
                    'percentage' => 66.7,
                ],
            ],
            'top_products' => [
                [
                    'name' => 'Arroz 1kg',
                    'sku' => 'ARR-01',
                    'quantity' => 12,
                    'revenue' => 48000,
                    'revenue_formatted' => '$48.000',
                ],
            ],
            'critical_stock' => [],
            'critical_stock_count' => 0,
            'ai_conclusion' => 'Excelente jornada con alta rotación de productos básicos.',
        ];

        $summaryMail = new DailyBusinessSummaryMail($summary, $business);
        $summaryHtml = $summaryMail->render();

        $this->assertStringContainsString('PuntoStock', $summaryHtml);
        $this->assertStringContainsString('Supermercado Central', $summaryHtml);
        $this->assertStringContainsString('$150.000', $summaryHtml);
        $this->assertStringContainsString('Arroz 1kg', $summaryHtml);
        $this->assertStringContainsString('puntostockpos.com', $summaryHtml);
    }
}

