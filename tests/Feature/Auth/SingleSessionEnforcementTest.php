<?php

namespace Tests\Feature\Auth;

use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SingleSessionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Business $business;
    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::firstOrCreate(
            ['slug' => Role::ROLE_ADMIN],
            ['name' => 'Administrador', 'description' => 'Administrador del negocio']
        );

        $this->business = Business::create([
            'name' => 'Comercio Sesión Única',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'email' => 'seguridad@puntostock.com',
            'password' => bcrypt('Password123!'),
            'must_change_password' => false,
        ]);

        $this->business->users()->attach($this->user->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: El login asigna un token de sesión único al usuario en BD y en la sesión.
     */
    public function test_login_assigns_unique_session_token_to_user(): void
    {
        $this->assertNull($this->user->current_session_id);

        $response = $this->post('/login', [
            'email' => 'seguridad@puntostock.com',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->user);

        $freshUser = $this->user->fresh();
        $this->assertNotNull($freshUser->current_session_id);
        $this->assertNotEmpty($freshUser->current_session_id);
        $this->assertSame($freshUser->current_session_id, session('auth_session_token'));
    }

    /**
     * Test 2: Un intento de inicio de sesión simultáneo desde un segundo dispositivo es BLOQUEADO
     * con error de validación en la pantalla de login, protegiendo la sesión del primer dispositivo.
     */
    public function test_concurrent_login_from_second_device_is_blocked_with_validation_error(): void
    {
        // 1. Dispositivo A ya tiene sesión activa
        $deviceAToken = 'token-dispositivo-1';
        $this->user->forceFill(['current_session_id' => $deviceAToken])->saveQuietly();

        // 2. Dispositivo B intenta iniciar sesión con las mismas credenciales
        $response = $this->post('/login', [
            'email' => 'seguridad@puntostock.com',
            'password' => 'Password123!',
        ]);

        // Dispositivo B debe rebotar al login con el error explicativo
        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'email' => __('auth.active_session'),
        ]);
        $this->assertGuest();

        // La sesión del Dispositivo A permanece intacta en BD
        $freshUser = $this->user->fresh();
        $this->assertSame($deviceAToken, $freshUser->current_session_id);
    }

    /**
     * Test 3: Solicitud JSON en sesión con token desincronizado responde 401 con mensaje descriptivo.
     */
    public function test_concurrent_login_invalidates_json_request_with_401(): void
    {
        $oldToken = 'token-dispositivo-viejo';
        $newToken = 'token-dispositivo-nuevo';
        $this->user->forceFill(['current_session_id' => $newToken])->saveQuietly();

        $response = $this->actingAs($this->user)
            ->withSession(['auth_session_token' => $oldToken])
            ->getJson(route('dashboard'));

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Tu sesión fue cerrada porque se inició sesión en otro dispositivo.',
        ]);
        $this->assertGuest();
    }

    /**
     * Test 4: El logout limpia el campo current_session_id y permite un posterior login.
     */
    public function test_logout_clears_active_session_allowing_subsequent_login(): void
    {
        // 1. Iniciar sesión
        $this->post('/login', [
            'email' => 'seguridad@puntostock.com',
            'password' => 'Password123!',
        ]);
        $this->assertNotNull($this->user->fresh()->current_session_id);

        // 2. Cerrar sesión
        $response = $this->post(route('logout'));
        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull($this->user->fresh()->current_session_id);

        // 3. Otro dispositivo ahora sí puede iniciar sesión
        $response2 = $this->post('/login', [
            'email' => 'seguridad@puntostock.com',
            'password' => 'Password123!',
        ]);
        $response2->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->user);
    }

    /**
     * Test 5: Petición GET a /logout cierra sesión y redirige al login sin error.
     */
    public function test_get_request_to_logout_clears_session_and_redirects_to_login(): void
    {
        $response = $this->actingAs($this->user)->get(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * Test 6: POST a /logout sin token CSRF no arroja 419 y redirige directamente a /login.
     */
    public function test_logout_without_csrf_token_does_not_throw_419_and_redirects_to_login(): void
    {
        $response = $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
            ->actingAs($this->user)
            ->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * Test 7: Con driver de base de datos, si hay una sesión activa en sessions table, el segundo login es bloqueado.
     */
    public function test_concurrent_login_blocked_with_database_session_driver(): void
    {
        config(['session.driver' => 'database']);

        // Insertar sesión activa en la tabla sessions para este usuario
        DB::table('sessions')->insert([
            'id' => 'sesion-activa-en-otro-equipo',
            'user_id' => $this->user->id,
            'ip_address' => '192.168.1.50',
            'user_agent' => 'Mozilla/5.0 Laptop',
            'payload' => serialize(['dummy' => 'payload']),
            'last_activity' => time(),
        ]);

        $this->user->forceFill(['current_session_id' => 'sesion-activa-en-otro-equipo'])->saveQuietly();

        // Intento de login desde móvil
        $response = $this->post('/login', [
            'email' => 'seguridad@puntostock.com',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'email' => __('auth.active_session'),
        ]);
        $this->assertGuest();
    }
}

