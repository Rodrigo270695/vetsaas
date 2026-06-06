<?php

declare(strict_types=1);

namespace App\Services\OpenWa;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
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
