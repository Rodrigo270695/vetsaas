<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SalesConversation;
use Illuminate\Console\Command;

/**
 * Atajo para reactivar el bot (alias de salesbot:pause --resume).
 *
 * Uso:
 *   php artisan salesbot:resume 51986709811
 */
final class SalesBotResumeCommand extends Command
{
    protected $signature = 'salesbot:resume
                            {phone : Número de teléfono sin + (ej: 51986709811)}';

    protected $description = 'Reactiva el bot de ventas para un número de WhatsApp';

    public function handle(): int
    {
        $phone = (string) $this->argument('phone');

        $conversation = SalesConversation::query()->where('phone', $phone)->first();
        if ($conversation === null) {
            $this->warn("No existe conversación para el número {$phone}.");

            return self::FAILURE;
        }

        $conversation->resumeBot();
        $this->info("✓ Bot REACTIVADO para {$phone} ({$conversation->prospect_name}).");

        return self::SUCCESS;
    }
}
