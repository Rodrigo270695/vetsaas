<?php

namespace App\Http\Middleware;

use App\Tenancy\Resolvers\SubdomainResolver;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrada al sistema multi-tenant.
 *
 * Para cada request HTTP:
 *   1. Extrae el subdominio del host con {@see SubdomainResolver}.
 *   2. Si el host es central (panel SaaS) deja pasar sin hacer nada:
 *      el `search_path` se queda en `public` por defecto y el
 *      superadmin trabaja contra las tablas globales.
 *   3. Si el host es subdominio de tenant, le pide al manager que
 *      resuelva el slug y aplique `SET search_path` a la conexión.
 *
 * Las excepciones (`TenantNotFoundException`, `TenantSuspendedException`)
 * se dejan **subir libremente**: el handler global registrado en
 * `bootstrap/app.php` se encarga de renderizar páginas Inertia
 * específicas (`tenant/errors/not-found`, `tenant/errors/blocked`)
 * en lugar de la pantalla 4xx genérica de Laravel.
 *
 * Se registra automáticamente al inicio del grupo `web` para que
 * cualquier ruta del subdominio reciba el contexto antes incluso
 * de la sesión y la autenticación.
 */
class ResolveTenant
{
    public function __construct(
        protected SubdomainResolver $resolver,
        protected TenantManager $manager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Evita que un search_path residual de otro tenant en la misma
        // conexión PgSQL afecte la resolución o las tablas de `public`.
        $this->manager->forget();

        $slug = $this->resolver->resolveFromRequest($request);

        if ($slug === null) {
            return $next($request);
        }

        $this->manager->resolveBySlug($slug);

        // Inyectamos el slug como default del parámetro de dominio para que
        // `route('tenant.*')` no requiera pasarlo manualmente. Sin esto,
        // generar la URL de un redirect (login fallido, logout, etc.)
        // fallaría con "Missing required parameter 'tenant_subdomain'".
        URL::defaults(['tenant_subdomain' => $slug]);

        // El comodín `{tenant_subdomain}` del dominio se elimina de los
        // parámetros de la ruta: si se queda, Laravel lo pasa POSICIONALMENTE
        // como primer argumento del controller y desplaza los parámetros
        // reales (p. ej. `Paciente $paciente` recibiría el slug como string).
        $request->route()?->forgetParameter('tenant_subdomain');

        return $next($request);
    }
}
