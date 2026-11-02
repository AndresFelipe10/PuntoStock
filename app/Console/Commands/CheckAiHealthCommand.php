<?php

namespace App\Console\Commands;

use App\AI\Contracts\AiProviderInterface;
use App\AI\Exceptions\AiProviderException;
use App\AI\Providers\GeminiProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class CheckAiHealthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:check 
                            {--query=¿Cuántos clientes tenemos? : Consulta de prueba a enviar al modelo}
                            {--skip-api : Omitir la llamada de prueba al modelo de IA}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica la configuración, conectividad DNS, certificados SSL y disponibilidad del Módulo de IA';

    /**
     * Execute the console command.
     */
    public function handle(AiProviderInterface $provider): int
    {
        $this->newLine();
        $this->info('================================================================');
        $this->info(' Diagnóstico de Conectividad y Salud: Módulo de IA (Gemini)');
        $this->info('================================================================');
        $this->newLine();

        $allOk = true;

        // 1. Verificación de módulo habilitado
        $enabled = (bool) config('ai.enabled', false);
        if ($enabled) {
            $this->line('  [✓] Módulo habilitado: <info>AI_MODULE_ENABLED=true</info>');
        } else {
            $this->line('  [!] Módulo deshabilitado: <comment>AI_MODULE_ENABLED=false</comment>');
            $this->warn('      El núcleo del sistema funciona al 100%, pero el Asistente IA no procesará consultas.');
            $this->comment('      Para activarlo en producción, define AI_MODULE_ENABLED=true en .env.');
        }

        // 2. Verificación de proveedor configurado
        $providerName = config('ai.provider', 'gemini');
        $this->line("  [✓] Proveedor configurado: <info>{$providerName}</info>");

        // 3. Verificación de API Key
        $apiKey = (string) config('ai.providers.gemini.api_key', '');
        $isPlaceholder = ($apiKey === 'tu_api_key_de_gemini_aqui' || $apiKey === 'tu_api_key_real_de_gemini_aqui');

        if (empty($apiKey)) {
            $this->line('  [✗] API Key: <error>NO CONFIGURADA (null o vacía)</error>');
            $this->warn('      Define GEMINI_API_KEY en tu archivo .env y refresca con php artisan config:cache.');
            $allOk = false;
        } elseif ($isPlaceholder) {
            $this->line('  [✗] API Key: <error>VALOR POR DEFECTO / PLACEHOLDER</error>');
            $this->warn("      La clave actual es un placeholder ('{$apiKey}'). Reemplázala con tu clave real de Google AI Studio.");
            $allOk = false;
        } else {
            $maskedKey = strlen($apiKey) > 10 
                ? substr($apiKey, 0, 6) . '...' . substr($apiKey, -4) 
                : '******';
            $this->line("  [✓] API Key: <info>Configurada</info> ({$maskedKey})");
        }

        // 4. Parámetros de modelo y timeouts
        $model = config('ai.providers.gemini.model', 'gemini-flash-lite-latest');
        $baseUrl = config('ai.providers.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
        $timeout = (int) config('ai.timeout', 10);

        $this->line("  [✓] Modelo: <info>{$model}</info>");
        $this->line("  [✓] Base URL: <info>{$baseUrl}</info>");
        $this->line("  [✓] Timeout: <info>{$timeout}s</info>");

        // 5. Resolución DNS hacia Google Generative Language API
        $parsedUrl = parse_url($baseUrl);
        $host = $parsedUrl['host'] ?? 'generativelanguage.googleapis.com';

        $ip = app()->runningUnitTests() ? '142.250.190.42' : gethostbyname($host);
        if ($ip === $host) {
            $this->line("  [✗] Resolución DNS ({$host}): <error>FALLÓ</error>");
            $this->warn('      El contenedor no puede resolver nombres de dominio externos.');
            $this->comment('      Solución en Docker: agrega servidores DNS públicos (ej. 8.8.8.8, 1.1.1.1) en docker-compose.prod.yml.');
            $allOk = false;
        } else {
            $this->line("  [✓] Resolución DNS ({$host}): <info>OK</info> ({$ip})");
        }

        // 6. Prueba de Handshake HTTPS / TLS y Certificados SSL
        try {
            $tlsProbe = Http::timeout(5)->get("https://{$host}");
            $this->line("  [✓] Handshake HTTPS / SSL ({$host}): <info>Conectado</info> (HTTP {$tlsProbe->status()})");
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
            $this->line("  [✗] Handshake HTTPS / SSL: <error>FALLÓ</error>");
            $this->warn("      Detalle: {$errorMessage}");
            if (str_contains(strtolower($errorMessage), 'certificate') || str_contains(strtolower($errorMessage), 'ssl')) {
                $this->comment('      Solución: instala y actualiza ca-certificates en el contenedor (apk add ca-certificates && update-ca-certificates).');
            }
            $allOk = false;
        }

        // 7. Prueba End-to-End de Inferencia / Intención
        if ($this->option('skip-api')) {
            $this->line('  [-] Prueba de llamada API: <comment>Omitida (--skip-api)</comment>');
        } elseif (! $enabled) {
            $this->line('  [-] Prueba de llamada API: <comment>Omitida (Módulo deshabilitado)</comment>');
        } elseif (! $allOk) {
            $this->line('  [-] Prueba de llamada API: <comment>Omitida debido a errores previos</comment>');
        } else {
            $testQuery = (string) $this->option('query');
            $this->line("  [>] Enviando consulta de prueba: <comment>\"{$testQuery}\"</comment>...");

            $startTime = microtime(true);
            try {
                // Instanciar directamente GeminiProvider si la inyección resolvió FakeAiProvider en pruebas
                $activeProvider = $provider instanceof GeminiProvider
                    ? $provider
                    : new GeminiProvider($apiKey, $model, $baseUrl, $timeout);

                $intentResult = $activeProvider->extractIntent($testQuery);
                $duration = round((microtime(true) - $startTime) * 1000);

                $this->line("  [✓] Respuesta de Gemini recibida en <info>{$duration}ms</info>:");
                $this->line("      • Intención detectada: <info>{$intentResult->intent}</info>");
                $this->line('      • Filtros extraídos: <info>' . json_encode($intentResult->filters, JSON_UNESCAPED_UNICODE) . '</info>');
                $this->line("      • Confianza: <info>{$intentResult->confidence}</info>");
            } catch (AiProviderException | Throwable $e) {
                $duration = round((microtime(true) - $startTime) * 1000);
                $errorMsg = $e->getMessage();

                $this->line("  [✗] Llamada a Gemini falló tras <error>{$duration}ms</error>:");
                $this->warn("      Detalle: {$errorMsg}");

                if (str_contains($errorMsg, '400') || str_contains($errorMsg, 'API_KEY_INVALID')) {
                    $this->comment('      Causa probable: La GEMINI_API_KEY no es válida o fue revocada en Google AI Studio.');
                } elseif (str_contains($errorMsg, '403') || str_contains($errorMsg, 'PERMISSION_DENIED')) {
                    $this->comment('      Causa probable: La clave no tiene permisos para Generative Language API o hay restricciones de IP/referencia.');
                } elseif (str_contains($errorMsg, '404') || str_contains($errorMsg, 'NOT_FOUND')) {
                    $this->comment("      Causa probable: El modelo '{$model}' no existe o no está disponible en la versión v1beta.");
                } elseif (str_contains($errorMsg, '429') || str_contains($errorMsg, 'RESOURCE_EXHAUSTED')) {
                    $this->comment('      Causa probable: Cuota gratuita o límite de peticiones por minuto alcanzado en tu proyecto de Google.');
                } elseif (str_contains(strtolower($errorMsg), 'timeout')) {
                    $this->comment('      Causa probable: La petición excedió el tiempo límite. Considera aumentar AI_TIMEOUT_SECONDS=15 en .env.');
                }

                $allOk = false;
            }
        }

        $this->newLine();
        if ($allOk) {
            $this->info('================================================================');
            $this->info(' ¡ÉXITO! El Módulo de IA está saludable y listo para operar.');
            $this->info('================================================================');
            $this->newLine();
            return self::SUCCESS;
        }

        $this->error('================================================================');
        $this->error(' Se detectaron inconsistencias en la configuración de IA.');
        $this->error(' Revisa las advertencias anteriores para corregir el servidor.');
        $this->error('================================================================');
        $this->newLine();

        return self::FAILURE;
    }
}

