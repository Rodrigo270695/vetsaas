<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * @property string      $id
 * @property string      $title
 * @property string      $bullet_1
 * @property string|null $bullet_2
 * @property string|null $bullet_3
 * @property string|null $guide_title
 * @property string|null $guide_body
 * @property string|null $guide_tip_1
 * @property string|null $guide_tip_2
 * @property string|null $guide_tip_3
 * @property bool        $is_active
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 */
final class BotIaAnnouncement extends Model
{
    use HasUuids;
    use UsesPublicSchema;

    private const CACHE_KEY = 'bot_ia_announcement_current';

    protected $fillable = [
        'title',
        'bullet_1',
        'bullet_2',
        'bullet_3',
        'guide_title',
        'guide_body',
        'guide_tip_1',
        'guide_tip_2',
        'guide_tip_3',
        'is_active',
        'published_at',
        'expires_at',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $q): void {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public static function currentForTenants(): ?self
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(5), function (): ?self {
            return self::query()
                ->published()
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->first();
        });
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     bullets: list<string>,
     *     guide_title: string|null,
     *     guide_body: string|null,
     *     guide_tips: list<string>
     * }
     */
    public function toTenantPayload(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'bullets' => $this->nonEmptyLines([
                $this->bullet_1,
                $this->bullet_2,
                $this->bullet_3,
            ]),
            'guide_title' => $this->guide_title,
            'guide_body' => $this->guide_body,
            'guide_tips' => $this->nonEmptyLines([
                $this->guide_tip_1,
                $this->guide_tip_2,
                $this->guide_tip_3,
            ]),
        ];
    }

    /**
     * @param  list<string|null>  $lines
     * @return list<string>
     */
    private function nonEmptyLines(array $lines): array
    {
        return array_values(array_filter(
            array_map(static fn (?string $line): string => trim((string) $line), $lines),
            static fn (string $line): bool => $line !== '',
        ));
    }
}
