<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserAuthSessionLog;
use App\Notifications\Tenancy\TenantOnboardingCheckInNotification;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Support\Tenancy\TenantSubdomainUrl;
use App\Support\WhatsApp\WhatsAppChatId;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Reporte de clínicas: si entraron, qué usaron y seguimiento por WhatsApp.
 */
final class FreeOnboardingService
{
    public function __construct(
        private readonly PlatformWhatsAppMessenger $whatsAppMessenger,
    ) {}

    /**
     * @return array{
     *     items: LengthAwarePaginator,
     *     filters: array{search: string, stage: string, plan: string, per_page: int},
     *     stats: array<string, int>
     * }
     */
    public function paginate(string $search, string $stage, string $plan, int $perPage): array
    {
        $perPage = in_array($perPage, [10, 15, 25, 50], true) ? $perPage : 15;
        $stage = in_array($stage, ['todos', 'nunca_entro', 'activo', 'inactivo', 'sin_whatsapp'], true)
            ? $stage
            : 'todos';
        $plan = in_array($plan, ['todos', 'free', 'pago'], true) ? $plan : 'free';
        $search = trim($search);

        $query = Subscription::query()
            ->whereIn('estado', ['trial', 'active', 'grace', 'suspended'])
            ->whereHas('tenant', fn ($tenant) => $tenant->where('estado', '!=', 'cancelled'))
            ->with([
                'tenant:id,slug,nombre_comercial,razon_social,estado,telefono,email_admin,created_at',
                'plan:id,codigo,nombre',
            ]);

        if ($plan === 'free') {
            $query->whereHas('plan', fn ($q) => $q->where('codigo', Plan::CODIGO_FREE));
        } elseif ($plan === 'pago') {
            $query->whereHas('plan', fn ($q) => $q->where('codigo', '!=', Plan::CODIGO_FREE));
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->whereHas('tenant', function ($tenant) use ($like): void {
                $tenant->where('slug', 'ilike', $like)
                    ->orWhere('nombre_comercial', 'ilike', $like)
                    ->orWhere('razon_social', 'ilike', $like)
                    ->orWhere('email_admin', 'ilike', $like)
                    ->orWhere('telefono', 'ilike', $like);
            });
        }

        $subs = $query->orderByDesc('created_at')->get()->unique('tenant_id')->values();
        $activity = $this->activityByTenant($subs->pluck('tenant_id')->filter()->all());

        $rows = $subs->map(function (Subscription $sub) use ($activity): array {
            $tenant = $sub->tenant;
            $act = $activity[(string) $sub->tenant_id] ?? [
                'last_login_at' => null,
                'last_seen_at' => null,
                'last_module' => null,
                'last_path' => null,
                'login_count' => 0,
                'never_opened_welcome' => true,
            ];

            $row = $this->serialize($sub, $tenant, $act);
            $row['stage'] = $this->stageOf($row);

            return $row;
        });

        $stats = [
            'total' => $rows->count(),
            'nunca_entro' => $rows->where('stage', 'nunca_entro')->count(),
            'activo' => $rows->where('stage', 'activo')->count(),
            'inactivo' => $rows->where('stage', 'inactivo')->count(),
            'sin_whatsapp' => $rows->where('stage', 'sin_whatsapp')->count(),
        ];

        if ($stage !== 'todos') {
            $rows = $rows->filter(fn (array $row): bool => $row['stage'] === $stage)->values();
        }

        $page = max(1, (int) request()->integer('page', 1));
        $total = $rows->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
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
                'stage' => $stage,
                'plan' => $plan,
                'per_page' => $perPage,
            ],
            'stats' => $stats,
        ];
    }

    /**
     * @return array{whatsapp_sent: bool, email_sent: bool, warning: string|null}
     */
    public function sendCheckIn(Tenant $tenant): array
    {
        $chatId = WhatsAppChatId::fromPhone($tenant->telefono);
        $email = $this->adminEmail($tenant);
        $loginUrl = TenantSubdomainUrl::login($tenant);
        $isFree = $tenant->activeSubscription()?->plan?->codigo === Plan::CODIGO_FREE;

        if ($chatId !== null && $this->whatsAppMessenger->isReady()) {
            $wa = $this->sendWhatsApp($tenant, $chatId, $loginUrl, $isFree);
            if ($wa['whatsapp_sent']) {
                return $wa;
            }
            if ($email !== null) {
                return $this->sendEmail($tenant, $email, $loginUrl, $isFree);
            }

            return $wa;
        }

        if ($email !== null) {
            return $this->sendEmail($tenant, $email, $loginUrl, $isFree);
        }

        if ($chatId !== null && ! $this->whatsAppMessenger->isReady()) {
            return [
                'whatsapp_sent' => false,
                'email_sent' => false,
                'warning' => 'WhatsApp de plataforma no está conectado y no hay correo para enviar.',
            ];
        }

        return [
            'whatsapp_sent' => false,
            'email_sent' => false,
            'warning' => 'El tenant no tiene celular ni correo válido.',
        ];
    }

    /**
     * @param  list<string>  $tenantIds
     * @return array{sent: int, failed: int, skipped: int, errors: list<string>}
     */
    public function sendCheckInBulk(array $tenantIds): array
    {
        $ids = array_values(array_unique(array_filter($tenantIds)));
        $tenants = Tenant::query()->whereIn('id', $ids)->get();

        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($tenants as $tenant) {
            $result = $this->sendCheckIn($tenant);
            if ($result['whatsapp_sent']) {
                $sent++;
                continue;
            }

            if (($result['warning'] ?? '') === 'El tenant no tiene celular ni correo válido.') {
                $skipped++;
                continue;
            }

            $failed++;
            if (is_string($result['warning']) && $result['warning'] !== '') {
                $errors[] = $tenant->slug.': '.$result['warning'];
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<mixed>  $tenantIds
     * @return array<string, array{
     *     last_login_at: ?string,
     *     last_seen_at: ?string,
     *     last_module: ?string,
     *     last_path: ?string,
     *     login_count: int,
     *     never_opened_welcome: bool
     * }>
     */
    private function activityByTenant(array $tenantIds): array
    {
        $ids = array_values(array_filter(array_map('strval', $tenantIds)));
        if ($ids === []) {
            return [];
        }

        $users = User::query()
            ->whereIn('tenant_id', $ids)
            ->get([
                'tenant_id',
                'last_login_at',
                'last_seen_at',
                'last_module',
                'last_path',
                'bootstrap_login_token',
            ]);

        $out = [];
        foreach ($users as $user) {
            $tid = (string) $user->tenant_id;
            $current = $out[$tid] ?? [
                'last_login_at' => null,
                'last_seen_at' => null,
                'last_module' => null,
                'last_path' => null,
                'login_count' => 0,
                'never_opened_welcome' => true,
            ];

            if ($user->last_login_at !== null) {
                $iso = $user->last_login_at->toIso8601String();
                if ($current['last_login_at'] === null || $iso > $current['last_login_at']) {
                    $current['last_login_at'] = $iso;
                }
                $current['login_count']++;
            }

            if ($user->last_seen_at !== null) {
                $iso = $user->last_seen_at->toIso8601String();
                if ($current['last_seen_at'] === null || $iso > $current['last_seen_at']) {
                    $current['last_seen_at'] = $iso;
                    $current['last_module'] = $user->last_module;
                    $current['last_path'] = $user->last_path;
                }
            }

            if (blank($user->bootstrap_login_token) || $user->last_login_at !== null) {
                $current['never_opened_welcome'] = false;
            }

            $out[$tid] = $current;
        }

        $sessions = UserAuthSessionLog::query()
            ->selectRaw('tenant_id, count(*) as login_count, max(logged_in_at) as last_session_at')
            ->whereIn('tenant_id', $ids)
            ->groupBy('tenant_id')
            ->get();

        foreach ($sessions as $session) {
            $tid = (string) $session->tenant_id;
            $current = $out[$tid] ?? [
                'last_login_at' => null,
                'last_seen_at' => null,
                'last_module' => null,
                'last_path' => null,
                'login_count' => 0,
                'never_opened_welcome' => true,
            ];
            $current['login_count'] = max($current['login_count'], (int) $session->login_count);
            if ($session->last_session_at !== null) {
                $iso = Carbon::parse($session->last_session_at)->toIso8601String();
                if ($current['last_login_at'] === null || $iso > $current['last_login_at']) {
                    $current['last_login_at'] = $iso;
                    $current['never_opened_welcome'] = false;
                }
            }
            $out[$tid] = $current;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $activity
     * @return array<string, mixed>
     */
    private function serialize(Subscription $sub, ?Tenant $tenant, array $activity): array
    {
        $nombre = $tenant !== null
            ? trim((string) ($tenant->nombre_comercial ?: $tenant->razon_social ?: $tenant->slug))
            : '—';

        return [
            'id' => (string) $sub->id,
            'tenant' => [
                'id' => (string) $sub->tenant_id,
                'slug' => $tenant?->slug ?? '—',
                'nombre' => $nombre,
                'estado' => $tenant?->estado,
                'telefono' => $tenant?->telefono,
                'email' => $tenant?->email_admin,
                'created_at' => $tenant?->created_at?->toIso8601String(),
            ],
            'plan' => $sub->plan?->nombre ?? '—',
            'plan_codigo' => $sub->plan?->codigo,
            'last_login_at' => $activity['last_login_at'],
            'last_seen_at' => $activity['last_seen_at'],
            'last_module' => $activity['last_module'],
            'last_path' => $activity['last_path'],
            'login_count' => (int) ($activity['login_count'] ?? 0),
            'never_opened_welcome' => (bool) $activity['never_opened_welcome'],
            'has_phone' => WhatsAppChatId::fromPhone($tenant?->telefono) !== null,
            'has_email' => $this->adminEmail($tenant) !== null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function stageOf(array $row): string
    {
        if (! $row['has_phone']) {
            return 'sin_whatsapp';
        }
        if ($row['last_login_at'] === null) {
            return 'nunca_entro';
        }
        if ($row['last_seen_at'] !== null) {
            $seen = Carbon::parse($row['last_seen_at']);
            if ($seen->gte(now()->subDays(7))) {
                return 'activo';
            }
        }

        return 'inactivo';
    }

    /**
     * @return array{whatsapp_sent: bool, email_sent: bool, warning: string|null}
     */
    private function sendWhatsApp(Tenant $tenant, string $chatId, string $loginUrl, bool $isFree): array
    {
        $brand = $tenant->nombre_comercial ?: $tenant->razon_social ?: $tenant->slug;
        $planLine = $isFree
            ? 'Te escribimos de VetSaaS para ver cómo te está yendo con el plan Free.'
            : 'Te escribimos de VetSaaS para ver cómo te está yendo con tu clínica.';
        $upgradeLine = $isFree
            ? 'Cuando quieras pasar a un plan de pago, también lo vemos por aquí.'
            : 'Si necesitas algo del plan o de la clínica, responde este WhatsApp.';
        $message = implode("\n", [
            "Hola, {$brand} 👋",
            '',
            $planLine,
            '¿Pudiste entrar a tu clínica y cargar pacientes o una cita?',
            '',
            "Tu acceso: {$loginUrl}",
            "Correo: {$tenant->email_admin}",
            '',
            'Si te trabaste en algún paso, responde este WhatsApp y te ayudamos.',
            $upgradeLine,
            '',
            '— Equipo VetSaaS / Orvae',
        ]);

        try {
            $this->whatsAppMessenger->sendText($chatId, $message);
        } catch (\Throwable $e) {
            return [
                'whatsapp_sent' => false,
                'email_sent' => false,
                'warning' => app()->hasDebugModeEnabled() ? $e->getMessage() : 'No se pudo enviar el WhatsApp.',
            ];
        }

        return ['whatsapp_sent' => true, 'email_sent' => false, 'warning' => null];
    }

    /**
     * @return array{whatsapp_sent: bool, email_sent: bool, warning: string|null}
     */
    private function sendEmail(Tenant $tenant, string $email, string $loginUrl, bool $isFree): array
    {
        try {
            Notification::sendNow(
                Notification::route('mail', $email),
                new TenantOnboardingCheckInNotification($tenant, $loginUrl, $isFree),
            );
        } catch (\Throwable $e) {
            return [
                'whatsapp_sent' => false,
                'email_sent' => false,
                'warning' => app()->hasDebugModeEnabled() ? $e->getMessage() : 'No se pudo enviar el correo.',
            ];
        }

        return ['whatsapp_sent' => false, 'email_sent' => true, 'warning' => null];
    }

    private function adminEmail(?Tenant $tenant): ?string
    {
        $email = strtolower(trim((string) ($tenant?->email_admin ?? '')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }
}
