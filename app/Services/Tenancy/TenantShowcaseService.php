<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Support\Clinic\ClinicBrandingUrls;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Clientes VetSaaS visibles en marketing (Orvae): plan de pago + logo propio.
 */
final class TenantShowcaseService
{
    public function __construct(
        private readonly TenantManager $manager,
    ) {}

    /**
     * @return list<array{
     *     slug: string,
     *     name: string,
     *     logo_url: string,
     *     plan: string|null,
     *     subdomain_url: string
     * }>
     */
    public function clientsForCarousel(): array
    {
        return Cache::remember('vetsaas.public.showcase_clients', 900, function (): array {
            $tenants = Tenant::query()
                ->whereIn('estado', ['active', 'trial'])
                ->whereHas('subscriptions', function ($query): void {
                    $query
                        ->whereIn('estado', ['active', 'trial'])
                        ->whereHas('plan', fn ($planQuery) => $planQuery->excludingFree());
                })
                ->with([
                    'subscriptions' => fn ($q) => $q
                        ->whereIn('estado', ['active', 'trial'])
                        ->latest()
                        ->limit(1),
                    'subscriptions.plan:id,codigo,nombre',
                ])
                ->orderByDesc('created_at')
                ->get();

            $items = [];

            foreach ($tenants as $tenant) {
                $branding = $this->resolveBranding($tenant);

                if (! $branding['has_custom_logo']) {
                    continue;
                }

                $subscription = $tenant->subscriptions->first();
                $label = trim((string) ($tenant->nombre_comercial ?: ''));
                if ($label === '') {
                    $label = (string) $tenant->razon_social;
                }

                $items[] = [
                    'slug' => (string) $tenant->slug,
                    'name' => $label,
                    'logo_url' => $branding['logo_url'],
                    'plan' => $subscription?->plan?->nombre,
                    'subdomain_url' => $this->tenantLoginUrl($tenant),
                ];
            }

            return $items;
        });
    }

    public function forgetCache(): void
    {
        Cache::forget('vetsaas.public.showcase_clients');
    }

    /**
     * @return array{logo_url: string, has_custom_logo: bool}
     */
    private function resolveBranding(Tenant $tenant): array
    {
        return ClinicBrandingUrls::resolveForTenant($this->manager, $tenant);
    }

    private function tenantLoginUrl(Tenant $tenant): string
    {
        $scheme = (string) config('orvae.tenant.scheme', 'https');
        $domain = (string) config('tenant.root_domain', config('orvae.tenant.domain', 'vetsaas.orvae.pe'));
        $path = (string) config('orvae.tenant.login_path', '/login');

        return sprintf('%s://%s.%s%s', $scheme, $tenant->slug, $domain, $path);
    }
}
