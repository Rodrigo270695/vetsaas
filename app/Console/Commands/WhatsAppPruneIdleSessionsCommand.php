<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TenantWhatsAppSession;
use App\Services\OpenWa\OpenWaClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Apaga y borra en OpenWA las sesiones que no corresponden a un plan de pago.
 * No lista GET /api/sessions (esa ruta congela el gateway): usa los IDs locales.
 */
class WhatsAppPruneIdleSessionsCommand extends Command
{
    protected $signature = 'vetsaas:whatsapp-prune-idle-sessions
                            {--dry-run : Solo listar, no stop/delete}
                            {--execute : Aplicar stop + delete en OpenWA}';

    protected $description = 'Quita de OpenWA sesiones free/demo/sin plan de pago (deja las clínicas de pago)';

    public function handle(OpenWaClient $client): int
    {
        $execute = (bool) $this->option('execute');
        $dry = (bool) $this->option('dry-run') || ! $execute;

        if ($execute && $dry) {
            $dry = false;
        }

        if (! $client->isConfigured()) {
            $this->warn('OpenWA deshabilitado o sin OPENWA_API_KEY.');

            return self::SUCCESS;
        }

        $rows = TenantWhatsAppSession::query()
            ->with(['tenant.subscriptions.plan'])
            ->orderBy('openwa_session_name')
            ->get();

        $keep = 0;
        $prune = 0;
        $failed = 0;

        foreach ($rows as $session) {
            $tenant = $session->tenant;
            $keepThis = $tenant !== null && $tenant->qualifiesForPaidWhatsApp();

            if ($keepThis) {
                $keep++;
                $this->line(sprintf(
                    '  KEEP  %s | %s | %s',
                    $session->openwa_session_name,
                    $session->status,
                    $session->phone ?: 'sin teléfono',
                ));

                continue;
            }

            $prune++;
            $this->warn(sprintf(
                '  PRUNE %s | %s | tenant=%s',
                $session->openwa_session_name,
                $session->status,
                $tenant?->slug ?? 'huérfana',
            ));

            if ($dry) {
                continue;
            }

            $sessionId = trim((string) $session->openwa_session_id);
            try {
                if ($sessionId !== '') {
                    try {
                        $client->stopSession($sessionId);
                    } catch (Throwable) {
                        // Puede estar ya caída.
                    }
                    usleep(250_000);
                    try {
                        $client->deleteSession($sessionId);
                    } catch (Throwable $e) {
                        $this->comment('    delete: '.$e->getMessage());
                    }
                    usleep(400_000);
                }

                $session->forceFill([
                    'status' => 'disconnected',
                    'auto_reconnect' => false,
                    'last_synced_at' => now(),
                    'last_error' => 'Poda: plan free o sin pago; sesión retirada de OpenWA.',
                ])->save();
            } catch (Throwable $e) {
                $failed++;
                $session->forceFill([
                    'auto_reconnect' => false,
                    'last_error' => $e->getMessage(),
                    'last_synced_at' => now(),
                ])->save();
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Resumen: keep=%d prune=%d fallos=%d %s',
            $keep,
            $prune,
            $failed,
            $dry ? '(dry-run: no se tocó OpenWA)' : '(aplicado)',
        ));

        if ($dry) {
            $this->comment('Para aplicar: php artisan vetsaas:whatsapp-prune-idle-sessions --execute');
        } else {
            $this->comment('Las ~12 de pago desfasadas se reconectan solas (máx. 2 por corrida del cron). No abras /sessions.');
        }

        return self::SUCCESS;
    }
}
