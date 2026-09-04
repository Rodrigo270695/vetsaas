<?php

declare(strict_types=1);

namespace App\Support\Agenda;

use App\Models\PlatformWhatsAppSession;
use App\Models\TenantWhatsAppSession;
use App\Services\Agenda\AgendaOwnerRsvpService;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Cache;

/**
 * Resuelve el tenant de un SI/NO inbound (sesión clínica o la de plataforma).
 */
final class AgendaRsvpFromInbound
{
    public static function cacheKeyForDestinatario(string $destinatario): string
    {
        return 'agenda-rsvp-dest:'.md5(strtolower(trim($destinatario)));
    }

    public static function cacheKeyForPhone(string $phoneDigits): string
    {
        return 'agenda-rsvp-phone:'.md5($phoneDigits);
    }

    public static function rememberTenant(string $tenantSlug, string $destinatario, ?string $phoneDigits = null): void
    {
        $ttl = now()->addDays(7);
        Cache::put(self::cacheKeyForDestinatario($destinatario), $tenantSlug, $ttl);
        if ($phoneDigits !== null && $phoneDigits !== '') {
            Cache::put(self::cacheKeyForPhone($phoneDigits), $tenantSlug, $ttl);
        }
    }

    /**
     * @return array{handled: true, reply: string, kind: string, id: string, intent: string}|null
     */
    public function tryHandle(
        string $openWaSessionId,
        string $phone,
        string $waChatId,
        string $body,
    ): ?array {
        if (AgendaRsvpIntent::parse($body) === null) {
            return null;
        }

        $slugs = [];
        foreach ($this->tenantSlugsFor($openWaSessionId, $phone, $waChatId) as $slug) {
            if ($slug !== '' && ! in_array($slug, $slugs, true)) {
                $slugs[] = $slug;
            }
        }

        $rsvp = app(AgendaOwnerRsvpService::class);
        $tenants = app(TenantManager::class);

        foreach ($slugs as $slug) {
            $result = $tenants->runForSlug($slug, fn (): ?array => $rsvp->tryHandle($phone, $body, $waChatId));
            if (is_array($result)) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function tenantSlugsFor(string $openWaSessionId, string $phone, string $waChatId): array
    {
        $slugs = [];

        if ($openWaSessionId !== '') {
            $sessions = TenantWhatsAppSession::query()
                ->with('tenant:id,slug')
                ->where('openwa_session_id', $openWaSessionId)
                ->get();
            foreach ($sessions as $session) {
                $slug = (string) ($session->tenant?->slug ?? '');
                if ($slug !== '') {
                    $slugs[] = $slug;
                }
            }
        }

        $cached = Cache::get(self::cacheKeyForDestinatario($waChatId));
        if (is_string($cached) && $cached !== '') {
            $slugs[] = $cached;
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if ($digits !== '' && ! str_starts_with($phone, 'lid:')) {
            $cachedPhone = Cache::get(self::cacheKeyForPhone($digits));
            if (is_string($cachedPhone) && $cachedPhone !== '') {
                $slugs[] = $cachedPhone;
            }
            $last9 = strlen($digits) > 9 ? substr($digits, -9) : $digits;
            $cached9 = Cache::get(self::cacheKeyForPhone($last9));
            if (is_string($cached9) && $cached9 !== '') {
                $slugs[] = $cached9;
            }
        }

        if ($openWaSessionId !== '') {
            $platform = PlatformWhatsAppSession::query()
                ->where('openwa_session_id', $openWaSessionId)
                ->first();
            $platformDigits = preg_replace('/\D/', '', (string) ($platform?->phone ?? '')) ?? '';
            if ($platformDigits !== '') {
                $tail = substr($platformDigits, -9);
                $tenantSessions = TenantWhatsAppSession::query()
                    ->with('tenant:id,slug')
                    ->whereNotNull('phone')
                    ->get();
                foreach ($tenantSessions as $session) {
                    $sDigits = preg_replace('/\D/', '', (string) $session->phone) ?? '';
                    if ($sDigits !== '' && str_ends_with($sDigits, $tail)) {
                        $slug = (string) ($session->tenant?->slug ?? '');
                        if ($slug !== '') {
                            $slugs[] = $slug;
                        }
                    }
                }
            }
        }

        return $slugs;
    }
}
