<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Agenda\AgendaRsvpInboxPoller;
use Illuminate\Console\Command;

/**
 * Lee el historial OpenWA de chats con recordatorio reciente y aplica SI/NO.
 */
final class AgendaRsvpPollInboxCommand extends Command
{
    protected $signature = 'vetsaas:agenda-rsvp-poll-inbox
                            {--dry-run : Solo listar; no confirma ni responde}
                            {--debug : Muestra chat, cantidad de mensajes y un extracto}';

    protected $description = 'No-op: la confirmación SI/NO no usa el WhatsApp de plataforma (SalesBot)';

    public function handle(AgendaRsvpInboxPoller $poller): int
    {
        $debug = (bool) $this->option('debug');
        $trace = $debug
            ? function (string $chatId, int $count, ?string $sample): void {
                $this->line(sprintf(
                    '  %s  msgs=%d  %s',
                    $chatId,
                    $count,
                    $sample !== null && $sample !== '' ? $sample : '(sin texto)',
                ));
            }
            : null;

        $stats = $poller->poll((bool) $this->option('dry-run'), $trace);

        $this->info("Chats revisados: {$stats['chats']}");
        $this->info("Aplicados:       {$stats['applied']}");
        $this->info("Sin cambio:      {$stats['skipped']}");

        return self::SUCCESS;
    }
}
