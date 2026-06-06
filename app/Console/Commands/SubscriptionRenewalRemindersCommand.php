<?php

namespace App\Console\Commands;

use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Services\Subscriptions\SubscriptionRenewalReminderScanner;
use Illuminate\Console\Command;

class SubscriptionRenewalRemindersCommand extends Command
{
    protected $signature = 'vetsaas:subscription-renewal-reminders
                            {--verbose : Muestra por qué se omitió cada suscripción de pago}
                            {--dry-run : Solo diagnostica, sin enviar WhatsApp}';

    protected $description = 'Envía WhatsApp de aviso de vencimiento de suscripción a los tenants (plataforma → clínica)';

    public function handle(
        SubscriptionRenewalReminderScanner $scanner,
        PlatformWhatsAppMessenger $messenger,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $verbose = (bool) $this->option('verbose') || $dryRun;

        if (! $dryRun && ! $messenger->isReady()) {
            $this->warn('Sesión OpenWA de plataforma no configurada o sin conectar.');
            $this->line('Conéctala en Plataforma → Avisos renovación (botón Conectar WhatsApp + QR).');
            $this->line('Usa --dry-run --verbose para ver el diagnóstico sin WhatsApp.');

            return self::SUCCESS;
        }

        $report = $scanner->runWithReport(send: ! $dryRun);
        $result = $report['stats'];

        if ($dryRun) {
            $this->info(sprintf(
                'Diagnóstico (sin envío): %d suscripciones de pago revisadas.',
                $result['scanned'],
            ));
        } else {
            $this->info(sprintf(
                'Recordatorios renovación: %d revisadas, %d enviados, %d omitidos, %d fallidos',
                $result['scanned'],
                $result['sent'],
                $result['skipped'],
                $result['failed'],
            ));
        }

        $this->line(sprintf(
            'WhatsApp plataforma: %s · Días de aviso: %s · Hoy: %s',
            $report['whatsapp_ready'] ? 'conectado' : 'no conectado',
            implode(', ', $report['reminder_days']),
            now()->timezone(config('app.timezone', 'America/Lima'))->format('d/m/Y'),
        ));

        if ($verbose && $report['rows'] !== []) {
            $this->newLine();
            $this->table(
                ['Clínica', 'Slug', 'Vence', 'Días', 'Resultado', 'Motivo'],
                collect($report['rows'])->map(fn (array $row): array => [
                    $row['tenant'],
                    $row['slug'] ?? '—',
                    $row['anchor_at']
                        ? \Carbon\Carbon::parse($row['anchor_at'])
                            ->timezone(config('app.timezone', 'America/Lima'))
                            ->format('d/m/Y H:i')
                        : '—',
                    $row['days_until'] ?? '—',
                    $row['result'],
                    $row['skip_reason'] ?? ($row['result'] === 'sent' ? 'Enviado' : '—'),
                ])->all(),
            );
        } elseif ($result['scanned'] > 0 && $result['sent'] === 0 && ! $verbose) {
            $this->newLine();
            $this->comment('Ninguna suscripción cumple hoy las condiciones de envío.');
            $this->comment('Ejecuta con --verbose o --dry-run --verbose para ver el detalle por clínica.');
        }

        return self::SUCCESS;
    }
}
