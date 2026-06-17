<?php

namespace App\Support\Fel;

use App\Models\ClinicSetting;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use Throwable;

/**
 * Credenciales APISUNAT (Lucode) por clínica, almacenadas cifradas en cfg_clinic_settings.
 */
final class ApisunatCredentialResolver
{
    /**
     * @return array{token: string, mode: 'sandbox'|'produccion'}
     */
    public static function fromClinicSetting(ClinicSetting $clinic): array
    {
        if (! (bool) $clinic->apisunat_configurado || empty($clinic->apisunat_token_enc)) {
            throw new RuntimeException(__('caja.ventas.fel.apisunat_no_configurado'));
        }

        try {
            $token = Crypt::decryptString($clinic->apisunat_token_enc);
        } catch (Throwable) {
            throw new RuntimeException(__('caja.ventas.fel.apisunat_token_invalido'));
        }

        $mode = in_array($clinic->apisunat_mode, ['sandbox', 'produccion'], true)
            ? $clinic->apisunat_mode
            : 'sandbox';

        return [
            'token' => $token,
            'mode' => $mode,
        ];
    }

    public static function estaConfigurado(ClinicSetting $clinic): bool
    {
        return (bool) $clinic->apisunat_configurado
            && filled($clinic->apisunat_token_enc);
    }
}
