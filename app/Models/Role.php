<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Rol de la aplicación (extensión del modelo nativo de Spatie).
 *
 * Mantenemos todo el comportamiento de Spatie (asignación de permisos,
 * caché interno, sincronización con `model_has_roles`) y solo agregamos:
 *
 *   - Columna `description` (UI/UX): explicación humana del propósito del rol.
 *   - Roles protegidos: no se pueden renombrar ni eliminar desde el panel.
 *     Incluye `superadmin` (plataforma) y los roles base de clínica.
 *     Esto evita un CASCADE global en `model_has_roles` si alguien borra
 *     `admin_clinica` desde cualquier tenant (los roles Spatie son globales).
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property ?string $description
 * @property-read bool $is_system
 */
class Role extends SpatieRole
{
    use UsesPublicSchema;

    /**
     * Solo panel central. Ocultos en el UI de clínica (no asignables allí).
     *
     * @var list<string>
     */
    public const PLATFORM_ROLES = ['superadmin'];

    /**
     * Roles operativos compartidos por todas las clínicas.
     * No eliminables / no renombrables (sí se pueden ajustar permisos).
     *
     * @var list<string>
     */
    public const BASE_CLINIC_ROLES = [
        'admin_clinica',
        'veterinario',
        'asistente_vet',
        'recepcionista',
        'groomer',
    ];

    /**
     * Alias histórico: todos los roles protegidos (plataforma + clínica base).
     * Preferir `protectedRoleNames()` / `PLATFORM_ROLES` / `BASE_CLINIC_ROLES`.
     *
     * @var list<string>
     */
    public const SYSTEM_ROLES = [
        'superadmin',
        'admin_clinica',
        'veterinario',
        'asistente_vet',
        'recepcionista',
        'groomer',
    ];

    /**
     * Spatie usa `$guarded = []` (todo es mass-assignable), así que la
     * columna `description` ya viaja por `update`/`create` sin tocar nada.
     * Listamos los fillable explícitos solo como documentación de intención.
     */
    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'tenant_id',
    ];

    /**
     * Accesor `is_system`: hay que appendearlo para que Inertia/JSON
     * lo envíe al front (badge Sistema, ocultar Editar/Eliminar).
     *
     * @var list<string>
     */
    protected $appends = [
        'is_system',
    ];

    /**
     * @return list<string>
     */
    public static function protectedRoleNames(): array
    {
        return self::SYSTEM_ROLES;
    }

    /**
     * Roles que la clínica no debe ver/asignar (solo plataforma).
     *
     * @return list<string>
     */
    public static function platformOnlyRoleNames(): array
    {
        return self::PLATFORM_ROLES;
    }

    public function isBaseClinicRole(): bool
    {
        return in_array($this->name, self::BASE_CLINIC_ROLES, true);
    }

    public function isPlatformRole(): bool
    {
        return in_array($this->name, self::PLATFORM_ROLES, true);
    }

    /**
     * Indica si el rol es protegido (no renombrable / no eliminable).
     */
    protected function isSystem(): Attribute
    {
        return Attribute::make(
            get: fn () => in_array($this->name, self::protectedRoleNames(), true),
        );
    }

    /**
     * Scope para filtrar por tipo desde el listado:
     *   - 'todos'        → sin filtro
     *   - 'sistema'      → roles reservados
     *   - 'personalizados'→ roles creados por el cliente
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        if ($type === 'sistema') {
            return $query->whereIn('name', self::protectedRoleNames());
        }

        if ($type === 'personalizado') {
            return $query->whereNotIn('name', self::protectedRoleNames());
        }

        return $query;
    }
}
