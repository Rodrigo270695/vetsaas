<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

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

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => $request->getLocale(),
            'timezone' => config('app.timezone'),
            'tenant' => $tenantPayload,
            'auth' => [
                'user' => $user,
                'permissions' => $user
                    ? $user->getAllPermissions()->pluck('name')->values()->all()
                    : [],
                'roles' => $user
                    ? $user->getRoleNames()->values()->all()
                    : [],
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
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
