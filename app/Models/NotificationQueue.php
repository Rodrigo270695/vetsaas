<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NotificationQueue extends Model
{
    use HasUuids;

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_PROCESANDO = 'procesando';

    public const ESTADO_ENVIADO = 'enviado';

    public const ESTADO_FALLIDO = 'fallido';

    public const ESTADO_CANCELADO = 'cancelado';

    public const CANAL_WHATSAPP = 'whatsapp';

    protected $table = 'notifications_queue';

    protected $fillable = [
        'tipo',
        'canal',
        'destinatario',
        'destinatario_nombre',
        'asunto',
        'cuerpo',
        'referencia_tipo',
        'referencia_id',
        'dedupe_key',
        'enviar_at',
        'prioridad',
        'estado',
        'intentos',
        'max_intentos',
        'ultimo_intento_at',
        'error_mensaje',
        'proveedor_msg_id',
    ];

    protected function casts(): array
    {
        return [
            'enviar_at' => 'datetime',
            'ultimo_intento_at' => 'datetime',
            'prioridad' => 'integer',
            'intentos' => 'integer',
            'max_intentos' => 'integer',
        ];
    }
}
