<?php

declare(strict_types=1);

namespace App\Support\Agenda;

use App\Models\PlatformWhatsAppSession;
use App\Models\Tenant;
use App\Models\TenantWhatsAppSession;
use App\Services\Agenda\AgendaOwnerRsvpService;
use App\Services\Subscriptions\TenantSubscriptionAccess;
use App\Tenancy\Exceptions\TenantSuspendedException;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        $destinatario = strtolower(trim($destinatario));
        if ($destinatario !== '') {
            Cache::put(self::cacheKeyForDestinatario($destinatario), $tenantSlug, $ttl);
        }

        $digits = preg_replace('/\D/', '', (string) $phoneDigits) ?? '';
        if ($digits === '') {
            $digits = preg_replace('/\D/', '', $destinatario) ?? '';
        }
        if ($digits === '') {
            return;
        }

        Cache::put(self::cacheKeyForPhone($digits), $tenantSlug, $ttl);
        $last9 = strlen($digits) > 9 ? substr($digits, -9) : $digits;
        Cache::put(self::cacheKeyForPhone($last9), $tenantSlug, $ttl);
        if (strlen($last9) === 9 && str_starts_with($last9, '9')) {
            Cache::put(self::cacheKeyForPhone('51'.$last9), $tenantSlug, $ttl);
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

        foreach ($this->allActiveTenantSlugs() as $slug) {
            if ($slug !== '' && ! in_array($slug, $slugs, true)) {
                $slugs[] = $slug;
            }
        }

        $rsvp = app(AgendaOwnerRsvpService::class);
        $tenants = app(TenantManager::class);

        foreach ($slugs as $slug) {
            try {
                $result = $tenants->runForSlug($slug, fn (): ?array => $rsvp->tryHandle($phone, $body, $waChatId));
            } catch (TenantSuspendedException) {
                continue;
            }
            if (is_array($result)) {
                self::rememberTenant($slug, $waChatId, preg_replace('/\D/', '', $phone) ?: null);

                return $result;
            }
        }

        Log::warning('Agenda RSVP inbound sin tenant o sin turno', [
            'phone' => $phone,
            'wa_chat_id' => $waChatId,
            'session' => $openWaSessionId,
            'slugs' => $slugs,
            'body' => mb_substr($body, 0, 40),
        ]);

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
            foreach ([$digits, strlen($digits) > 9 ? substr($digits, -9) : $digits] as $key) {
                if ($key === '') {
                    continue;
                }
                $cachedPhone = Cache::get(self::cacheKeyForPhone($key));
                if (is_string($cachedPhone) && $cachedPhone !== '') {
                    $slugs[] = $cachedPhone;
                }
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

    /**
     * @return list<string>
     */
    private function allActiveTenantSlugs(): array
    {
        $access = app(TenantSubscriptionAccess::class);

        return Tenant::query()
            ->whereIn('estado', ['trial', 'active'])
            ->orderBy('slug')
            ->get()
            ->filter(static fn (Tenant $tenant): bool => $access->allowsAccess($tenant))
            ->pluck('slug')
            ->filter(static fn (mixed $slug): bool => is_string($slug) && $slug !== '')
            ->values()
            ->all();
    }
}
