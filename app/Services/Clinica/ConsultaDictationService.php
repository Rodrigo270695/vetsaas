<?php

declare(strict_types=1);

namespace App\Services\Clinica;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Dictado clínico: audio/texto → vitales + anamnesis destilada + diálogo etiquetado.
 */
final class ConsultaDictationService
{
    public function isConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    /**
     * @return array{
     *     transcript: string,
     *     fields: array{
     *         motivo: ?string,
     *         subjetivo: ?string,
     *         objetivo: ?string,
     *         analisis: ?string,
     *         plan: ?string,
     *         peso_kg: ?string,
     *         temperatura_c: ?string,
     *         fc_lpm: ?string,
     *         fr_rpm: ?string
     *     },
     *     conversation: list<array{role: string, text: string}>,
     *     highlights: list<string>
     * }
     */
    public function fromTranscript(string $transcript): array
    {
        $transcript = trim($transcript);
        if ($transcript === '') {
            throw new RuntimeException('La transcripción está vacía.');
        }

        $structured = $this->structureFields($transcript);

        return [
            'transcript' => $transcript,
            'fields' => $structured['fields'],
            'conversation' => $structured['conversation'],
            'highlights' => $structured['highlights'],
        ];
    }

    /**
     * @return array{
     *     transcript: string,
     *     fields: array{
     *         motivo: ?string,
     *         subjetivo: ?string,
     *         objetivo: ?string,
     *         analisis: ?string,
     *         plan: ?string,
     *         peso_kg: ?string,
     *         temperatura_c: ?string,
     *         fc_lpm: ?string,
     *         fr_rpm: ?string
     *     },
     *     conversation: list<array{role: string, text: string}>,
     *     highlights: list<string>
     * }
     */
    public function fromAudio(UploadedFile $audio): array
    {
        $transcript = $this->transcribe($audio);

        return $this->fromTranscript($transcript);
    }

    public function transcribe(UploadedFile $audio): string
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY no está configurada.');
        }

        $ext = $audio->getClientOriginalExtension() ?: 'webm';
        $filename = $audio->getClientOriginalName() ?: 'dictado.'.$ext;
        $path = $audio->getRealPath();
        if ($path === false || $path === '') {
            throw new RuntimeException('No se pudo leer el audio.');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
        ])->timeout(180)->attach(
            'file',
            fopen($path, 'r'),
            $filename,
        )->post('https://api.openai.com/v1/audio/transcriptions', [
            'model' => (string) config('consulta-dictation.whisper_model', 'whisper-1'),
            'language' => (string) config('consulta-dictation.whisper_lang', 'es'),
        ]);

        if (! $response->successful()) {
            Log::error('ConsultaDictation Whisper error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('No pude transcribir el audio (HTTP '.$response->status().').');
        }

        $text = trim((string) ($response->json('text') ?? ''));
        if ($text === '') {
            throw new RuntimeException('La transcripción quedó vacía.');
        }

        return $text;
    }

    /**
     * @return array{
     *     fields: array{
     *         motivo: ?string,
     *         subjetivo: ?string,
     *         objetivo: ?string,
     *         analisis: ?string,
     *         plan: ?string,
     *         peso_kg: ?string,
     *         temperatura_c: ?string,
     *         fc_lpm: ?string,
     *         fr_rpm: ?string
     *     },
     *     conversation: list<array{role: string, text: string}>,
     *     highlights: list<string>
     * }
     */
    public function structureFields(string $transcript): array
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY no está configurada.');
        }

        $model = (string) config('consulta-dictation.openai_model', config('in-app-assistant.openai_model', 'gpt-4o-mini'));

        $system = <<<'PROMPT'
Eres un asistente clínico veterinario. El audio/texto es una CONVERSACIÓN entre el veterinario y el propietario (dueño) de la mascota. No es un dictado clínico limpio.

Tareas:
1) Identificar quién habla en cada turno (veterinario vs propietario). Si no está claro, usa "desconocido".
2) Extraer signos vitales SOLO si se mencionan explícitamente (peso, temperatura, FC/pulso, FR).
3) Redactar "subjetivo" como ANAMNESIS CLÍNICA: lo importante para la historia, no el diálogo literal. Sintetiza quejas, evolución, ambiente, dietas, medicamentos, alergias, vacunas, hábitos, lo que el dueño relata y lo que el veterinario confirma. 8–20 líneas, español clínico, tercera persona o estilo SOAP subjetivo. NO copies 15 minutos de charla.
4) "highlights": 5–10 viñetas de lo más relevante.
5) "conversation": turnos fusionados (mismo hablante seguido = un turno). Máximo 50 turnos. Cada "text" es el contenido de ese bloque, recortado si es muy largo (hasta ~400 caracteres por turno; prioriza sentido clínico).

Reglas:
- No inventes hechos que no estén en el texto.
- motivo, objetivo, analisis y plan deben ser SIEMPRE null. El relato clínico destilado va SOLO en subjetivo.
- No pongas la transcripción completa en subjetivo.

Responde ÚNICAMENTE JSON válido con:
{
  "motivo": null,
  "subjetivo": string|null,
  "objetivo": null,
  "analisis": null,
  "plan": null,
  "peso_kg": string|null,
  "temperatura_c": string|null,
  "fc_lpm": string|null,
  "fr_rpm": string|null,
  "highlights": string[],
  "conversation": [{"role":"veterinario"|"propietario"|"desconocido","text":"..."}]
}
PROMPT;

        $userContent = $this->truncateForModel($transcript);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(90)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model,
            'temperature' => 0.15,
            'max_tokens' => 4000,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $userContent],
            ],
        ]);

        if (! $response->successful()) {
            Log::error('ConsultaDictation structure error', [
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

        $fields = $this->normalizeFields($decoded, $transcript);
        $conversation = $this->normalizeConversation($decoded, $transcript);
        $highlights = $this->normalizeHighlights($decoded);

        return [
            'fields' => $fields,
            'conversation' => $conversation,
            'highlights' => $highlights,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{
     *     motivo: ?string,
     *     subjetivo: ?string,
     *     objetivo: ?string,
     *     analisis: ?string,
     *     plan: ?string,
     *     peso_kg: ?string,
     *     temperatura_c: ?string,
     *     fc_lpm: ?string,
     *     fr_rpm: ?string
     * }
     */
    private function normalizeFields(array $raw, string $transcriptFallback = ''): array
    {
        $subjetivo = $this->nullableString($raw['subjetivo'] ?? null);
        if ($subjetivo === null) {
            $highlights = $this->normalizeHighlights($raw);
            if ($highlights !== []) {
                $subjetivo = implode("\n", array_map(static fn (string $line): string => '• '.$line, $highlights));
            }
        }
        if ($subjetivo === null) {
            $fallback = trim($transcriptFallback);
            if ($fallback !== '') {
                $subjetivo = mb_strlen($fallback) > 700
                    ? rtrim(mb_substr($fallback, 0, 700)).'…'
                    : $fallback;
            }
        }

        return [
            'motivo' => null,
            'subjetivo' => $subjetivo,
            'objetivo' => null,
            'analisis' => null,
            'plan' => null,
            'peso_kg' => $this->normalizeDecimal($raw['peso_kg'] ?? null),
            'temperatura_c' => $this->normalizeDecimal($raw['temperatura_c'] ?? null),
            'fc_lpm' => $this->normalizeInt($raw['fc_lpm'] ?? null),
            'fr_rpm' => $this->normalizeInt($raw['fr_rpm'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return list<array{role: string, text: string}>
     */
    private function normalizeConversation(array $raw, string $transcript): array
    {
        $source = $raw['conversation'] ?? $raw['turns'] ?? $raw['dialogo'] ?? null;
        $turns = [];
        if (is_array($source)) {
            foreach ($source as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $text = $this->nullableString($item['text'] ?? $item['content'] ?? $item['mensaje'] ?? null);
                if ($text === null) {
                    continue;
                }
                $role = $this->normalizeRole($item['role'] ?? $item['speaker'] ?? $item['hablante'] ?? null);
                $turns[] = ['role' => $role, 'text' => $text];
            }
        }

        $merged = [];
        foreach ($turns as $turn) {
            $last = $merged === [] ? null : $merged[array_key_last($merged)];
            if ($last !== null && $last['role'] === $turn['role']) {
                $merged[array_key_last($merged)]['text'] = trim($last['text'].' '.$turn['text']);
                continue;
            }
            $merged[] = $turn;
        }

        if (count($merged) > 50) {
            $merged = array_slice($merged, 0, 50);
        }

        if ($merged === []) {
            $fallback = trim($transcript);
            if ($fallback !== '') {
                $merged[] = [
                    'role' => 'desconocido',
                    'text' => mb_strlen($fallback) > 1200
                        ? rtrim(mb_substr($fallback, 0, 1200)).'…'
                        : $fallback,
                ];
            }
        }

        return array_values($merged);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return list<string>
     */
    private function normalizeHighlights(array $raw): array
    {
        $source = $raw['highlights'] ?? $raw['puntos'] ?? null;
        if (! is_array($source)) {
            return [];
        }

        $out = [];
        foreach ($source as $item) {
            if (! is_string($item) && ! is_numeric($item)) {
                continue;
            }
            $line = trim((string) $item);
            if ($line === '' || strcasecmp($line, 'null') === 0) {
                continue;
            }
            $out[] = $line;
            if (count($out) >= 12) {
                break;
            }
        }

        return $out;
    }

    private function normalizeRole(mixed $value): string
    {
        $raw = mb_strtolower(trim((string) $value));
        if (in_array($raw, ['veterinario', 'vet', 'doctor', 'dra', 'dr', 'médico', 'medico', 'clinico', 'clínico'], true)) {
            return 'veterinario';
        }
        if (in_array($raw, ['propietario', 'dueño', 'dueno', 'dueña', 'duena', 'owner', 'tutor', 'cliente'], true)) {
            return 'propietario';
        }

        return 'desconocido';
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '' || strcasecmp($value, 'null') === 0) {
            return null;
        }

        return $value;
    }

    private function truncateForModel(string $transcript): string
    {
        if (mb_strlen($transcript) <= 24000) {
            return $transcript;
        }

        $head = mb_substr($transcript, 0, 16000);
        $tail = mb_substr($transcript, -7000);

        return $head."\n\n[…transcripción recortada por longitud…]\n\n".$tail;
    }

    private function normalizeDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
            if ($value === '' || strcasecmp($value, 'null') === 0) {
                return null;
            }
        }
        if (! is_numeric($value)) {
            return null;
        }
        $n = (float) $value;
        if ($n < 0 || $n > 99999) {
            return null;
        }

        return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.') ?: '0';
    }

    private function normalizeInt(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '' || strcasecmp($value, 'null') === 0) {
                return null;
            }
        }
        if (! is_numeric($value)) {
            return null;
        }
        $n = (int) round((float) $value);
        if ($n < 0 || $n > 9999) {
            return null;
        }

        return (string) $n;
    }

    private function apiKey(): string
    {
        $key = trim((string) config('consulta-dictation.openai_api_key', ''));
        if ($key === '') {
            $key = trim((string) config('in-app-assistant.openai_api_key', ''));
        }
        if ($key === '') {
            $key = trim((string) config('bot-ia.openai_api_key', ''));
        }

        return $key;
    }
}
