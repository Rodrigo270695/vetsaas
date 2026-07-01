<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TenantWhatsAppSession;
use App\Services\OpenWa\TenantWhatsAppWebhookRegistrar;
use Illuminate\Console\Command;

class ClinicBotRegisterWebhooksCommand extends Command
{
    protected $signature = 'vetsaas:clinic-bot-register-webhooks
                            {--slug= : Solo este tenant}
                            {--dry-run : Listar sin registrar}';

    protected $description = 'Registra el webhook del asistente IA en sesiones OpenWA conectadas.';

    public function handle(TenantWhatsAppWebhookRegistrar $registrar): int
    {
        $slug = $this->option('slug') ? (string) $this->option('slug') : null;

        $query = TenantWhatsAppSession::query()
            ->with('tenant')
            ->where('status', TenantWhatsAppSession::STATUS_READY);

        if ($slug !== null && $slug !== '') {
            $query->whereHas('tenant', fn ($q) => $q->where('slug', $slug));
        }

        $sessions = $query->get();

        if ($sessions->isEmpty()) {
            $this->warn('No hay sesiones WhatsApp conectadas (ready).');

            return self::SUCCESS;
        }

        foreach ($sessions as $session) {
            $tenantSlug = $session->tenant?->slug ?? '?';
            $this->line("· {$tenantSlug} → {$session->openwa_session_id}");

            if ($this->option('dry-run')) {
                continue;
            }

            $registrar->ensureForSession($session);
        }

        if ($this->option('dry-run')) {
            $this->info('Dry-run: no se registraron webhooks.');
        } else {
            $this->info('Webhooks registrados (si OpenWA lo soporta en la API).');
        }

        return self::SUCCESS;
    }
}
