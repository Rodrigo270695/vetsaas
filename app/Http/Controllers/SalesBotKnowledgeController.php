<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SalesBotKnowledgeRequest;
use App\Models\SalesBotKnowledge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD de la base de conocimiento del bot de ventas.
 *
 * Solo accesible por superadmin (permiso `salesbot-knowledge.*`).
 * Cualquier cambio invalida automáticamente el caché de 5 minutos.
 */
final class SalesBotKnowledgeController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50];

    private const SORTABLE_COLUMNS = [
        'product',
        'section',
        'title',
        'sort_order',
        'updated_at',
    ];

    public function index(Request $request): Response
    {
        $search    = $request->input('search', '');
        $section   = $request->input('section', 'todos');
        $sort      = $request->input('sort', 'sort_order');
        $direction = $request->input('direction', 'asc');
        $perPage   = (int) $request->input('per_page', 10);

        if (! in_array($sort, self::SORTABLE_COLUMNS, true)) {
            $sort = 'sort_order';
        }
        $direction = $direction === 'desc' ? 'desc' : 'asc';
        $perPage   = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 10;

        $query = SalesBotKnowledge::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('content', 'ilike', "%{$search}%")
                  ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        if ($section !== 'todos') {
            $query->where('section', $section);
        }

        $query->orderBy($sort, $direction);
        $paginated = $query->paginate($perPage)->withQueryString();

        $stats = [
            'total'    => SalesBotKnowledge::query()->count(),
            'planes'   => SalesBotKnowledge::query()->where('section', 'plan')->count(),
            'modulos'  => SalesBotKnowledge::query()->where('section', 'modulo')->count(),
            'faqs'     => SalesBotKnowledge::query()->where('section', 'faq')->count(),
            'objeciones' => SalesBotKnowledge::query()->where('section', 'objecion')->count(),
            'coincidencias' => $paginated->total(),
        ];

        return Inertia::render('plataforma/salesbot-knowledge/index', [
            'entries' => $paginated,
            'filters' => [
                'search'    => $search,
                'section'   => $section,
                'sort'      => $sort,
                'direction' => $direction,
                'per_page'  => $perPage,
            ],
            'stats' => $stats,
        ]);
    }

    public function store(SalesBotKnowledgeRequest $request): RedirectResponse
    {
        SalesBotKnowledge::create($request->validated());
        SalesBotKnowledge::flushCache((string) $request->input('product', 'vetsaas'));

        return back()->with('success', 'Entrada creada correctamente.');
    }

    public function update(SalesBotKnowledgeRequest $request, SalesBotKnowledge $salesbotKnowledge): RedirectResponse
    {
        $salesbotKnowledge->update($request->validated());
        SalesBotKnowledge::flushCache($salesbotKnowledge->product);

        return back()->with('success', 'Entrada actualizada correctamente.');
    }

    public function destroy(SalesBotKnowledge $salesbotKnowledge): RedirectResponse
    {
        $product = $salesbotKnowledge->product;
        $salesbotKnowledge->delete();
        SalesBotKnowledge::flushCache($product);

        return back()->with('success', 'Entrada eliminada correctamente.');
    }

    /**
     * Limpia el caché del bot de ventas manualmente.
     * Útil después de múltiples ediciones para forzar la recarga inmediata.
     */
    public function flushCache(Request $request): JsonResponse
    {
        $product = (string) $request->input('product', 'vetsaas');
        SalesBotKnowledge::flushCache($product);

        return response()->json(['ok' => true, 'message' => "Caché del bot ({$product}) limpiado correctamente."]);
    }
}
