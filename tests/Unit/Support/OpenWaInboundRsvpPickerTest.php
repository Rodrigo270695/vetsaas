<?php

declare(strict_types=1);

use App\Support\Agenda\OpenWaInboundRsvpPicker;

it('elige el SI inbound más reciente e ignora fromMe', function (): void {
    $hit = OpenWaInboundRsvpPicker::latest([
        [
            'id' => 'out-1',
            'fromMe' => true,
            'body' => 'Responde SI para confirmar',
            'timestamp' => 100,
        ],
        [
            'id' => 'in-si',
            'fromMe' => false,
            'from' => '51911111111@c.us',
            'body' => 'Si',
            'timestamp' => 200,
        ],
        [
            'id' => 'in-hola',
            'fromMe' => false,
            'from' => '51911111111@c.us',
            'body' => 'hola',
            'timestamp' => 300,
        ],
    ], '51911111111@c.us');

    expect($hit)->not->toBeNull()
        ->and($hit['body'])->toBe('Si')
        ->and($hit['message_id'])->toBe('in-si')
        ->and($hit['phone'])->toBe('51911111111');
});
