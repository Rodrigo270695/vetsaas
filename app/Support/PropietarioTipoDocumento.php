<?php

namespace App\Support;

/**
 * Catálogo de tipos de documento para titulares (Perú / uso clínico).
 * Mantener alineado con `resources/js/lib/document-type-options.ts`.
 */
final class PropietarioTipoDocumento
{
    /** @var list<string> */
    public const VALUES = ['DNI', 'RUC', 'CE', 'PAS', 'OTR'];
}
