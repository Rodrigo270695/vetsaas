<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro inmutable de actividad dentro del tenant (append-only).
 *
 * @property int $id
 * @property ?string $usuario_id
 * @property ?string $usuario_nombre
 * @property ?string $usuario_email
 * @property string $accion
 * @property string $modulo
 * @property ?string $tabla
 * @property ?string $registro_id
 * @property ?string $registro_label
 * @property ?array<string, mixed> $cambios
 * @property ?string $ip_address
 * @property ?string $user_agent
 * @property \Illuminate\Support\Carbon $created_at
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    public const ACCION_CREATED = 'created';

    public const ACCION_UPDATED = 'updated';

    public const ACCION_DELETED = 'deleted';

    public const ACCION_EXPORTED = 'exported';

    public const ACCION_DOWNLOADED = 'downloaded';

    protected $table = 'audit_logs';

    protected $fillable = [
        'usuario_id',
        'usuario_nombre',
        'usuario_email',
        'accion',
        'modulo',
        'tabla',
        'registro_id',
        'registro_label',
        'cambios',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'cambios' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
