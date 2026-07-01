<?php

declare(strict_types=1);

namespace App\Support\Clinic;

use App\Models\ClinicSetting;
use App\Models\Tenant;
use App\Tenancy\TenantManager;
use Throwable;

/**
 * URLs de logo de clínica con fallback al branding VetSaaS.
 */
final class ClinicBrandingUrls
{
    public static function default(): string
    {
        return asset('logo.png');
    }

    public static function fromClinicSetting(?ClinicSetting $setting): string
    {
        $logoUrl = $setting?->logo_url;

        if (! is_string($logoUrl) || $logoUrl === '') {
            return self::default();
        }

        return self::withCacheBuster($logoUrl, $setting->updated_at?->timestamp);
    }

    public static function forTenant(TenantManager $manager, Tenant $tenant): string
    {
        if (! is_string($tenant->slug) || $tenant->slug === '') {
            return self::default();
        }

        try {
            return $manager->runForSlug($tenant->slug, function (): string {
                $setting = ClinicSetting::query()->first();

                return self::fromClinicSetting($setting);
            });
        } catch (Throwable) {
            return self::default();
        }
    }

    /**
     * @return array{
     *     logo_url: string,
     *     updated_at: string|null,
     *     color_primario: string|null,
     *     color_secundario: string|null
     * }
     */
    public static function sharedPayload(?ClinicSetting $setting): array
    {
        $logoUrl = self::fromClinicSetting($setting);

        return [
            'logo_url' => $logoUrl,
            'updated_at' => $setting?->updated_at?->toIso8601String(),
            'color_primario' => self::sanitizeHexColor($setting?->color_primario),
            'color_secundario' => self::sanitizeHexColor($setting?->color_secundario),
        ];
    }

    private static function sanitizeHexColor(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = trim($value);

        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $normalized) !== 1) {
            return null;
        }

        return strtoupper($normalized);
    }

    private static function withCacheBuster(string $logoUrl, ?int $version): string
    {
        if ($version === null) {
            return $logoUrl;
        }

        $separator = str_contains($logoUrl, '?') ? '&' : '?';

        return $logoUrl.$separator.'v='.$version;
    }
}
