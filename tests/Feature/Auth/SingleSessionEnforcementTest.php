<?php

namespace Tests\Feature\Auth;

use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
     * Test 2: Un inicio de sesión simultáneo en un segundo dispositivo invalida la sesión del primer dispositivo.
     */
    public function test_concurrent_login_from_second_device_invalidates_first_session(): void
    {
        // 1. Dispositivo A inicia sesión
        $this->post('/login', [
            'email' => 'seguridad@puntostock.com',
            'password' => 'Password123!',
        ]);
        $deviceAToken = session('auth_session_token');
        $this->assertNotNull($deviceAToken);

        // 2. Dispositivo B inicia sesión con las mismas credenciales (nuevo token)
        $deviceBToken = (string) Str::uuid();
        $this->user->forceFill(['current_session_id' => $deviceBToken])->saveQuietly();

        // 3. Dispositivo A intenta realizar una petición con su token anterior (mismatch)
        $response = $this->actingAs($this->user)
            ->withSession(['auth_session_token' => $deviceAToken])
            ->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email' => 'Tu sesión se cerró automáticamente porque tu cuenta inició sesión en otro dispositivo.',
        ]);
        $this->assertGuest();
    }

    /**
     * Test 3: Solicitud JSON en sesión invalidada responde 401 con mensaje descriptivo.
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
     * Test 4: El logout limpia el campo current_session_id.
     */
    public function test_logout_clears_current_session_id(): void
    {
        // Iniciar sesión
        $this->post('/login', [
            'email' => 'seguridad@puntostock.com',
            'password' => 'Password123!',
        ]);
        $this->assertNotNull($this->user->fresh()->current_session_id);

        // Cerrar sesión
        $response = $this->post(route('logout'));
        $response->assertRedirect(route('login'));
        $this->assertGuest();

        $this->assertNull($this->user->fresh()->current_session_id);
    }
}

