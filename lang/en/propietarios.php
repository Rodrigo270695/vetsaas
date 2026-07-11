<?php

return [
    'validation' => [
        'documento_duplicado' => 'An owner with this document type and number is already registered in this clinic.',
    ],
    'consulta' => [
        'rate_limit' => 'The external lookup service rate limit was reached. Enter the details manually or try again later.',
        'no_disponible' => 'The lookup service is unavailable right now. Try again or enter the details manually.',
        'error_generico' => 'Could not look up the document (HTTP :status). Verify the number or enter the details manually.',
        'token_missing' => 'Document lookup is unavailable: the service is not configured on the server.',
        'apisunat_token_missing' => 'Fallback lookup (Lucode) is unavailable: configure APISUNAT_LOOKUP_TOKEN or the clinic APISUNAT token.',
        'apisunat_token_invalid' => 'The Lucode (APISUNAT) token is invalid. Check your configuration.',
    ],
];
