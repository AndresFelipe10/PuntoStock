<?php

namespace App\Console\Commands;

use App\Mail\DailyBusinessSummaryMail;
use App\Models\Business;
use App\Models\Role;
use App\Services\DailySummaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendDailyBusinessSummaryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:send-daily-summary 
                            {--business_id= : ID específico de un negocio para procesar individualmente}
                            {--email= : Correo destinatario específico (útil para pruebas y depuración)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía el resumen operativo y financiero diario por correo electrónico a los administradores de negocios activos.';

    /**
     * Execute the console command.
     */
    public function handle(DailySummaryService $summaryService): int
    {
        $this->info('===============================================================');
        $this->info(' PuntoStock POS - Envío Automatizado de Resumen Diario');
        $this->info('===============================================================');

        $businessId = $this->option('business_id');
        $customEmail = $this->option('email');

        if ($businessId) {
            $businesses = Business::where('id', $businessId)->get();
            if ($businesses->isEmpty()) {
                $this->error("No se encontró el negocio con ID {$businessId}.");
                return Command::FAILURE;
            }
        } else {
            $businesses = Business::where('is_active', true)
                ->where('nightly_report_enabled', true)
                ->get();
        }

        $total = $businesses->count();
        $sentCount = 0;
        $failedCount = 0;
        $skippedCount = 0;

        $adminRole = Role::where('slug', Role::ROLE_ADMIN)->first();

        foreach ($businesses as $business) {
            $adminUser = null;
            if ($adminRole) {
                $adminUser = $business->users()
                    ->wherePivot('is_active', true)
                    ->wherePivot('role_id', $adminRole->id)
                    ->first();
            }

            $recipientEmail = $customEmail ?: ($adminUser?->email ?? $business->email);

            if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                $this->warn("[-] Omitido negocio '{$business->name}' (ID {$business->id}): No cuenta con un correo electrónico válido configurado.");
                $skippedCount++;
                continue;
            }

            try {
                $summary = $summaryService->generateSummary($business);

                Mail::to($recipientEmail)->send(new DailyBusinessSummaryMail($summary, $business));

                $this->info("[✓] Resumen diario enviado exitosamente a {$recipientEmail} para '{$business->name}' (ID {$business->id}).");
                Log::info("Resumen diario enviado exitosamente", [
                    'business_id' => $business->id,
                    'recipient' => $recipientEmail,
                    'revenue_today' => $summary['revenue_today'],
                    'sales_count' => $summary['sales_count'],
                ]);

                $sentCount++;
            } catch (Throwable $e) {
                $failedCount++;
                $this->error("[!] Fallo al enviar resumen a {$recipientEmail} para negocio '{$business->name}': " . $e->getMessage());

                Log::error("Fallo al enviar resumen diario a negocio", [
                    'business_id' => $business->id,
                    'recipient' => $recipientEmail,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->line("Proceso finalizado. Total negocios: {$total} | Enviados: {$sentCount} | Fallidos: {$failedCount} | Omitidos: {$skippedCount}");

        return Command::SUCCESS;
    }
}
