<?php

namespace App\Console\Commands;

use App\Models\PlatformWhatsAppSession;
use App\Models\TenantWhatsAppSession;
use App\Services\OpenWa\OpenWaClient;
use Illuminate\Console\Command;

/**
 * Reactiva sesiones OpenWA que ya tenían número (auth en disco) tras un stop
 * por RAM: auto_reconnect=true + start. No pide QR.
 */
class WhatsAppWakeAuthenticatedSessionsCommand extends Command
{
    protected $signature = 'vetsaas:whatsapp-wake-authenticated
                            {--dry-run : Solo listar, no start}
                            {--exclude=* : Nombres a excluir (repetible), ej. demo}';

    protected $description = 'Despierta sesiones WhatsApp con teléfono (auth guardada) en todos los tenants + plataforma';

    public function handle(OpenWaClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->warn('OpenWA deshabilitado o sin OPENWA_API_KEY.');

            return self::SUCCESS;
        }

        $exclude = collect($this->option('exclude') ?: ['demo', 'openvet', 'clinica-demo'])
            ->filter(fn ($v) => is_string($v) && $v !== '' && $v !== '*')
            ->values()
            ->all();

        if ($exclude === []) {
            $exclude = ['demo', 'openvet', 'clinica-demo'];
        }

        $dry = (bool) $this->option('dry-run');
        $started = 0;
        $skipped = 0;

        $tenants = TenantWhatsAppSession::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->whereNotNull('openwa_session_id')
            ->whereNotIn('openwa_session_name', $exclude)
            ->orderBy('openwa_session_name')
            ->get();

        foreach ($tenants as $session) {
            $this->line(sprintf(
                '  [tenant] %s | %s | %s',
                $session->openwa_session_name,
                $session->status,
                $session->phone,
            ));

            if ($dry) {
                $skipped++;

                continue;
            }

            $session->forceFill(['auto_reconnect' => true])->save();

            if ($session->isReady()) {
                $skipped++;

                continue;
            }

            try {
                $client->tryStartIfDown(
                    (string) $session->openwa_session_id,
                    (string) $session->status === 'ready' ? 'disconnected' : (string) $session->status,
                );
                $remote = $client->getSession((string) $session->openwa_session_id);
                $session->forceFill([
                    'status' => (string) ($remote['status'] ?? $session->status),
                    'phone' => isset($remote['phone']) ? (string) $remote['phone'] : $session->phone,
                    'push_name' => isset($remote['pushName']) ? (string) $remote['pushName'] : $session->push_name,
                    'last_synced_at' => now(),
                    'last_error' => null,
                ])->save();
                $started++;
                $this->info('    → start OK ('.$session->fresh()?->status.')');
            } catch (\Throwable $e) {
                $session->forceFill([
                    'last_error' => $e->getMessage(),
                    'last_synced_at' => now(),
                ])->save();
                $this->error('    → '.$e->getMessage());
            }
        }

        $platforms = PlatformWhatsAppSession::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->whereNotNull('openwa_session_id')
            ->get();

        // También plataforma sin phone en BD pero conocida (orvae) si está disconnected.
        $platformExtra = PlatformWhatsAppSession::query()
            ->whereIn('openwa_session_name', [
                (string) config('openwa.platform_session_name', 'vetsaas-platform'),
                'orvae-platform',
                'vetsaas-platform',
            ])
            ->get();

        $platforms = $platforms->merge($platformExtra)->unique('id');

        foreach ($platforms as $session) {
            $this->line(sprintf(
                '  [plataforma] %s | %s | %s',
                $session->openwa_session_name,
                $session->status,
                $session->phone ?? '—',
            ));

            if ($dry) {
                $skipped++;

                continue;
            }

            $session->forceFill(['auto_reconnect' => true])->save();

            if ($session->isReady()) {
                $skipped++;

                continue;
            }

            try {
                $client->tryStartIfDown(
                    (string) $session->openwa_session_id,
                    (string) $session->status === 'ready' ? 'disconnected' : (string) $session->status,
                );
                $remote = $client->getSession((string) $session->openwa_session_id);
                $session->forceFill([
                    'status' => (string) ($remote['status'] ?? $session->status),
                    'phone' => isset($remote['phone']) ? (string) $remote['phone'] : $session->phone,
                    'push_name' => isset($remote['pushName']) ? (string) $remote['pushName'] : $session->push_name,
                    'last_synced_at' => now(),
                    'last_error' => null,
                ])->save();
                $started++;
                $this->info('    → start OK ('.$session->fresh()?->status.')');
            } catch (\Throwable $e) {
                $session->forceFill([
                    'last_error' => $e->getMessage(),
                    'last_synced_at' => now(),
                ])->save();
                $this->error('    → '.$e->getMessage());
            }
        }

        $this->info($dry
            ? "Dry-run: {$tenants->count()} tenants + {$platforms->count()} plataforma."
            : "Start lanzados: {$started}. Ya ready / omitidos: {$skipped}."
        );
        $this->comment('Espera 30–60 s y corre: php artisan vetsaas:whatsapp-sync-sessions');

        return self::SUCCESS;
    }
}
