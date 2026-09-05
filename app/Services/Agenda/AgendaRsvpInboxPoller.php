<?php

declare(strict_types=1);

namespace App\Services\Agenda;

/**
 * Antes leía el WhatsApp de plataforma para confirmar citas con SI/NO.
 * Eso chocaba con el bot de ventas (superadmin): un «Sí» comercial confirmaba
 * turnos de clínicas (p. ej. CANELA). El RSVP solo corre en el webhook clinic-bot.
 */
final class AgendaRsvpInboxPoller
{
    /**
     * @param  (callable(string, int, ?string): void)|null  $trace
     * @return array{chats: int, applied: int, skipped: int}
     */
    public function poll(bool $dryRun = false, ?callable $trace = null): array
    {
        $trace && $trace('(sesion)', 0, 'omitido: RSVP no corre en el WhatsApp de plataforma (SalesBot)');

        return ['chats' => 0, 'applied' => 0, 'skipped' => 0];
    }
}
