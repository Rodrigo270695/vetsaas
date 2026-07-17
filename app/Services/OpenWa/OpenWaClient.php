<?php

declare(strict_types=1);

namespace App\Services\OpenWa;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Cliente HTTP para OpenWA ({@see https://www.open-wa.org/}).
 */
final class OpenWaClient
{
    public function isConfigured(): bool
    {
        return (bool) config('openwa.enabled')
            && trim((string) config('openwa.api_key', '')) !== '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSessions(): array
    {
        $response = $this->request('get', '/api/sessions');

        return is_array($response) ? $response : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function findSessionByName(string $name): ?array
    {
        foreach ($this->listSessions() as $session) {
            if (is_array($session) && ($session['name'] ?? null) === $name) {
                return $session;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function createSession(string $name): array
    {
        $response = $this->request('post', '/api/sessions', [
            'name' => $name,
            'config' => ['autoReconnect' => true],
        ]);

        if (! is_array($response)) {
            throw new RuntimeException('OpenWA no devolvió sesión al crear.');
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSession(string $sessionId): array
    {
        $response = $this->request('get', '/api/sessions/'.$sessionId);

        if (! is_array($response)) {
            throw new RuntimeException('Sesión OpenWA no encontrada: '.$sessionId);
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContact(string $sessionId, string $contactId): array
    {
        // OpenWA acepta número, 51999@c.us o 141...@lid (URL-encoded).
        $encoded = rawurlencode($contactId);

        $response = $this->request('get', '/api/sessions/'.$sessionId.'/contacts/'.$encoded);

        if (! is_array($response)) {
            throw new RuntimeException('OpenWA no devolvió datos del contacto.');
        }

        // Respuesta puede venir envuelta en { success, data: {...} }.
        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function sendText(string $sessionId, string $chatId, string $text): array
    {
        $response = $this->request('post', '/api/sessions/'.$sessionId.'/messages/send-text', [
            'chatId' => $chatId,
            'text' => $text,
        ]);

        if (! is_array($response)) {
            throw new RuntimeException('OpenWA no confirmó el envío del mensaje.');
        }

        return $response;
    }

    /**
     * Igual que {@see sendText}, pero si OpenWA responde con timeout o 5xx
     * tardío (el mensaje suele haber salido igual), se asume entrega en vez
     * de propagar el error. Útil para mensajes one-shot iniciados por el
     * usuario donde un falso "falló" es peor que un improbable duplicado.
     *
     * @return array<string, mixed>
     */
    public function sendTextWithDeliveryFallback(string $sessionId, string $chatId, string $text): array
    {
        try {
            return $this->sendText($sessionId, $chatId, $text);
        } catch (RuntimeException $error) {
            if ($this->shouldAssumeDelivered($error)) {
                Log::warning('OpenWA send-text: respuesta ambigua; se asume envío OK', [
                    'error' => $error->getMessage(),
                    'chat_id' => $chatId,
                ]);

                return ['messageId' => null, 'assumed_delivery' => true];
            }

            throw $error;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function startSession(string $sessionId): array
    {
        $response = $this->request('post', '/api/sessions/'.$sessionId.'/start');

        if (! is_array($response)) {
            throw new RuntimeException('OpenWA no pudo iniciar la sesión.');
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQrCode(string $sessionId): array
    {
        $response = $this->request('get', '/api/sessions/'.$sessionId.'/qr');

        if (! is_array($response)) {
            throw new RuntimeException('OpenWA no devolvió código QR.');
        }

        return $response;
    }

    /**
     * Detiene la sesión y desconecta WhatsApp (API desplegada: POST /stop, sin /logout).
     *
     * @return array<string, mixed>
     */
    public function stopSession(string $sessionId): array
    {
        $response = $this->request('post', '/api/sessions/'.$sessionId.'/stop');

        if (! is_array($response)) {
            throw new RuntimeException('OpenWA no confirmó la desconexión de WhatsApp.');
        }

        return $response;
    }

    /**
     * Registra el webhook de mensajes entrantes para una sesión.
     *
     * @see https://github.com/rmyndharis/OpenWA/blob/main/docs/06-api-specification.md
     *
     * @return array<string, mixed>
     */
    public function registerWebhook(string $sessionId, string $url, ?string $secret = null): array
    {
        $payload = [
            'url' => $url,
            'events' => ['message.received'],
        ];

        if ($secret !== null && $secret !== '') {
            $payload['secret'] = $secret;
            $payload['headers'] = [
                'X-Webhook-Secret' => $secret,
            ];
        }

        $response = $this->request('post', '/api/sessions/'.$sessionId.'/webhooks', $payload);

        if (! is_array($response)) {
            throw new RuntimeException('OpenWA no confirmó el registro del webhook.');
        }

        return $response;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listWebhooks(string $sessionId): array
    {
        $response = $this->request('get', '/api/sessions/'.$sessionId.'/webhooks');

        if (! is_array($response)) {
            return [];
        }

        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }

        return $response;
    }

    /**
     * Envía una nota de voz (audio ogg/opus) a un chat de WhatsApp.
     *
     * El audio se guarda temporalmente en storage/app/public/salesbot/ y se
     * envía a OpenWA como URL pública. OpenWA descarga el archivo y lo envía
     * como PTT (nota de voz) al destinatario.
     *
     * El archivo temporal se borra automáticamente después del envío.
     *
     * @param  string  $sessionId  ID de la sesión OpenWA activa.
     * @param  string  $chatId  Chat destino (ej: "51987654321@c.us").
     * @param  string  $audioContent  Contenido binario del audio (ogg/opus).
     * @return array<string, mixed>
     */
    public function sendVoice(string $sessionId, string $chatId, string $audioContent): array
    {
        // Guardar temporalmente en storage público para que OpenWA lo descargue.
        $filename = 'voice_'.uniqid().'.ogg';
        $storagePath = 'salesbot/'.$filename;

        Storage::disk('public')->put($storagePath, $audioContent);

        try {
            // URL accesible por OpenWA (mismo servidor).
            $publicUrl = Storage::disk('public')->url($storagePath);

            // Si la URL es relativa, convertirla en absoluta con la URL de la app.
            if (! str_starts_with($publicUrl, 'http')) {
                $publicUrl = rtrim((string) config('app.url'), '/').$publicUrl;
            }

            $response = $this->request('post', '/api/sessions/'.$sessionId.'/messages/send-audio', [
                'chatId' => $chatId,
                'url' => $publicUrl,
            ]);
        } finally {
            // Borrar el archivo temporal siempre, haya éxito o error.
            Storage::disk('public')->delete($storagePath);
        }

        return is_array($response) ? $response : [];
    }

    /**
     * Envía un documento (PDF, etc.) por URL pública o contenido binario.
     *
     * - URL remota: OpenWA la descarga directamente.
     * - Binario: se guarda en storage público temporal (mismo patrón que {@see sendVoice}),
     *   OpenWA lo descarga por URL y se borra al terminar. No se usa base64 (límite 413 en OpenWA).
     *
     * @param  string|null  $url  URL http(s) remota.
     * @param  string|null  $binaryContent  Bytes del archivo.
     * @return array<string, mixed>
     */
    public function sendDocument(
        string $sessionId,
        string $chatId,
        ?string $url = null,
        ?string $binaryContent = null,
        string $filename = 'documento.pdf',
        string $mimetype = 'application/pdf',
        ?string $caption = null,
    ): array {
        $captionTrimmed = $caption !== null && $caption !== ''
            ? mb_substr($caption, 0, 1024)
            : null;
        $safeFilename = mb_substr($filename !== '' ? $filename : 'documento.pdf', 0, 255);
        $mime = $mimetype !== '' ? $mimetype : 'application/pdf';

        $urlTrimmed = $url !== null ? trim($url) : '';
        if ($urlTrimmed !== '' && ($binaryContent === null || $binaryContent === '')) {
            return $this->postDocumentWithDeliveryFallback($sessionId, [
                'chatId' => $chatId,
                'url' => $urlTrimmed,
                'filename' => $safeFilename,
                'mimetype' => $mime,
                'caption' => $captionTrimmed,
            ]);
        }

        if ($binaryContent === null || $binaryContent === '') {
            throw new RuntimeException('sendDocument requiere url o contenido binario.');
        }

        $ext = pathinfo($safeFilename, PATHINFO_EXTENSION) ?: 'pdf';
        $storagePath = 'whatsapp-temp/doc_'.uniqid('', true).'.'.$ext;
        Storage::disk('public')->put($storagePath, $binaryContent);

        $publicUrl = Storage::disk('public')->url($storagePath);
        if (! str_starts_with($publicUrl, 'http')) {
            $publicUrl = rtrim((string) config('app.url'), '/').$publicUrl;
        }

        try {
            $result = $this->postDocumentWithDeliveryFallback($sessionId, [
                'chatId' => $chatId,
                'url' => $publicUrl,
                'filename' => $safeFilename,
                'mimetype' => $mime,
                'caption' => $captionTrimmed,
            ]);
        } catch (RuntimeException $urlError) {
            Storage::disk('public')->delete($storagePath);

            throw $urlError;
        }

        if (! ($result['_assumed_delivery'] ?? false)) {
            Storage::disk('public')->delete($storagePath);
        }

        unset($result['_assumed_delivery']);

        return $result;
    }

    /**
     * Envía una imagen por URL pública (OpenWA la descarga). Preferir disco `public`
     * permanente; no usa base64 (límite 413).
     *
     * @return array<string, mixed>
     */
    public function sendImage(
        string $sessionId,
        string $chatId,
        string $url,
        ?string $caption = null,
    ): array {
        $urlTrimmed = trim($url);
        if ($urlTrimmed === '' || ! str_starts_with($urlTrimmed, 'http')) {
            throw new RuntimeException('sendImage requiere una URL http(s) pública.');
        }

        $captionTrimmed = $caption !== null && $caption !== ''
            ? mb_substr($caption, 0, 1024)
            : null;

        $payload = [
            'chatId' => $chatId,
            'url' => $urlTrimmed,
        ];
        if ($captionTrimmed !== null) {
            $payload['caption'] = $captionTrimmed;
        }

        try {
            return $this->postImage($sessionId, $payload);
        } catch (RuntimeException $error) {
            if ($this->shouldAssumeDelivered($error)) {
                Log::warning('OpenWA send-image: respuesta ambigua; se asume envío OK', [
                    'error' => $error->getMessage(),
                    'source_url' => $urlTrimmed,
                ]);

                return ['messageId' => null, 'assumed_delivery' => true];
            }

            throw $error;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function postImage(string $sessionId, array $payload): array
    {
        $apiKey = trim((string) config('openwa.api_key', ''));
        if ($apiKey === '') {
            throw new RuntimeException('OPENWA_API_KEY no configurada.');
        }

        $url = rtrim((string) config('openwa.api_url'), '/')
            .'/api/sessions/'.$sessionId.'/messages/send-image';

        try {
            $response = Http::timeout((int) config('openwa.document_timeout_seconds', 90))
                ->acceptJson()
                ->withHeaders(['X-API-Key' => $apiKey])
                ->post($url, $payload);
        } catch (RequestException $e) {
            throw new RuntimeException('Error de red con OpenWA: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'OpenWA HTTP '.$response->status().': '.(string) $response->body(),
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            return ['messageId' => null];
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function postDocumentWithDeliveryFallback(string $sessionId, array $payload): array
    {
        try {
            return $this->postDocument($sessionId, $payload);
        } catch (RuntimeException $error) {
            if ($this->shouldAssumeDelivered($error)) {
                Log::warning('OpenWA send-document: respuesta ambigua; se asume envío OK', [
                    'error' => $error->getMessage(),
                    'filename' => $payload['filename'] ?? null,
                    'source_url' => $payload['url'] ?? null,
                ]);

                return ['messageId' => null, '_assumed_delivery' => true];
            }

            throw $error;
        }
    }

    /**
     * OpenWA a veces tarda en responder (timeout / 5xx tardío) aunque el
     * mensaje o documento ya llegó a WhatsApp.
     */
    private function shouldAssumeDelivered(RuntimeException $error): bool
    {
        $message = $error->getMessage();

        if (str_contains($message, 'Error de red con OpenWA')
            || str_contains($message, 'timed out')
            || str_contains($message, 'cURL error 28')) {
            return true;
        }

        return (bool) preg_match('/OpenWA HTTP 5\d{2}/', $message);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function postDocument(string $sessionId, array $payload): array
    {
        if (($payload['caption'] ?? null) === null) {
            unset($payload['caption']);
        }

        $apiKey = trim((string) config('openwa.api_key', ''));
        if ($apiKey === '') {
            throw new RuntimeException('OPENWA_API_KEY no configurada.');
        }

        $url = rtrim((string) config('openwa.api_url'), '/')
            .'/api/sessions/'.$sessionId.'/messages/send-document';

        try {
            $response = Http::timeout((int) config('openwa.document_timeout_seconds', 90))
                ->acceptJson()
                ->withHeaders(['X-API-Key' => $apiKey])
                ->post($url, $payload);
        } catch (RequestException $e) {
            throw new RuntimeException('Error de red con OpenWA: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'OpenWA HTTP '.$response->status().': '.(string) $response->body(),
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            return ['messageId' => null];
        }

        if (isset($json['data']) && is_array($json['data'])) {
            return $json['data'];
        }

        return $json;
    }

    /**
     * Descarga el contenido binario de un mensaje de media (audio, imagen, doc).
     *
     * OpenWA incluye en el payload del webhook el campo `mediaUrl` o `body`
     * con la URL del archivo. Este método lo descarga y devuelve el contenido.
     *
     * @return string Contenido binario del archivo.
     */
    public function downloadMedia(string $mediaUrl): string
    {
        $apiKey = trim((string) config('openwa.api_key', ''));

        // Si la URL es relativa al servidor OpenWA, le anteponemos la base.
        if (! str_starts_with($mediaUrl, 'http')) {
            $base = rtrim((string) config('openwa.api_url'), '/');
            $mediaUrl = $base.'/'.ltrim($mediaUrl, '/');
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders($apiKey !== '' ? ['X-API-Key' => $apiKey] : [])
                ->get($mediaUrl);
        } catch (\Throwable $e) {
            throw new RuntimeException('Error al descargar media de OpenWA: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('OpenWA devolvió HTTP '.$response->status().' al descargar el audio.');
        }

        return (string) $response->body();
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>|list<array<string, mixed>>|null
     */
    private function request(string $method, string $path, ?array $body = null): mixed
    {
        $apiKey = trim((string) config('openwa.api_key', ''));
        if ($apiKey === '') {
            throw new RuntimeException('OPENWA_API_KEY no configurada.');
        }

        $url = rtrim((string) config('openwa.api_url'), '/').$path;

        try {
            $pending = Http::timeout((int) config('openwa.timeout_seconds', 30))
                ->acceptJson()
                ->withHeaders(['X-API-Key' => $apiKey]);

            $response = match ($method) {
                'get' => $pending->get($url),
                'post' => $pending->post($url, $body ?? []),
                'delete' => $pending->delete($url),
                default => throw new RuntimeException('Método HTTP no soportado: '.$method),
            };
        } catch (RequestException $e) {
            throw new RuntimeException('Error de red con OpenWA: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'OpenWA HTTP '.$response->status().': '.(string) $response->body(),
            );
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }
}
