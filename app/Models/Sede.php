<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $nombre
 * @property string $codigo
 * @property ?string $direccion
 * @property ?string $telefono
 * @property ?string $email
 * @property ?int $distrito_id
 * @property ?string $distrito
 * @property ?string $provincia
 * @property ?string $departamento
 * @property ?string $serie_factura
 * @property ?string $serie_boleta
 * @property bool $activa
 * @property ?string $created_by_id
 * @property ?string $updated_by_id
 * @property-read ?User $creadoPor
 * @property-read ?User $actualizadoPor
 * @property-read ?Distrito $distritoModel
 */
class Sede extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'sedes';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'codigo',
        'direccion',
        'telefono',
        'email',
        'distrito_id',
        'distrito',
        'provincia',
        'departamento',
        'serie_factura',
        'serie_boleta',
        'activa',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    /**
     * Distrito vinculado al catálogo oficial.
     *
     * Se nombra `distritoModel` para no chocar con el campo string
     * `distrito` (cache denormalizado).
     */
    public function distritoModel(): BelongsTo
    {
        return $this->belongsTo(Distrito::class, 'distrito_id');
    }

    /**
     * Genera el siguiente código correlativo para una sede.
     *
     * Patrón: `SEDE-001`, `SEDE-002`, … (3 dígitos con padding).
     * El número se basa en el mayor numérico encontrado en cualquier código
     * existente (incluyendo soft-deleted), lo que evita reusar correlativos.
     *
     * Considera solo las sedes del mismo {@see Tenant::$id}.
     */
    public static function generateNextCode(string $tenantId): string
    {
        $maxNumber = self::withTrashed()
            ->where('tenant_id', $tenantId)
            ->pluck('codigo')
            ->map(fn ($c) => (int) preg_replace('/\D/', '', (string) $c))
            ->max() ?? 0;

        return 'SEDE-'.str_pad((string) ($maxNumber + 1), 3, '0', STR_PAD_LEFT);
    }
}
