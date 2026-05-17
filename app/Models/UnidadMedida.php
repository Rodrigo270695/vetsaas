<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class UnidadMedida extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'unidades_medida';

    protected $fillable = [
        'codigo',
        'nombre',
        'es_sistema',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'es_sistema' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public static function generateCodigoFromNombre(string $nombre): string
    {
        $ascii = Str::ascii($nombre);
        $slug = Str::upper(Str::slug($ascii, '_'));
        $slug = preg_replace('/[^A-Z0-9_]/', '', $slug) ?: 'UNIT';

        return substr($slug, 0, 20);
    }

    /**
     * @return non-empty-string
     */
    public static function uniqueCodigoDesdeNombre(string $nombre): string
    {
        $base = self::generateCodigoFromNombre($nombre);
        $codigo = $base;
        $i = 2;
        while (self::withTrashed()->where('codigo', $codigo)->exists()) {
            $suf = '_'.$i;
            $codigo = substr($base, 0, max(1, 20 - strlen($suf))).$suf;
            $i++;
        }

        return $codigo;
    }
}
