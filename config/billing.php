<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Días de gracia tras vencimiento de cobro o trial
    |--------------------------------------------------------------------------
    |
    | Durante el grace el tenant conserva acceso (subscription.estado = grace;
    | el tenant se muestra como active vía sync). Pasado este plazo sin pago
    | registrado, el supervisor pasa a suspended.
    |
    */
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Recordatorios de renovación (plataforma → tenant)
    |--------------------------------------------------------------------------
    |
    | Días antes del vencimiento (proximo_cobro_at / trial_ends_at) en que se
    | envía WhatsApp al teléfono del tenant. Cada valor es un día exacto
    | (ej. 7,3,1 → tres avisos por ciclo). Requiere sesión OpenWA de plataforma.
    |
    */
    'renewal_reminder_days' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('BILLING_RENEWAL_REMINDER_DAYS', '7,3,1'))
    ))),

    /*
    | URL de checkout Orvae para renovar (proyecto orvaepe). Plantilla recomendada:
    |   https://orvae.pe/renovar/vetsaas?tenant={tenant}&plan={plan}&ciclo={ciclo}
    | La ruta /renovar/vetsaas redirige al carrito con el SKU correcto.
    */
    'renewal_url' => rtrim((string) env('ORVAE_RENEWAL_URL', 'https://orvae.pe/renovar/vetsaas'), '/'),

];
