<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\PlatformWhatsAppSession;
use App\Models\Tenant;
use App\Models\TenantWhatsAppSession;
use App\Services\OpenWa\OpenWaClient;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Radar de sesiones WhatsApp por clínica. Solo lee DB local
 * (el cron `vetsaas:whatsapp-sync-sessions` alimenta last_synced_at).
 * No llama a OpenWA al listar.
 */
final class WhatsAppHealthRadarService
{
    public const STALE_MINUTES = 15;

    /** @var list<string> */
    private const LIVING_ESTADOS = ['trial', 'active', 'grace', 'suspended'];

    /** @var list<string> */
    public const SCOPES = [
        'problemas',
        'todos',
        'listos',
        'error',
        'desconectados',
        'sin_sesion',
        'stale',
        'sin_reconnect',
    ];

    public function __construct(
        private readonly OpenWaClient $openWa,
    ) {}

    /**
     * @return array{
     *     items: LengthAwarePaginator,
     *     filters: array{search: string, scope: string, per_page: int},
     *     stats: array<string, mixed>,
     *     platform: array<string, mixed>
     * }
     */
    public function paginate(string $search, string $scope, int $perPage): array
    {
        $perPage = in_array($perPage, [10, 15, 25, 50], true) ? $perPage : 15;
        $scope = in_array($scope, self::SCOPES, true) ? $scope : 'problemas';
        $search = trim($search);
        $staleBefore = now()->subMinutes(self::STALE_MINUTES);

        $query = $this->baseQuery();
        $this->applySearch($query, $search);
        $this->applyScope($query, $scope, $staleBefore);

        $query
            ->orderByRaw($this->prioritySql())
            ->orderByDesc('tws.updated_at')
            ->orderBy('tenants.slug');

        $paginator = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Tenant $tenant): array => $this->serialize($tenant, $staleBefore));

        return [
            'items' => $paginator,
            'filters' => [
                'search' => $search,
                'scope' => $scope,
                'per_page' => $perPage,
            ],
            'stats' => $this->stats($staleBefore),
            'platform' => $this->platformPayload(),
        ];
    }

    /**
     * @return Builder<Tenant>
     */
    private function baseQuery(): Builder
    {
        return Tenant::query()
            ->select('tenants.*')
            ->leftJoin('tenant_whatsapp_sessions as tws', 'tws.tenant_id', '=', 'tenants.id')
            ->whereIn('tenants.estado', self::LIVING_ESTADOS)
            ->with(['whatsappSession']);
    }

    /**
     * @param  Builder<Tenant>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $like = '%'.$search.'%';
        $query->where(function (Builder $inner) use ($like): void {
            $inner->where('tenants.slug', 'ilike', $like)
                ->orWhere('tenants.nombre_comercial', 'ilike', $like)
                ->orWhere('tenants.razon_social', 'ilike', $like)
                ->orWhere('tws.phone', 'ilike', $like)
                ->orWhere('tws.openwa_session_name', 'ilike', $like);
        });
    }

    /**
     * @param  Builder<Tenant>  $query
     */
    private function applyScope(Builder $query, string $scope, DateTimeInterface $staleBefore): void
    {
        match ($scope) {
            'listos' => $query->where('tws.status', TenantWhatsAppSession::STATUS_READY)
                ->where(function (Builder $q): void {
                    $q->whereNull('tws.last_error')->orWhere('tws.last_error', '');
                }),
            'error' => $query->whereNotNull('tws.last_error')->where('tws.last_error', '!=', ''),
            'desconectados' => $query->whereIn('tws.status', ['disconnected', 'failed']),
            'sin_sesion' => $query->whereNull('tws.id'),
            'stale' => $query->whereNotNull('tws.id')
                ->where(function (Builder $q) use ($staleBefore): void {
                    $q->whereNull('tws.last_synced_at')
                        ->orWhere('tws.last_synced_at', '<', $staleBefore);
                }),
            'sin_reconnect' => $query->where('tws.auto_reconnect', false),
            'problemas' => $query->where(function (Builder $q) use ($staleBefore): void {
                $q->whereNull('tws.id')
                    ->orWhere('tws.status', '!=', TenantWhatsAppSession::STATUS_READY)
                    ->orWhere(function (Builder $err): void {
                        $err->whereNotNull('tws.last_error')->where('tws.last_error', '!=', '');
                    })
                    ->orWhereNull('tws.last_synced_at')
                    ->orWhere('tws.last_synced_at', '<', $staleBefore);
            }),
            default => null,
        };
    }

    private function prioritySql(): string
    {
        return <<<'SQL'
CASE
    WHEN tws.id IS NULL THEN 0
    WHEN tws.last_error IS NOT NULL AND tws.last_error <> '' THEN 1
    WHEN tws.status IN ('failed', 'disconnected') THEN 2
    WHEN tws.status <> 'ready' THEN 3
    ELSE 4
END
SQL;
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(DateTimeInterface $staleBefore): array
    {
        $living = Tenant::query()->whereIn('estado', self::LIVING_ESTADOS);
        $livingCount = (clone $living)->count();

        $sessions = TenantWhatsAppSession::query()
            ->whereIn('tenant_id', (clone $living)->select('id'));

        $ready = (clone $sessions)->where('status', TenantWhatsAppSession::STATUS_READY)->count();
        $withError = (clone $sessions)
            ->whereNotNull('last_error')
            ->where('last_error', '!=', '')
            ->count();
        $disconnected = (clone $sessions)
            ->whereIn('status', ['disconnected', 'failed'])
            ->count();
        $withSession = (clone $sessions)->count();
        $stale = (clone $sessions)
            ->where(function (Builder $q) use ($staleBefore): void {
                $q->whereNull('last_synced_at')
                    ->orWhere('last_synced_at', '<', $staleBefore);
            })
            ->count();
        $reconnectOff = (clone $sessions)->where('auto_reconnect', false)->count();

        return [
            'living' => $livingCount,
            'with_session' => $withSession,
            'without_session' => max(0, $livingCount - $withSession),
            'ready' => $ready,
            'not_ready' => max(0, $withSession - $ready),
            'with_error' => $withError,
            'disconnected' => $disconnected,
            'stale' => $stale,
            'reconnect_off' => $reconnectOff,
            'openwa_configured' => $this->openWa->isConfigured(),
            'rate_limited' => $this->openWa->isRateLimited(),
            'stale_minutes' => self::STALE_MINUTES,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function platformPayload(): array
    {
        $platform = PlatformWhatsAppSession::query()->latest('updated_at')->first();

        return [
            'status' => $platform?->status,
            'phone' => $platform?->phone,
            'last_error' => $platform?->last_error,
            'last_synced_at' => $platform?->last_synced_at?->toIso8601String(),
            'auto_reconnect' => $platform?->auto_reconnect,
            'ready' => $platform?->isReady() ?? false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Tenant $tenant, DateTimeInterface $staleBefore): array
    {
        $session = $tenant->whatsappSession;
        $lastSynced = $session?->last_synced_at;
        $stale = $session !== null
            && ($lastSynced === null || $lastSynced->lt($staleBefore));
        $error = trim((string) ($session?->last_error ?? ''));
        $nombre = trim((string) ($tenant->nombre_comercial ?: $tenant->razon_social ?: $tenant->slug));

        return [
            'tenant' => [
                'id' => (string) $tenant->id,
                'slug' => $tenant->slug,
                'nombre' => $nombre,
                'estado' => $tenant->estado,
            ],
            'has_session' => $session !== null,
            'status' => $session?->status,
            'phone' => $session?->phone,
            'session_name' => $session?->openwa_session_name,
            'last_error' => $error !== '' ? $error : null,
            'last_synced_at' => $lastSynced?->toIso8601String(),
            'auto_reconnect' => $session?->auto_reconnect ?? null,
            'stale' => $stale,
            'can_act' => in_array($tenant->estado, ['trial', 'active', 'suspended'], true),
        ];
    }
}
