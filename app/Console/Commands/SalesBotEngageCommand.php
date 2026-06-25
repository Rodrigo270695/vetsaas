<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SalesConversation;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Services\Sales\SalesBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Fuerza al bot a intervenir en un chat de WhatsApp (crea conversación si no existe).
 *
 * Útil cuando el lead escribió pero no activó keywords automáticas.
 *
 * Uso:
 *   php artisan salesbot:engage 51961777549
 *   php artisan salesbot:engage 51961777549 --message="Buenos días, información de costos"
 *   php artisan salesbot:engage 51961777549 --name="Beatriz Moscol" --dry-run
 */
final class SalesBotEngageCommand extends Command
{
    protected $signature = 'salesbot:engage
        {phone : Número sin + (ej: 51961777549 o 961777549)}
        {--message= : Mensaje del lead para contexto de la IA (default: saludo genérico)}
        {--name= : Nombre del prospecto si no está en la BD}
        {--dry-run : Genera la respuesta pero no envía por WhatsApp}';

    protected $description = 'Activa el bot y envía una respuesta IA a un lead de WhatsApp';

    public function __construct(
        private readonly SalesBotService $botService,
        private readonly PlatformWhatsAppMessenger $messenger,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! config('salesbot.enabled')) {
            $this->error('SALESBOT_ENABLED=false en .env — el bot está desactivado.');

            return self::FAILURE;
        }

        $phone   = $this->normalizePhone((string) $this->argument('phone'));
        $message = (string) ($this->option('message') ?: 'Hola, quisiera información sobre VetSaaS y los costos.');
        $name    = $this->option('name') !== null ? trim((string) $this->option('name')) : null;
        $dryRun  = (bool) $this->option('dry-run');

        if ($phone === '' || strlen($phone) < 8) {
            $this->error('Número inválido. Usa formato 51961777549 o 961777549.');

            return self::FAILURE;
        }

        $waChatId     = $phone.'@c.us';
        $conversation = $this->botService->findExistingConversation($phone, $waChatId);

        if ($conversation === null) {
            $conversation = $this->botService->createConversation(
                phone: $phone,
                waChatId: $waChatId,
                prospectName: $name,
                trigger: 'manual:engage',
            );
            $this->info("Conversación creada para {$phone}.");
        } else {
            $conversation->resumeBot();
            if ($name !== null && ($conversation->prospect_name === null || $conversation->prospect_name === '')) {
                $conversation->prospect_name = $name;
                $conversation->save();
            }
            $this->info("Conversación existente reactivada ({$phone}).");
        }

        $this->line("Contexto: \"{$message}\"");

        try {
            $reply = $this->botService->reply($conversation, $message);
        } catch (\Throwable $e) {
            $this->error('OpenAI falló: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->comment('── Respuesta IA ──');
        $this->line($reply);
        $this->newLine();

        if ($dryRun) {
            $this->warn('Dry-run: no se envió por WhatsApp.');

            return self::SUCCESS;
        }

        if (! $this->messenger->isReady()) {
            $this->error('OpenWA no está conectado. La respuesta quedó guardada pero no se envió.');

            return self::FAILURE;
        }

        try {
            $this->messenger->sendText($conversation->wa_chat_id, $reply);
        } catch (\Throwable $e) {
            Log::error('salesbot:engage send failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            $this->error('Error al enviar por WhatsApp: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("✓ Mensaje enviado a {$phone} ({$conversation->prospect_name}).");

        return self::SUCCESS;
    }

    private function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            return '51'.$digits;
        }

        return $digits;
    }
}
