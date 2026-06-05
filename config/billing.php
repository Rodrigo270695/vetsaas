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

];
