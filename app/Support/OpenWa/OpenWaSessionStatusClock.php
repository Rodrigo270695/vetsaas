<?php

declare(strict_types=1);

namespace App\Support\OpenWa;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Marca cuándo entró el estado OpenWA actual (p. ej. initializing).
 * No usar last_synced_at: se actualiza en cada poll y nunca envejece el boot.
 */
final class OpenWaSessionStatusClock
{
    public static function touchedAt(?Model $session, string $remoteStatus): ?CarbonInterface
    {
        if ($session === null) {
            return null;
        }

        $localStatus = (string) ($session->getAttribute('status') ?? '');
        if ($localStatus !== $remoteStatus) {
            return null;
        }

        $changed = $session->getAttribute('status_changed_at');
        if ($changed instanceof CarbonInterface) {
            return Carbon::instance($changed);
        }

        if ($changed !== null) {
            return Carbon::parse($changed);
        }

        $created = $session->getAttribute('created_at');
        if ($created instanceof CarbonInterface) {
            return Carbon::instance($created);
        }

        return null;
    }

    public static function nextChangedAt(?Model $session, string $newStatus): CarbonInterface
    {
        if ($session === null || (string) ($session->getAttribute('status') ?? '') !== $newStatus) {
            return now();
        }

        $existing = $session->getAttribute('status_changed_at');
        if ($existing instanceof CarbonInterface) {
            return Carbon::instance($existing);
        }

        if ($existing !== null) {
            return Carbon::parse($existing);
        }

        $created = $session->getAttribute('created_at');
        if ($created instanceof CarbonInterface) {
            return Carbon::instance($created);
        }

        return now();
    }
}
