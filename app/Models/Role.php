<?php

namespace App\Models;

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
 *   - Concept "rol del sistema": roles protegidos que NO se pueden editar
 *     ni eliminar desde el panel (ej. `superadmin`). Esto evita que un
 *     usuario con permisos `roles.update/delete` se quede sin acceso por
 *     accidente.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property ?string $description
 * @property-read bool $is_system
 */
class Role extends SpatieRole
{
    /**
     * Nombres de roles reservados por la plataforma. NO son editables ni
     * eliminables desde el panel. Si en el futuro agregas más (admin_clinica,
     * veterinario, etc.) listalos aquí o muévelo a config para no tocar código.
     */
    public const SYSTEM_ROLES = ['superadmin'];

    /**
     * Spatie usa `$guarded = []` (todo es mass-assignable), así que la
     * columna `description` ya viaja por `update`/`create` sin tocar nada.
     * Listamos los fillable explícitos solo como documentación de intención.
     */
    protected $fillable = [
        'name',
        'guard_name',
        'description',
    ];

    /**
     * Indica si el rol es del sistema (protegido).
     *
     * Se calcula a partir del nombre, lo que evita persistir el flag y
     * permite versionar el listado en código sin tocar BD.
     */
    protected function isSystem(): Attribute
    {
        return Attribute::make(
            get: fn () => in_array($this->name, self::SYSTEM_ROLES, true),
        );
    }

    /**
     * Scope para filtrar por tipo desde el listado:
     *   - 'todos'        → sin filtro
     *   - 'sistema'      → roles reservados
     *   - 'personalizado'→ roles creados por el cliente
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        if ($type === 'sistema') {
            return $query->whereIn('name', self::SYSTEM_ROLES);
        }

        if ($type === 'personalizado') {
            return $query->whereNotIn('name', self::SYSTEM_ROLES);
        }

        return $query;
    }
}
