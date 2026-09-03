<?php

declare(strict_types=1);

namespace App\Services\Platform;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Lee el inventario SSL generado por scripts/vetsaas-ssl-status.sh (root).
 */
final class SslCertsStatusService
{
    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $path = (string) config('ssl.manifest_path');
        $warnDays = (int) config('ssl.warn_days', 21);
        $criticalDays = (int) config('ssl.critical_days', 7);
        $staleHours = (int) config('ssl.stale_after_hours', 36);
        $watch = (string) config('ssl.watch_name', 'vetsaas.orvae.pe');

        $empty = [
            'ok' => null,
            'missing' => true,
            'stale' => false,
            'generated_at' => null,
            'age_hours' => null,
            'warn_days' => $warnDays,
            'critical_days' => $criticalDays,
            'watch_name' => $watch,
            'watch_ok' => false,
            'expiring' => 0,
            'expired' => 0,
            'certs' => [],
        ];

        if (! File::isFile($path)) {
            return $empty;
        }

        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded)) {
            return $empty;
        }

        $generatedAt = isset($decoded['generated_at'])
            ? Carbon::parse((string) $decoded['generated_at'])
            : null;
        $ageHours = $generatedAt?->diffInHours(now(), true);
        $stale = $generatedAt === null || $ageHours >= $staleHours;

        $certs = [];
        $expired = 0;
        $expiring = 0;
        $watchOk = false;

        foreach ($decoded['certs'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = (string) ($row['name'] ?? '');
            $expiryRaw = $row['expiry'] ?? null;
            $expiry = is_string($expiryRaw) && $expiryRaw !== '' ? Carbon::parse($expiryRaw) : null;
            $daysLeftInt = $expiry === null
                ? null
                : (int) now()->startOfDay()->diffInDays($expiry->copy()->startOfDay(), false);
            $valid = (bool) ($row['valid'] ?? false) && ($daysLeftInt === null || $daysLeftInt >= 0);
            $domains = array_values(array_filter(
                array_map('strval', is_array($row['domains'] ?? null) ? $row['domains'] : []),
            ));

            if ($daysLeftInt !== null && $daysLeftInt < 0) {
                $expired++;
                $valid = false;
            } elseif ($daysLeftInt !== null && $daysLeftInt <= $warnDays) {
                $expiring++;
            }

            $isWatch = $name === $watch && in_array('*.'.$watch, $domains, true);
            if ($isWatch && $valid && ($daysLeftInt === null || $daysLeftInt > $criticalDays)) {
                $watchOk = true;
            }

            $certs[] = [
                'name' => $name,
                'domains' => $domains,
                'expiry' => $expiry?->toIso8601String(),
                'days_left' => $daysLeftInt,
                'valid' => $valid,
                'watch' => $isWatch,
            ];
        }

        usort($certs, static function (array $a, array $b): int {
            $da = $a['days_left'] ?? 9999;
            $db = $b['days_left'] ?? 9999;

            return $da <=> $db;
        });

        $ok = ! $stale && $expired === 0 && $watchOk;

        return [
            'ok' => $ok,
            'missing' => false,
            'stale' => $stale,
            'generated_at' => $generatedAt?->toIso8601String(),
            'age_hours' => $ageHours !== null ? (int) round((float) $ageHours) : null,
            'warn_days' => $warnDays,
            'critical_days' => $criticalDays,
            'watch_name' => $watch,
            'watch_ok' => $watchOk,
            'expiring' => $expiring,
            'expired' => $expired,
            'certs' => $certs,
        ];
    }
}
