<?php

namespace App\Console\Commands;

use App\Models\ClinicSetting;
use App\Models\FelSerie;
use App\Models\Sede;
use App\Models\Tenant;
use App\Support\Fel\FelSerieResolver;
use App\Support\Fel\SunatSerieCodigo;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Muestra qué series usaría VetSaaS al emitir y si Nubefact está configurado.
 */
class NubefactDiagnoseCommand extends Command
{
    protected $signature = 'vetsaas:nubefact-diagnose {slug : Slug del tenant (subdominio)}';

    protected $description = 'Diagnóstico de credenciales Nubefact y series SUNAT por sede (error código 21)';

    public function handle(TenantManager $manager, FelSerieResolver $series): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('Requiere PostgreSQL.');

            return self::FAILURE;
        }

        $slug = strtolower(trim((string) $this->argument('slug')));
        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant === null) {
            $this->error("No existe tenant: {$slug}");

            return self::FAILURE;
        }

        $this->info("Tenant: {$tenant->razon_social} ({$slug})");
        $this->line("Schema: {$tenant->schema_name}");
        $this->newLine();

        $manager->runForSlug($slug, function () use ($series, $tenant): void {
            $clinic = ClinicSetting::current();
            $hasRutaCol = Schema::hasColumn('cfg_clinic_settings', 'nubefact_api_ruta');

            $this->info('Nubefact (Configuración › General)');
            $this->line('  emite_comprobantes_sunat: '.($clinic->emite_comprobantes_sunat ? 'sí' : 'no'));
            $this->line('  nubefact_configurado: '.($clinic->nubefact_configurado ? 'sí' : 'no'));
            $this->line('  nubefact_ruc: '.($clinic->nubefact_ruc ?? '—'));
            if ($hasRutaCol) {
                $ruta = (string) ($clinic->nubefact_api_ruta ?? '');
                $this->line('  nubefact_api_ruta: '.($ruta !== '' ? $ruta : '— (falta)'));
            } else {
                $this->warn('  Falta columna nubefact_api_ruta → ejecuta vetsaas:tenant-migrate-all');
            }
            $this->line('  token: '.($clinic->nubefact_configurado ? 'guardado (cifrado)' : 'no'));

            $this->newLine();
            $this->info('Sedes (Configuración › Sedes) — series que usa la emisión');
            $sedes = Sede::query()
                ->where('tenant_id', $tenant->getKey())
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'codigo', 'activa', 'serie_boleta', 'serie_factura']);

            if ($sedes->isEmpty()) {
                $this->warn('  No hay sedes.');
            }

            foreach ($sedes as $sede) {
                $b = SunatSerieCodigo::normalizar($sede->serie_boleta) ?? '—';
                $f = SunatSerieCodigo::normalizar($sede->serie_factura) ?? '—';
                $estado = $sede->activa ? 'activa' : 'inactiva';
                $this->line("  · {$sede->nombre} ({$sede->codigo}) [{$estado}] → boleta: {$b}, factura: {$f}");
            }

            $this->newLine();
            $this->info('Catálogo fel_series (correlativos internos)');
            foreach (FelSerie::query()->orderBy('tipo_comprobante')->orderBy('serie')->get() as $fs) {
                $tipo = (int) $fs->tipo_comprobante === FelSerie::TIPO_FACTURA ? 'factura' : 'boleta';
                $this->line("  · {$tipo} {$fs->serie} → último correlativo: {$fs->ultimo_correlativo}");
            }

            $this->newLine();
            $this->info('Qué enviaría VetSaaS a Nubefact (JSON)');
            $sedeActiva = $sedes->firstWhere('activa', true) ?? $sedes->first();
            if ($sedeActiva !== null) {
                $ventaFake = new \App\Models\Venta([
                    'sede_id' => $sedeActiva->id,
                    'tipo_comprobante_sunat' => FelSerie::TIPO_BOLETA,
                ]);
                $ventaFake->setRelation('sede', $sedeActiva);

                foreach ([FelSerie::TIPO_BOLETA => 'boleta', FelSerie::TIPO_FACTURA => 'factura'] as $tipo => $label) {
                    try {
                        $fel = $series->resolverParaVenta($ventaFake, $tipo, false);
                        $this->line("  · {$label}: serie «{$fel->serie}», próximo número ".(((int) $fel->ultimo_correlativo) + 1));
                    } catch (\Throwable $e) {
                        $this->error("  · {$label}: ".$e->getMessage());
                    }
                }
            }

            $this->newLine();
            $this->warn('Si Nubefact devuelve código 21, la serie «enviada» arriba NO está habilitada en');
            $this->warn('Nubefact › Configuración › Locales y series para el MISMO local de tu RUTA API.');
            $this->warn('Formato SUNAT: B001 / F001 (4 caracteres, sin guión). Demo y producción usan RUTA+TOKEN distintos.');
        });

        return self::SUCCESS;
    }
}
