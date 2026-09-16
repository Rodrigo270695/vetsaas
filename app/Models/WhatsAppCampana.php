<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\WhatsApp\WhatsAppCampaignClock;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $nombre
 * @property ?string $imagen_path
 * @property list<string> $variantes
 * @property int $tope_diario
 * @property int $intervalo_minutos
 * @property string $hora_inicio
 * @property string $hora_fin
 * @property string $estado
 * @property ?Carbon $last_sent_at
 * @property ?Carbon $started_at
 * @property ?Carbon $paused_at
 */
class WhatsAppCampana extends Model
{
    use HasUuids;

    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_ENVIANDO = 'enviando';

    public const ESTADO_PAUSADA = 'pausada';

    public const ESTADO_TERMINADA = 'terminada';

    protected $table = 'whatsapp_campanas';

    protected $fillable = [
        'nombre',
        'imagen_path',
        'variantes',
        'tope_diario',
        'intervalo_minutos',
        'hora_inicio',
        'hora_fin',
        'estado',
        'last_sent_at',
        'started_at',
        'paused_at',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'variantes' => 'array',
            'tope_diario' => 'integer',
            'intervalo_minutos' => 'integer',
            'last_sent_at' => 'datetime',
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
        ];
    }

    public function destinatarios(): HasMany
    {
        return $this->hasMany(WhatsAppCampanaDestinatario::class, 'campana_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return list<string>
     */
    public function variantesLimpias(): array
    {
        $raw = $this->variantes;
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (! is_string($item)) {
                continue;
            }
            $text = trim($item);
            if ($text !== '') {
                $out[] = $text;
            }
        }

        return array_values($out);
    }

    public function imagenUrl(): ?string
    {
        $path = trim((string) $this->imagen_path);
        if ($path === '') {
            return null;
        }

        $url = Storage::disk('public')->url($path);
        if (str_starts_with($url, 'http')) {
            return $url;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
    }

    public function enviadosHoy(): int
    {
        $lima = WhatsAppCampaignClock::now();

        return $this->destinatarios()
            ->where('estado', WhatsAppCampanaDestinatario::ESTADO_ENVIADO)
            ->whereBetween('enviado_at', [$lima->copy()->startOfDay(), $lima->copy()->endOfDay()])
            ->count();
    }

    public function intervaloEfectivo(): int
    {
        return max(1, min(30, (int) $this->intervalo_minutos));
    }

    public function inSendWindow(?CarbonInterface $now = null): bool
    {
        $lima = WhatsAppCampaignClock::now($now);
        $current = ($lima->hour * 60) + $lima->minute;
        $start = WhatsAppCampaignClock::minutes($this->hora_inicio);
        $end = WhatsAppCampaignClock::minutes($this->hora_fin);

        return $current >= $start && $current <= $end;
    }

    /**
     * @return array{code: string, label: string}
     */
    public function pacingHint(?CarbonInterface $now = null): array
    {
        $lima = WhatsAppCampaignClock::now($now);
        $start = WhatsAppCampaignClock::hm($this->hora_inicio);
        $end = WhatsAppCampaignClock::hm($this->hora_fin);
        $interval = $this->intervaloEfectivo();

        if ($this->estado === self::ESTADO_BORRADOR) {
            return ['code' => 'draft', 'label' => 'Borrador'];
        }

        if ($this->estado === self::ESTADO_PAUSADA) {
            return ['code' => 'paused', 'label' => 'Pausada'];
        }

        if ($this->estado === self::ESTADO_TERMINADA) {
            return ['code' => 'done', 'label' => 'Terminada'];
        }

        if (! $this->inSendWindow($lima)) {
            return [
                'code' => 'window',
                'label' => "Fuera de horario ({$start}–{$end}, hora Perú {$lima->format('H:i')})",
            ];
        }

        if ($this->last_sent_at instanceof CarbonInterface
            && $this->last_sent_at->copy()->addMinutes($interval)->greaterThan($lima)
        ) {
            $next = $this->last_sent_at
                ->copy()
                ->addMinutes($interval)
                ->timezone(WhatsAppCampaignClock::TIMEZONE);

            return ['code' => 'wait', 'label' => 'Próximo '.$next->format('H:i').' (hora Perú)'];
        }

        return ['code' => 'due', 'label' => 'Saliendo ahora · '.$lima->format('H:i').' hora Perú'];
    }
}
