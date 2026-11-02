<?php

namespace Tests\Feature\AI;

use App\AI\Contracts\AiProviderInterface;
use App\AI\DTOs\AiQueryIntent;
use App\AI\Enums\AiIntent;
use App\AI\Exceptions\AiProviderException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckAiHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_check_command_warns_when_module_is_disabled(): void
    {
        config(['ai.enabled' => false]);

        Http::fake([
            'https://generativelanguage.googleapis.com*' => Http::response([], 200),
        ]);

        $this->artisan('ai:check', ['--skip-api' => true])
            ->expectsOutputToContain('Módulo deshabilitado: AI_MODULE_ENABLED=false')
            ->assertSuccessful();
    }

    public function test_ai_check_command_fails_when_api_key_is_missing(): void
    {
        config([
            'ai.enabled' => true,
            'ai.providers.gemini.api_key' => '',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com*' => Http::response([], 200),
        ]);

        $this->artisan('ai:check', ['--skip-api' => true])
            ->expectsOutputToContain('API Key: NO CONFIGURADA')
            ->assertFailed();
    }

    public function test_ai_check_command_fails_when_api_key_is_placeholder(): void
    {
        config([
            'ai.enabled' => true,
            'ai.providers.gemini.api_key' => 'tu_api_key_de_gemini_aqui',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com*' => Http::response([], 200),
        ]);

        $this->artisan('ai:check', ['--skip-api' => true])
            ->expectsOutputToContain('API Key: VALOR POR DEFECTO / PLACEHOLDER')
            ->assertFailed();
    }

    public function test_ai_check_command_succeeds_with_valid_config_and_skip_api(): void
    {
        config([
            'ai.enabled' => true,
            'ai.providers.gemini.api_key' => 'VALID_TEST_API_KEY_1234567890',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com*' => Http::response([], 200),
        ]);

        $this->artisan('ai:check', ['--skip-api' => true])
            ->expectsOutputToContain('Módulo habilitado: AI_MODULE_ENABLED=true')
            ->expectsOutputToContain('API Key: Configurada')
            ->expectsOutputToContain('¡ÉXITO! El Módulo de IA está saludable')
            ->assertSuccessful();
    }

    public function test_ai_check_command_executes_api_call_and_displays_intent(): void
    {
        config([
            'ai.enabled' => true,
            'ai.providers.gemini.api_key' => 'VALID_TEST_API_KEY_1234567890',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'functionCall' => [
                                        'name' => AiIntent::COUNT_CUSTOMERS,
                                        'args' => [],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
            'https://generativelanguage.googleapis.com*' => Http::response([], 200),
        ]);

        $this->artisan('ai:check', ['--query' => '¿Cuántos clientes tenemos?'])
            ->expectsOutputToContain('Respuesta de Gemini recibida')
            ->expectsOutputToContain(AiIntent::COUNT_CUSTOMERS)
            ->expectsOutputToContain('¡ÉXITO! El Módulo de IA está saludable')
            ->assertSuccessful();
    }

    public function test_ai_check_command_handles_provider_error_gracefully(): void
    {
        config([
            'ai.enabled' => true,
            'ai.providers.gemini.api_key' => 'INVALID_TEST_KEY_FOR_ERROR',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models*' => Http::response([
                'error' => [
                    'code' => 400,
                    'message' => 'API_KEY_INVALID: The provided API key is expired or invalid.',
                    'status' => 'INVALID_ARGUMENT',
                ],
            ], 400),
            'https://generativelanguage.googleapis.com*' => Http::response([], 200),
        ]);

        $this->artisan('ai:check', ['--query' => '¿Ventas de hoy?'])
            ->expectsOutputToContain('Llamada a Gemini falló')
            ->expectsOutputToContain('Causa probable: La GEMINI_API_KEY no es válida')
            ->assertFailed();
    }
}
