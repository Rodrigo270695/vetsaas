<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Venta;
use App\Services\Fel\FelEmisionVentaService;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * Une una venta local con un comprobante que Lucode ya tiene y VetSaaS no registró.
 * No vuelve a emitir.
 */
final class FelEnlazarComprobanteCommand extends Command
{
    protected $signature = 'vetsaas:fel-enlazar
        {slug : Slug del tenant}
        {venta : Número de venta, por ejemplo VTA-2026-00134}
        {serie : Serie SUNAT, por ejemplo BA02}
        {numero : Correlativo que ya figura en Lucode, por ejemplo 38}';

    protected $description = 'Enlaza una venta con una boleta o factura que ya está en Lucode, sin reemitir';

    public function handle(TenantManager $tenants, FelEmisionVentaService $emision): int
    {
        $slug = trim((string) $this->argument('slug'));
        $numeroVenta = trim((string) $this->argument('venta'));
        $serie = strtoupper(trim((string) $this->argument('serie')));
        $correlativo = (int) $this->argument('numero');

        if ($correlativo < 1) {
            $this->error('El correlativo tiene que ser mayor que cero.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()->where('slug', $slug)->first();
        if ($tenant === null) {
            $this->error('No existe el tenant '.$slug.'.');

            return self::FAILURE;
        }

        try {
            $documento = $tenants->runForTenant($tenant, function () use ($emision, $numeroVenta, $serie, $correlativo) {
                $venta = Venta::query()->where('numero', $numeroVenta)->first();
                if ($venta === null) {
                    throw new \RuntimeException('No existe la venta '.$numeroVenta.'.');
                }

                return $emision->enlazarComprobanteYaEnLucode($venta, $serie, $correlativo);
            }, enforceAccess: false);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Listo. %s quedó como %s (%s).',
            $numeroVenta,
            $documento->numero_completo,
            $documento->estado,
        ));

        return self::SUCCESS;
    }
}
