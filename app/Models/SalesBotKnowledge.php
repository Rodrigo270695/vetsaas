<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int         $id
 * @property string      $product       "vetsaas" | "aula-virtual" | "inventario"
 * @property string      $section       "plan" | "modulo" | "faq" | "objecion" | "general"
 * @property string      $slug          "plan-pro" | "modulo-grooming"
 * @property string      $title
 * @property string      $content
 * @property array|null  $meta
 * @property int         $sort_order
 * @property bool        $is_active
 */
final class SalesBotKnowledge extends Model
{
    protected $table = 'salesbot_knowledge';

    protected $fillable = [
        'product',
        'section',
        'slug',
        'title',
        'content',
        'meta',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'meta'       => 'array',
            'sort_order' => 'integer',
            'is_active'  => 'boolean',
        ];
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForProduct(Builder $query, string $product): Builder
    {
        return $query->where('product', $product)->where('is_active', true);
    }

    public function scopeSection(Builder $query, string $section): Builder
    {
        return $query->where('section', $section);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Construye el contexto de producto completo para inyectarlo en el system prompt.
     * El resultado se cachea 5 minutos para no golpear la BD en cada mensaje.
     *
     * Para forzar la actualización del caché (tras editar la BD):
     *   Cache::forget('salesbot_knowledge_vetsaas');
     */
    public static function buildContext(string $product = 'vetsaas'): string
    {
        $cacheKey = "salesbot_knowledge_{$product}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($product): string {
            $entries = self::query()
                ->forProduct($product)
                ->orderBy('section')
                ->orderBy('sort_order')
                ->get();

            if ($entries->isEmpty()) {
                return '';
            }

            $sections = [];
            foreach ($entries->groupBy('section') as $section => $items) {
                $sectionTitle = match ($section) {
                    'plan'      => 'PLANES Y PRECIOS',
                    'modulo'    => 'MÓDULOS Y FUNCIONALIDADES',
                    'faq'       => 'PREGUNTAS FRECUENTES',
                    'objecion'  => 'CÓMO MANEJAR OBJECIONES',
                    default     => strtoupper($section),
                };

                $block = "## {$sectionTitle}\n\n";
                foreach ($items as $item) {
                    $block .= "### {$item->title}\n{$item->content}\n\n";
                }
                $sections[] = trim($block);
            }

            return implode("\n\n---\n\n", $sections);
        });
    }

    /**
     * Invalida el caché de un producto específico.
     * Llamar después de actualizar cualquier entrada de conocimiento.
     */
    public static function flushCache(string $product = 'vetsaas'): void
    {
        Cache::forget("salesbot_knowledge_{$product}");
        Cache::forget("salesbot_knowledge_{$product}_no_plans");
        Cache::forget("salesbot_plans_{$product}");
    }
}
