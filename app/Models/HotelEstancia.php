<?php

namespace App\Models;

use App\Hotel\HotelCatalogoTipoEstancia;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $paciente_id
 * @property ?string $responsable_id
 * @property ?string $sede_id
 * @property \Illuminate\Support\Carbon $ingreso_at
 * @property ?\Illuminate\Support\Carbon $egreso_at
 * @property string $estado
 * @property string $tipo_estancia
 * @property ?string $tipo_detalle
 * @property ?string $notas
 * @property ?string $venta_id
 * @property ?string $created_by_id
 * @property ?string $updated_by_id
 */
class HotelEstancia extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const ESTADO_PROGRAMADA = 'programada';

    public const ESTADO_CONFIRMADA = 'confirmada';

    public const ESTADO_EN_ESTANCIA = 'en_estancia';

    public const ESTADO_COMPLETADA = 'completada';

    public const ESTADO_CANCELADA = 'cancelada';

    public const ESTADO_NO_PRESENTO = 'no_presento';

    /** @var list<string> */
    public const ESTADOS = [
        self::ESTADO_PROGRAMADA,
        self::ESTADO_CONFIRMADA,
        self::ESTADO_EN_ESTANCIA,
        self::ESTADO_COMPLETADA,
        self::ESTADO_CANCELADA,
        self::ESTADO_NO_PRESENTO,
    ];

    protected $table = 'hotel_estancias';

    protected $fillable = [
        'paciente_id',
        'responsable_id',
        'sede_id',
        'ingreso_at',
        'egreso_at',
        'estado',
        'tipo_estancia',
        'tipo_detalle',
        'notas',
        'venta_id',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'ingreso_at' => 'datetime',
            'egreso_at' => 'datetime',
        ];
    }

    public function nochesSugeridasParaVenta(): int
    {
        $in = $this->ingreso_at->copy()->startOfDay();
        $fin = ($this->egreso_at ?? $this->ingreso_at)->copy()->startOfDay();
        $dias = (int) $in->diffInDays($fin, false);

        return max(1, abs($dias));
    }

    public function descripcionParaVenta(): string
    {
        if ($this->tipo_estancia === HotelCatalogoTipoEstancia::OTRO_PERSONALIZADO) {
            $d = trim((string) ($this->tipo_detalle ?? ''));

            return $d !== '' ? mb_substr($d, 0, 300) : 'Hotel / guardería (personalizado)';
        }

        $slug = $this->tipo_estancia;
        $readable = mb_convert_case(str_replace('_', ' ', $slug), MB_CASE_TITLE, 'UTF-8');
        $n = $this->nochesSugeridasParaVenta();

        return mb_substr(
            'Hotel · '.$readable.' ('.$n.' '.($n === 1 ? 'noche' : 'noches').')',
            0,
            300,
        );
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function diarios(): HasMany
    {
        return $this->hasMany(HotelEstanciaDiario::class, 'hotel_estancia_id')->orderBy('fecha');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
