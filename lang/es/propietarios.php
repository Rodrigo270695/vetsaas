<?php

return [
    'validation' => [
        'documento_duplicado' => 'Ya existe un propietario registrado con este tipo y número de documento en esta clínica.',
    ],
    'consulta' => [
        'rate_limit' => 'Se alcanzó el límite de consultas al servicio externo. Complete los datos manualmente o intente más tarde.',
        'no_disponible' => 'El servicio de consulta no está disponible en este momento. Intente de nuevo o complete los datos manualmente.',
        'error_generico' => 'No se pudo consultar el documento (HTTP :status). Verifique el número o complete los datos manualmente.',
        'token_missing' => 'Consulta de documento no disponible: el servicio no está configurado en el servidor.',
    ],
];
