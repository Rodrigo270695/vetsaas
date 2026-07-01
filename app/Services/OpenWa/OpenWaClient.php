<?php

declare(strict_types=1);

namespace App\Services\OpenWa;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
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
     * El audio se envía como base64. OpenWA lo procesa como PTT (push-to-talk)
     * para que se muestre como nota de voz en WhatsApp, no como archivo adjunto.
     *
     * @param  string  $sessionId  ID de la sesión OpenWA activa.
     * @param  string  $chatId  Chat destino (ej: "51987654321@c.us").
     * @param  string  $audioContent  Contenido binario del audio (ogg/opus).
     * @return array<string, mixed>
     */
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

        return $response->json();
    }
}
