<?php

declare(strict_types=1);

return [
    /*
    | Vigencia de los enlaces públicos enviados al propietario.
    | El enlace está firmado y deja de funcionar al vencer.
    */
    'public_link_ttl_minutes' => (int) env('CLINIC_DOCUMENT_PUBLIC_LINK_TTL_MINUTES', 10080),
];
