<?php

declare(strict_types=1);

namespace App\Services\Fel;

use App\Models\ClinicSetting;
use App\Models\FelDocument;
use App\Models\Venta;
use App\Support\Fel\ApisunatCredentialResolver;
use App\Support\Fel\FelDocumentApisunatModeResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Sincroniza el estado Lucode/SUNAT (ACEPTADO / PENDIENTE / RECHAZADO)
 * hacia fel_documents y ventas.fel_estado.
 */
final class FelDocumentStatusSyncService
{
    public function __construct(
        private readonly ApisunatClient $apisunat,
        private readonly FelDocumentApisunatFileService $files,
    ) {}

    /**
     * @return array{
     *     checked: int,
     *     updated: int,
     *     accepted: int,
     *     pending: int,
     *     rejected: int,
     *     failed: int,
     *     errors: list<string>
     * }
     */
    public function syncClinic(?ClinicSetting $clinic = null, int $limit = 100): array
    {
        $clinic ??= ClinicSetting::current();
        $stats = [
            'checked' => 0,
            'updated' => 0,
            'accepted' => 0,
            'pending' => 0,
            'rejected' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        if (! ApisunatCredentialResolver::estaConfigurado($clinic)) {
            return $stats;
        }

        try {
            $credenciales = ApisunatCredentialResolver::fromClinicSetting($clinic);
        } catch (RuntimeException $e) {
            $stats['errors'][] = $e->getMessage();

            return $stats;
        }

        $docs = FelDocument::query()
            ->whereIn('estado', [
                FelDocument::ESTADO_EMITIDO,
                FelDocument::ESTADO_PENDIENTE,
                FelDocument::ESTADO_RECHAZADO,
            ])
            ->whereNotNull('serie')
            ->whereNotNull('correlativo')
            // Pendientes primero (desfase Lucode vs VetSaaS), luego emitidos
            // (pueden estar mal marcados), luego rechazados.
            ->orderByRaw("CASE estado WHEN 'pendiente' THEN 0 WHEN 'emitido' THEN 1 WHEN 'rechazado' THEN 2 ELSE 3 END")
            ->orderByDesc('emitido_at')
            ->orderByDesc('updated_at')
            ->limit(max(1, min(500, $limit)))
            ->get();

        foreach ($docs as $doc) {
            $stats['checked']++;
            try {
                $result = $this->syncDocument($doc, $clinic, $credenciales);
                if ($result['changed']) {
                    $stats['updated']++;
                }
                match ($result['sunat']) {
                    'ACEPTADO' => $stats['accepted']++,
                    'PENDIENTE' => $stats['pending']++,
                    'RECHAZADO', 'EXCEPCION' => $stats['rejected']++,
                    default => null,
                };
            } catch (Throwable $e) {
                $stats['failed']++;
                $stats['errors'][] = ($doc->numero_completo ?? $doc->id).': '.$e->getMessage();
                Log::warning('fel.status_sync_failed', [
                    'fel_document_id' => $doc->id,
                    'numero' => $doc->numero_completo,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * @param  array{token: string, mode: 'sandbox'|'produccion'}|null  $credenciales
     * @return array{changed: bool, sunat: ?string, estado: string}
     */
    public function syncDocument(
        FelDocument $document,
        ?ClinicSetting $clinic = null,
        ?array $credenciales = null,
    ): array {
        $clinic ??= ClinicSetting::current();
        $credenciales ??= ApisunatCredentialResolver::fromClinicSetting($clinic);

        $docMode = FelDocumentApisunatModeResolver::resolveAndPersist($document);
        if (in_array($docMode, ['sandbox', 'produccion'], true)) {
            $credenciales['mode'] = $docMode;
        }

        $docNombre = $this->apisunat->nombreDocumentoTipo((int) $document->tipo_comprobante);
        $respuesta = $this->apisunat->consultarEstado(
            $credenciales,
            $docNombre,
            (string) $document->serie,
            (int) $document->correlativo,
        );

        $sunat = $this->apisunat->extraerEstado($respuesta);
        if ($sunat === null) {
            if (! ($respuesta['success'] ?? false)) {
                throw new RuntimeException(
                    is_string($respuesta['message'] ?? null)
                        ? (string) $respuesta['message']
                        : 'APISUNAT no devolvió estado.',
                );
            }

            throw new RuntimeException('APISUNAT no devolvió el campo payload.estado.');
        }

        $enlaces = $this->apisunat->extraerEnlaces($respuesta);
        $error = null;
        $cdrDebug = null;
        if (in_array($sunat, ['RECHAZADO', 'EXCEPCION'], true)) {
            $resolved = $this->resolverMotivoRechazo($respuesta, $document, $enlaces['cdr'] ?? null, $clinic);
            $error = $resolved['motivo'];
            $cdrDebug = $resolved['debug'];
        }

        return DB::transaction(function () use (
            $document,
            $respuesta,
            $sunat,
            $credenciales,
            $enlaces,
            $error,
            $cdrDebug,
        ): array {
            $locked = FelDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $before = (string) $locked->estado;
            $beforeError = (string) ($locked->error_mensaje ?? '');
            $mapped = $this->mapSunatToLocal($sunat);

            $payloadMerge = [
                'success' => (bool) ($respuesta['success'] ?? false),
                'message' => $respuesta['message'] ?? null,
                'payload' => $respuesta['payload'] ?? null,
                '_http_status' => $respuesta['_http_status'] ?? null,
                '_vetsaas_emission_mode' => $credenciales['mode'],
                '_vetsaas_api_base' => $respuesta['_vetsaas_api_base'] ?? null,
                '_vetsaas_status_synced_at' => now()->toIso8601String(),
            ];
            if ($cdrDebug !== null) {
                $payloadMerge['_vetsaas_cdr_motivo_debug'] = $cdrDebug;
            }

            $locked->fill([
                'estado' => $mapped['fel_document'],
                'nubefact_id' => 'apisunat:'.$sunat,
                'url_pdf' => $enlaces['pdf'] ?? $locked->url_pdf,
                'url_xml' => $enlaces['xml'] ?? $locked->url_xml,
                'url_cdr' => $enlaces['cdr'] ?? $locked->url_cdr,
                'enlace_consulta' => $enlaces['consulta'] ?? $locked->enlace_consulta,
                'apisunat_payload' => array_merge(
                    is_array($locked->apisunat_payload) ? $locked->apisunat_payload : [],
                    $payloadMerge,
                ),
                'apisunat_mode' => $credenciales['mode'],
                'error_mensaje' => $error,
            ]);

            if ($mapped['fel_document'] === FelDocument::ESTADO_EMITIDO && $locked->emitido_at === null) {
                $locked->emitido_at = now();
            }

            $locked->save();

            Venta::query()
                ->whereKey($locked->venta_id)
                ->update(['fel_estado' => $mapped['venta']]);

            return [
                'changed' => $before !== $mapped['fel_document']
                    || $beforeError !== (string) ($error ?? ''),
                'sunat' => $sunat,
                'estado' => $mapped['fel_document'],
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $respuesta
     * @return array{motivo: string, debug: array<string, mixed>}
     */
    private function resolverMotivoRechazo(
        array $respuesta,
        FelDocument $document,
        ?string $cdrUrlFromStatus,
        ClinicSetting $clinic,
    ): array {
        $motivo = $this->apisunat->extraerMotivoRechazo($respuesta);
        $debug = [
            'cdr_urls_tried' => [],
            'cdr_error' => null,
            'cdr_parsed' => false,
        ];

        if ($motivo !== '' && ! $this->apisunat->esMensajeGenericoRechazo($motivo)) {
            return ['motivo' => mb_substr($motivo, 0, 2000), 'debug' => $debug];
        }

        $cdrUrls = [];
        foreach ([
            $cdrUrlFromStatus,
            $document->url_cdr,
            data_get($respuesta, 'payload.cdr'),
            data_get($document->apisunat_payload, 'payload.cdr'),
            data_get($document->apisunat_payload, 'cdr'),
        ] as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }
            $candidate = trim($candidate);
            if ($candidate === '' || in_array($candidate, $cdrUrls, true)) {
                continue;
            }
            $cdrUrls[] = $candidate;
        }

        foreach ($cdrUrls as $cdrUrl) {
            $debug['cdr_urls_tried'][] = $cdrUrl;
            try {
                $cdrBody = $this->files->descargarUrl($cdrUrl, $clinic, 'cdr');
                $desdeCdr = ApisunatCdrMotivoExtractor::fromXml($cdrBody);
                if (is_string($desdeCdr) && $desdeCdr !== '') {
                    $debug['cdr_parsed'] = true;

                    return ['motivo' => mb_substr($desdeCdr, 0, 2000), 'debug' => $debug];
                }
                $debug['cdr_error'] = 'CDR descargado pero sin Description/ResponseCode parseable (len='.strlen($cdrBody).').';
            } catch (Throwable $e) {
                $debug['cdr_error'] = $e->getMessage();
                Log::warning('fel.cdr_motivo_unavailable', [
                    'fel_document_id' => $document->id,
                    'numero' => $document->numero_completo,
                    'message' => $e->getMessage(),
                    'cdr' => $cdrUrl,
                ]);
            }
        }

        if ($cdrUrls === []) {
            $debug['cdr_error'] = 'Lucode no devolvió URL de CDR para este rechazo.';
        }

        // Si solo hay mensaje genérico, deja pista accionable (el detalle real no viene en /status).
        if ($motivo !== '' && $this->apisunat->esMensajeGenericoRechazo($motivo)) {
            $hint = $debug['cdr_error'] ?? 'sin CDR';

            return [
                'motivo' => mb_substr(
                    'Rechazado por SUNAT (Lucode no envió el detalle técnico). '
                    .$hint
                    .' Descarga el CDR en Acciones o revísalo en el panel Lucode.',
                    0,
                    2000,
                ),
                'debug' => $debug,
            ];
        }

        if ($motivo !== '') {
            return ['motivo' => mb_substr($motivo, 0, 2000), 'debug' => $debug];
        }

        return [
            'motivo' => 'APISUNAT: RECHAZADO (sin detalle; descarga el CDR).',
            'debug' => $debug,
        ];
    }

    /**
     * @return array{fel_document: string, venta: string}
     */
    public function mapSunatToLocal(string $sunatEstado): array
    {
        return match (strtoupper($sunatEstado)) {
            'ACEPTADO' => [
                'fel_document' => FelDocument::ESTADO_EMITIDO,
                'venta' => Venta::FEL_EMITIDO,
            ],
            'PENDIENTE' => [
                'fel_document' => FelDocument::ESTADO_PENDIENTE,
                'venta' => Venta::FEL_PENDIENTE,
            ],
            'RECHAZADO', 'EXCEPCION' => [
                'fel_document' => FelDocument::ESTADO_RECHAZADO,
                'venta' => Venta::FEL_RECHAZADO,
            ],
            default => [
                'fel_document' => FelDocument::ESTADO_PENDIENTE,
                'venta' => Venta::FEL_PENDIENTE,
            ],
        };
    }
}
