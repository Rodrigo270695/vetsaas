<?php

declare(strict_types=1);

namespace App\Services\Clinica;

use App\Support\Clinica\DocumentoAutorizacionRenderer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

final class DocumentoAutorizacionPlantillaFromAiService
{
    public function isConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    /**
     * @return array{nombre: string, descripcion: string|null, cuerpo: string}
     */
    public function generateFromUpload(UploadedFile $file): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('La IA no está configurada. Falta OPENAI_API_KEY.');
        }

        $path = (string) $file->getRealPath();
        if ($path === '' || ! is_file($path)) {
            throw new RuntimeException('No se pudo leer el archivo.');
        }

        $mime = $this->detectMime($file);
        $isPdf = $mime === 'application/pdf' || str_ends_with(strtolower($file->getClientOriginalName()), '.pdf');

        if ($isPdf) {
            return $this->generateFromPdf($file, $path);
        }

        if (str_starts_with($mime, 'image/')) {
            $content = [
                [
                    'type' => 'text',
                    'text' => 'Convierte este documento de autorización / consentimiento veterinario en una plantilla HTML. Responde solo JSON.',
                ],
                [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => 'data:'.$this->safeImageMime($mime).';base64,'.base64_encode((string) file_get_contents($path)),
                    ],
                ],
            ];

            return $this->complete($content, (string) config('in-app-assistant.openai_model', 'gpt-4o-mini'));
        }

        throw new RuntimeException('Usa un PDF o una imagen (JPG, PNG o WebP).');
    }

    /**
     * @return array{nombre: string, descripcion: string|null, cuerpo: string}
     */
    private function generateFromPdf(UploadedFile $file, string $path): array
    {
        $bytes = (string) file_get_contents($path);
        if ($bytes === '') {
            throw new RuntimeException('El PDF está vacío.');
        }

        $filename = $file->getClientOriginalName() ?: 'documento.pdf';
        $filePart = [
            'type' => 'file',
            'file' => [
                'filename' => $filename,
                'file_data' => 'data:application/pdf;base64,'.base64_encode($bytes),
            ],
        ];
        $prompt = [
            'type' => 'text',
            'text' => 'Convierte este PDF de autorización / consentimiento veterinario en una plantilla HTML. Puede ser un escaneo. Responde solo JSON.',
        ];

        try {
            return $this->complete([$prompt, $filePart], $this->pdfModel());
        } catch (RuntimeException $e) {
            Log::info('Plantilla autorización IA: PDF como archivo falló, se intenta rasterizar', [
                'error' => $e->getMessage(),
            ]);
        }

        $images = $this->pdfPagesAsJpeg($path);
        if ($images !== []) {
            $parts = [$prompt];
            foreach ($images as $jpeg) {
                $parts[] = [
                    'type' => 'image_url',
                    'image_url' => ['url' => 'data:image/jpeg;base64,'.base64_encode($jpeg)],
                ];
            }

            return $this->complete($parts, $this->pdfModel());
        }

        $text = trim($this->pdfToText($path));
        if (mb_strlen($text) >= 40) {
            return $this->complete([
                [
                    'type' => 'text',
                    'text' => $prompt['text']."\n\nTexto extraído del PDF:\n".mb_substr($text, 0, 12000),
                ],
            ], (string) config('in-app-assistant.openai_model', 'gpt-4o-mini'));
        }

        throw new RuntimeException('No se pudo leer este PDF (suele ser un escaneo). Prueba de nuevo o sube fotos nítidas de las páginas.');
    }

    /**
     * @param  list<array<string, mixed>>  $userContent
     * @return array{nombre: string, descripcion: string|null, cuerpo: string}
     */
    private function complete(array $userContent, string $model): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey(),
            'Content-Type' => 'application/json',
        ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model,
            'temperature' => 0.2,
            'max_tokens' => 3500,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $userContent],
            ],
        ]);

        if (! $response->successful()) {
            Log::warning('Plantilla autorización IA: OpenAI HTTP '.$response->status(), [
                'model' => $model,
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            throw new RuntimeException('No se pudo leer el documento. Intenta de nuevo o usa una foto más nítida.');
        }

        $raw = (string) $response->json('choices.0.message.content', '');
        $parsed = $this->decodeJsonObject($raw);
        $nombre = trim((string) ($parsed['nombre'] ?? ''));
        $descripcion = trim((string) ($parsed['descripcion'] ?? ''));
        $cuerpo = DocumentoAutorizacionRenderer::sanitizeHtml((string) ($parsed['cuerpo'] ?? ''));
        $cuerpo = $this->ensureLogo($cuerpo);

        if ($nombre === '' || $cuerpo === '' || ! preg_match('/[a-zA-ZáéíóúñÁÉÍÓÚÑ]{8,}/u', strip_tags($cuerpo))) {
            throw new RuntimeException('La IA no pudo armar un texto usable. Sube un PDF o foto más claro.');
        }

        return [
            'nombre' => mb_substr($nombre, 0, 160),
            'descripcion' => $descripcion !== '' ? mb_substr($descripcion, 0, 500) : null,
            'cuerpo' => $cuerpo,
        ];
    }

    private function pdfModel(): string
    {
        $configured = trim((string) config('in-app-assistant.openai_pdf_model', ''));
        if ($configured !== '') {
            return $configured;
        }

        return 'gpt-4o';
    }

    private function detectMime(UploadedFile $file): string
    {
        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        if ($mime !== '' && $mime !== 'application/octet-stream') {
            return $mime;
        }
        $ext = strtolower((string) $file->getClientOriginalExtension());

        return match ($ext) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => $mime,
        };
    }

    /**
     * @return list<string> JPEG binaries
     */
    private function pdfPagesAsJpeg(string $path): array
    {
        if (! class_exists(\Imagick::class)) {
            return [];
        }

        try {
            $imagick = new \Imagick;
            $imagick->setResolution(140, 140);
            $imagick->readImage($path.'[0-2]');
            $out = [];
            foreach ($imagick as $page) {
                $page->setImageFormat('jpeg');
                $page->setImageCompressionQuality(72);
                $blob = (string) $page->getImageBlob();
                if ($blob !== '') {
                    $out[] = $blob;
                }
                if (count($out) >= 3) {
                    break;
                }
            }
            $imagick->clear();

            return $out;
        } catch (\Throwable $e) {
            Log::info('Plantilla autorización IA: Imagick PDF falló', ['error' => $e->getMessage()]);
        }

        return $this->pdfPagesWithGhostscript($path);
    }

    /**
     * @return list<string>
     */
    private function pdfPagesWithGhostscript(string $path): array
    {
        $gs = $this->ghostscriptBinary();
        if ($gs === null) {
            return [];
        }

        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'auth-pdf-'.uniqid('', true);
        if (! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            return [];
        }

        try {
            $pattern = $dir.DIRECTORY_SEPARATOR.'p%d.jpg';
            $result = Process::timeout(40)->run([
                $gs,
                '-dSAFER',
                '-dBATCH',
                '-dNOPAUSE',
                '-sDEVICE=jpeg',
                '-dJPEGQ=75',
                '-r120',
                '-dFirstPage=1',
                '-dLastPage=3',
                '-sOutputFile='.$pattern,
                $path,
            ]);
            if (! $result->successful()) {
                return [];
            }
            $out = [];
            foreach ([1, 2, 3] as $n) {
                $file = $dir.DIRECTORY_SEPARATOR.'p'.$n.'.jpg';
                if (is_file($file)) {
                    $blob = (string) file_get_contents($file);
                    if ($blob !== '') {
                        $out[] = $blob;
                    }
                }
            }

            return $out;
        } finally {
            foreach (glob($dir.DIRECTORY_SEPARATOR.'*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }

    private function ghostscriptBinary(): ?string
    {
        foreach (['gs', 'gswin64c', 'gswin32c'] as $bin) {
            $result = Process::timeout(5)->run([$bin, '-v']);
            if ($result->successful() || str_contains($result->errorOutput().$result->output(), 'Ghostscript')) {
                return $bin;
            }
        }

        return null;
    }

    private function pdfToText(string $path): string
    {
        $result = Process::timeout(20)->run(['pdftotext', '-layout', '-nopgbrk', $path, '-']);
        if ($result->successful()) {
            return (string) $result->output();
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeJsonObject(string $raw): array
    {
        $raw = trim($raw);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $raw, $m)) {
            $raw = trim($m[1]);
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('La IA no devolvió un JSON válido.');
        }

        return $decoded;
    }

    public function ensureLogo(string $cuerpo): string
    {
        if (str_contains($cuerpo, 'auth-doc-logo')) {
            return $cuerpo;
        }

        return '<p style="text-align:center"><img class="auth-doc-logo" alt=""></p>'.$cuerpo;
    }

    private function safeImageMime(string $mime): string
    {
        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)
            ? $mime
            : 'image/jpeg';
    }

    private function apiKey(): string
    {
        $key = trim((string) config('in-app-assistant.openai_api_key', ''));
        if ($key !== '') {
            return $key;
        }

        return trim((string) config('bot-ia.openai_api_key', ''));
    }

    private function systemPrompt(): string
    {
        $vars = '{{paciente}}, {{especie}}, {{raza}}, {{edad}}, {{sexo}}, {{propietario}}, {{documento}}, {{telefono}}, {{clinica}}, {{ciudad}}, {{veterinario}}, {{motivo}}, {{fecha}}, {{fecha_corta}}, {{dia}}, {{mes}}, {{mes_nombre}}, {{anio}}';

        return <<<PROMPT
Eres un asistente de VetSaaS. Conviertes un consentimiento/autorización veterinaria (PDF o foto) en una plantilla HTML.

Responde SOLO un JSON con:
{"nombre":"título corto de la plantilla","descripcion":"una línea o vacío","cuerpo":"HTML"}

Reglas del cuerpo:
- HTML con p, strong, em, u, ol, ul, li, br, span (style solo text-align, font-family, font-size).
- Conserva el sentido legal y el orden de cláusulas.
- Sustituye datos reales por variables de esta lista y NINGUNA otra: {$vars}
- {{motivo}} es el motivo de la consulta. No uses {{causa}}.
- No pongas bloque de firma ni {{firma}}: la firma digital se añade sola al final.
- Incluye al inicio: <p style="text-align:center"><img class="auth-doc-logo" alt=""></p>
- Título centrado en strong o p.
- Fecha típica: {{ciudad}}, {{dia}} de {{mes_nombre}} de {{anio}}
- Español, sin markdown, sin explicaciones fuera del JSON.
PROMPT;
    }
}
