<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ClinicBotKnowledgeRequest;
use App\Models\BotIaAnnouncement;
use App\Models\ClinicBotConversation;
use App\Models\ClinicBotKnowledge;
use App\Models\ClinicSetting;
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

    private const CONVERSATION_ESTADO_OPTIONS = ['todos', 'activo', 'pausado'];

    private const TAB_OPTIONS = ['chats', 'conocimiento'];

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

        $conversations = $isActive
            ? $this->paginateConversations($request)
            : null;

        return Inertia::render('comunicaciones/bot-ia/index', [
            'bot_ia' => $botIa,
            'whatsapp' => $whatsapp->forTenant($tenant),
            'can_manage' => $canManage,
            'announcement' => $this->announcementPayload(! $isActive),
            'assistant' => $isActive ? $this->assistantPayload() : null,
            'tab' => $this->resolveTab($request),
            'knowledge' => $knowledge,
            'knowledge_stats' => $knowledgeStats,
            'knowledge_filters' => $isActive ? $this->knowledgeFilters($request) : null,
            'conversations' => $conversations,
            'conversation_filters' => $isActive ? $this->conversationFilters($request) : null,
            'conversation_stats' => $isActive ? $this->conversationStats() : null,
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

    public function pauseConversation(
        Request $request,
        TenantManager $tenants,
        ClinicBotConversation $clinicBotConversation,
    ): RedirectResponse {
        $this->assertAddonActive($request, $tenants);
        $clinicBotConversation->pauseBotManually();

        return back()->with('success', 'Asistente pausado para este chat.');
    }

    public function resumeConversation(
        Request $request,
        TenantManager $tenants,
        ClinicBotConversation $clinicBotConversation,
    ): RedirectResponse {
        $this->assertAddonActive($request, $tenants);
        $clinicBotConversation->resumeBot();

        return back()->with('success', 'Asistente reactivado para este chat.');
    }

    public function toggleAssistant(Request $request, TenantManager $tenants): RedirectResponse
    {
        $this->assertAddonActive($request, $tenants);

        $validated = $request->validate([
            'respuestas_activas' => ['required', 'boolean'],
        ]);

        $settings = ClinicSetting::current();
        $settings->bot_ia_respuestas_activo = (bool) $validated['respuestas_activas'];
        $settings->updated_by_id = $request->user()?->id;
        $settings->save();

        $message = $settings->bot_ia_respuestas_activo
            ? 'Asistente IA activado: responderá automáticamente por WhatsApp.'
            : 'Asistente IA pausado: no responderá hasta que lo reactives.';

        return back()->with('success', $message);
    }

    /**
     * Novedad promocional: solo para tenants sin el add-on Bot IA contratado.
     *
     * @return array{
     *     id: string,
     *     badge: string,
     *     title: string,
     *     bullets: list<string>,
     *     guide_title: string|null,
     *     guide_body: string|null,
     *     guide_tips: list<string>
     * }|null
     */
    private function announcementPayload(bool $shouldShow): ?array
    {
        if (! $shouldShow) {
            return null;
        }

        return BotIaAnnouncement::resolvePromoForTenants();
    }

    /**
     * @return array{respuestas_activas: bool}
     */
    private function assistantPayload(): array
    {
        return [
            'respuestas_activas' => ClinicSetting::current()->isBotIaResponding(),
        ];
    }

    private function resolveTab(Request $request): string
    {
        $tab = (string) $request->input('tab', 'chats');

        return in_array($tab, self::TAB_OPTIONS, true) ? $tab : 'chats';
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

    /**
     * @return array{chat_search: string, chat_estado: string, chat_per_page: int}
     */
    private function conversationFilters(Request $request): array
    {
        $search = (string) $request->input('chat_search', '');
        $estado = (string) $request->input('chat_estado', 'todos');
        $perPage = (int) $request->input('chat_per_page', 10);

        if (! in_array($estado, self::CONVERSATION_ESTADO_OPTIONS, true)) {
            $estado = 'todos';
        }

        return [
            'chat_search' => $search,
            'chat_estado' => $estado,
            'chat_per_page' => in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 10,
        ];
    }

    private function paginateConversations(Request $request): LengthAwarePaginator
    {
        $filters = $this->conversationFilters($request);

        $query = ClinicBotConversation::query()->withAiResponses();

        if ($filters['chat_search'] !== '') {
            $search = $filters['chat_search'];
            $query->where(function ($q) use ($search): void {
                $q->where('phone', 'ilike', "%{$search}%")
                    ->orWhere('client_name', 'ilike', "%{$search}%");
            });
        }

        if ($filters['chat_estado'] === 'activo') {
            $query->where('bot_active', true);
        } elseif ($filters['chat_estado'] === 'pausado') {
            $query->where('bot_active', false);
        }

        return $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->paginate($filters['chat_per_page'], ['*'], 'chat_page')
            ->withQueryString()
            ->through(fn (ClinicBotConversation $conversation): array => $this->conversationPayload($conversation));
    }

    /**
     * @return array<string, int>
     */
    private function conversationStats(): array
    {
        $base = ClinicBotConversation::query()->withAiResponses();

        return [
            'total' => (clone $base)->count(),
            'activos' => (clone $base)->where('bot_active', true)->count(),
            'pausados' => (clone $base)->where('bot_active', false)->count(),
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     phone: string,
     *     client_name: string|null,
     *     bot_active: bool,
     *     bot_paused_manually: bool,
     *     last_message_at: string|null,
     *     last_message_preview: string|null,
     *     turn_count: int,
     *     messages: array<int, array{role: string, content: string}>
     * }
     */
    private function conversationPayload(ClinicBotConversation $conversation): array
    {
        $messages = $conversation->getOpenAiMessages();
        $last = $messages !== [] ? $messages[array_key_last($messages)] : null;
        $preview = is_array($last) ? trim((string) ($last['content'] ?? '')) : null;

        return [
            'id' => $conversation->id,
            'phone' => $conversation->phone,
            'client_name' => $conversation->client_name,
            'bot_active' => $conversation->bot_active,
            'bot_paused_manually' => $conversation->bot_paused_manually,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'last_message_preview' => $preview !== '' ? $preview : null,
            'turn_count' => (int) $conversation->turn_count,
            'messages' => $messages,
        ];
    }
}
