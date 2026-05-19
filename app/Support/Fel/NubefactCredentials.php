<?php

namespace App\Support\Fel;

/**
 * Credenciales de integración Nubefact por clínica (panel API).
 *
 * @see https://www.nubefact.com/integracion
 */
final readonly class NubefactCredentials
{
    public function __construct(
        public string $apiRuta,
        public string $apiToken,
    ) {}
}
