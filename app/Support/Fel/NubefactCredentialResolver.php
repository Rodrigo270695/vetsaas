<?php

namespace App\Support\Fel;

use App\Models\ClinicSetting;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

final class NubefactCredentialResolver
{
    public static function fromClinicSetting(ClinicSetting $clinic): NubefactCredentials
    {
        $ruta = trim((string) ($clinic->nubefact_api_ruta ?? ''));
        if ($ruta === '') {
            throw new RuntimeException('Ruta de API de Nubefact no configurada.');
        }

        if (empty($clinic->nubefact_token_enc)) {
            throw new RuntimeException('Token de Nubefact no configurado.');
        }

        $token = trim(Crypt::decryptString($clinic->nubefact_token_enc));
        if ($token === '') {
            throw new RuntimeException('Token de Nubefact vacío.');
        }

        return new NubefactCredentials(apiRuta: $ruta, apiToken: $token);
    }
}
