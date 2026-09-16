<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $campana_id
 * @property string $propietario_id
 * @property string $telefono_normalizado
 * @property string $nombre_snapshot
 * @property ?string $mascota_nombres
 * @property ?int $variante_index
 * @property ?string $cuerpo_enviado
 * @property string $estado
 * @property ?string $error
 * @property ?Carbon $enviado_at
 */
class WhatsAppCampanaDestinatario extends Model
{
    use HasUuids;

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_ENVIADO = 'enviado';

    public const ESTADO_FALLIDO = 'fallido';

    public const ESTADO_OMITIDO = 'omitido';

    protected $table = 'whatsapp_campana_destinatarios';

    protected $fillable = [
        'campana_id',
        'propietario_id',
        'telefono_normalizado',
        'nombre_snapshot',
        'mascota_nombres',
        'variante_index',
        'cuerpo_enviado',
        'estado',
        'error',
        'enviado_at',
    ];

    protected function casts(): array
    {
        return [
            'variante_index' => 'integer',
            'enviado_at' => 'datetime',
        ];
    }

    public function campana(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampana::class, 'campana_id');
    }

    public function propietario(): BelongsTo
    {
        return $this->belongsTo(Propietario::class, 'propietario_id');
    }
}
