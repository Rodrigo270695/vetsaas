<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $grooming_turno_id
 * @property string $tipo
 * @property string $path
 * @property ?string $caption
 * @property ?Carbon $enviado_whatsapp_at
 * @property ?string $created_by_id
 * @property-read ?string $url
 */
class GroomingTurnoFoto extends Model
{
    use HasUuids;

    public const TIPO_PROCESO = 'proceso';

    public const TIPO_FINAL = 'final';

    /** @var list<string> */
    public const TIPOS = [
        self::TIPO_PROCESO,
        self::TIPO_FINAL,
    ];

    protected $table = 'grooming_turno_fotos';

    protected $fillable = [
        'grooming_turno_id',
        'tipo',
        'path',
        'caption',
        'enviado_whatsapp_at',
        'created_by_id',
    ];

    protected $appends = [
        'url',
    ];

    protected function casts(): array
    {
        return [
            'enviado_whatsapp_at' => 'datetime',
        ];
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->path !== ''
                ? asset('storage/'.ltrim($this->path, '/'))
                : null,
        );
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(GroomingTurno::class, 'grooming_turno_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
