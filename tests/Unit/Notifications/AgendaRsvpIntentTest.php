<?php

declare(strict_types=1);

use App\Support\Agenda\AgendaRsvpIntent;

it('detecta confirmaciones cortas', function (string $body): void {
    expect(AgendaRsvpIntent::parse($body))->toBe(AgendaRsvpIntent::YES);
})->with(['SI', 'sí', 'ok', 'confirmo', 'Si voy']);

it('detecta cancelaciones cortas', function (string $body): void {
    expect(AgendaRsvpIntent::parse($body))->toBe(AgendaRsvpIntent::NO);
})->with(['NO', 'no puedo', 'cancelar']);

it('ignora mensajes que no son RSVP', function (string $body): void {
    expect(AgendaRsvpIntent::parse($body))->toBeNull();
})->with([
    '¿Qué horarios atienden?',
    'si me puedes decir el precio de la consulta',
    'no se si llegar a tiempo mañana',
    '',
]);
