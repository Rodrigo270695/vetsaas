<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Grooming\GroomingCatalogoServicio;
use App\Hotel\HotelCatalogoTipoEstancia;
use App\Models\GroomingServicio;
use App\Models\HotelTipoEstancia;
use Illuminate\Support\Str;

/**
 * Siembra catálogos editables por clínica a partir de los slugs predefinidos (legacy).
 * Cada tenant puede renombrar, desactivar o eliminar tipos que no use.
 */
final class ClinicCatalogSeeder
{
    public static function seedGroomingIfEmpty(): void
    {
        if (GroomingServicio::query()->exists()) {
            return;
        }

        $labels = self::groomingLabels();
        $orden = 0;

        foreach (GroomingCatalogoServicio::grupos() as $bloque) {
            $grupoLabel = $labels['grupos'][$bloque['grupo']] ?? Str::headline($bloque['grupo']);

            foreach ($bloque['items'] as $slug) {
                GroomingServicio::query()->create([
                    'nombre' => $labels['items'][$slug] ?? Str::headline(str_replace('_', ' ', $slug)),
                    'categoria' => $grupoLabel,
                    'codigo_legacy' => $slug,
                    'precio_lista' => 0,
                    'moneda' => 'PEN',
                    'duracion_minutos' => GroomingCatalogoServicio::duracionSugeridaPara($slug),
                    'activo' => true,
                    'orden' => ++$orden,
                ]);
            }
        }
    }

    public static function seedHotelIfEmpty(): void
    {
        if (HotelTipoEstancia::query()->exists()) {
            return;
        }

        $labels = self::hotelLabels();
        $orden = 0;

        foreach (HotelCatalogoTipoEstancia::grupos() as $bloque) {
            $grupoLabel = $labels['grupos'][$bloque['grupo']] ?? Str::headline($bloque['grupo']);

            foreach ($bloque['items'] as $slug) {
                HotelTipoEstancia::query()->create([
                    'nombre' => $labels['items'][$slug] ?? Str::headline(str_replace('_', ' ', $slug)),
                    'categoria' => $grupoLabel,
                    'codigo_legacy' => $slug,
                    'precio_lista' => 0,
                    'moneda' => 'PEN',
                    'activo' => true,
                    'orden' => ++$orden,
                ]);
            }
        }
    }

    /**
     * @return array{grupos: array<string, string>, items: array<string, string>}
     */
    private static function groomingLabels(): array
    {
        return self::labelsFromJson('grooming.json', 'tipos_servicio');
    }

    /**
     * @return array{grupos: array<string, string>, items: array<string, string>}
     */
    private static function hotelLabels(): array
    {
        return self::labelsFromJson('hotel.json', 'tipos_estancia');
    }

    /**
     * @return array{grupos: array<string, string>, items: array<string, string>}
     */
    private static function labelsFromJson(string $file, string $rootKey): array
    {
        $path = resource_path('js/lang/es/'.$file);
        if (! is_readable($path)) {
            return ['grupos' => [], 'items' => []];
        }

        $raw = json_decode((string) file_get_contents($path), true);
        if (! is_array($raw) || ! isset($raw[$rootKey]) || ! is_array($raw[$rootKey])) {
            return ['grupos' => [], 'items' => []];
        }

        $root = $raw[$rootKey];
        $grupos = is_array($root['grupos'] ?? null) ? $root['grupos'] : [];
        $itemsRaw = is_array($root['items'] ?? null) ? $root['items'] : [];
        $items = [];

        foreach ($itemsRaw as $slug => $meta) {
            if (is_array($meta) && isset($meta['label']) && is_string($meta['label'])) {
                $items[$slug] = $meta['label'];
            }
        }

        return ['grupos' => $grupos, 'items' => $items];
    }
}
