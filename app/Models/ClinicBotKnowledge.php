<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $section
 * @property string $slug
 * @property string $title
 * @property string $content
 * @property array|null $meta
 * @property int $sort_order
 * @property bool $is_active
 */
final class ClinicBotKnowledge extends Model
{
    public const SECTIONS = [
        'faq',
        'horario',
        'politica',
        'servicio',
        'contacto',
        'general',
    ];

    protected $table = 'clinic_bot_knowledge';

    protected $fillable = [
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
            'meta' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSection(Builder $query, string $section): Builder
    {
        return $query->where('section', $section);
    }

    public static function buildContext(?string $tenantId = null): string
    {
        $tenantId ??= tenant_id();

        if ($tenantId === null) {
            return '';
        }

        $cacheKey = "clinic_bot_knowledge_{$tenantId}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function (): string {
            $entries = self::query()
                ->active()
                ->orderBy('section')
                ->orderBy('sort_order')
                ->get();

            if ($entries->isEmpty()) {
                return '';
            }

            $sections = [];
            foreach ($entries->groupBy('section') as $section => $items) {
                $sectionTitle = match ($section) {
                    'faq' => 'PREGUNTAS FRECUENTES',
                    'horario' => 'HORARIOS DE ATENCIÓN',
                    'politica' => 'POLÍTICAS DE LA CLÍNICA',
                    'servicio' => 'SERVICIOS',
                    'contacto' => 'CONTACTO Y UBICACIÓN',
                    default => strtoupper($section),
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

    public static function flushCache(?string $tenantId = null): void
    {
        $tenantId ??= tenant_id();

        if ($tenantId !== null) {
            Cache::forget("clinic_bot_knowledge_{$tenantId}");
        }
    }
}
