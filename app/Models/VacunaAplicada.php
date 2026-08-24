<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property ?string $consulta_id
 * @property ?string $producto_id
 * @property ?string $servicio_clinico_id
 * @property string $nombre_vacuna
 * @property \Illuminate\Support\Carbon $aplicada_at
 * @property ?int $numero_dosis
 * @property ?string $lote
 * @property string $categoria_registro
 * @property ?string $esquema_antigenos
 * @property ?\Illuminate\Support\Carbon $fecha_proxima_sugerida
 * @property ?string $notas
 * @property ?string $veterinario_id
 * @property ?string $sede_id
 * @property ?string $movimiento_inventario_id
 * @property ?string $created_by_id
 * @property ?string $updated_by_id
 */
class VacunaAplicada extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const CATEGORIA_VACUNA = 'vacuna';

    public const CATEGORIA_DESPARASITACION = 'desparasitacion';

    public const CATEGORIA_OTRO = 'otro';

    /** @var list<string> */
    public const CATEGORIAS_REGISTRO = [
        self::CATEGORIA_VACUNA,
        self::CATEGORIA_DESPARASITACION,
        self::CATEGORIA_OTRO,
    ];

    protected $table = 'vacunas_aplicadas';

    protected $fillable = [
        'paciente_id',
        'consulta_id',
        'producto_id',
        'servicio_clinico_id',
        'nombre_vacuna',
        'aplicada_at',
        'numero_dosis',
        'lote',
        'categoria_registro',
        'esquema_antigenos',
        'fecha_proxima_sugerida',
        'notas',
        'veterinario_id',
        'sede_id',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'aplicada_at' => 'datetime',
            'numero_dosis' => 'integer',
            'fecha_proxima_sugerida' => 'date',
        ];
    }

    public function permiteCargosPreCuenta(): bool
    {
        return true;
    }

    public function urlCobrarEnCaja(): ?string
    {
        if (! $this->permiteCargosPreCuenta()) {
            return null;
        }

        $cargo = $this->relationLoaded('cargo')
            ? $this->cargo
            : $this->cargo()->first();

        if ($cargo !== null
            && $cargo->estado === ConsultaCargo::ESTADO_CONFIRMADO
            && $cargo->venta_id === null) {
            return route('caja.ventas.create-desde-vacuna', ['vacuna_aplicada' => $this], absolute: false);
        }

        return null;
    }

    public function descripcionParaVenta(): string
    {
        if ($this->servicio_clinico_id !== null) {
            $nombre = $this->relationLoaded('servicioClinico')
                ? $this->servicioClinico?->nombre
                : ServicioClinico::query()->whereKey($this->servicio_clinico_id)->value('nombre');

            if (is_string($nombre) && $nombre !== '') {
                return mb_substr('Vacunación · '.$nombre, 0, 300);
            }
        }

        $n = trim((string) $this->nombre_vacuna);

        return $n !== '' ? mb_substr('Vacunación · '.$n, 0, 300) : 'Vacunación';
    }

    public function usaPaqueteInventario(): bool
    {
        if ($this->servicio_clinico_id === null) {
            return false;
        }

        if ($this->relationLoaded('servicioClinico') && $this->servicioClinico !== null) {
            if ($this->servicioClinico->relationLoaded('productosPaquete')) {
                return $this->servicioClinico->productosPaquete->isNotEmpty();
            }
        }

        return ServicioClinicoProducto::query()
            ->where('servicio_clinico_id', $this->servicio_clinico_id)
            ->exists();
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function consulta(): BelongsTo
    {
        return $this->belongsTo(Consulta::class, 'consulta_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function servicioClinico(): BelongsTo
    {
        return $this->belongsTo(ServicioClinico::class, 'servicio_clinico_id');
    }

    public function veterinario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'veterinario_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    public function movimientoInventario(): BelongsTo
    {
        return $this->belongsTo(MovimientoInventario::class, 'movimiento_inventario_id');
    }

    public function cargo(): HasOne
    {
        return $this->hasOne(ConsultaCargo::class, 'vacuna_aplicada_id')
            ->whereNull('venta_id');
    }

    public function cargos(): HasMany
    {
        return $this->hasMany(ConsultaCargo::class, 'vacuna_aplicada_id');
    }

    /**
     * Estado de cobro para listados. Ver ConsultaCargoCobroEstado.
     */
    public function estadoCobro(): string
    {
        $pending = $this->relationLoaded('cargo')
            ? $this->cargo
            : $this->cargo()->first();

        $cobradoCount = (int) ($this->cargos_cobrados_count
            ?? ($this->relationLoaded('cargos')
                ? $this->cargos->whereNotNull('venta_id')->count()
                : $this->cargos()->whereNotNull('venta_id')->count()));

        return \App\Support\ConsultaCargo\ConsultaCargoCobroEstado::resolve(
            $pending,
            $cobradoCount,
            null,
        );
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
