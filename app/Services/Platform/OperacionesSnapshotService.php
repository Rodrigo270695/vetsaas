<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\PlatformSetting;
use App\Models\PlatformWhatsAppSession;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\TenantWhatsAppSession;
use App\Services\OpenWa\OpenWaClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Snapshot de salud operativa del SaaS para el panel del superadmin.
 *
 * Agrega señales de tenants, WhatsApp, colas fallidas, suscripciones
 * en grace / próximo cobro, cobros fallidos y credenciales globales.
 * No es monitoreo de infraestructura (CPU/disco); es el radar de negocio.
 */
final class OperacionesSnapshotService
{
    public function __construct(
        private readonly OpenWaClient $openWa,
        private readonly DatabaseBackupService $backups,
        private readonly PresenceSnapshotService $presence,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $setting = PlatformSetting::current();
        $now = Carbon::now();

        return [
            'health' => $this->health(),
            'credentials' => [
                'openwa' => $this->openWa->isConfigured(),
                'twilio' => (bool) $setting->twilio_configurado,
                'brevo' => (bool) $setting->brevo_configurado,
            ],
            'tenants' => $this->tenantsByEstado(),
            'whatsapp' => $this->whatsappRadar(),
            'presence' => $this->presence->build(),
            'backups' => $this->backups->status(),
            'subscriptions' => $this->subscriptionSignals($now),
            'cobros' => $this->cobrosSignals($now),
            'failed_jobs' => $this->failedJobs(),
        ];
    }

    /**
     * @return array{ok: bool, database: bool, checked_at: string, queue_default: string}
     */
    private function health(): array
    {
        $databaseOk = false;

        try {
            DB::connection()->getPdo();
            DB::select('select 1');
            $databaseOk = true;
        } catch (Throwable) {
            $databaseOk = false;
        }

        return [
            'ok' => $databaseOk,
            'database' => $databaseOk,
            'checked_at' => Carbon::now()->toIso8601String(),
            'queue_default' => (string) config('queue.default', 'sync'),
        ];
    }

    /**
     * @return array{total: int, trial: int, active: int, suspended: int, cancelled: int}
     */
    private function tenantsByEstado(): array
    {
        $byEstado = Tenant::query()
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->all();

        return [
            'total' => Tenant::query()->count(),
            'trial' => (int) ($byEstado['trial'] ?? 0),
            'active' => (int) ($byEstado['active'] ?? 0),
            'suspended' => (int) ($byEstado['suspended'] ?? 0),
            'cancelled' => (int) ($byEstado['cancelled'] ?? 0),
        ];
    }

    /**
     * @return array{
     *     openwa_configured: bool,
     *     platform: array{status: string|null, phone: string|null, last_error: string|null, last_synced_at: string|null, ready: bool},
     *     tenants_ready: int,
     *     tenants_not_ready: int,
     *     tenants_with_error: int,
     *     broken: list<array{tenant_id: string, tenant_slug: string, tenant_label: string, status: string, phone: string|null, last_error: string|null, last_synced_at: string|null}>
     * }
     */
    private function whatsappRadar(): array
    {
        $platform = PlatformWhatsAppSession::query()->latest('updated_at')->first();

        $ready = TenantWhatsAppSession::query()
            ->where('status', TenantWhatsAppSession::STATUS_READY)
            ->count();
        $totalSessions = TenantWhatsAppSession::query()->count();
        $withError = TenantWhatsAppSession::query()
            ->whereNotNull('last_error')
            ->where('last_error', '!=', '')
            ->count();

        $broken = TenantWhatsAppSession::query()
            ->with(['tenant:id,slug,nombre_comercial,razon_social'])
            ->where(function ($q): void {
                $q->where('status', '!=', TenantWhatsAppSession::STATUS_READY)
                    ->orWhere(function ($inner): void {
                        $inner->whereNotNull('last_error')
                            ->where('last_error', '!=', '');
                    });
            })
            ->orderByDesc('updated_at')
            ->limit(15)
            ->get()
            ->map(static function (TenantWhatsAppSession $session): array {
                $tenant = $session->tenant;
                $label = $tenant !== null
                    ? (trim((string) ($tenant->nombre_comercial ?: '')) ?: (string) $tenant->razon_social)
                    : '—';

                return [
                    'tenant_id' => (string) $session->tenant_id,
                    'tenant_slug' => $tenant?->slug ?? '—',
                    'tenant_label' => $label,
                    'status' => (string) $session->status,
                    'phone' => $session->phone,
                    'last_error' => $session->last_error,
                    'last_synced_at' => $session->last_synced_at?->toIso8601String(),
                ];
            })
            ->all();

        return [
            'openwa_configured' => $this->openWa->isConfigured(),
            'platform' => [
                'status' => $platform?->status,
                'phone' => $platform?->phone,
                'last_error' => $platform?->last_error,
                'last_synced_at' => $platform?->last_synced_at?->toIso8601String(),
                'ready' => $platform?->isReady() ?? false,
            ],
            'tenants_ready' => $ready,
            'tenants_not_ready' => max(0, $totalSessions - $ready),
            'tenants_with_error' => $withError,
            'broken' => $broken,
        ];
    }

    /**
     * @return array{grace: int, suspended: int, proximo_cobro_7d: int}
     */
    private function subscriptionSignals(Carbon $now): array
    {
        return [
            'grace' => Subscription::query()->where('estado', 'grace')->count(),
            'suspended' => Subscription::query()->where('estado', 'suspended')->count(),
            'proximo_cobro_7d' => Subscription::query()
                ->billable()
                ->whereNotNull('proximo_cobro_at')
                ->whereBetween('proximo_cobro_at', [$now->copy()->startOfDay(), $now->copy()->addDays(7)->endOfDay()])
                ->count(),
        ];
    }

    /**
     * @return array{fallidos_7d: int, pendientes: int}
     */
    private function cobrosSignals(Carbon $now): array
    {
        return [
            'fallidos_7d' => SubscriptionPayment::query()
                ->where('estado', 'fallido')
                ->where('created_at', '>=', $now->copy()->subDays(7))
                ->count(),
            'pendientes' => SubscriptionPayment::query()
                ->where('estado', 'pendiente')
                ->count(),
        ];
    }

    /**
     * @return array{
     *     total: int,
     *     recent: list<array{id: int, uuid: string, connection: string, queue: string, failed_at: string|null, exception_preview: string, job_name: string|null}>
     * }
     */
    private function failedJobs(): array
    {
        $total = (int) DB::table('failed_jobs')->count();

        $recent = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(20)
            ->get(['id', 'uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'])
            ->map(static function (object $row): array {
                $exception = (string) $row->exception;
                $preview = mb_substr(preg_replace('/\s+/', ' ', $exception) ?? $exception, 0, 220);

                return [
                    'id' => (int) $row->id,
                    'uuid' => (string) $row->uuid,
                    'connection' => (string) $row->connection,
                    'queue' => (string) $row->queue,
                    'failed_at' => $row->failed_at
                        ? Carbon::parse($row->failed_at)->toIso8601String()
                        : null,
                    'exception_preview' => $preview,
                    'job_name' => self::jobNameFromPayload((string) $row->payload),
                ];
            })
            ->all();

        return [
            'total' => $total,
            'recent' => $recent,
        ];
    }

    private static function jobNameFromPayload(string $payload): ?string
    {
        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        $displayName = $decoded['displayName'] ?? null;
        if (is_string($displayName) && $displayName !== '') {
            return class_basename($displayName);
        }

        $commandName = data_get($decoded, 'data.commandName');
        if (is_string($commandName) && $commandName !== '') {
            return class_basename($commandName);
        }

        return null;
    }
}
