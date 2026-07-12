<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MovimientoInventario extends Model
{
    use HasUuids;

    public const TIPO_ENTRADA = 'entrada';

    public const TIPO_SALIDA = 'salida';

    public const TIPO_MERMA = 'merma';

    public const TIPO_AJUSTE = 'ajuste';

    /** @var list<string> */
    public const TIPOS_OPERATIVOS = [
        self::TIPO_ENTRADA,
        self::TIPO_SALIDA,
        self::TIPO_MERMA,
    ];

    protected $table = 'movimientos_inventario';

    public $timestamps = false;

    protected $fillable = [
        'producto_id',
        'compra_id',
        'venta_id',
        'producto_lote_id',
        'fefo_grupo_id',
        'sede_id',
        'tipo',
        'delta',
        'stock_anterior',
        'stock_despues',
        'notas',
        'created_by_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'delta' => 'decimal:3',
            'stock_anterior' => 'decimal:3',
            'stock_despues' => 'decimal:3',
            'created_at' => 'datetime',
        ];
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    public function productoLote(): BelongsTo
    {
        return $this->belongsTo(ProductoLote::class, 'producto_lote_id');
    }

    /**
     * Aplica el movimiento sobre `existencias_sede` y persiste la fila de kardex.
     *
     * @throws ValidationException
     */
    public static function aplicar(
        string $productoId,
        string $sedeId,
        string $tipo,
        string $delta,
        ?string $notas,
        ?string $userId,
        ?string $compraId = null,
        ?string $ventaId = null,
        ?string $productoLoteId = null,
        ?string $fefoGrupoId = null,
    ): self {
        if (! in_array($tipo, [self::TIPO_ENTRADA, self::TIPO_SALIDA, self::TIPO_MERMA, self::TIPO_AJUSTE], true)) {
            throw ValidationException::withMessages([
                'tipo' => 'Tipo de movimiento inválido.',
            ]);
        }

        return DB::transaction(function () use ($productoId, $sedeId, $tipo, $delta, $notas, $userId, $compraId, $ventaId, $productoLoteId, $fefoGrupoId): self {
            $existencia = ExistenciaSede::query()
                ->where('producto_id', $productoId)
                ->where('sede_id', $sedeId)
                ->lockForUpdate()
                ->first();

            $stockAnterior = round((float) (string) ($existencia?->cantidad ?? 0), 3);
            $deltaNum = round((float) $delta, 3);
            $stockDespues = round($stockAnterior + $deltaNum, 3);

            if ($stockDespues < 0) {
                throw ValidationException::withMessages([
                    'cantidad' => 'El movimiento dejaría existencias negativas.',
                ]);
            }

            ExistenciaSede::query()->updateOrCreate(
                [
                    'producto_id' => $productoId,
                    'sede_id' => $sedeId,
                ],
                ['cantidad' => $stockDespues],
            );

            return self::query()->create([
                'producto_id' => $productoId,
                'compra_id' => $compraId,
                'venta_id' => $ventaId,
                'producto_lote_id' => $productoLoteId,
                'fefo_grupo_id' => $fefoGrupoId,
                'sede_id' => $sedeId,
                'tipo' => $tipo,
                'delta' => $deltaNum,
                'stock_anterior' => $stockAnterior,
                'stock_despues' => $stockDespues,
                'notas' => $notas,
                'created_by_id' => $userId,
                'created_at' => now(),
            ]);
        });
    }
}
