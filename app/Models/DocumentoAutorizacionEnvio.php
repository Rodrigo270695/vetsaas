<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * @property string $id
 * @property ?string $plantilla_id
 * @property string $consulta_id
 * @property string $paciente_id
 * @property ?string $propietario_id
 * @property string $titulo
 * @property string $cuerpo_snapshot
 * @property string $token
 * @property string $estado
 * @property Carbon $expires_at
 * @property ?Carbon $firmado_at
 * @property ?string $firmante_nombre
 * @property ?string $firmante_documento
 * @property ?string $firma_path
 * @property ?string $pdf_path
 * @property ?string $ip
 * @property bool $enviado_whatsapp
 * @property bool $enviado_email
 */
class DocumentoAutorizacionEnvio extends Model
{
    use HasUuids;

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_FIRMADO = 'firmado';

    protected $table = 'documento_autorizacion_envios';

    protected $fillable = [
        'plantilla_id',
        'consulta_id',
        'paciente_id',
        'propietario_id',
        'titulo',
        'cuerpo_snapshot',
        'token',
        'estado',
        'expires_at',
        'firmado_at',
        'firmante_nombre',
        'firmante_documento',
        'firma_path',
        'pdf_path',
        'ip',
        'enviado_whatsapp',
        'enviado_email',
        'created_by_id',
    ];

    protected $appends = [
        'pdf_url',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'firmado_at' => 'datetime',
            'enviado_whatsapp' => 'boolean',
            'enviado_email' => 'boolean',
        ];
    }

    public function pdfUrl(): ?string
    {
        if ($this->estado !== self::ESTADO_FIRMADO || $this->pdf_path === null || $this->pdf_path === '') {
            return null;
        }

        return URL::route('clinica.documentos-autorizacion.pdf', $this);
    }

    public function getPdfUrlAttribute(): ?string
    {
        return $this->pdfUrl();
    }

    public function isPending(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE && $this->expires_at->isFuture();
    }

    public function diskPathExists(): bool
    {
        return is_string($this->pdf_path) && $this->pdf_path !== '' && Storage::disk('public')->exists($this->pdf_path);
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(DocumentoAutorizacionPlantilla::class, 'plantilla_id');
    }

    public function consulta(): BelongsTo
    {
        return $this->belongsTo(Consulta::class, 'consulta_id');
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function propietario(): BelongsTo
    {
        return $this->belongsTo(Propietario::class, 'propietario_id');
    }
}
