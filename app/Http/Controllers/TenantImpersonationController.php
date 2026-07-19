<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\ImpersonationAuditLogger;
use App\Support\Tenancy\TenantImpersonationAcceptUrl;
use App\Support\Tenancy\TenantImpersonationCentralUrl;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Soporte: permitir al superadmin operar en el panel de una clínica sin segunda cuenta.
 *
 * Flujo: POST central → token único en cache → redirect al subdominio → GET `/impersonate/accept`
 * establece sesión en ese host con bandera `tenant_impersonation`.
 */
class TenantImpersonationController extends Controller
{
    private const CACHE_PREFIX = 'tenant_impersonate:';

    private const CACHE_TTL_SECONDS = 300;

    public function start(Request $request, Tenant $tenant): Response
    {
        abort_unless($request->user()?->can('plataforma-tenants.impersonate'), 403);
        abort_unless($request->user()?->isPlatformSuperadmin(), 403);

        $allowed = (array) config('tenant.allowed_states', ['active', 'trial', 'grace']);
        if (! in_array($tenant->estado, $allowed, true)) {
            throw ValidationException::withMessages([
                'tenant' => __('tenants.impersonate.invalid_state'),
            ]);
        }

        $slug = trim((string) $tenant->slug);
        if ($slug === '') {
            throw ValidationException::withMessages([
                'tenant' => __('tenants.impersonate.invalid_slug'),
            ]);
        }


        $token = Str::random(64);
        Cache::put(
            self::CACHE_PREFIX.$token,
            [
                'superadmin_id' => (string) $request->user()->id,
                'tenant_id' => (string) $tenant->getKey(),
                'central_origin' => TenantImpersonationCentralUrl::originFromRequest($request),
            ],
            now()->addSeconds(self::CACHE_TTL_SECONDS),
        );

        return Inertia::location(TenantImpersonationAcceptUrl::build($tenant, $token, $request));
    }

    public function accept(
        Request $request,
        TenantManager $manager,
        ImpersonationAuditLogger $auditLogger,
    ): RedirectResponse
    {
        $token = (string) $request->query('token', '');
        if ($token === '' || strlen($token) < 32) {
            abort(404);
        }

        /** @var array{superadmin_id?: string, tenant_id?: string, central_origin?: string}|null $payload */
        $payload = Cache::pull(self::CACHE_PREFIX.$token);

        if (! is_array($payload)
            || empty($payload['superadmin_id'])
            || empty($payload['tenant_id'])) {
            return redirect()
                ->route('login')
                ->with('error', __('tenants.impersonate.expired_token'));
        }

        $currentTenantId = $manager->check() ? $manager->id() : null;
        if ($currentTenantId === null || $currentTenantId !== $payload['tenant_id']) {
            abort(404);
        }

        /** @var User|null $superadmin */
        $superadmin = User::query()->whereKey($payload['superadmin_id'])->first();

        if ($superadmin === null || ! $superadmin->isPlatformSuperadmin()) {
            abort(403);
        }

        Auth::guard('web')->login($superadmin);
        $request->session()->regenerate();

        $tenantModel = $manager->current()?->tenant
            ?? Tenant::query()->whereKey($payload['tenant_id'])->firstOrFail();

        $label = trim((string) ($tenantModel->nombre_comercial ?: '')) !== ''
            ? trim((string) $tenantModel->nombre_comercial)
            : $tenantModel->razon_social;

        $centralOrigin = isset($payload['central_origin']) && is_string($payload['central_origin'])
            ? trim($payload['central_origin'])
            : '';
        $auditLog = $auditLogger->logStarted(
            $superadmin,
            $tenantModel,
            $request,
            $centralOrigin !== '' ? $centralOrigin : null,
        );

        $request->session()->put('tenant_impersonation', [
            'tenant_id' => $payload['tenant_id'],
            'tenant_label' => $label,
            'started_at' => now()->toIso8601String(),
            'central_origin' => $centralOrigin !== '' ? $centralOrigin : null,
            'audit_log_id' => (string) $auditLog->getKey(),
        ]);

        return redirect()
            ->route('dashboard')
            ->with('success', __('tenants.impersonate.entered'));
    }

    public function leave(
        Request $request,
        ImpersonationAuditLogger $auditLogger,
    ): Response|RedirectResponse
    {
        $user = Auth::guard('web')->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        abort_unless($user instanceof User && $user->isPlatformSuperadmin(), 403);

        $session = $request->session();
        /** @var array{tenant_id?: string, central_origin?: string|null}|null $imp */
        $imp = $session->get('tenant_impersonation');

        if (! is_array($imp) || empty($imp['tenant_id'])) {
            return redirect()->route('dashboard');
        }

        $centralOrigin = isset($imp['central_origin']) && is_string($imp['central_origin'])
            ? trim($imp['central_origin'])
            : '';

        $loginUrl = $centralOrigin !== ''
            ? TenantImpersonationCentralUrl::loginUrl($centralOrigin)
            : TenantImpersonationCentralUrl::fallbackLoginUrl($request);

        $auditLogId = isset($imp['audit_log_id']) && is_string($imp['audit_log_id'])
            ? $imp['audit_log_id']
            : null;
        $auditLogger->logEnded($auditLogId);

        $session->forget('tenant_impersonation');

        Auth::guard('web')->logout();
        $session->invalidate();
        $session->regenerateToken();

        return Inertia::location($loginUrl);
    }
}
