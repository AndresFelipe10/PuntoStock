<?php

namespace Tests\Feature\Auth;

use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $this->assertSame($freshUser->current_session_id, session()->getId());
    }

    /**
     * Test 2: Si el usuario ya tiene una sesión previa, el nuevo inicio de sesión tiene ÉXITO
     * (patrón Kick-out) y reemplaza current_session_id con el nuevo ID de sesión.
     */
    public function test_subsequent_login_from_second_device_succeeds_and_replaces_session(): void
    {
        $deviceAToken = 'token-dispositivo-1';
        $this->user->forceFill(['current_session_id' => $deviceAToken])->saveQuietly();

        $response = $this->post('/login', [
            'email' => 'seguridad@puntostock.com',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->user);

        $freshUser = $this->user->fresh();
        $this->assertNotEquals($deviceAToken, $freshUser->current_session_id);
        $this->assertSame($freshUser->current_session_id, session()->getId());
    }

    /**
     * Test 3: Con driver database, el login invalida y destruye el registro de la sesión previa en la tabla sessions.
     */
    public function test_subsequent_login_deletes_old_session_from_database_sessions_table(): void
    {
        config(['session.driver' => 'database']);

        $oldSessionId = 'sesion-previa-dispositivo-viejo';

        DB::table('sessions')->insert([
            'id' => $oldSessionId,
            'user_id' => $this->user->id,
            'ip_address' => '192.168.1.50',
            'user_agent' => 'Mozilla/5.0 Laptop',
            'payload' => serialize(['dummy' => 'payload']),
            'last_activity' => time(),
        ]);

        $this->user->forceFill(['current_session_id' => $oldSessionId])->saveQuietly();

        $response = $this->post('/login', [
            'email' => 'seguridad@puntostock.com',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->user);

        // La sesión vieja fue purgada de la tabla sessions
        $this->assertFalse(DB::table('sessions')->where('id', $oldSessionId)->exists());

        $freshUser = $this->user->fresh();
        $this->assertNotEquals($oldSessionId, $freshUser->current_session_id);
    }

    /**
     * Test 4: Cuando la sesión vieja intenta navegar, EnsureSingleSession la expulsa al login con mensaje explicativo.
     */
    public function test_old_session_is_expelled_by_middleware_when_navigating(): void
    {
        $oldToken = 'sesion-vieja-dispositivo-1';
        $newToken = 'sesion-nueva-dispositivo-2';

        $this->user->forceFill(['current_session_id' => $newToken])->saveQuietly();

        $response = $this->actingAs($this->user)
            ->withSession(['auth_session_token' => $oldToken])
            ->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('warning', 'Tu sesión ha caducado o se inició sesión desde otro dispositivo.');
        $response->assertSessionHasErrors([
            'email' => 'Tu sesión ha caducado o se inició sesión desde otro dispositivo.',
        ]);
        $this->assertGuest();
    }

    /**
     * Test 5: Solicitud JSON en sesión vieja responde 401 con mensaje descriptivo.
     */
    public function test_concurrent_login_invalidates_json_request_with_401(): void
    {
        $oldToken = 'sesion-vieja-dispositivo-1';
        $newToken = 'sesion-nueva-dispositivo-2';

        $this->user->forceFill(['current_session_id' => $newToken])->saveQuietly();

        $response = $this->actingAs($this->user)
            ->withSession(['auth_session_token' => $oldToken])
            ->getJson(route('dashboard'));

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Tu sesión ha caducado o se inició sesión desde otro dispositivo.',
        ]);
        $this->assertGuest();
    }

    /**
     * Test 6: El logout voluntario limpia el campo current_session_id en BD dejándolo en null.
     */
    public function test_logout_clears_active_session_leaving_it_null(): void
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
    }

    /**
     * Test 7: Petición GET a /logout cierra sesión voluntariamente y limpia current_session_id.
     */
    public function test_get_request_to_logout_clears_session_and_redirects_to_login(): void
    {
        $this->user->forceFill(['current_session_id' => 'sesion-para-cerrar'])->saveQuietly();

        $response = $this->actingAs($this->user)->get(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull($this->user->fresh()->current_session_id);
    }

    /**
     * Test 8: POST a /logout sin token CSRF no arroja 419 y redirige directamente a /login.
     */
    public function test_logout_without_csrf_token_does_not_throw_419_and_redirects_to_login(): void
    {
        $response = $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
            ->actingAs($this->user)
            ->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
