<?php

namespace Tests\Feature\Legal;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LegalComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Business $business;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::firstOrCreate(
            ['slug' => Role::ROLE_ADMIN],
            ['name' => 'Administrador', 'description' => 'Administrador del negocio']
        );

        $this->business = Business::create(['name' => 'Comercio Cali Legal']);
        $this->adminUser = User::factory()->create();
        $this->business->users()->attach($this->adminUser->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);
    }

    /**
     * 1. Las rutas públicas legales emiten redirección canónica 301 hacia el dominio oficial puntostockpos.com.
     */
    public function test_public_legal_routes_are_accessible_without_authentication(): void
    {
        $termsResponse = $this->get('/terminos-y-condiciones');
        $termsResponse->assertStatus(301);
        $this->assertEquals('https://puntostockpos.com/terminos-y-condiciones/', $termsResponse->headers->get('Location'));

        $privacyResponse = $this->get('/politica-de-privacidad');
        $privacyResponse->assertStatus(301);
        $this->assertEquals('https://puntostockpos.com/politica-de-privacidad/', $privacyResponse->headers->get('Location'));
    }

    /**
     * 2. Usuarios autenticados que consulten las rutas legales también son redirigidos permanentemente (301).
     */
    public function test_authenticated_users_can_access_legal_routes(): void
    {
        $this->actingAs($this->adminUser);

        $termsResponse = $this->get(route('legal.terms'));
        $termsResponse->assertStatus(301);
        $this->assertEquals('https://puntostockpos.com/terminos-y-condiciones/', $termsResponse->headers->get('Location'));

        $privacyResponse = $this->get(route('legal.privacy'));
        $privacyResponse->assertStatus(301);
        $this->assertEquals('https://puntostockpos.com/politica-de-privacidad/', $privacyResponse->headers->get('Location'));
    }

    /**
     * 3. El cambio de contraseña obligatorio rechaza solicitudes sin aceptación de términos.
     */
    public function test_mandatory_password_change_requires_accepting_terms(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('claveTemporal123'),
            'must_change_password' => true,
            'terms_accepted_at' => null,
            'terms_accepted_ip' => null,
        ]);
        $this->business->users()->attach($user->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->assertFalse($user->hasAcceptedTerms());

        // Intento 1: omitiendo accept_terms por completo
        $responseWithoutCheckbox = $this->actingAs($user)->post(route('password.change.update'), [
            'current_password' => 'claveTemporal123',
            'password' => 'nuevaClaveSegura2026',
            'password_confirmation' => 'nuevaClaveSegura2026',
        ]);

        $responseWithoutCheckbox->assertSessionHasErrors('accept_terms');
        $this->assertTrue((bool) $user->fresh()->must_change_password);
        $this->assertNull($user->fresh()->terms_accepted_at);
        $this->assertFalse($user->fresh()->hasAcceptedTerms());

        // Intento 2: enviando accept_terms con valor falso/cero
        $responseWithZero = $this->actingAs($user)->post(route('password.change.update'), [
            'current_password' => 'claveTemporal123',
            'password' => 'nuevaClaveSegura2026',
            'password_confirmation' => 'nuevaClaveSegura2026',
            'accept_terms' => '0',
        ]);

        $responseWithZero->assertSessionHasErrors('accept_terms');
        $this->assertTrue((bool) $user->fresh()->must_change_password);
        $this->assertNull($user->fresh()->terms_accepted_at);
    }

    /**
     * 4. Al aceptar términos y cambiar contraseña se registra estampa de tiempo e IP probatoria (Ley 527/1999).
     */
    public function test_mandatory_password_change_persists_timestamp_and_ip_evidence(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('temporalPassword123'),
            'must_change_password' => true,
            'terms_accepted_at' => null,
            'terms_accepted_ip' => null,
        ]);
        $this->business->users()->attach($user->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $mockIp = '190.84.112.55';

        $response = $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => $mockIp])
            ->post(route('password.change.update'), [
                'current_password' => 'temporalPassword123',
                'password' => 'definitivaSegura2026!',
                'password_confirmation' => 'definitivaSegura2026!',
                'accept_terms' => '1',
            ]);

        $response->assertRedirect(route('dashboard'));

        $freshUser = $user->fresh();
        $this->assertFalse((bool) $freshUser->must_change_password);
        $this->assertNotNull($freshUser->terms_accepted_at);
        $this->assertInstanceOf(Carbon::class, $freshUser->terms_accepted_at);
        $this->assertEquals($mockIp, $freshUser->terms_accepted_ip);
        $this->assertTrue($freshUser->hasAcceptedTerms());
        $this->assertTrue(Hash::check('definitivaSegura2026!', $freshUser->password));
    }

    /**
     * 5. La pantalla de login incluye enlaces discretos a Términos y Condiciones y Privacidad.
     */
    public function test_login_screen_contains_links_to_legal_documents(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertSee('https://puntostockpos.com/terminos-y-condiciones/', false);
        $response->assertSee('https://puntostockpos.com/politica-de-privacidad/', false);
        $response->assertSee('Términos y Condiciones');
        $response->assertSee('Privacidad y Cookies');
        $response->assertSee('target="_blank"', false);
        $response->assertSee('rel="noopener noreferrer"', false);
        $response->assertSee('https://puntostockpos.com', false);
        $response->assertSee('¿Quieres PuntoStock para tu negocio?');
        $response->assertSee('Escríbenos');
        $response->assertSee('https://wa.me/' . config('app.support_whatsapp', '573163765939'), false);
    }

    /**
     * 6. Las vistas de registro y edición de clientes contienen la leyenda legal de Habeas Data (Ley 1581 de 2012).
     */
    public function test_customer_forms_contain_habeas_data_statutory_notice(): void
    {
        $this->actingAs($this->adminUser);

        $legalText = 'El comercio certifica que cuenta con la autorización del titular para la recolección y tratamiento de sus datos de contacto conforme a la Ley 1581 de 2012 de Habeas Data.';

        // Vista de creación
        $createResponse = $this->get(route('customers.create'));
        $createResponse->assertStatus(200);
        $createResponse->assertSee($legalText);

        // Vista de edición
        $customer = Customer::create([
            'business_id' => $this->business->id,
            'name' => 'Cliente Prueba SAS',
            'identification_number' => '900987654-1',
            'email' => 'prueba@cliente.com',
            'phone' => '3001234567',
            'is_active' => true,
        ]);

        $editResponse = $this->get(route('customers.edit', $customer));
        $editResponse->assertStatus(200);
        $editResponse->assertSee($legalText);
    }
}
