<?php

declare(strict_types=1);

namespace App\Support\Sales;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Lee de ORVAE (Centro de Ventas) las conversaciones de ventas sobre VetSaaS que atiende su bot.
 * GET firmado con el mismo secreto HMAC de la provisión: sha256 sobre "{timestamp}.".
 */
final class OrvaeSalesHubClient
{
    public function isConfigured(): bool
    {
        return $this->baseUrl() !== null && $this->secret() !== '';
    }

    /**
     * @param  array{search?: string, estado?: string, page?: int, per_page?: int}  $query
     * @return array<string, mixed>|null null si ORVAE no está configurado o no respondió
     */
    public function conversations(array $query = []): ?array
    {
        return $this->get('', array_filter($query, fn (mixed $v): bool => $v !== null && $v !== ''));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function conversation(string $id): ?array
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        return $this->get('/'.$id);
    }

    public function baseUrl(): ?string
    {
        $explicit = trim((string) config('salesbot.orvae.conversations_url', ''));
        if ($explicit !== '') {
            return rtrim($explicit, '/');
        }

        $refresh = trim((string) config('salesbot.orvae.refresh_url', ''));
        if ($refresh === '' || preg_match('#/knowledge/refresh/?$#', $refresh) !== 1) {
            return null;
        }

        return (string) preg_replace('#/knowledge/refresh/?$#', '/saleshub/conversations', $refresh);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    private function get(string $path, array $query = []): ?array
    {
        $base = $this->baseUrl();
        $secret = $this->secret();

        if ($base === null || $secret === '') {
            return null;
        }

        $timestamp = (string) time();

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->withHeaders([
                    'X-Orvae-Timestamp' => $timestamp,
                    'X-Orvae-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.', $secret),
                    'X-Idempotency-Key' => (string) Str::uuid(),
                ])
                ->get($base.$path, $query);
        } catch (Throwable $e) {
            Log::warning('salesbot.orvae_conversations_failed', ['error' => class_basename($e)]);

            return null;
        }

        if (! $response->successful() || ! is_array($response->json())) {
            if ($response->status() !== 404) {
                Log::warning('salesbot.orvae_conversations_failed', ['status' => $response->status()]);
            }

            return null;
        }

        return (array) $response->json();
    }

    private function secret(): string
    {
        return (string) config('orvae.provision.hmac_secret', '');
    }
}
