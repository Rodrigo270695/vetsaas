<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ClinicBotKnowledgeRequest;
use App\Models\ClinicBotKnowledge;
use App\Support\OpenWa\TenantWhatsAppPresenter;
use App\Support\Subscriptions\BotIaAccess;
use App\Support\Subscriptions\SubscriptionBotIaAddon;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel del tenant para el add-on Asistente IA y su base de conocimiento.
 */
final class ClinicBotIaController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50];

    private const SORTABLE_COLUMNS = [
        'section',
        'title',
        'sort_order',
        'updated_at',
    ];

    public function show(Request $request, TenantManager $tenants, TenantWhatsAppPresenter $whatsapp): Response
    {
        abort_unless(BotIaAccess::userCanView($request->user()), 403);

        $tenant = $tenants->current()?->tenant;
        abort_if($tenant === null, 404);

        $subscription = $tenant->subscriptions()
            ->orderByDesc('created_at')
            ->first();

        $botIa = SubscriptionBotIaAddon::payload($subscription);
        $canManage = BotIaAccess::userCanManage($request->user(), $tenant);
        $isActive = SubscriptionBotIaAddon::isActive($subscription);

        $knowledge = $isActive
            ? $this->paginateKnowledge($request)
            : null;

        $knowledgeStats = $isActive
            ? $this->knowledgeStats()
            : null;

        return Inertia::render('comunicaciones/bot-ia/index', [
            'bot_ia' => $botIa,
            'whatsapp' => $whatsapp->forTenant($tenant),
            'can_manage' => $canManage,
            'knowledge' => $knowledge,
            'knowledge_stats' => $knowledgeStats,
            'knowledge_filters' => $isActive ? $this->knowledgeFilters($request) : null,
        ]);
    }

    public function storeKnowledge(ClinicBotKnowledgeRequest $request, TenantManager $tenants): RedirectResponse
    {
        $this->assertAddonActive($request, $tenants);

        $data = $request->validated();

        if (empty($data['sort_order'])) {
            $data['sort_order'] = (int) ClinicBotKnowledge::query()
                ->where('section', $data['section'])
                ->max('sort_order') + 1;
        }

        ClinicBotKnowledge::create($data);
        ClinicBotKnowledge::flushCache();

        return back()->with('success', 'Entrada creada correctamente.');
    }

    public function updateKnowledge(
        ClinicBotKnowledgeRequest $request,
        TenantManager $tenants,
        ClinicBotKnowledge $clinicBotKnowledge,
    ): RedirectResponse {
        $this->assertAddonActive($request, $tenants);

        $clinicBotKnowledge->update($request->validated());
        ClinicBotKnowledge::flushCache();

        return back()->with('success', 'Entrada actualizada correctamente.');
    }

    public function destroyKnowledge(
        Request $request,
        TenantManager $tenants,
        ClinicBotKnowledge $clinicBotKnowledge,
    ): RedirectResponse {
        abort_unless(BotIaAccess::userCanManage($request->user()), 403);
        $this->assertAddonActive($request, $tenants);

        $clinicBotKnowledge->delete();
        ClinicBotKnowledge::flushCache();

        return back()->with('success', 'Entrada eliminada correctamente.');
    }

    private function assertAddonActive(Request $request, TenantManager $tenants): void
    {
        abort_unless(BotIaAccess::userCanManage($request->user()), 403);

        $tenant = $tenants->current()?->tenant;
        abort_if($tenant === null, 404);

        $subscription = $tenant->subscriptions()
            ->orderByDesc('created_at')
            ->first();

        abort_unless(SubscriptionBotIaAddon::isActive($subscription), 403);
    }

    /**
     * @return array{search: string, section: string, sort: string, direction: string, per_page: int}
     */
    private function knowledgeFilters(Request $request): array
    {
        $search = (string) $request->input('search', '');
        $section = (string) $request->input('section', 'todos');
        $sort = (string) $request->input('sort', 'sort_order');
        $direction = (string) $request->input('direction', 'asc');
        $perPage = (int) $request->input('per_page', 10);

        if (! in_array($sort, self::SORTABLE_COLUMNS, true)) {
            $sort = 'sort_order';
        }

        if (! in_array($section, [...ClinicBotKnowledge::SECTIONS, 'todos'], true)) {
            $section = 'todos';
        }

        return [
            'search' => $search,
            'section' => $section,
            'sort' => $sort,
            'direction' => $direction === 'desc' ? 'desc' : 'asc',
            'per_page' => in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 10,
        ];
    }

    private function paginateKnowledge(Request $request): LengthAwarePaginator
    {
        $filters = $this->knowledgeFilters($request);

        $query = ClinicBotKnowledge::query();

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('content', 'ilike', "%{$search}%")
                    ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        if ($filters['section'] !== 'todos') {
            $query->where('section', $filters['section']);
        }

        return $query
            ->orderBy($filters['sort'], $filters['direction'])
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    /**
     * @return array<string, int>
     */
    private function knowledgeStats(): array
    {
        $base = ClinicBotKnowledge::query();

        return [
            'total' => (clone $base)->count(),
            'faqs' => (clone $base)->where('section', 'faq')->count(),
            'horarios' => (clone $base)->where('section', 'horario')->count(),
            'politicas' => (clone $base)->where('section', 'politica')->count(),
            'servicios' => (clone $base)->where('section', 'servicio')->count(),
            'contacto' => (clone $base)->where('section', 'contacto')->count(),
            'general' => (clone $base)->where('section', 'general')->count(),
        ];
    }
}
