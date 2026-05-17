<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea el acceso a rutas que SOLO existen en el dominio central
 * (panel SaaS de Orvae/superadmin).
 *
 * Se usa en `/plataforma/*`. Si alguien entrara desde un subdominio
 * de tenant a `/plataforma/tenants`, devolvemos 404 sin filtrar
 * información: ese panel simplemente no existe desde el punto de
 * vista de una clínica.
 *
 * Funciona en pareja con `ResolveTenant`: éste decide si hay tenant
 * o no, y nosotros vetamos el caso en el que sí lo hay.
 */
class EnsureNoTenant
{
    public function __construct(protected TenantManager $manager) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->manager->check()) {
            abort(404);
        }

        return $next($request);
    }
}
