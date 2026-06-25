<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SalesConversation;
use App\Support\WhatsApp\WhatsAppContactResolver;
use Illuminate\Console\Command;

/**
 * Intenta resolver teléfonos/nombres reales de leads guardados con ID @lid de WhatsApp.
 *
 * Uso:
 *   php artisan vetsaas:resolve-lid-leads
 *   php artisan vetsaas:resolve-lid-leads --dry-run
 */
final class ResolveLidLeadsCommand extends Command
{
    protected $signature = 'vetsaas:resolve-lid-leads {--dry-run : Solo muestra qué cambiaría}';

    protected $description = 'Resuelve teléfono y nombre de leads con ID privado (@lid) vía OpenWA.';

    public function handle(WhatsAppContactResolver $resolver): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = SalesConversation::query()
            ->where(function ($q) {
                $q->where('phone', 'like', 'lid:%')
                    ->orWhere('wa_chat_id', 'like', '%@lid');
            })
            ->get()
            ->filter(function (SalesConversation $conversation) use ($resolver): bool {
                if (str_starts_with($conversation->phone, 'lid:') || str_ends_with($conversation->wa_chat_id, '@lid')) {
                    return true;
                }

                return $resolver->looksLikeLidDigits(preg_replace('/\D/', '', $conversation->phone) ?? '');
            });

        if ($candidates->isEmpty()) {
            $this->info('No hay leads con ID @lid para resolver.');

            return self::SUCCESS;
        }

        $updated = 0;

        foreach ($candidates as $conversation) {
            $waChatId = $conversation->wa_chat_id;

            if ($waChatId === '' || ! str_ends_with($waChatId, '@lid')) {
                $digits = preg_replace('/\D/', '', $conversation->phone) ?? '';
                $waChatId = $digits !== '' ? $digits.'@lid' : '';
            }

            if ($waChatId === '') {
                $this->warn("Lead #{$conversation->id}: sin wa_chat_id, omitido.");

                continue;
            }

            $contact = $resolver->resolve(['from' => $waChatId, 'chatId' => $waChatId], null);

            $newPhone = $contact['phone'];
            $newName  = $contact['prospect_name'];

            $changes = [];

            if ($newPhone !== '' && $newPhone !== $conversation->phone) {
                $changes[] = "teléfono: {$conversation->phone} → {$newPhone}";
            }

            if ($newName !== null && $newName !== $conversation->prospect_name) {
                $changes[] = "nombre: ".($conversation->prospect_name ?? '—')." → {$newName}";
            }

            if ($changes === []) {
                $this->line("Lead #{$conversation->id}: sin cambios (OpenWA no devolvió datos nuevos).");

                continue;
            }

            $this->info("Lead #{$conversation->id}: ".implode(', ', $changes));

            if (! $dryRun) {
                if ($newPhone !== '' && $newPhone !== $conversation->phone) {
                    $conversation->phone = $newPhone;
                }
                if ($newName !== null) {
                    $conversation->prospect_name = $newName;
                }
                $conversation->save();
                $updated++;
            }
        }

        if ($dryRun) {
            $this->comment('Dry-run: no se guardó nada.');
        } else {
            $this->info("Listo. {$updated} lead(s) actualizado(s).");
        }

        return self::SUCCESS;
    }
}
