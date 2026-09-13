<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ClinicBotConversation;
use App\Models\ClinicSetting;
use App\Models\Tenant;
use App\Models\TenantWhatsAppSession;
use App\Services\OpenWa\OpenWaClient;
use App\Support\Subscriptions\SubscriptionBotIaAddon;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * Diagnóstico del asistente IA de una clínica (webhook / OpenWA / último chat).
 */
final class ClinicBotDiagnoseCommand extends Command
{
    protected $signature = 'vetsaas:clinic-bot-diagnose {slug : Slug del tenant}';

    protected $description = 'Muestra por qué el asistente IA de un tenant puede no responder (sin tocar SalesBot).';

    public function handle(OpenWaClient $client, TenantManager $tenants): int
    {
        $slug = strtolower(trim((string) $this->argument('slug')));
        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant === null) {
            $this->error("No existe tenant con slug: {$slug}");

            return self::FAILURE;
        }

        $this->info($tenant->nombre_comercial.' ('.$slug.')');

        $secretSet = trim((string) config('bot-ia.webhook_secret', '')) !== '';
        $webhookUrl = trim((string) config('bot-ia.webhook_url', ''));
        if ($webhookUrl === '') {
            $webhookUrl = rtrim((string) config('app.url'), '/').'/api/webhooks/clinic-bot';
        }

        $this->line('  bot-ia.enabled: '.(config('bot-ia.enabled') ? 'sí' : 'no'));
        $this->line('  BOT_IA_WEBHOOK_SECRET: '.($secretSet ? 'sí' : 'NO (webhook responde 503)'));
        $this->line('  webhook_url: '.$webhookUrl);
        $this->line('  OPENAI_API_KEY: '.(trim((string) config('bot-ia.openai_api_key', '')) !== '' ? 'sí' : 'NO'));
        $this->line('  queue: '.(string) config('queue.default'));

        $subscription = $tenant->subscriptions()->orderByDesc('created_at')->first();
        $this->line('  add-on bot_ia: '.(SubscriptionBotIaAddon::isActive($subscription) ? 'activo' : 'INACTIVO'));

        $tenants->runForSlug($slug, function (): void {
            $settings = ClinicSetting::current();
            $this->line('  respuestas_automaticas: '.($settings->isBotIaResponding() ? 'sí' : 'NO'));

            $last = ClinicBotConversation::query()
                ->orderByDesc('last_message_at')
                ->orderByDesc('updated_at')
                ->first();

            if ($last === null) {
                $this->line('  ultimo_chat: ninguno');
            } else {
                $when = $last->last_message_at?->toDateTimeString() ?? $last->updated_at?->toDateTimeString();
                $this->line('  ultimo_chat: '.$when.'  ('.$last->phone.')');
            }
        });

        $session = TenantWhatsAppSession::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($session === null) {
            $this->warn('  sesión local: no hay fila en tenant_whatsapp_sessions');

            return self::SUCCESS;
        }

        $this->line(sprintf(
            '  sesión local: status=%s phone=%s id=%s synced=%s',
            $session->status,
            $session->phone ?? '—',
            $session->openwa_session_id,
            $session->last_synced_at?->toDateTimeString() ?? '—',
        ));

        if (! $client->isConfigured()) {
            $this->warn('  OpenWA no configurado en este servidor.');

            return self::SUCCESS;
        }

        $this->line('  OpenWA ping: '.($client->ping() ? 'ok' : 'NO RESPONDE (congelado/504)'));

        $sessionId = trim((string) $session->openwa_session_id);
        if ($sessionId === '') {
            $this->warn('  sin openwa_session_id');

            return self::SUCCESS;
        }

        $remote = $client->tryGetSession($sessionId);
        if ($remote === null) {
            $this->warn('  OpenWA remoto: no se pudo leer la sesión (timeout, 404 o caída).');
            $this->line('  Sin motor vivo, WhatsApp en el teléfono SÍ recibe, Laravel NO. El bot no puede contestar.');

            return self::SUCCESS;
        }

        $this->line(sprintf(
            '  OpenWA remoto: status=%s phone=%s',
            (string) ($remote['status'] ?? '?'),
            (string) ($remote['phone'] ?? '—'),
        ));

        try {
            $hooks = $client->listWebhooks($sessionId);
        } catch (Throwable $e) {
            $this->warn('  webhooks: error '.$e->getMessage());

            return self::SUCCESS;
        }

        $clinic = 0;
        foreach ($hooks as $hook) {
            if (! is_array($hook)) {
                continue;
            }
            $url = (string) ($hook['url'] ?? '');
            $active = filter_var($hook['active'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $events = $hook['events'] ?? [];
            $eventsLabel = is_array($events) ? implode(',', $events) : (string) $events;
            $mark = str_contains($url, '/api/webhooks/clinic-bot') ? ' [clinic-bot]' : '';
            if ($mark !== '') {
                $clinic++;
            }
            $this->line('  webhook: '.($active ? 'on' : 'off').' '.$url.'  events='.$eventsLabel.$mark);
        }

        if ($clinic === 0) {
            $this->warn('  No hay webhook clinic-bot. Corré:');
            $this->line('  php artisan vetsaas:clinic-bot-register-webhooks --slug='.$slug);
        }

        return self::SUCCESS;
    }
}
