<?php

namespace App\Http\Middleware;

use App\Models\ClinicSetting;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\InAppAssistant\InAppAssistantService;
use App\Support\Clinic\ClinicBrandingUrls;
use App\Support\Database\PublicSchema;
use App\Support\OpenWa\PlatformWhatsAppPresenter;
use App\Support\OpenWa\TenantWhatsAppPresenter;
use App\Support\Plan\PlanLimits;
use App\Support\Subscriptions\BotIaAccess;
use App\Support\Subscriptions\TenantSubscriptionSummary;
use App\Support\Tenancy\TenantModuleAccess;
use App\Support\Tenancy\TenantSubdomainUrl;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Middleware;
use Throwable;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        try {
            return $this->buildSharedProps($request);
        } catch (Throwable $e) {
            report($e);
            $this->appendEmergencyLog($e);

            return [
                ...parent::share($request),
                'name' => config('app.name'),
                'locale' => $request->getLocale(),
                'timezone' => config('app.timezone'),
                'tenant' => null,
                'clinic_branding' => null,
                'tenancy' => [
                    'root_domain' => TenantSubdomainUrl::rootDomain(),
                    'scheme' => TenantSubdomainUrl::scheme(),
                    'login_path' => TenantSubdomainUrl::loginPath(),
                ],
                'plan_limits' => null,
                'subscription_renewal_alert' => null,
                'auth' => [
                    'user' => Auth::guard('web')->user(),
                    'permissions' => [],
                    'roles' => [],
                ],
                'flash' => null,
                'tenant_impersonation' => null,
                'whatsapp_connection' => null,
                'push' => null,
                'sidebarOpen' => true,
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSharedProps(Request $request): array
    {
        // Snapshot del tenant activo (si lo hay). Se expone como prop
        // global `page.props.tenant` para que cualquier layout, sidebar
        // o componente React pueda saber en qué clínica está sin tener
        // que consultar al backend. En el panel central (host `localhost`)
        // este valor es `null` y los componentes deben tratarlo así.
        $tenantContext = app(TenantManager::class)->current();
        $tenantPayload = $tenantContext === null ? null : [
            'id' => $tenantContext->id(),
            'slug' => $tenantContext->slug,
            'razon_social' => $tenantContext->razonSocial(),
            'nombre_comercial' => $tenantContext->nombreComercial(),
            'estado' => $tenantContext->estado(),
        ];

        // Un solo guard `web` para todos los usuarios (single-login).
        // Spatie permissions decide qué puede hacer cada uno.
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        $skipHeavySharedProps = $request->routeIs('password.change.form', 'password.change.update')
            || $request->is('cuenta/cambiar-password')
            || ($user instanceof User && $user->must_change_password === true);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => $request->getLocale(),
            'timezone' => config('app.timezone'),
            'tenant' => $tenantPayload,
            'clinic_branding' => $tenantContext === null
                ? null
                : $this->resolveClinicBranding(),
            'clinic_location_gate' => $skipHeavySharedProps || $tenantContext === null
                ? null
                : static function () use ($tenantContext, $user): ?array {
                    if (! ($user instanceof User)) {
                        return null;
                    }

                    try {
                        $onboarding = app(\App\Services\Onboarding\ClinicOnboardingService::class);
                        $tenant = $tenantContext->tenant;
                        $tenantId = (string) $tenant->id;
                        $needsSede = ! $onboarding->hasAnyActiveSede($tenantId);
                        $needsSedeGeo = ! $needsSede && $onboarding->hasActiveSedeMissingGeo($tenantId);
                        $canEditSedes = $user->can('sedes.create') || $user->can('sedes.update');

                        // IMPORTANTE: Schema::hasColumn() falla con search_path del tenant
                        // (busca en schema de clínica, no en public.tenants).
                        $geoReady = PublicSchema::hasColumn('tenants', 'geo_consent_at');
                        $hasConsent = $geoReady && $tenant->geo_consent_at !== null;
                        // Cualquier usuario autenticado del tenant puede aceptar/rechazar:
                        // el GPS es de la clínica (tenant), no de la sede.
                        $needsGps = $geoReady
                            && $tenant->geo_consent_at === null
                            && $tenant->geo_denied_at === null;
                        $gpsCaptured = $geoReady
                            && $tenant->geo_lat !== null
                            && $tenant->geo_lng !== null;

                        $gpsRefreshDue = false;
                        if ($hasConsent && $tenant->geo_denied_at === null) {
                            $requested = PublicSchema::hasColumn('tenants', 'geo_refresh_requested_at')
                                ? $tenant->geo_refresh_requested_at
                                : null;
                            $captured = $tenant->geo_captured_at;
                            if ($tenant->geo_lat === null || $tenant->geo_lng === null) {
                                $gpsRefreshDue = true;
                            } elseif ($requested !== null && ($captured === null || $requested->gt($captured))) {
                                $gpsRefreshDue = true;
                            }
                        }

                        return [
                            'needs_sede' => $needsSede,
                            'needs_sede_geo' => $needsSedeGeo,
                            'needs_gps' => $needsGps,
                            'gps_captured' => $gpsCaptured,
                            'has_gps_consent' => $hasConsent,
                            'gps_refresh_due' => $gpsRefreshDue,
                            'can_edit_sedes' => $canEditSedes,
                            'sedes_url' => '/configuracion/sedes',
                        ];
                    } catch (Throwable $e) {
                        report($e);

                        return null;
                    }
                },
            'tenancy' => [
                'root_domain' => TenantSubdomainUrl::rootDomain(),
                'scheme' => TenantSubdomainUrl::scheme(),
                'login_path' => TenantSubdomainUrl::loginPath(),
            ],
            'plan_limits' => $skipHeavySharedProps
                ? null
                : static function () {
                    try {
                        return PlanLimits::snapshot();
                    } catch (Throwable $e) {
                        report($e);

                        return null;
                    }
                },
            'subscription_renewal_alert' => $skipHeavySharedProps
                ? null
                : static function () use ($tenantContext, $user) {
                    if ($tenantContext === null || ! ($user instanceof User)) {
                        return null;
                    }

                    try {
                        if (! $user->can('config-general.view')) {
                            return null;
                        }

                        return TenantSubscriptionSummary::renewalAlertForTenant(
                            $tenantContext->tenant,
                        );
                    } catch (Throwable $e) {
                        report($e);

                        return null;
                    }
                },
            'bot_ia_addon' => $skipHeavySharedProps || $tenantContext === null
                ? null
                : static fn () => BotIaAccess::navPayload($tenantContext->tenant),
            'in_app_assistant' => $skipHeavySharedProps
                ? null
                : static function () use ($tenantContext, $user): ?array {
                    $isClinic = $tenantContext !== null
                        && $user instanceof User
                        && $user->can('in-app-assistant.use');
                    $isPlatform = $tenantContext === null
                        && $user instanceof User
                        && $user->isPlatformSuperadmin();

                    if (! $isClinic && ! $isPlatform) {
                        return null;
                    }

                    $assistant = app(InAppAssistantService::class);
                    $enabled = (bool) config('in-app-assistant.enabled', true);
                    $configured = $assistant->isConfigured();

                    $announcement = null;
                    if ($isClinic && $enabled && $configured) {
                        try {
                            $announcement = PlatformSetting::current()->assistantAnnouncementPayload();
                        } catch (Throwable) {
                            $announcement = null;
                        }
                    }

                    return [
                        'enabled' => $enabled,
                        'configured' => $configured,
                        'scope' => $isPlatform ? 'platform' : 'clinic',
                        'unlimited' => $user instanceof User && $user->isPlatformSuperadmin(),
                        'announcement' => $announcement,
                    ];
                },
            'tenant_modules' => $skipHeavySharedProps || $tenantContext === null
                ? null
                : static fn () => TenantModuleAccess::snapshot($tenantContext->tenant),
            'auth' => [
                'user' => $user,
                'permissions' => $skipHeavySharedProps
                    ? []
                    : $this->resolveUserPermissions($user),
                'roles' => $skipHeavySharedProps
                    ? []
                    : $this->resolveUserRoles($user),
            ],
            /*
             * Flash session compartido como UN solo closure (no por key).
             *
             * Motivo: Inertia evalúa los closures de share() solo cuando la
             * key está incluida en `only=` del partial reload. Con keys
             * separadas (success/error/...), cualquier partial reload que NO
             * pida `flash` mantenía el flash anterior en page.props,
             * provocando que el toast se re-disparara una y otra vez.
             *
             * Con un único closure y un `id` por payload, el cliente
             * deduplica por id: si recibe la misma id que ya mostró, ignora.
             */
            'flash' => function () use ($request) {
                $session = $request->session();
                $payload = [
                    'success' => $session->get('success'),
                    'error' => $session->get('error'),
                    'info' => $session->get('info'),
                    'warning' => $session->get('warning'),
                ];

                $hasMessage = collect($payload)
                    ->filter(fn ($v) => is_string($v) && $v !== '')
                    ->isNotEmpty();

                if (! $hasMessage) {
                    return null;
                }

                return [
                    'id' => sha1(serialize($payload).microtime(true)),
                    ...$payload,
                ];
            },
            'tenant_impersonation' => static function () use ($request) {
                $raw = $request->session()->get('tenant_impersonation');

                if (! is_array($raw) || empty($raw['tenant_id'])) {
                    return null;
                }

                return [
                    'tenant_label' => is_string($raw['tenant_label'] ?? null)
                        ? $raw['tenant_label']
                        : '',
                    'started_at' => is_string($raw['started_at'] ?? null)
                        ? $raw['started_at']
                        : null,
                ];
            },
            'whatsapp_connection' => $skipHeavySharedProps
                ? null
                : function () use ($tenantContext, $user): ?array {
                    if (! ($user instanceof User)) {
                        return null;
                    }

                    try {
                        return $this->resolveWhatsAppConnection($tenantContext?->tenant, $user);
                    } catch (Throwable $e) {
                        report($e);

                        return null;
                    }
                },
            /*
             * Web Push solo en panel central (superadmin / staff sin tenant).
             * En clínicas el icono no se muestra.
             */
            'push' => function () use ($user, $tenantContext): ?array {
                if (! ($user instanceof User) || $user->tenant_id !== null || $tenantContext !== null) {
                    return null;
                }

                $publicKey = trim((string) config('webpush.vapid.public_key', ''));

                return [
                    'enabled' => $publicKey !== '',
                    'vapidPublicKey' => $publicKey !== '' ? $publicKey : null,
                ];
            },
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Estado liviano de WhatsApp (solo DB) para toast global de desconexión.
     *
     * @return array{
     *     scope: 'tenant'|'platform',
     *     disconnected: bool,
     *     session_id: string|null,
     *     status: string|null,
     *     last_synced_at: string|null,
     *     manage_url: string|null
     * }|null
     */
    private function resolveWhatsAppConnection(?\App\Models\Tenant $tenant, User $user): ?array
    {
        if ($tenant !== null) {
            if (! $user->can('comunicaciones-cola.view') && ! $user->can('comunicaciones-bot-ia.view')) {
                return null;
            }

            $payload = app(TenantWhatsAppPresenter::class)->forTenant($tenant);
            $session = is_array($payload['session'] ?? null) ? $payload['session'] : null;
            $status = is_string($session['status'] ?? null) ? $session['status'] : null;
            $disconnected = $session !== null
                && ! (bool) ($session['is_ready'] ?? false)
                && in_array($status, ['disconnected', 'failed'], true);

            return [
                'scope' => 'tenant',
                'disconnected' => $disconnected,
                'session_id' => isset($session['id']) ? (string) $session['id'] : null,
                'status' => $status,
                'last_synced_at' => isset($session['last_synced_at']) && is_string($session['last_synced_at'])
                    ? $session['last_synced_at']
                    : null,
                'manage_url' => route('comunicaciones.cola'),
            ];
        }

        if (! $user->isPlatformSuperadmin() && ! $user->can('plataforma-suscripciones.view')) {
            return null;
        }

        $payload = app(PlatformWhatsAppPresenter::class)->present();
        $session = is_array($payload['session'] ?? null) ? $payload['session'] : null;
        $status = is_string($session['status'] ?? null) ? $session['status'] : null;
        $disconnected = $session !== null
            && ! (bool) ($session['is_ready'] ?? false)
            && in_array($status, ['disconnected', 'failed'], true);

        return [
            'scope' => 'platform',
            'disconnected' => $disconnected,
            'session_id' => isset($session['id']) ? (string) $session['id'] : null,
            'status' => $status,
            'last_synced_at' => isset($session['last_synced_at']) && is_string($session['last_synced_at'])
                ? $session['last_synced_at']
                : null,
            'manage_url' => route('plataforma.avisos-renovacion.index'),
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveUserPermissions(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        try {
            if ($user->isPlatformSuperadmin()) {
                $previousTeam = getPermissionsTeamId();
                setPermissionsTeamId(null);

                try {
                    $user->unsetRelation('roles');
                    $user->unsetRelation('permissions');

                    return $user->getAllPermissions()->pluck('name')->values()->all();
                } finally {
                    setPermissionsTeamId($previousTeam);
                    $user->unsetRelation('roles');
                    $user->unsetRelation('permissions');
                }
            }

            return $user->getAllPermissions()->pluck('name')->values()->all();
        } catch (Throwable $e) {
            report($e);
            Log::error('No se pudieron cargar permisos Inertia.', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return list<string>
     */
    private function resolveUserRoles(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        try {
            if ($user->isPlatformSuperadmin()) {
                return ['superadmin'];
            }

            return $user->getRoleNames()->values()->all();
        } catch (Throwable $e) {
            report($e);
            Log::error('No se pudieron cargar roles Inertia.', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function appendEmergencyLog(Throwable $e): void
    {
        $line = sprintf(
            "[%s] inertia.share.ERROR: %s in %s:%d\n",
            now()->toDateTimeString(),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
        );

        @file_put_contents(storage_path('logs/laravel.log'), $line, FILE_APPEND | LOCK_EX);
    }

    private function resolveClinicBranding(): ?array
    {
        try {
            return ClinicBrandingUrls::sharedPayload(ClinicSetting::current());
        } catch (Throwable $e) {
            report($e);

            return [
                'logo_url' => ClinicBrandingUrls::default(),
                'updated_at' => null,
                'color_primario' => null,
                'color_secundario' => null,
            ];
        }
    }
}
