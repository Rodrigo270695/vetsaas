<?php

return [
    'validation' => [
        'emite_sin_plan' => 'Your current plan does not include SUNAT electronic invoicing. Upgrade the plan or turn this option off.',
        'emite_sin_tenant' => 'Could not validate the clinic context.',
    ],
    'nubefact_ruta_migration_pending' => 'Settings were saved, but the Nubefact API route could not be stored: run tenant migrations on the server (vetsaas:tenant-migrate-all).',
];
