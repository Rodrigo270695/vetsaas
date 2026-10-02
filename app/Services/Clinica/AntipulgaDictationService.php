<?php

declare(strict_types=1);

namespace App\Services\Clinica;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Dictado de una ficha de antipulgas: audio o texto → campos del formulario.
 */
final class AntipulgaDictationService
{
    public function __construct(private ConsultaDictationService $consulta) {}

    public function isConfigured(): bool
    {
        return $this->consulta->isConfigured();
    }

    /**
     * @return array{transcript: string, fields: array<string, mixed>}
     */
    public function fromAudio(UploadedFile $audio): array
    {
        return $this->fromTranscript($this->consulta->transcribe($audio));
    }

    /**
     * @return array{transcript: string, fields: array<string, mixed>}
     */
    public function fromTranscript(string $transcript): array
    {
        $transcript = trim($transcript);
        if ($transcript === '') {
            throw new RuntimeException('La transcripción está vacía.');
        }

        return [
            'transcript' => $transcript,
            'fields' => $this->structureFields($transcript),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function structureFields(string $transcript): array
    {
        $apiKey = trim((string) config('consulta-dictation.openai_api_key', ''));
        if ($apiKey === '') {
            $apiKey = trim((string) config('in-app-assistant.openai_api_key', ''));
        }
        if ($apiKey === '') {
            $apiKey = trim((string) config('bot-ia.openai_api_key', ''));
        }
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY no está configurada.');
        }

        $model = (string) config('consulta-dictation.openai_model', config('in-app-assistant.openai_model', 'gpt-4o-mini'));

        $system = <<<'PROMPT'
Eres un asistente clínico veterinario. El texto es una conversación o un dictado de ANTIPULGAS, no de una consulta general.

Extrae solo lo que se mencione. No inventes. No extraigas anamnesis ni signos digestivos. Responde ÚNICAMENTE JSON válido con estas claves (null si no se dijo):
{
  "peso_kg": string|null,
  "temperatura_c": string|null,
  "fc_lpm": string|null,
  "fr_rpm": string|null,
  "tlc": string|null,
  "pa": string|null,
  "hidratacion": string|null,
  "producto_nombre": string|null,
  "especificaciones": string|null,
  "proxima_nombre": string|null,
  "comentarios": string|null
}

producto_nombre: el antipulgas aplicado si se nombra.
comentarios: notas libres que no sean constantes ni el producto.
PROMPT;

        $userContent = mb_strlen($transcript) > 12000
            ? mb_substr($transcript, 0, 12000)
            : $transcript;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(90)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model,
            'temperature' => 0.15,
            'max_tokens' => 2000,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $userContent],
            ],
        ]);

        if (! $response->successful()) {
            Log::error('AntipulgaDictation structure error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('No pude estructurar el dictado (HTTP '.$response->status().').');
        }

        $content = trim((string) ($response->json('choices.0.message.content') ?? ''));
        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('La IA no devolvió un JSON válido.');
        }

        return $this->normalize($decoded);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalize(array $raw): array
    {
        $text = static function (mixed $value, int $max = 4000): ?string {
            if (! is_string($value) && ! is_numeric($value)) {
                return null;
            }
            $v = trim((string) $value);

            if ($v === '') {
                return null;
            }

            return mb_strlen($v) > $max ? mb_substr($v, 0, $max) : $v;
        };

        return [
            'peso_kg' => $text($raw['peso_kg'] ?? null, 12),
            'temperatura_c' => $text($raw['temperatura_c'] ?? null, 8),
            'fc_lpm' => $text($raw['fc_lpm'] ?? null, 6),
            'fr_rpm' => $text($raw['fr_rpm'] ?? null, 6),
            'tlc' => $text($raw['tlc'] ?? null, 40),
            'pa' => $text($raw['pa'] ?? null, 40),
            'hidratacion' => $text($raw['hidratacion'] ?? null, 40),
            'producto_nombre' => $text($raw['producto_nombre'] ?? null, 160),
            'especificaciones' => $text($raw['especificaciones'] ?? null, 500),
            'proxima_nombre' => $text($raw['proxima_nombre'] ?? null, 160),
            'comentarios' => $text($raw['comentarios'] ?? null),
        ];
    }
}
