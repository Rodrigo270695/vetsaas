<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\OpenWa\PlatformSalesBotWebhookRegistrar;
use Illuminate\Console\Command;
use Throwable;

/**
 * Re-registra / actualiza el webhook SalesBot en OpenWA con el secret actual.
 *
 * Uso:
 *   php artisan salesbot:register-webhook
 */
final class SalesBotRegisterWebhookCommand extends Command
{
    protected $signature = 'salesbot:register-webhook
                            {--no-test : No disparar el POST de prueba de OpenWA}';

    protected $description = 'Alinea el webhook OpenWA de SalesBot con SALESBOT_WEBHOOK_SECRET';

    public function handle(PlatformSalesBotWebhookRegistrar $registrar): int
    {
        try {
            $result = $registrar->ensure(runTest: ! $this->option('no-test'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Sesión OpenWA: {$result['session_id']}");
        $this->info("URL webhook:   {$result['webhook_url']}");
        $this->info("Webhook id:    {$result['webhook_id']}");
        $this->info("Acción:        {$result['action']}");

        if ($result['deleted_duplicates'] > 0) {
            $this->warn("Duplicados eliminados: {$result['deleted_duplicates']}");
        }

        $test = $result['test'] ?? null;
        if (is_array($test)) {
            $ok = (bool) ($test['success'] ?? false);
            $status = $test['statusCode'] ?? ($test['error'] ?? 'n/a');
            if ($ok) {
                $this->info("Test OpenWA → Laravel: OK (HTTP {$status})");
            } else {
                $this->error("Test OpenWA → Laravel: FALLÓ ({$status})");
                $this->line('Revisa storage/logs/laravel.log (SalesBot webhook 401).');

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
