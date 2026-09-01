<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\ClosingQueueWhatsAppSend;
use App\Models\SalesConversation;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\VeterinariaProspecto;
use App\Services\Sales\SalesBotService;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Cola diaria de cierre: leads calientes, trials por vencer, prospectos en pipeline
 * y clínicas de pago a las que pedir referido. Solo lee public; no toca schemas de clínica.
 */
final class ClosingQueueService
{
    public const MONTHLY_GOAL = 25;

    /** @var list<string> */
    public const SCOPES = ['hoy', 'leads', 'trials', 'prospectos', 'referidos'];

    /**
     * @return array{
     *     items: LengthAwarePaginator,
     *     filters: array{search: string, scope: string, per_page: int},
     *     stats: array<string, mixed>
     * }
     */
    public function paginate(string $search, string $scope, int $perPage, int $page = 1): array
    {
        $perPage = in_array($perPage, [10, 15, 25, 50], true) ? $perPage : 15;
        $scope = in_array($scope, self::SCOPES, true) ? $scope : 'hoy';
        $search = trim($search);
        $page = max(1, $page);
        $now = now();

        $all = $this->withLastSends($this->collectRows($now));
        $rows = $all;

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(function (array $row) use ($needle): bool {
                $hay = mb_strtolower(implode(' ', [
                    $row['name'],
                    $row['phone'] ?? '',
                    $row['reason'],
                    $row['detail'] ?? '',
                ]));

                return str_contains($hay, $needle);
            });
        }

        if ($scope !== 'hoy') {
            $kind = match ($scope) {
                'leads' => 'lead',
                'trials' => 'trial',
                default => $scope === 'prospectos' ? 'prospecto' : 'referido',
            };
            $rows = $rows->where('kind', $kind);
        }

        $sorted = $rows
            ->sortBy([
                ['priority', 'asc'],
                ['sort_at', 'asc'],
            ])
            ->values();

        $total = $sorted->count();
        $slice = $sorted->forPage($page, $perPage)->values()->all();

        $paginator = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );

        return [
            'items' => $paginator,
            'filters' => [
                'search' => $search,
                'scope' => $scope,
                'per_page' => $perPage,
            ],
            'stats' => $this->stats($all),
        ];
    }

    /**
     * @param  list<string>  $ids
     * @return Collection<int, array<string, mixed>>
     */
    public function rowsByIds(array $ids): Collection
    {
        $want = [];
        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $want[$id] = true;
            }
        }

        if ($want === []) {
            return collect();
        }

        return $this->withLastSends(
            $this->collectRows(now())
                ->filter(static fn (array $row): bool => isset($want[(string) ($row['id'] ?? '')]))
                ->values(),
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectRows(CarbonInterface $now): Collection
    {
        return $this->leadRows($now)
            ->concat($this->trialRows($now))
            ->concat($this->prospectoRows($now))
            ->concat($this->referidoRows())
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function leadRows(CarbonInterface $now): Collection
    {
        $since = $now->copy()->subDays(14);

        $conversations = SalesConversation::query()
            ->where('converted', false)
            ->whereNull('lost_at')
            ->where('turn_count', '>', 0)
            ->where(function ($q): void {
                $q->where('product', SalesBotService::PRODUCT_VETSAAS)
                    ->orWhereNull('product');
            })
            ->whereRaw('COALESCE(last_message_at, updated_at) >= ?', [$since])
            ->orderByRaw('COALESCE(last_message_at, updated_at) DESC')
            ->limit(50)
            ->get();

        return $conversations->map(function (SalesConversation $c) use ($now): array {
            $name = trim((string) ($c->prospect_name ?: '')) ?: $c->phone;
            $hot = $c->demo_sent_at !== null
                || $c->meet_at !== null
                || in_array((string) $c->meet_status, ['proposed', 'confirmed'], true);
            $when = $c->last_message_at ?? $c->updated_at;
            $reason = $hot
                ? 'Demo o Meet pendiente — cerrar pago este mes'
                : 'Conversó en los últimos 14 días';

            $script = sprintf(
                'Hola%s, te escribo de VetSaaS (Orvae). ¿Seguimos con la demo o te queda alguna duda para activar este mes?',
                $c->prospect_name ? ' '.trim((string) $c->prospect_name) : '',
            );

            return $this->row(
                id: 'lead:'.$c->id,
                kind: 'lead',
                name: $name,
                phone: $c->phone,
                reason: $reason,
                detail: $c->activation_trigger ? 'Trigger: '.$c->activation_trigger : null,
                script: $script,
                panelUrl: '/plataforma/salesbot-conversations?search='.rawurlencode((string) $c->phone),
                priority: $hot ? 2 : 3,
                sortAt: $when?->toIso8601String() ?? $now->toIso8601String(),
            ) + [
                'wa_chat_id' => (string) $c->wa_chat_id,
                'last_sent_at' => $this->colaCierreSentAtFromMessages($c),
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function trialRows(CarbonInterface $now): Collection
    {
        $until = $now->copy()->addDays(14);

        $subs = Subscription::query()
            ->with([
                'tenant:id,slug,nombre_comercial,razon_social,telefono,estado',
                'plan:id,nombre',
            ])
            ->where('estado', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>=', $now)
            ->where('trial_ends_at', '<=', $until)
            ->orderBy('trial_ends_at')
            ->get();

        return $subs->map(function (Subscription $sub) use ($now): array {
            $tenant = $sub->tenant;
            $nombre = $this->tenantNombre($tenant);
            $ends = $sub->trial_ends_at;
            $days = $ends !== null ? (int) floor((float) $now->diffInDays($ends, false)) : 14;
            $urgent = $days <= 3;
            $fecha = $ends?->timezone((string) config('app.timezone'))->format('d/m/Y') ?? '—';
            $phone = $tenant?->telefono;

            $script = sprintf(
                'Hola %s, tu prueba de VetSaaS vence el %s. ¿Lo activamos hoy para no perder el historial y la agenda?',
                $nombre,
                $fecha,
            );

            return $this->row(
                id: 'trial:'.$sub->id,
                kind: 'trial',
                name: $nombre,
                phone: is_string($phone) ? $phone : null,
                reason: $urgent
                    ? "Trial vence en {$days} día(s) ({$fecha})"
                    : "Trial vence el {$fecha}",
                detail: $sub->plan?->nombre,
                script: $script,
                panelUrl: '/plataforma/embudo?scope=vence_7d&search='.rawurlencode((string) ($tenant?->slug ?? '')),
                priority: $urgent ? 0 : 1,
                sortAt: $ends?->toIso8601String() ?? $now->toIso8601String(),
            );
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function prospectoRows(CarbonInterface $now): Collection
    {
        $since = $now->copy()->subDays(14);

        $prospectos = VeterinariaProspecto::query()
            ->whereNotNull('telefono_normalizado')
            ->where(function ($q) use ($since): void {
                $q->whereIn('estado', ['contactado', 'conversando', 'demo_agendada'])
                    ->orWhere(function ($inner) use ($since): void {
                        $inner->where('estado', 'nuevo')
                            ->whereNotNull('mensaje_enviado_at')
                            ->where('mensaje_enviado_at', '>=', $since);
                    });
            })
            ->orderByRaw("CASE estado WHEN 'demo_agendada' THEN 0 WHEN 'conversando' THEN 1 WHEN 'contactado' THEN 2 ELSE 3 END")
            ->orderByDesc('mensaje_enviado_at')
            ->limit(40)
            ->get();

        return $prospectos->map(function (VeterinariaProspecto $p) use ($now): array {
            $phone = $p->telefono_normalizado ?: $p->telefono;
            $priority = match ($p->estado) {
                'demo_agendada' => 4,
                'conversando' => 5,
                'contactado' => 6,
                default => 7,
            };
            $script = sprintf(
                'Hola, soy de VetSaaS. Vi %s. ¿Te agendo 15 min para mostrarte agenda + historia clínica?',
                $p->nombre,
            );

            return $this->row(
                id: 'prospecto:'.$p->id,
                kind: 'prospecto',
                name: $p->nombre,
                phone: is_string($phone) ? $phone : null,
                reason: 'Prospecto · '.$p->estado,
                detail: $p->departamento,
                script: $script,
                panelUrl: '/plataforma/prospectos-veterinarias?search='.rawurlencode($p->nombre),
                priority: $priority,
                sortAt: ($p->mensaje_enviado_at ?? $p->capturado_at ?? $now)->toIso8601String(),
            );
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function referidoRows(): Collection
    {
        $subs = Subscription::query()
            ->with(['tenant:id,slug,nombre_comercial,razon_social,telefono,referral_code,estado'])
            ->whereIn('estado', ['active', 'grace'])
            ->whereHas('plan', fn ($q) => $q->excludingFree())
            ->get();

        $seen = [];
        $rows = collect();

        foreach ($subs as $sub) {
            $tenant = $sub->tenant;
            if ($tenant === null || isset($seen[$tenant->id])) {
                continue;
            }
            $seen[$tenant->id] = true;

            $nombre = $this->tenantNombre($tenant);
            $shareUrl = $this->shareUrlFor($tenant);
            $script = sprintf(
                'Hola %s 👋 Si conocés otra clínica que todavía anota en papel, este link les da el trial con tu código y a vos te suma días: %s',
                $nombre,
                $shareUrl,
            );

            $rows->push($this->row(
                id: 'referido:'.$tenant->id,
                kind: 'referido',
                name: $nombre,
                phone: is_string($tenant->telefono) ? $tenant->telefono : null,
                reason: 'Cliente de pago — pedir 1 referido',
                detail: $shareUrl,
                script: $script,
                panelUrl: '/plataforma/suscripciones?search='.rawurlencode((string) $tenant->slug),
                priority: 20,
                sortAt: $tenant->slug,
            ));
        }

        return $rows;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $all
     * @return array<string, mixed>
     */
    private function stats(Collection $all): array
    {
        $paying = (int) Subscription::query()
            ->whereIn('estado', ['active', 'grace'])
            ->whereHas('plan', fn ($q) => $q->excludingFree())
            ->selectRaw('count(distinct tenant_id) as aggregate')
            ->value('aggregate');

        return [
            'paying' => $paying,
            'goal' => self::MONTHLY_GOAL,
            'remaining' => max(0, self::MONTHLY_GOAL - $paying),
            'leads' => $all->where('kind', 'lead')->count(),
            'trials' => $all->where('kind', 'trial')->count(),
            'prospectos' => $all->where('kind', 'prospecto')->count(),
            'referidos' => $all->where('kind', 'referido')->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        string $id,
        string $kind,
        string $name,
        ?string $phone,
        string $reason,
        ?string $detail,
        string $script,
        string $panelUrl,
        int $priority,
        string $sortAt,
    ): array {
        return [
            'id' => $id,
            'kind' => $kind,
            'name' => $name,
            'phone' => $phone,
            'reason' => $reason,
            'detail' => $detail,
            'script' => $script,
            'wa_url' => $this->waMe($phone, $script),
            'panel_url' => $panelUrl,
            'priority' => $priority,
            'sort_at' => $sortAt,
            'last_sent_at' => null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function withLastSends(Collection $rows): Collection
    {
        if ($rows->isEmpty() || ! Schema::hasTable('closing_queue_whatsapp_sends')) {
            return $rows;
        }

        $sends = ClosingQueueWhatsAppSend::query()
            ->whereIn('row_key', $rows->pluck('id')->all())
            ->get()
            ->keyBy('row_key');

        return $rows->map(function (array $row) use ($sends): array {
            $send = $sends->get((string) ($row['id'] ?? ''));
            if ($send !== null) {
                $row['last_sent_at'] = $send->sent_at?->toIso8601String();
            }

            return $row;
        });
    }

    private function colaCierreSentAtFromMessages(SalesConversation $conversation): ?string
    {
        $messages = $conversation->messages ?? [];
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            $message = $messages[$i] ?? null;
            if (! is_array($message)) {
                continue;
            }
            $content = (string) ($message['content'] ?? '');
            if (($message['role'] ?? '') === 'assistant' && str_starts_with($content, '[cola-cierre]')) {
                return ($conversation->last_message_at ?? $conversation->updated_at)?->toIso8601String();
            }
        }

        return null;
    }

    private function tenantNombre(?Tenant $tenant): string
    {
        if ($tenant === null) {
            return '—';
        }

        $nombre = trim((string) ($tenant->nombre_comercial ?: $tenant->razon_social ?: $tenant->slug));

        return $nombre !== '' ? $nombre : '—';
    }

    private function shareUrlFor(Tenant $tenant): string
    {
        $code = trim((string) ($tenant->referral_code ?: $tenant->slug));
        $template = (string) config('referral.share_url_template', 'https://orvae.pe/software/VETSAAS?ref={code}');

        return str_replace('{code}', rawurlencode($code), $template);
    }

    private function waMe(?string $phone, string $text): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            $digits = '51'.$digits;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($text);
    }
}
