<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Permisos globales (schema public). Con search_path del tenant activo,
 * las consultas deben ir siempre a public.* para no confundir tablas.
 */
class Permission extends SpatiePermission
{
    use UsesPublicSchema;
}
