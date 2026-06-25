<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SalesConversation;
use Illuminate\Console\Command;

/**
 * Pausa o reactiva el bot para un número específico.
 *
 * Uso:
 *   php artisan salesbot:pause 51986709811     → Rodrigo toma el control manual
 *   php artisan salesbot:resume 51986709811    → el bot vuelve a responder
 *   php artisan salesbot:pause --list          → ver todas las conversaciones y su estado
 */
final class SalesBotPauseCommand extends Command
{
    protected $signature = 'salesbot:pause
                            {phone? : Número de teléfono sin + (ej: 51986709811)}
                            {--resume : Reactivar el bot en vez de pausarlo}
                            {--list : Listar todas las conversaciones}';

    protected $description = 'Pausa o reactiva el bot de ventas para un número de WhatsApp';

    public function handle(): int
    {
        if ($this->option('list')) {
            $this->showList();

            return self::SUCCESS;
        }

        $phone = (string) $this->argument('phone');
        if ($phone === '') {
            $this->error('Especifica un número. Ej: php artisan salesbot:pause 51986709811');
            $this->info('O usa --list para ver todas las conversaciones.');

            return self::FAILURE;
        }

        $conversation = SalesConversation::query()->where('phone', $phone)->first();
        if ($conversation === null) {
            $this->warn("No existe conversación para el número {$phone}.");
            $this->info('Si quieres bloquear ese número desde el inicio, agrega una entrada manual en la tabla sales_conversations.');

            return self::FAILURE;
        }

        $reactivar = $this->option('resume');

        if ($reactivar) {
            $conversation->resumeBot();
            $this->info("✓ Bot REACTIVADO para {$phone} ({$conversation->prospect_name}).");
            $this->comment('El bot volverá a responder automáticamente a este número.');
        } else {
            $conversation->pauseBot();
            $this->info("✓ Bot PAUSADO para {$phone} ({$conversation->prospect_name}).");
            $this->comment('Ahora puedes escribirle manualmente desde WhatsApp sin que el bot interfiera.');
            $this->comment('Para reactivarlo: php artisan salesbot:resume '.$phone);
        }

        return self::SUCCESS;
    }

    private function showList(): void
    {
        $conversations = SalesConversation::query()
            ->orderByDesc('last_message_at')
            ->get(['phone', 'prospect_name', 'bot_active', 'activation_trigger', 'turn_count', 'last_message_at']);

        if ($conversations->isEmpty()) {
            $this->info('No hay conversaciones registradas todavía.');

            return;
        }

        $this->table(
            ['Teléfono', 'Nombre', 'Bot', 'Trigger', 'Turnos', 'Último mensaje'],
            $conversations->map(fn ($c) => [
                $c->phone,
                $c->prospect_name ?? '-',
                $c->bot_active ? '✓ activo' : '⏸ pausado',
                $c->activation_trigger ?? '-',
                $c->turn_count,
                $c->last_message_at?->diffForHumans() ?? '-',
            ]),
        );
    }
}
