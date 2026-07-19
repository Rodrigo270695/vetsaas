<?php

declare(strict_types=1);

namespace App\Services\Clinica;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Dictado clínico: audio/texto → campos SOAP + vitales de una consulta.
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
     *     }
     * }
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
     *     }
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
        ])->timeout(90)->attach(
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
    public function structureFields(string $transcript): array
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY no está configurada.');
        }

        $model = (string) config('consulta-dictation.openai_model', config('in-app-assistant.openai_model', 'gpt-4o-mini'));

        $system = <<<'PROMPT'
Eres un asistente veterinario clínico. Recibes el dictado oral de un veterinario sobre una consulta.
Extrae SOLO lo dicho y rellénalo en campos SOAP/vitales en español.
No inventes diagnósticos ni datos que no estén en el texto.
Si un campo no aparece, usa null.
Responde ÚNICAMENTE con JSON válido (sin markdown) con estas claves:
motivo, subjetivo, objetivo, analisis, plan, peso_kg, temperatura_c, fc_lpm, fr_rpm.
- motivo: motivo breve de consulta (string|null)
- subjetivo: anamnesis / lo que reporta el dueño (string|null)
- objetivo: exploración clínica / hallazgos (string|null)
- analisis: impresión diagnóstica / análisis (string|null)
- plan: plan/tratamiento (string|null)
- peso_kg: número como string, ej. "12.5" (string|null)
- temperatura_c: número como string, ej. "38.9" (string|null)
- fc_lpm: entero como string (string|null)
- fr_rpm: entero como string (string|null)
PROMPT;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model,
            'temperature' => 0.1,
            'max_tokens' => 1200,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $transcript],
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

        return $this->normalizeFields($decoded);
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
    private function normalizeFields(array $raw): array
    {
        $textKeys = ['motivo', 'subjetivo', 'objetivo', 'analisis', 'plan'];
        $out = [];

        foreach ($textKeys as $key) {
            $value = $raw[$key] ?? null;
            if (! is_string($value)) {
                $out[$key] = null;
                continue;
            }
            $value = trim($value);
            $out[$key] = $value === '' || strcasecmp($value, 'null') === 0 ? null : $value;
        }

        foreach (['peso_kg', 'temperatura_c'] as $key) {
            $out[$key] = $this->normalizeDecimal($raw[$key] ?? null);
        }
        foreach (['fc_lpm', 'fr_rpm'] as $key) {
            $out[$key] = $this->normalizeInt($raw[$key] ?? null);
        }

        return $out;
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
