<?php

namespace App\Support\Pdf;

use App\Models\Consulta;
use App\Models\HistoriaClinica;
use App\Models\Paciente;
use App\Models\VacunaAplicada;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class HistorialClinicoPdfBuilder
{
    public function __construct(
        private readonly string $timezone,
    ) {}

    public static function make(): self
    {
        return new self((string) config('app.timezone', 'UTC'));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function entriesForPaciente(
        Paciente $paciente,
        bool $includeConsultas,
        bool $includeAplicaciones,
        ?int $limit = 200,
    ): Collection {
        $entries = collect();

        if ($includeConsultas) {
            $hc = HistoriaClinica::query()->where('paciente_id', $paciente->id)->first();
            if ($hc !== null) {
                $consultas = $hc->consultas()
                    ->with([
                        'veterinario:id,name',
                        'recetas:id,consulta_id,estado',
                        'pedidosLaboratorio:id,consulta_id,estado',
                        'cirugias:id,consulta_id,estado,nombre_procedimiento',
                        'internamientos:id,consulta_id,estado,motivo_ingreso',
                    ])
                    ->orderByDesc('atendido_at')
                    ->limit($limit)
                    ->get();

                foreach ($consultas as $consulta) {
                    $entries->push($this->fromConsulta($consulta));
                }
            }
        }

        if ($includeAplicaciones) {
            $vacunas = VacunaAplicada::query()
                ->where('paciente_id', $paciente->id)
                ->with([
                    'veterinario:id,name',
                    'sede:id,nombre,codigo',
                    'producto:id,nombre,sku',
                ])
                ->orderByDesc('aplicada_at')
                ->limit($limit)
                ->get();

            foreach ($vacunas as $vacuna) {
                $entries->push($this->fromAplicacion($vacuna));
            }
        }

        return $entries
            ->sortByDesc('sort_at')
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function fromConsulta(Consulta $consulta): array
    {
        $at = $consulta->atendido_at;

        $signos = array_values(array_filter([
            $this->signoLine(__('historial_clinico.label_weight'), $consulta->peso_kg !== null && trim((string) $consulta->peso_kg) !== '' ? trim((string) $consulta->peso_kg).' kg' : null),
            $this->signoLine(__('historial_clinico.label_temp'), $consulta->temperatura_c !== null && trim((string) $consulta->temperatura_c) !== '' ? trim((string) $consulta->temperatura_c).' °C' : null),
            $this->signoLine(__('historial_clinico.label_hr'), $consulta->fc_lpm !== null ? (string) $consulta->fc_lpm.' lpm' : null),
            $this->signoLine(__('historial_clinico.label_rr'), $consulta->fr_rpm !== null ? (string) $consulta->fr_rpm.' rpm' : null),
        ]));

        $soap = array_values(array_filter([
            $this->soapBlock(__('historial_clinico.soap_subjective'), $consulta->subjetivo),
            $this->soapBlock(__('historial_clinico.soap_objective'), $consulta->objetivo),
            $this->soapBlock(__('historial_clinico.soap_assessment'), $consulta->analisis),
            $this->soapBlock(__('historial_clinico.soap_plan'), $consulta->plan),
        ]));

        $vinculos = array_values(array_filter([
            $consulta->recetas->count() > 0
                ? __('historial_clinico.link_prescriptions', ['count' => $consulta->recetas->count()])
                : null,
            $consulta->pedidosLaboratorio->count() > 0
                ? __('historial_clinico.link_laboratory', ['count' => $consulta->pedidosLaboratorio->count()])
                : null,
            $consulta->cirugias->count() > 0
                ? __('historial_clinico.link_surgeries', ['count' => $consulta->cirugias->count()])
                : null,
            $consulta->internamientos->count() > 0
                ? __('historial_clinico.link_hospitalizations', ['count' => $consulta->internamientos->count()])
                : null,
        ]));

        $motivo = trim((string) ($consulta->motivo ?? ''));

        return [
            'kind' => 'consulta',
            'sort_at' => $at?->toIso8601String() ?? '',
            'fecha' => $this->formatDatetime($at),
            'tipo_label' => __('historial_clinico.kind_consultation'),
            'titulo' => $motivo !== '' ? $motivo : '—',
            'estado_label' => $consulta->cerrada_at !== null
                ? __('historial_clinico.status_closed')
                : __('historial_clinico.status_open'),
            'veterinario' => $consulta->veterinario?->name,
            'signos' => $signos,
            'soap' => $soap,
            'vinculos' => $vinculos,
            'fields' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fromAplicacion(VacunaAplicada $vacuna): array
    {
        $sedeTxt = null;
        if ($vacuna->sede !== null) {
            $sedeTxt = $vacuna->sede->nombre;
            if ($vacuna->sede->codigo) {
                $sedeTxt .= ' ('.$vacuna->sede->codigo.')';
            }
        }

        $proxima = '—';
        if ($vacuna->fecha_proxima_sugerida !== null) {
            $proxima = Carbon::parse($vacuna->fecha_proxima_sugerida)->format('d/m/Y');
        }

        $fields = array_values(array_filter([
            $this->field(__('historial_clinico.label_product'), $vacuna->producto?->nombre),
            $this->field(__('historial_clinico.label_sku'), $vacuna->producto?->sku),
            $this->field(__('historial_clinico.label_batch'), $vacuna->lote),
            $this->field(__('historial_clinico.label_dose'), $vacuna->numero_dosis !== null ? (string) $vacuna->numero_dosis : null),
            $this->field(__('historial_clinico.label_next'), $proxima !== '—' ? $proxima : null),
            $this->field(__('historial_clinico.label_schema'), $vacuna->esquema_antigenos),
            $this->field(__('historial_clinico.label_branch'), $sedeTxt),
            $this->field(__('historial_clinico.label_notes'), $vacuna->notas),
        ]));

        return [
            'kind' => 'aplicacion',
            'sort_at' => $vacuna->aplicada_at?->toIso8601String() ?? '',
            'fecha' => $this->formatDatetime($vacuna->aplicada_at),
            'tipo_label' => $this->categoriaLabel((string) $vacuna->categoria_registro),
            'titulo' => $vacuna->nombre_vacuna,
            'estado_label' => __('historial_clinico.kind_application'),
            'veterinario' => $vacuna->veterinario?->name,
            'signos' => [],
            'soap' => [],
            'vinculos' => [],
            'fields' => $fields,
        ];
    }

    /**
     * @return array{label: string, value: string}|null
     */
    private function field(string $label, mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);
        if ($text === '' || $text === '—') {
            return null;
        }

        return ['label' => $label, 'value' => $text];
    }

    /**
     * @return array{label: string, value: string}|null
     */
    private function signoLine(string $label, ?string $value): ?array
    {
        return $this->field($label, $value);
    }

    /**
     * @return array{label: string, text: string}|null
     */
    private function soapBlock(string $label, ?string $text): ?array
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        return ['label' => $label, 'text' => trim($text)];
    }

    private function formatDatetime(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        return Carbon::parse($value)->timezone($this->timezone)->format('d/m/Y H:i');
    }

    private function categoriaLabel(string $categoria): string
    {
        return match ($categoria) {
            VacunaAplicada::CATEGORIA_DESPARASITACION => __('carnet_vacunacion.categoria_desparasitacion'),
            VacunaAplicada::CATEGORIA_OTRO => __('carnet_vacunacion.categoria_otro'),
            default => __('carnet_vacunacion.categoria_vacuna'),
        };
    }
}
