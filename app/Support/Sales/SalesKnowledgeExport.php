<?php

declare(strict_types=1);

namespace App\Support\Sales;

use App\Models\Plan;
use App\Models\SalesBotKnowledge;
use Illuminate\Support\Carbon;

/**
 * Conocimiento comercial de VetSaaS para el Centro de Ventas de ORVAE (contrato v1).
 */
final class SalesKnowledgeExport
{
    public const PRODUCT = 'vetsaas';

    private const LIMIT_LABELS = [
        'max_sedes' => ['sede', 'sedes'],
        'max_usuarios' => ['usuario', 'usuarios'],
        'max_pacientes' => ['paciente', 'pacientes'],
        'max_propietarios' => ['propietario', 'propietarios'],
        'max_productos' => ['producto', 'productos'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $secciones = $this->secciones();

        return [
            'version' => 1,
            'producto' => self::PRODUCT,
            'nombre' => 'VetSaaS',
            'resumen' => (string) config('salesbot.orvae.resumen'),
            'publico' => (string) config('salesbot.orvae.publico'),
            'palabras_clave' => array_values((array) config('salesbot.orvae.palabras_clave', [])),
            'planes' => $this->planes(),
            'secciones' => $secciones['items'],
            'demo' => [
                'url' => (string) config('salesbot.demo_url'),
                'usuario' => (string) config('salesbot.demo_email'),
                'password' => (string) config('salesbot.demo_password'),
            ],
            'registro_url' => (string) config('salesbot.register_url'),
            'actualizado_at' => $secciones['actualizado_at'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function planes(): array
    {
        return Plan::query()
            ->where('activo', true)
            ->where('es_publico', true)
            ->orderBy('orden')
            ->get()
            ->map(fn (Plan $plan): array => [
                'codigo' => $plan->codigo,
                'nombre' => $plan->nombre,
                'descripcion' => $plan->descripcion,
                'precio_mensual' => $plan->precio_mensual !== null ? (float) $plan->precio_mensual : null,
                'precio_anual' => $plan->precio_anual !== null ? (float) $plan->precio_anual : null,
                'moneda' => 'PEN',
                'trial_days' => (int) $plan->trial_days,
                'incluye' => $this->incluye($plan),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function incluye(Plan $plan): array
    {
        $items = [];

        foreach (self::LIMIT_LABELS as $feature => [$singular, $plural]) {
            $value = $plan->resolveFeature($feature);

            $items[] = match (true) {
                ! is_int($value) || $value < 0 => "Sin límite de {$plural}",
                $value === 1 => "1 {$singular}",
                default => "{$value} {$plural}",
            };
        }

        $comprobantes = array_values(array_filter([
            $plan->resolveFeature('boletas_electronicas') === true ? 'boletas' : null,
            $plan->resolveFeature('facturas_electronicas') === true ? 'facturas' : null,
            $plan->resolveFeature('guias_remision') === true ? 'guías de remisión' : null,
        ]));

        if ($comprobantes !== []) {
            $max = $plan->resolveFeature('max_comprobantes_mes');
            $items[] = 'Facturación electrónica SUNAT: '.implode(', ', $comprobantes)
                .(is_int($max) && $max >= 0 ? " (hasta {$max} comprobantes/mes)" : ' (comprobantes ilimitados)');
        }

        return $items;
    }

    /**
     * @return array{items: list<array<string, mixed>>, actualizado_at: ?string}
     */
    private function secciones(): array
    {
        $entries = SalesBotKnowledge::query()
            ->forProduct(self::PRODUCT)
            ->where('section', '!=', 'plan')
            ->orderBy('section')
            ->orderBy('sort_order')
            ->get();

        $latest = $entries->max('updated_at');

        return [
            'items' => $entries->map(fn (SalesBotKnowledge $entry): array => [
                'tipo' => $entry->section,
                'titulo' => $entry->title,
                'contenido' => $entry->content,
                'fecha' => $entry->updated_at?->toIso8601String(),
            ])->values()->all(),
            'actualizado_at' => $latest !== null ? Carbon::parse($latest)->toIso8601String() : null,
        ];
    }
}
