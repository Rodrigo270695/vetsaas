<?php

declare(strict_types=1);

namespace App\Services\Google;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * OAuth + creación de eventos Google Calendar con Meet (cuenta organizadora).
 */
final class GoogleCalendarMeetService
{
    public function isConfigured(): bool
    {
        if (! (bool) config('google-calendar.enabled', true)) {
            return false;
        }

        return $this->clientId() !== ''
            && $this->clientSecret() !== ''
            && $this->refreshToken() !== '';
    }

    public function hasClientCredentials(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function isConnected(): bool
    {
        return $this->refreshToken() !== '';
    }

    /**
     * @return array{connected: bool, configured: bool, email: string|null, has_client: bool}
     */
    public function status(): array
    {
        $stored = $this->readTokenFile();

        return [
            'connected' => $this->isConnected(),
            'configured' => $this->isConfigured(),
            'has_client' => $this->hasClientCredentials(),
            'email' => is_string($stored['email'] ?? null) ? $stored['email'] : null,
        ];
    }

    public function redirectUri(): string
    {
        $configured = trim((string) config('google-calendar.redirect_uri', ''));
        if ($configured !== '') {
            return $configured;
        }

        return url('/google/oauth/callback');
    }

    public function authorizationUrl(string $state): string
    {
        if (! $this->hasClientCredentials()) {
            throw new RuntimeException('Faltan GOOGLE_CALENDAR_CLIENT_ID / CLIENT_SECRET.');
        }

        $query = http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', (array) config('google-calendar.scopes', [])),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.$query;
    }

    public function exchangeAuthorizationCode(string $code): void
    {
        $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful()) {
            Log::error('Google OAuth token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Google no intercambió el código OAuth.');
        }

        $payload = $response->json();
        $refresh = (string) ($payload['refresh_token'] ?? '');
        $access = (string) ($payload['access_token'] ?? '');
        $expiresIn = (int) ($payload['expires_in'] ?? 3600);

        if ($refresh === '') {
            // Re-consent a veces no reenvía refresh si ya existía; conserva el anterior.
            $refresh = $this->refreshToken();
        }

        if ($refresh === '' || $access === '') {
            throw new RuntimeException('Google no devolvió refresh_token. Revoca el acceso de la app y vuelve a conectar con prompt=consent.');
        }

        $email = $this->fetchUserEmail($access);

        $this->writeTokenFile([
            'refresh_token' => $refresh,
            'access_token' => $access,
            'expires_at' => now()->addSeconds(max(60, $expiresIn - 60))->getTimestamp(),
            'email' => $email,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function disconnect(): void
    {
        $path = $this->tokenDiskPath();
        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * Crea un evento con Meet.
     *
     * @return array{event_id: string, meet_link: string, html_link: string|null, starts_at: string, ends_at: string}
     */
    public function createMeetEvent(
        CarbonImmutable $startsAt,
        string $summary,
        ?string $description = null,
        ?int $durationMinutes = null,
    ): array {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Google Calendar no está conectado.');
        }

        $duration = $durationMinutes ?? (int) config('google-calendar.meeting_duration_minutes', 20);
        $duration = max(10, min(60, $duration));
        $endsAt = $startsAt->addMinutes($duration);
        $tz = (string) config('google-calendar.timezone', 'America/Lima');
        $calendarId = rawurlencode((string) config('google-calendar.calendar_id', 'primary'));

        $accessToken = $this->accessToken();

        $startLocal = $startsAt->setTimezone($tz);
        $endLocal = $endsAt->setTimezone($tz);

        $body = [
            'summary' => $summary,
            'description' => $description ?? 'Tour VetSaaS (SalesBot).',
            'start' => [
                'dateTime' => $startLocal->format('Y-m-d\TH:i:s'),
                'timeZone' => $tz,
            ],
            'end' => [
                'dateTime' => $endLocal->format('Y-m-d\TH:i:s'),
                'timeZone' => $tz,
            ],
            'conferenceData' => [
                'createRequest' => [
                    'requestId' => (string) Str::uuid(),
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ],
        ];

        $response = Http::withToken($accessToken)
            ->timeout(25)
            ->post(
                "https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events?conferenceDataVersion=1",
                $body,
            );

        if ($response->status() === 401) {
            $this->invalidateAccessToken();
            $accessToken = $this->accessToken();
            $response = Http::withToken($accessToken)
                ->timeout(25)
                ->post(
                    "https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events?conferenceDataVersion=1",
                    $body,
                );
        }

        if (! $response->successful()) {
            Log::error('Google Calendar create event failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('No se pudo crear el Meet en Google Calendar.');
        }

        $json = $response->json();
        $meetLink = (string) ($json['hangoutLink']
            ?? data_get($json, 'conferenceData.entryPoints.0.uri')
            ?? '');

        if ($meetLink === '') {
            throw new RuntimeException('El evento se creó pero Google no devolvió link de Meet.');
        }

        return [
            'event_id' => (string) ($json['id'] ?? ''),
            'meet_link' => $meetLink,
            'html_link' => isset($json['htmlLink']) ? (string) $json['htmlLink'] : null,
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
        ];
    }

    private function accessToken(): string
    {
        $stored = $this->readTokenFile();
        $access = (string) ($stored['access_token'] ?? '');
        $expiresAt = (int) ($stored['expires_at'] ?? 0);

        if ($access !== '' && $expiresAt > time() + 30) {
            return $access;
        }

        return $this->refreshAccessToken();
    }

    private function refreshAccessToken(): string
    {
        $refresh = $this->refreshToken();
        if ($refresh === '') {
            throw new RuntimeException('No hay refresh_token de Google.');
        }

        $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'refresh_token' => $refresh,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            Log::error('Google OAuth refresh failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('No se pudo renovar el token de Google. Vuelve a conectar la cuenta.');
        }

        $payload = $response->json();
        $access = (string) ($payload['access_token'] ?? '');
        $expiresIn = (int) ($payload['expires_in'] ?? 3600);

        if ($access === '') {
            throw new RuntimeException('Google no devolvió access_token.');
        }

        $stored = $this->readTokenFile();
        $stored['access_token'] = $access;
        $stored['expires_at'] = now()->addSeconds(max(60, $expiresIn - 60))->getTimestamp();
        $stored['refresh_token'] = $refresh;
        $stored['updated_at'] = now()->toIso8601String();
        $this->writeTokenFile($stored);

        return $access;
    }

    private function invalidateAccessToken(): void
    {
        $stored = $this->readTokenFile();
        if ($stored === []) {
            return;
        }
        $stored['expires_at'] = 0;
        $stored['access_token'] = '';
        $this->writeTokenFile($stored);
    }

    private function fetchUserEmail(string $accessToken): ?string
    {
        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->get('https://www.googleapis.com/oauth2/v2/userinfo');

            if ($response->successful()) {
                $email = (string) ($response->json('email') ?? '');

                return $email !== '' ? $email : null;
            }
        } catch (Throwable) {
            // opcional
        }

        return null;
    }

    private function clientId(): string
    {
        return trim((string) config('google-calendar.client_id', ''));
    }

    private function clientSecret(): string
    {
        return trim((string) config('google-calendar.client_secret', ''));
    }

    private function refreshToken(): string
    {
        $stored = $this->readTokenFile();
        $fromFile = trim((string) ($stored['refresh_token'] ?? ''));
        if ($fromFile !== '') {
            return $fromFile;
        }

        return trim((string) config('google-calendar.refresh_token', ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function readTokenFile(): array
    {
        $path = $this->tokenDiskPath();
        if (! Storage::disk('local')->exists($path)) {
            return [];
        }

        try {
            $raw = Storage::disk('local')->get($path);
            $json = Crypt::decryptString((string) $raw);
            $data = json_decode($json, true);

            return is_array($data) ? $data : [];
        } catch (Throwable $e) {
            Log::warning('Google Calendar token file unreadable', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeTokenFile(array $data): void
    {
        $path = $this->tokenDiskPath();
        $dir = dirname($path);
        if ($dir !== '.' && $dir !== '') {
            Storage::disk('local')->makeDirectory($dir);
        }

        Storage::disk('local')->put(
            $path,
            Crypt::encryptString(json_encode($data, JSON_THROW_ON_ERROR)),
        );
    }

    private function tokenDiskPath(): string
    {
        return ltrim((string) config('google-calendar.token_path', 'google-calendar/oauth-token.json'), '/');
    }
}
