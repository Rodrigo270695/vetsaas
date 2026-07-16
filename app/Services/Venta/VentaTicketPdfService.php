<?php

declare(strict_types=1);

namespace App\Services\Venta;

use App\Models\ClinicSetting;
use App\Models\Sede;
use App\Models\Tenant;
use App\Models\Venta;
use App\Support\Caja\TicketAnchoMm;
use App\Support\Caja\VentaTicketPolicy;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Genera el PDF del ticket térmico de una venta (mismo contenido que la vista de impresión).
 */
final class VentaTicketPdfService
{
    /**
     * @return array<string, mixed>
     */
    public function viewData(Venta $venta, ClinicSetting $cfg, string $tenantId, ?string $anchoMm = null): array
    {
        $venta->loadMissing([
            'felDocument',
            'lineas' => fn ($q) => $q->orderBy('id'),
            'propietario:id,nombres,apellidos,razon_social,numero_documento',
            'paciente:id,nombre',
            'creadoPor:id,name',
        ]);

        $ancho = TicketAnchoMm::normalize($anchoMm, (string) $cfg->ticket_ancho_mm);

        $propietario = $venta->propietario;
        $clienteNombre = $propietario === null
            ? '—'
            : ($propietario->razon_social ?: trim(implode(' ', array_filter([$propietario->nombres, $propietario->apellidos]))));

        $sedeNombre = Sede::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($venta->sede_id)
            ->value('nombre');

        $metodoPagoLabel = $venta->metodo_pago !== null
            ? __('caja.ventas.ticket.metodo_'.$venta->metodo_pago)
            : null;

        $lineas = $venta->lineas->map(fn ($ln): array => [
            'descripcion' => $ln->descripcion_snapshot,
            'cantidad' => (string) $ln->cantidad,
            'subtotal' => (string) $ln->subtotal,
        ])->values()->all();

        $clinicNombre = $cfg->nombre_comercial ?: $cfg->razon_social ?: config('app.name');
        $trim = static function (?string $v): ?string {
            if ($v === null) {
                return null;
            }
            $t = trim($v);

            return $t === '' ? null : $t;
        };

        $fechaCobro = ($venta->fecha_pago ?? $venta->created_at)
            ?->timezone(config('app.timezone'))
            ->format('d/m/Y H:i') ?? '—';

        return [
            'ancho_mm' => $ancho,
            'clinic_logo_url' => $cfg->logo_url,
            'clinic_nombre' => $clinicNombre,
            'clinic_ruc' => $trim($cfg->ruc),
            'clinic_direccion' => $trim($cfg->direccion_fiscal),
            'clinic_telefono' => $trim($cfg->telefono_principal),
            'moneda' => $venta->moneda,
            'igv_porcentaje' => (string) $cfg->igv_porcentaje,
            'venta' => $venta,
            'lineas' => $lineas,
            'fecha_cobro' => $fechaCobro,
            'sede_nombre' => $sedeNombre,
            'cliente_nombre' => $clienteNombre,
            'cliente_doc' => $propietario?->numero_documento,
            'paciente_nombre' => $venta->paciente?->nombre,
            'cajero_nombre' => $venta->creadoPor?->name,
            'metodo_pago_label' => $metodoPagoLabel,
            'cpe_numero' => $venta->felDocument?->numero_completo,
            'auto_print' => false,
        ];
    }

    /**
     * PDF para impresión en caja (respeta política de ticket).
     *
     * @return array{binary: string, filename: string}|null
     */
    public function renderIfAllowed(Venta $venta, ClinicSetting $cfg, ?Tenant $tenant, string $tenantId): ?array
    {
        if (! VentaTicketPolicy::puedeImprimir($venta, $cfg, $tenant)) {
            return null;
        }

        return $this->renderPdf($venta, $cfg, $tenantId);
    }

    /**
     * PDF para WhatsApp: cualquier venta pagada no anulada (ticket interno).
     *
     * @return array{binary: string, filename: string}|null
     */
    public function renderForWhatsApp(Venta $venta, ClinicSetting $cfg, string $tenantId): ?array
    {
        if ($venta->estado !== Venta::ESTADO_PAGADO || $venta->estaAnulada()) {
            return null;
        }

        return $this->renderPdf($venta, $cfg, $tenantId);
    }

    /**
     * @return array{binary: string, filename: string}
     */
    private function renderPdf(Venta $venta, ClinicSetting $cfg, string $tenantId): array
    {
        $data = $this->viewData($venta, $cfg, $tenantId);
        $logoDataUri = $this->logoDataUri($cfg);
        if ($logoDataUri !== null) {
            $data['clinic_logo_url'] = $logoDataUri;
        }
        $data['tf'] = TicketAnchoMm::typography((string) $data['ancho_mm']);

        try {
            // A4 es más estable en DomPDF que el rollo térmico en mm.
            $pdf = Pdf::loadView('pdf.venta-ticket', $data);
            $pdf->setPaper('a4', 'portrait');
            $binary = $pdf->output();
        } catch (\Throwable $e) {
            Log::warning('DomPDF ticket venta falló', [
                'venta_id' => $venta->id,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('No se pudo generar el PDF del ticket: '.$e->getMessage(), 0, $e);
        }

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('El PDF del ticket quedó vacío.');
        }

        $safeNumero = preg_replace('/[^A-Za-z0-9_-]+/', '-', $venta->numero) ?: 'venta';

        return [
            'binary' => $binary,
            'filename' => 'ticket-'.$safeNumero.'.pdf',
        ];
    }

    private function logoDataUri(ClinicSetting $clinic): ?string
    {
        $path = $clinic->logo_path;
        if ($path === null || $path === '') {
            return null;
        }

        $path = ltrim((string) $path, '/');
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        $binary = Storage::disk('public')->get($path);
        $mime = Storage::disk('public')->mimeType($path) ?? 'image/png';
        if (! is_string($mime) || ! str_starts_with($mime, 'image/')) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode((string) $binary);
    }
}
