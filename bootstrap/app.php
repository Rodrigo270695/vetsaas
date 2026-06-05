<?php

use App\Http\Middleware\EnsureNoTenant;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureTenant;
use App\Http\Middleware\EnsureTenantSubscriptionAccess;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\MatchUserTenant;
use App\Http\Middleware\ResolveTenant;
use App\Tenancy\Exceptions\TenantNotFoundException;
use App\Tenancy\Exceptions\TenantSuspendedException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
        // `then` se ejecuta DESPUÉS de registrar las rutas estándar:
        // aquí enganchamos las rutas exclusivas de subdominios de tenant
        // (`routes/tenant.php`). El patrón `{tenant_subdomain}.<root>`
        // hace que Laravel solo enrute estas rutas cuando el host
        // coincide; las del dominio central nunca colisionan porque
        // jamás incluyen el subdominio en su matching.
        then: function (): void {
            Route::middleware('web')
                ->domain('{tenant_subdomain}.'.config('tenant.root_domain'))
                ->group(base_path('routes/tenant.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,

            // Multi-tenancy:
            //   - 'tenant'             resuelve el subdominio y fija search_path.
            //   - 'tenant.required'    aborta 404 si la ruta exige un tenant
            //                          y entró por dominio central.
            //   - 'tenant.none'        aborta 404 si la ruta es solo del panel
            //                          SaaS y entró por subdominio de tenant.
            //   - 'tenant.match-user'  valida que el usuario autenticado pertenezca
            //                          al tenant del host (o sea central si no hay
            //                          tenant resuelto). Si no, cierra sesión y 403.
            'tenant' => ResolveTenant::class,
            'tenant.required' => EnsureTenant::class,
            'tenant.none' => EnsureNoTenant::class,
            'tenant.match-user' => MatchUserTenant::class,

            // Fase 2.6: si `users.must_change_password = true`, todo
            // request a una ruta operativa se redirige al cambio de
            // contraseña hasta que el usuario lo complete.
            'force-password-change' => EnsurePasswordIsChanged::class,
        ]);

        // `ResolveTenant` se aplica a TODO el grupo web. Es inocuo en
        // dominios centrales (devuelve sin tocar nada), y obligatorio
        // en subdominios para que la BD use el schema correcto antes
        // de que llegue cualquier query.
        $middleware->web(prepend: [
            ResolveTenant::class,
        ]);

        // Laravel reordena los middlewares en runtime según la lista de
        // prioridad. Si nuestros middlewares de tenancy no están en esa
        // lista, terminan ejecutándose DESPUÉS de `auth` y un request
        // no-autenticado a `/plataforma/*` desde un subdominio recibe
        // 302 → /login en vez del 404 que esperamos. Los registramos
        // ANTES de la interfaz `AuthenticatesRequests` (que es la que
        // implementa `Illuminate\Auth\Middleware\Authenticate`) para
        // que `EnsureNoTenant` aborte el request mucho antes de que la
        // autenticación intente redirigir.
        //
        // Importante: `EnsureTenant` NO se priorizar antes de auth.
        // Su lógica actual depende de tener `Auth::user()` ya resuelto
        // (para saber si es superadmin y mostrarle una pantalla
        // informativa via Inertia) y de que `HandleInertiaRequests`
        // ya haya registrado las shared props (`auth`, `tenant`, etc.).
        // Si lo priorizáramos antes de auth, `Inertia::render()` saldría
        // sin `auth.user` y el sidebar (NavUser) reventaría.
        $authInterface = AuthenticatesRequests::class;

        $middleware->prependToPriorityList(before: $authInterface, prepend: ResolveTenant::class);
        $middleware->prependToPriorityList(before: $authInterface, prepend: EnsureNoTenant::class);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsureTenantSubscriptionAccess::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('vetsaas:billing-supervisor')->dailyAt('06:00');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $userFacingHttpMessage = static function (?string $message): ?string {
            if ($message === null || $message === '') {
                return null;
            }

            if (in_array($message, ['Not Found', 'Forbidden', 'Unauthorized.', 'Unauthorized'], true)) {
                return null;
            }

            if (str_starts_with($message, 'The route ')
                || str_starts_with($message, 'No query results for model')) {
                return null;
            }

            return $message;
        };

        $renderInertiaHttpError = static function (
            Request $request,
            int $status,
            string $page,
            ?string $message = null,
        ) use ($userFacingHttpMessage) {
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return null;
            }

            return Inertia::render($page, [
                'message' => $userFacingHttpMessage($message),
                'attempted_path' => '/'.ltrim($request->path(), '/'),
                'is_authenticated' => Auth::guard('web')->check(),
            ])
                ->toResponse($request)
                ->setStatusCode($status);
        };

        $exceptions->renderable(function (NotFoundHttpException $e, Request $request) use ($renderInertiaHttpError) {
            return $renderInertiaHttpError(
                $request,
                404,
                'errors/not-found',
                $e->getMessage() !== '' ? $e->getMessage() : null,
            );
        });

        $exceptions->renderable(function (ModelNotFoundException $e, Request $request) use ($renderInertiaHttpError) {
            return $renderInertiaHttpError($request, 404, 'errors/not-found', null);
        });

        $exceptions->renderable(function (AuthorizationException $e, Request $request) use ($renderInertiaHttpError) {
            return $renderInertiaHttpError(
                $request,
                403,
                'errors/forbidden',
                $e->getMessage() !== '' ? $e->getMessage() : null,
            );
        });

        $exceptions->renderable(function (HttpException $e, Request $request) use ($renderInertiaHttpError) {
            $status = $e->getStatusCode();

            if ($status === 403) {
                return $renderInertiaHttpError(
                    $request,
                    403,
                    'errors/forbidden',
                    $e->getMessage() !== '' ? $e->getMessage() : null,
                );
            }

            if ($status === 404) {
                return $renderInertiaHttpError(
                    $request,
                    404,
                    'errors/not-found',
                    $e->getMessage() !== '' ? $e->getMessage() : null,
                );
            }

            return null;
        });

        // Cuando el middleware `ResolveTenant` no encuentra el tenant,
        // queremos una página bonita en lugar del 404 genérico.
        $exceptions->renderable(function (TenantNotFoundException $e, Request $request) {
            return Inertia::render('tenant/errors/not-found', [
                'slug' => $e->identifier,
            ])
                ->toResponse($request)
                ->setStatusCode(404);
        });

        // Tenant existe pero está bloqueado: pantalla con motivo y
        // contacto de soporte. Distinguimos visualmente suspendido vs
        // cancelado vía la prop `estado`.
        $exceptions->renderable(function (TenantSuspendedException $e, Request $request) {
            if (Auth::guard('web')->check()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            $tenant = $e->tenant;
            $blockType = in_array($e->blockType, ['suspended', 'cancelled', 'expired'], true)
                ? $e->blockType
                : 'suspended';

            return Inertia::render('tenant/errors/blocked', [
                'block_type' => $blockType,
                'estado' => $tenant->estado,
                'razon_social' => $tenant->razon_social,
                'reason' => $tenant->suspension_reason ?? $tenant->cancel_reason,
                'suspended_at' => $tenant->suspended_at?->toIso8601String(),
                'cancelled_at' => $tenant->cancelled_at?->toIso8601String(),
            ])
                ->toResponse($request)
                ->setStatusCode(403);
        });
    })->create();
