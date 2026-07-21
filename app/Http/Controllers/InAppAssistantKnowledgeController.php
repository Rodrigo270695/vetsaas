<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\InAppAssistantKnowledgeRequest;
use App\Models\InAppAssistantKnowledge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class InAppAssistantKnowledgeController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50];

    private const SORTABLE_COLUMNS = [
        'slug',
        'scope',
        'section',
        'title',
        'priority',
        'sort_order',
        'is_active',
        'updated_at',
    ];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $scope = (string) $request->input('scope', 'all');
        $section = (string) $request->input('section', 'all');
        $status = (string) $request->input('status', 'all');
        $sort = (string) $request->input('sort', 'priority');
        $direction = (string) $request->input('direction', 'desc');
        $perPage = (int) $request->input('per_page', 15);

        $scope = in_array($scope, InAppAssistantKnowledge::SCOPES, true) ? $scope : 'all';
        $section = in_array($section, InAppAssistantKnowledge::SECTIONS, true) ? $section : 'all';
        $status = in_array($status, ['active', 'inactive'], true) ? $status : 'all';
        $sort = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'priority';
        $direction = $direction === 'asc' ? 'asc' : 'desc';
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 15;

        $query = InAppAssistantKnowledge::query();

        if ($search !== '') {
            $escapedSearch = addcslashes($search, '%_\\');
            $query->where(function ($query) use ($escapedSearch): void {
                $query->where('title', 'ilike', "%{$escapedSearch}%")
                    ->orWhere('content', 'ilike', "%{$escapedSearch}%")
                    ->orWhere('slug', 'ilike', "%{$escapedSearch}%");
            });
        }

        if ($scope !== 'all') {
            $query->where('scope', $scope);
        }

        if ($section !== 'all') {
            $query->where('section', $section);
        }

        if ($status !== 'all') {
            $query->where('is_active', $status === 'active');
        }

        $entries = $query
            ->orderBy($sort, $direction)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('plataforma/in-app-assistant-knowledge/index', [
            'entries' => $entries,
            'filters' => [
                'search' => $search,
                'scope' => $scope,
                'section' => $section,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'stats' => [
                'total' => InAppAssistantKnowledge::query()->count(),
                'active' => InAppAssistantKnowledge::query()->where('is_active', true)->count(),
                'platform' => InAppAssistantKnowledge::query()->where('scope', InAppAssistantKnowledge::SCOPE_PLATFORM)->count(),
                'clinic' => InAppAssistantKnowledge::query()->where('scope', InAppAssistantKnowledge::SCOPE_CLINIC)->count(),
                'matches' => $entries->total(),
            ],
        ]);
    }

    public function store(InAppAssistantKnowledgeRequest $request): RedirectResponse
    {
        InAppAssistantKnowledge::query()->create($request->validated());

        return back()->with('success', 'Guía interna creada correctamente.');
    }

    public function update(
        InAppAssistantKnowledgeRequest $request,
        InAppAssistantKnowledge $inAppAssistantKnowledge,
    ): RedirectResponse {
        $inAppAssistantKnowledge->fill($request->validated())->save();

        return back()->with('success', 'Guía interna actualizada correctamente.');
    }

    public function destroy(InAppAssistantKnowledge $inAppAssistantKnowledge): RedirectResponse
    {
        $inAppAssistantKnowledge->delete();

        return back()->with('success', 'Guía interna eliminada correctamente.');
    }
}
