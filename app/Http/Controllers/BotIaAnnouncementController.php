<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\BotIaAnnouncementRequest;
use App\Models\BotIaAnnouncement;
use App\Support\Database\PublicSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD de novedades in-app del Asistente IA para tenants.
 */
final class BotIaAnnouncementController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50];

    /** @var list<string> */
    private const STATUS_FILTERS = ['todos', 'activo', 'inactivo', 'programado'];

    public function index(Request $request): Response
    {
        if (! PublicSchema::hasTable('bot_ia_announcements')) {
            abort(503, 'Falta la tabla bot_ia_announcements. Ejecuta: php artisan migrate');
        }

        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', 'todos');
        if (! in_array($status, self::STATUS_FILTERS, true)) {
            $status = 'todos';
        }

        $perPage = (int) $request->input('per_page', 10);
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 10;

        $query = BotIaAnnouncement::query();

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($like): void {
                $q->where('title', 'ilike', $like)
                    ->orWhere('bullet_1', 'ilike', $like)
                    ->orWhere('bullet_2', 'ilike', $like)
                    ->orWhere('bullet_3', 'ilike', $like);
            });
        }

        if ($status === 'activo') {
            $query->published();
        } elseif ($status === 'inactivo') {
            $query->where('is_active', false);
        } elseif ($status === 'programado') {
            $query->where('is_active', true)
                ->whereNotNull('published_at')
                ->where('published_at', '>', now());
        }

        $paginated = $query
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        try {
            $active = BotIaAnnouncement::currentForTenants();
        } catch (\Throwable $e) {
            report($e);
            $active = null;
        }

        return Inertia::render('plataforma/bot-ia-announcements/index', [
            'entries' => $paginated,
            'active_announcement_id' => $active?->id,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(BotIaAnnouncementRequest $request): RedirectResponse
    {
        $data = $this->normalizePayload($request->validated());

        DB::transaction(function () use ($data, $request): void {
            if ($data['is_active']) {
                $this->deactivateAll();
            }

            BotIaAnnouncement::create([
                ...$data,
                'created_by_id' => $request->user()?->id,
            ]);
        });

        BotIaAnnouncement::flushCache();

        return back()->with('success', 'Novedad creada correctamente.');
    }

    public function update(
        BotIaAnnouncementRequest $request,
        BotIaAnnouncement $botIaAnnouncement,
    ): RedirectResponse {
        $data = $this->normalizePayload($request->validated());

        DB::transaction(function () use ($data, $botIaAnnouncement): void {
            if ($data['is_active']) {
                $this->deactivateAll($botIaAnnouncement->id);
            }

            $botIaAnnouncement->update($data);
        });

        BotIaAnnouncement::flushCache();

        return back()->with('success', 'Novedad actualizada correctamente.');
    }

    public function destroy(BotIaAnnouncement $botIaAnnouncement): RedirectResponse
    {
        $botIaAnnouncement->delete();
        BotIaAnnouncement::flushCache();

        return back()->with('success', 'Novedad eliminada correctamente.');
    }

    public function activate(BotIaAnnouncement $botIaAnnouncement): RedirectResponse
    {
        DB::transaction(function () use ($botIaAnnouncement): void {
            $this->deactivateAll($botIaAnnouncement->id);

            $botIaAnnouncement->update([
                'is_active' => true,
                'published_at' => $botIaAnnouncement->published_at ?? now(),
            ]);
        });

        BotIaAnnouncement::flushCache();

        return back()->with('success', 'Novedad publicada para todos los tenants con Bot IA.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data): array
    {
        if ($data['is_active'] && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        if (! $data['is_active']) {
            $data['published_at'] = $data['published_at'] ?? null;
        }

        return $data;
    }

    private function deactivateAll(?string $exceptId = null): void
    {
        $query = BotIaAnnouncement::query()->where('is_active', true);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['is_active' => false]);
    }
}
