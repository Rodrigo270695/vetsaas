<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Programa de referidos VetSaaS
    |--------------------------------------------------------------------------
    |
    | El referido paga el 1.er mes → el referidor acumula días en bolsa.
    | En la próxima renovación del referidor (Orvae o manual) esos días
    | se suman a current_period_end / proximo_cobro_at.
    */

    'reward_days' => (int) env('REFERRAL_REWARD_DAYS', 15),

    /** Tope de recompensas "earned" por referidor en los últimos 30 días. */
    'max_rewards_per_month' => (int) env('REFERRAL_MAX_REWARDS_PER_MONTH', 10),

    /**
     * URL de landing/checkout con placeholder {code}.
     * Ej: https://orvae.pe/vetsaas?ref={code}
     */
    'share_url_template' => (string) env(
        'REFERRAL_SHARE_URL',
        'https://orvae.pe/?ref={code}',
    ),
];
