<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyOrvaeProvisionSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('orvae.provision.hmac_secret');
        $maxSkew = (int) config('orvae.provision.max_skew_seconds', 300);

        if ($secret === '') {
            Log::warning('orvae.provision: hmac_secret no configurado');

            return $this->unauthorized('integration_not_configured');
        }

        $timestamp = (string) $request->header('X-Orvae-Timestamp', '');
        $signature = (string) $request->header('X-Orvae-Signature', '');
        $idempotencyKey = (string) $request->header('X-Idempotency-Key', '');

        if ($timestamp === '' || $signature === '' || $idempotencyKey === '') {
            return $this->unauthorized('missing_signature_headers');
        }

        if (! ctype_digit($timestamp)) {
            return $this->unauthorized('invalid_timestamp');
        }

        $skew = abs(time() - (int) $timestamp);
        if ($skew > $maxSkew) {
            return $this->unauthorized('timestamp_skew_too_large');
        }

        $rawBody = $request->getContent();
        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
        $received = $this->stripPrefix($signature);

        if (! hash_equals($expected, $received)) {
            return $this->unauthorized('invalid_signature');
        }

        $request->attributes->set('orvae.idempotency_key', $idempotencyKey);

        return $next($request);
    }

    private function stripPrefix(string $signature): string
    {
        return str_starts_with($signature, 'sha256=')
            ? substr($signature, 7)
            : $signature;
    }

    private function unauthorized(string $reason): JsonResponse
    {
        return response()->json([
            'error' => 'unauthorized',
            'reason' => $reason,
        ], 401);
    }
}
