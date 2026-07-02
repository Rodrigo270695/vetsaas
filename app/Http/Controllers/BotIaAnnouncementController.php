<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\BotIaAnnouncementRequest;
use App\Models\BotIaAnnouncement;
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

    public function index(Request $request): Response
    {
        $search = (string) $request->input('search', '');
        $status = (string) $request->input('status', 'todos');
        $perPage = (int) $request->input('per_page', 10);
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 10;

        $query = BotIaAnnouncement::query()->with('createdBy:id,name');

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('bullet_1', 'ilike', "%{$search}%")
                    ->orWhere('bullet_2', 'ilike', "%{$search}%")
                    ->orWhere('bullet_3', 'ilike', "%{$search}%");
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

        $active = BotIaAnnouncement::currentForTenants();

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
