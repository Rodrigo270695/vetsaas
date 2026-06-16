<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasUuids, UsesPublicSchema;

    /**
     * Catálogo central de **features conocidos** que se pueden vincular
     * a un plan vía `plan_features`. Cada entrada define:
     *   - `type`: tipo de valor (`int` | `bool` | `str`) → determina qué
     *             columna usar (`valor_int` / `valor_bool` / `valor_str`).
     *   - `group`: agrupador visual en el modal de features.
     *   - `default`: valor por defecto al crear un plan nuevo desde la UI.
     *
     * Mantener sincronizado con cualquier feature que el código consuma
     * vía `Plan::resolveFeature($plan, 'max_sedes')`.
     */
    public const FEATURE_CATALOG = [
        // ───── Límites cuantitativos ─────
        'max_sedes'        => ['type' => 'int', 'group' => 'limites', 'default' => 1],
        'max_usuarios'     => ['type' => 'int', 'group' => 'limites', 'default' => 3],
        'max_pacientes'    => ['type' => 'int', 'group' => 'limites', 'default' => 500],
        'max_propietarios' => ['type' => 'int', 'group' => 'limites', 'default' => 500],
        'max_productos'    => ['type' => 'int', 'group' => 'limites', 'default' => 200],

        // ───── Facturación electrónica ─────
        'factura_electronica'  => ['type' => 'bool', 'group' => 'facturacion', 'default' => false],
        'boletas_electronicas' => ['type' => 'bool', 'group' => 'facturacion', 'default' => false],
        'facturas_ruc'         => ['type' => 'bool', 'group' => 'facturacion', 'default' => false],
        'notas_credito'        => ['type' => 'bool', 'group' => 'facturacion', 'default' => false],
        'max_comprobantes_mes' => ['type' => 'int', 'group' => 'facturacion', 'default' => 50],
    ];

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'badge',
        'color_hex',
        'precio_mensual',
        'precio_anual',
        'trial_days',
        'orden',
        'es_publico',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio_mensual' => 'decimal:2',
            'precio_anual' => 'decimal:2',
            'es_publico' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Resuelve el valor efectivo de una feature del plan.
     *
     * Si la feature no está en `plan_features`, devuelve el `default`
     * declarado en `FEATURE_CATALOG`. Si no existe en el catálogo
     * tampoco, devuelve `null` (es responsabilidad del código que
     * la consulta decidir el fallback en ese caso).
     */
    public function resolveFeature(string $feature): int|bool|string|null
    {
        $meta = self::FEATURE_CATALOG[$feature] ?? null;
        if (! $meta) {
            return null;
        }

        /** @var PlanFeature|null $row */
        $row = $this->features()->where('feature', $feature)->first();
        if (! $row) {
            return $meta['default'];
        }

        return match ($meta['type']) {
            'int' => $row->valor_int,
            'bool' => $row->valor_bool,
            'str' => $row->valor_str,
            default => null,
        };
    }
}
