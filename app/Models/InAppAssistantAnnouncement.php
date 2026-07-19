<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Novedad in-app del asistente (modal que ven las clínicas).
 *
 * @property string $id
 * @property string $title
 * @property string $body
 * @property array|null $features
 * @property bool $is_active
 * @property int $version
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property string|null $created_by_id
 */
final class InAppAssistantAnnouncement extends Model
{
    use HasUuids;
    use UsesPublicSchema;

    protected $table = 'in_app_assistant_announcements';

    protected $fillable = [
        'title',
        'body',
        'features',
        'is_active',
        'version',
        'published_at',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
            'version' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $q): void {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public static function currentLive(): ?self
    {
        return static::query()
            ->live()
            ->orderByDesc('published_at')
            ->orderByDesc('version')
            ->first();
    }

    /**
     * @return list<string>
     */
    public function featureList(): array
    {
        $raw = $this->features;
        if (! is_array($raw)) {
            return [];
        }

        $features = [];
        foreach ($raw as $item) {
            if (! is_string($item)) {
                continue;
            }
            $text = trim($item);
            if ($text === '') {
                continue;
            }
            $features[] = mb_substr($text, 0, 200);
            if (count($features) >= 4) {
                break;
            }
        }

        return $features;
    }

    /**
     * Payload para el modal de las clínicas.
     *
     * @return array{
     *     active: bool,
     *     id: string,
     *     version: int,
     *     title: string|null,
     *     body: string|null,
     *     features: list<string>
     * }|null
     */
    public static function tenantPayload(): ?array
    {
        $announcement = static::currentLive();
        if ($announcement === null) {
            return null;
        }

        $version = (int) $announcement->version;
        if ($version < 1) {
            return null;
        }

        $title = trim($announcement->title);
        $body = trim($announcement->body);

        return [
            'active' => true,
            'id' => $announcement->id,
            'version' => $version,
            'title' => $title !== '' ? mb_substr($title, 0, 160) : null,
            'body' => $body !== '' ? mb_substr($body, 0, 2000) : null,
            'features' => $announcement->featureList(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        $features = $this->featureList();
        while (count($features) < 4) {
            $features[] = '';
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'features' => array_slice($features, 0, 4),
            'is_active' => (bool) $this->is_active,
            'version' => (int) $this->version,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'created_by' => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ] : null,
        ];
    }
}
