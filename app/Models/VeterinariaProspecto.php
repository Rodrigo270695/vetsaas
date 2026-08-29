<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prospecto comercial (clínica u hospital veterinario) capturado desde
 * directorios públicos (scraping) o registrado manualmente, para
 * prospección de venta de VetSaaS. Vive en `public` (no es multi-tenant).
 *
 * @property string $id
 * @property string $nombre
 * @property string $tipo
 * @property ?string $telefono_normalizado
 * @property ?string $telefono
 * @property ?string $correo
 * @property ?string $direccion
 * @property ?string $departamento
 * @property ?string $provincia
 * @property ?string $distrito
 * @property ?string $horario
 * @property bool $es_24_horas
 * @property ?string $fuente_sitio
 * @property ?string $fuente_url
 * @property ?string $ubicacion_slug
 * @property string $origen
 * @property string $estado
 * @property \Illuminate\Support\Carbon $capturado_at
 * @property ?string $creado_por_id
 */
class VeterinariaProspecto extends Model
{
    use HasUuids, UsesPublicSchema;

    protected $table = 'veterinaria_prospectos';

    public const TIPO_CLINICA = 'clinica';

    public const TIPO_HOSPITAL = 'hospital';

    public const ORIGEN_MANUAL = 'manual';

    public const ORIGEN_SCRAPING = 'scraping_auto';

    public const ESTADOS = [
        'nuevo',
        'contactado',
        'conversando',
        'demo_agendada',
        'cliente',
        'no_interesado',
    ];

    protected $fillable = [
        'nombre',
        'tipo',
        'telefono_normalizado',
        'telefono',
        'correo',
        'direccion',
        'departamento',
        'provincia',
        'distrito',
        'horario',
        'es_24_horas',
        'fuente_sitio',
        'fuente_url',
        'ubicacion_slug',
        'origen',
        'estado',
        'capturado_at',
        'creado_por_id',
    ];

    protected function casts(): array
    {
        return [
            'es_24_horas' => 'boolean',
            'capturado_at' => 'datetime',
        ];
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    public static function normalizarTelefono(?string $telefono): ?string
    {
        if ($telefono === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $telefono) ?? '';

        if ($digits === '') {
            return null;
        }

        // Perú: si viene sin código de país y tiene 9 dígitos (celular) le
        // anteponemos 51 para uniformar el dedupe frente a números con +51.
        if (strlen($digits) === 9 && ! str_starts_with($digits, '51')) {
            $digits = '51'.$digits;
        }

        return $digits;
    }
}
