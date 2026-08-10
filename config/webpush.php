<?php

declare(strict_types=1);

return [

    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:soporte@orvae.pe'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

];
