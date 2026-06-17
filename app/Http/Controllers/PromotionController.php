<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewPromotionRequest;
use App\Http\Requests\PromotionRequest;
use App\Models\GroomingServicio;
use App\Models\GroomingServicioTarifa;
use App\Models\Promotion;
use App\Support\Venta\VentaPromotionPreview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50];

    private const SORTABLE_COLUMNS = [
        'name',
        'code',
        'scope',
        'priority',
        'is_active',
        'created_at',
    ];

    private const ESTADO_OPTIONS = ['todas', 'activa', 'inactiva'];

    public function index(Request $request): Response
    {
        if (! Schema::hasTable('promotions')) {
            abort(503, 'Faltan migraciones del tenant (promotions). Ejecuta: php artisan vetsaas:tenant-migrate-all --slug=<tu-clinica>');
        }

        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true) ? $perPageRequested : 10;

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $estado = (string) $request->string('estado', 'todas');
        if (! in_array($estado, self::ESTADO_OPTIONS, true)) {
            $estado = 'todas';
        }

        $canAudit = $request->user()?->can('audit-trail.view') ?? false;

        $query = Promotion::query();

        if ($canAudit) {
            $query->with(['createdBy:id,name,email', 'updatedBy:id,name,email']);
        }

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderBy('priority')->orderBy('name');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('code', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        if ($estado === 'activa') {
            $query->where('is_active', true);
        } elseif ($estado === 'inactiva') {
            $query->where('is_active', false);
        }

        $promotions = $query->paginate($perPage)->withQueryString();

        return Inertia::render('caja/descuentos/index', [
            'promotions' => $promotions,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'estado' => $estado,
            ],
            'stats' => [
                'total' => Promotion::count(),
                'activas' => Promotion::where('is_active', true)->count(),
                'inactivas' => Promotion::where('is_active', false)->count(),
                'coincidencias' => $promotions->total(),
            ],
            'groomingServiceOptions' => $this->groomingServiceOptions(),
            'meta' => [
                'discount_types' => Promotion::DISCOUNT_TYPES,
                'scopes' => Promotion::SCOPES,
                'condition_types' => Promotion::CONDITION_TYPES,
            ],
        ]);
    }

    public function store(PromotionRequest $request): RedirectResponse
    {
        $userId = Auth::id();

        Promotion::create([
            ...$request->validated(),
            'uses_count' => 0,
            'created_by_id' => $userId,
            'updated_by_id' => $userId,
        ]);

        return back()->with('success', __('promotions.flash.created'));
    }

    public function update(PromotionRequest $request, Promotion $promotion): RedirectResponse
    {
        $promotion->update([
            ...$request->validated(),
            'updated_by_id' => Auth::id(),
        ]);

        return back()->with('success', __('promotions.flash.updated'));
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        $promotion->update(['updated_by_id' => Auth::id()]);
        $promotion->delete();

        return back()->with('success', __('promotions.flash.deleted'));
    }

    public function preview(PreviewPromotionRequest $request, VentaPromotionPreview $preview): JsonResponse
    {
        $result = $preview->preview($request->validated());

        return response()->json(['data' => $result]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function groomingServiceOptions(): array
    {
        $options = [];

        $tarifas = GroomingServicioTarifa::query()
            ->where('activo', true)
            ->orderBy('servicio')
            ->get(['servicio']);

        foreach ($tarifas as $tarifa) {
            $options[$tarifa->servicio] = [
                'value' => $tarifa->servicio,
                'label' => $tarifa->servicio,
            ];
        }

        if (Schema::hasTable('grooming_servicios')) {
            $servicios = GroomingServicio::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['codigo_legacy', 'nombre']);

            foreach ($servicios as $servicio) {
                $legacy = trim((string) ($servicio->codigo_legacy ?? ''));
                if ($legacy === '') {
                    continue;
                }
                $options[$legacy] = [
                    'value' => $legacy,
                    'label' => $servicio->nombre,
                ];
            }
        }

        return array_values($options);
    }
}
