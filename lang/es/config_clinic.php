<?php

return [
    'validation' => [
        'emite_sin_plan' => 'Tu plan actual no incluye emisión de comprobantes electrónicos SUNAT. Actualiza el plan o desactiva esta opción.',
        'emite_sin_tenant' => 'No se pudo validar el contexto de la clínica.',
    ],
    'nubefact_ruta_migration_pending' => 'Se guardó la configuración, pero la ruta de Nubefact no pudo persistirse: falta aplicar migraciones del tenant en el servidor (vetsaas:tenant-migrate-all).',
];
