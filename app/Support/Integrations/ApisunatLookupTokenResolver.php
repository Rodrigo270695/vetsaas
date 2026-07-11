<?php

namespace App\Support\Integrations;

use App\Models\ClinicSetting;
use App\Support\Fel\ApisunatCredentialResolver;
use Throwable;

/**
 * Token para consultas DNI/RUC (APIs de apoyo Lucode / APISUNAT).
 *
 * Prioridad: variable global en .env → token APISUNAT de la clínica (facturación).
 */
final class ApisunatLookupTokenResolver
{
    public static function resolve(): ?string
    {
        $global = trim((string) config('services.apisunat_lookup.token', ''));
        if ($global !== '') {
            return $global;
        }

        try {
            if (! class_exists(ClinicSetting::class)) {
                return null;
            }

            $clinic = ClinicSetting::query()->first();
            if ($clinic === null || ! ApisunatCredentialResolver::estaConfigurado($clinic)) {
                return null;
            }

            return ApisunatCredentialResolver::fromClinicSetting($clinic)['token'];
        } catch (Throwable) {
            return null;
        }
    }
}
