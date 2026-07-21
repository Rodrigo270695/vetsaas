<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

final class InAppAssistantKnowledge extends Model
{
    use UsesPublicSchema;

    public const CACHE_KEY = 'in_app_assistant_knowledge.active.v1';

    public const SCOPE_CLINIC = 'clinic';

    public const SCOPE_PLATFORM = 'platform';

    public const SCOPE_BOTH = 'both';

    public const SCOPES = [
        self::SCOPE_CLINIC,
        self::SCOPE_PLATFORM,
        self::SCOPE_BOTH,
    ];

    public const SECTION_MODULE = 'module';

    public const SECTION_SCREEN = 'screen';

    public const SECTION_WORKFLOW = 'workflow';

    public const SECTION_ROLE = 'role';

    public const SECTION_FAQ = 'faq';

    public const SECTIONS = [
        self::SECTION_MODULE,
        self::SECTION_SCREEN,
        self::SECTION_WORKFLOW,
        self::SECTION_ROLE,
        self::SECTION_FAQ,
    ];

    public const PERMISSION_ANY = 'any';

    public const PERMISSION_ALL = 'all';

    public const PERMISSION_MODES = [
        self::PERMISSION_ANY,
        self::PERMISSION_ALL,
    ];

    protected $table = 'in_app_assistant_knowledge';

    protected $fillable = [
        'slug',
        'scope',
        'section',
        'title',
        'content',
        'keywords',
        'url_patterns',
        'component_patterns',
        'required_permissions',
        'permission_mode',
        'allowed_roles',
        'actions',
        'priority',
        'sort_order',
        'is_active',
    ];

    protected static function booted(): void
    {
        self::saved(static fn (): bool => Cache::forget(self::CACHE_KEY));
        self::deleted(static fn (): bool => Cache::forget(self::CACHE_KEY));
    }

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'url_patterns' => 'array',
            'component_patterns' => 'array',
            'required_permissions' => 'array',
            'allowed_roles' => 'array',
            'actions' => 'array',
            'priority' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForScope(Builder $query, string $scope): Builder
    {
        return $query->whereIn('scope', [$scope, self::SCOPE_BOTH]);
    }

    /**
     * Cachea datos escalares, no modelos serializados, para evitar estado Eloquent obsoleto.
     *
     * @return list<array<string, mixed>>
     */
    public static function cachedActiveRows(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(max(1, (int) config('in-app-assistant.knowledge.cache_ttl_minutes', 10))),
            static fn (): array => self::query()
                ->active()
                ->orderByDesc('priority')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(static fn (self $entry): array => $entry->attributesToArray())
                ->all(),
        );

        return $rows;
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
