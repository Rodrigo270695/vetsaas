<?php

declare(strict_types=1);

namespace App\Support\Sales;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class OrvaeKnowledgeNotifier
{
    public static function changed(string $product): void
    {
        $url = (string) config('salesbot.orvae.refresh_url');
        $secret = (string) config('orvae.provision.hmac_secret');

        if ($product !== SalesKnowledgeExport::PRODUCT || $url === '' || $secret === '') {
            return;
        }

        dispatch(static function () use ($url, $secret): void {
            $body = (string) json_encode(['evento' => 'knowledge.updated', 'producto' => SalesKnowledgeExport::PRODUCT]);
            $timestamp = (string) time();

            try {
                Http::timeout(5)
                    ->withHeaders([
                        'X-Orvae-Timestamp' => $timestamp,
                        'X-Orvae-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret),
                        'X-Idempotency-Key' => (string) Str::uuid(),
                    ])
                    ->withBody($body, 'application/json')
                    ->post($url)
                    ->throw();
            } catch (Throwable $e) {
                Log::warning('salesbot.orvae_knowledge_refresh_failed', ['error' => class_basename($e)]);
            }
        })->afterResponse();
    }
}
