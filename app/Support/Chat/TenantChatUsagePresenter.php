<?php

declare(strict_types=1);

namespace App\Support\Chat;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Platform\PresenceSnapshotService;
use App\Tenancy\TenantManager;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Actividad de chat interno por clínica (scan multi-schema).
 *
 * Las tablas `chat_*` viven en el schema del tenant; este presenter
 * itera clínicas activas y agrega métricas para el panel SaaS.
 */
final class TenantChatUsagePresenter
{
    private const CACHE_TTL_SECONDS = 45;

    public function __construct(
        private readonly TenantManager $tenants,
    ) {}

    /**
     * @return array{
     *     items: LengthAwarePaginator,
     *     filters: array{search: string, scope: string, per_page: int},
     *     stats: array{
     *         tenants_scanned: int,
     *         tenants_with_chat: int,
     *         tenants_active_7d: int,
     *         tenants_active_30d: int,
     *         messages_7d: int,
     *         messages_30d: int,
     *         users_online: int
     *     }
     * }
     */
    public function paginate(string $search, string $scope, int $perPage): array
    {
        $perPage = in_array($perPage, [10, 15, 25, 50], true) ? $perPage : 15;
        $scope = in_array($scope, ['activos', 'todos'], true) ? $scope : 'activos';
        $search = trim($search);

        $rows = $this->cachedRows();

        if ($scope === 'activos') {
            $rows = $rows->filter(
                static fn (array $row): bool => ($row['messages_30d'] ?? 0) > 0
                    || ($row['last_message_at'] !== null),
            )->values();
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(static function (array $row) use ($needle): bool {
                $nombre = mb_strtolower((string) ($row['tenant']['nombre'] ?? ''));
                $slug = mb_strtolower((string) ($row['tenant']['slug'] ?? ''));

                return str_contains($nombre, $needle) || str_contains($slug, $needle);
            })->values();
        }

        $stats = [
            'tenants_scanned' => $rows->count(),
            'tenants_with_chat' => $rows->where('chat_ready', true)->count(),
            'tenants_active_7d' => $rows->where('messages_7d', '>', 0)->count(),
            'tenants_active_30d' => $rows->where('messages_30d', '>', 0)->count(),
            'messages_7d' => (int) $rows->sum('messages_7d'),
            'messages_30d' => (int) $rows->sum('messages_30d'),
            'users_online' => (int) $rows->sum('users_online'),
        ];

        // Si filtramos "activos", los KPIs globales deben venir del set completo
        // (sin search) para no mentir al filtrar por nombre.
        if ($search !== '' || $scope !== 'todos') {
            $all = $this->cachedRows();
            $stats = [
                'tenants_scanned' => $all->count(),
                'tenants_with_chat' => $all->where('chat_ready', true)->count(),
                'tenants_active_7d' => $all->where('messages_7d', '>', 0)->count(),
                'tenants_active_30d' => $all->where('messages_30d', '>', 0)->count(),
                'messages_7d' => (int) $all->sum('messages_7d'),
                'messages_30d' => (int) $all->sum('messages_30d'),
                'users_online' => (int) $all->sum('users_online'),
            ];
        }

        $page = max(1, (int) request()->integer('page', 1));
        $total = $rows->count();
        $slice = $rows->forPage($page, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );

        return [
            'items' => $paginator,
            'filters' => [
                'search' => $search,
                'scope' => $scope,
                'per_page' => $perPage,
            ],
            'stats' => $stats,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function cachedRows(): Collection
    {
        /** @var list<array<string, mixed>> $cached */
        $cached = Cache::remember(
            'plataforma:chat-usage:v1',
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->buildRows()->all(),
        );

        return collect($cached);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildRows(): Collection
    {
        $tenants = Tenant::query()
            ->whereNotIn('estado', ['cancelled'])
            ->orderBy('razon_social')
            ->get(['id', 'slug', 'razon_social', 'nombre_comercial', 'ruc', 'estado', 'schema_name']);

        $onlineByTenant = $this->onlineUsersByTenant();

        return $tenants
            ->map(function (Tenant $tenant) use ($onlineByTenant): array {
                $usage = $this->scanTenant($tenant);

                return [
                    'tenant' => [
                        'id' => (string) $tenant->id,
                        'slug' => (string) ($tenant->slug ?? ''),
                        'nombre' => (string) ($tenant->nombre_comercial ?: $tenant->razon_social ?: $tenant->slug),
                        'ruc' => $tenant->ruc,
                        'estado' => $tenant->estado,
                    ],
                    'chat_ready' => $usage['chat_ready'],
                    'conversations' => $usage['conversations'],
                    'messages_7d' => $usage['messages_7d'],
                    'messages_30d' => $usage['messages_30d'],
                    'last_message_at' => $usage['last_message_at'],
                    'users_online' => (int) ($onlineByTenant[(string) $tenant->id] ?? 0),
                    'error' => $usage['error'],
                ];
            })
            ->sortByDesc(static function (array $row): int {
                $at = $row['last_message_at'] ?? null;
                if (! is_string($at) || $at === '') {
                    return 0;
                }

                return (int) strtotime($at);
            })
            ->values();
    }

    /**
     * @return array{chat_ready: bool, conversations: int, messages_7d: int, messages_30d: int, last_message_at: ?string, error: ?string}
     */
    private function scanTenant(Tenant $tenant): array
    {
        $empty = [
            'chat_ready' => false,
            'conversations' => 0,
            'messages_7d' => 0,
            'messages_30d' => 0,
            'last_message_at' => null,
            'error' => null,
        ];

        if (blank($tenant->schema_name)) {
            return $empty;
        }

        try {
            return $this->tenants->runForTenant(
                $tenant,
                function () use ($empty): array {
                    if (! Schema::hasTable('chat_messages')
                        || ! Schema::hasTable('chat_conversations')) {
                        return $empty;
                    }

                    $since7 = Carbon::now()->subDays(7);
                    $since30 = Carbon::now()->subDays(30);

                    $messages7d = (int) DB::table('chat_messages')
                        ->whereNull('deleted_at')
                        ->where('created_at', '>=', $since7)
                        ->count();

                    $messages30d = (int) DB::table('chat_messages')
                        ->whereNull('deleted_at')
                        ->where('created_at', '>=', $since30)
                        ->count();

                    $conversations = (int) DB::table('chat_conversations')->count();

                    $lastAt = DB::table('chat_messages')
                        ->whereNull('deleted_at')
                        ->max('created_at');

                    return [
                        'chat_ready' => true,
                        'conversations' => $conversations,
                        'messages_7d' => $messages7d,
                        'messages_30d' => $messages30d,
                        'last_message_at' => is_string($lastAt) && $lastAt !== ''
                            ? Carbon::parse($lastAt)->toIso8601String()
                            : null,
                        'error' => null,
                    ];
                },
                enforceAccess: false,
            );
        } catch (Throwable $e) {
            report($e);

            return [
                ...$empty,
                'error' => 'No se pudo leer el chat de este tenant.',
            ];
        }
    }

    /**
     * @return array<string, int> tenant_id => online users
     */
    private function onlineUsersByTenant(): array
    {
        $since = Carbon::now()->subMinutes(PresenceSnapshotService::ONLINE_MINUTES);

        return User::query()
            ->whereNotNull('tenant_id')
            ->where('last_seen_at', '>=', $since)
            ->selectRaw('tenant_id, COUNT(*) as c')
            ->groupBy('tenant_id')
            ->pluck('c', 'tenant_id')
            ->map(static fn ($c): int => (int) $c)
            ->all();
    }
}
