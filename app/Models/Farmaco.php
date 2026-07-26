<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de fármacos por tenant (empieza vacío; se crea al usarlo en HC).
 *
 * @property string $id
 * @property string $nombre
 */
class Farmaco extends Model
{
    use HasUuids;

    protected $table = 'farmacos';

    protected $fillable = [
        'nombre',
    ];
}
