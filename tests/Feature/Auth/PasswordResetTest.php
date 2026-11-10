<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
        $response->assertSee('Recuperar acceso');
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        $user = User::factory()->create([
            'email' => 'andres@example.com',
        ]);

        $response = $this->post('/forgot-password', [
            'email' => 'andres@example.com',
        ]);

        $response->assertSessionHas('status');
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create([
            'email' => 'andres@example.com',
        ]);

        $token = Password::createToken($user);

        $response = $this->get('/reset-password/' . $token . '?email=andres@example.com');

        $response->assertStatus(200);
        $response->assertSee('Crea una nueva contraseña');
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'andres@example.com',
            'password' => bcrypt('oldpassword123'),
        ]);

        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'andres@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_password_reset_revokes_current_session_id_and_purges_sessions_table(): void
    {
        $user = User::factory()->create([
            'email' => 'active-session@example.com',
            'password' => bcrypt('oldpassword123'),
            'current_session_id' => 'stale-session-123',
        ]);

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->insert([
                'id' => 'stale-session-123',
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => serialize(['test' => 1]),
                'last_activity' => now()->timestamp,
            ]);
            $this->assertTrue(DB::table('sessions')->where('id', 'stale-session-123')->exists());
        }

        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'active-session@example.com',
            'password' => 'brandnewpassword123',
            'password_confirmation' => 'brandnewpassword123',
        ]);

        $response->assertRedirect(route('login'));
        $freshUser = $user->fresh();
        $this->assertTrue(Hash::check('brandnewpassword123', $freshUser->password));
        $this->assertNull($freshUser->current_session_id);

        if (Schema::hasTable('sessions')) {
            $this->assertFalse(DB::table('sessions')->where('id', 'stale-session-123')->exists());
        }
    }

    public function test_password_reset_email_renders_spanish_corporate_template(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'name' => 'Carlos Mendoza',
            'email' => 'carlos.mendoza@example.com',
        ]);

        $response = $this->post('/forgot-password', [
            'email' => 'carlos.mendoza@example.com',
        ]);

        $response->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
            $mailMessage = $notification->toMail($user);

            // Asunto oficial en español
            $this->assertEquals('Restablecimiento de Contraseña — PuntoStock POS', $mailMessage->subject);

            // Renderizado del HTML
            $rendered = (string) $mailMessage->render();

            // Textos requeridos en español
            $this->assertStringContainsString('Recuperación de Acceso y Seguridad de Cuenta', $rendered);
            $this->assertStringContainsString('¡Hola, Carlos Mendoza!', $rendered);
            $this->assertStringContainsString('Restablecer mi Contraseña', $rendered);
            $this->assertStringContainsString('carlos.mendoza@example.com', $rendered);
            $this->assertStringContainsString($notification->token, $rendered);
            $this->assertStringContainsString('Aviso de Seguridad:', $rendered);
            $this->assertStringContainsString('minutos', $rendered);
            $this->assertStringContainsString('lang="es"', $rendered);

            // Ningún texto en inglés por defecto de Laravel
            $this->assertStringNotContainsString('Reset Password Notification', $rendered);
            $this->assertStringNotContainsString('You are receiving this email', $rendered);
            $this->assertStringNotContainsString('If you did not request a password reset', $rendered);

            return true;
        });
    }
}
