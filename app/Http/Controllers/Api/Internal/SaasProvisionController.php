<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\ProvisionTenantRequest;
use App\Http\Requests\Api\Internal\RenewTenantRequest;
use App\Models\Tenant;
use App\Services\Subscriptions\SubscriptionRenewalService;
use App\Services\Tenancy\TenantProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SaasProvisionController extends Controller
{
    public function __construct(
        private readonly TenantProvisioner $provisioner,
        private readonly SubscriptionRenewalService $renewalService,
    ) {}

    public function provision(ProvisionTenantRequest $request): JsonResponse
    {
        $idempotencyKey = (string) $request->attributes->get('orvae.idempotency_key');

        if ($cached = $this->findCachedResponse($idempotencyKey)) {
            return response()->json($cached['body'], $cached['status']);
        }

        try {
            $tenant = $this->provisioner->provision($request->validated());
        } catch (\InvalidArgumentException $e) {
            return $this->storeAndReturn($idempotencyKey, null, 422, [
                'error' => 'invalid_payload',
                'message' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            Log::error('orvae.provision: fallo al provisionar tenant', [
                'exception' => $e->getMessage(),
                'idempotency_key' => $idempotencyKey,
            ]);

            return response()->json([
                'error' => 'provisioning_failed',
                'message' => app()->hasDebugModeEnabled() ? $e->getMessage() : 'No se pudo crear el tenant.',
            ], 500);
        }

        $loginUrl = $this->provisioner->buildLoginUrl($tenant);

        Log::info('orvae.provision: tenant creado', [
            'tenant_id' => $tenant->id,
            'slug' => $tenant->slug,
            'plan' => $request->validated('plan_slug'),
            'external_order_id' => $request->validated('external_order_id'),
            'idempotency_key' => $idempotencyKey,
        ]);

        $body = [
            'status' => 'ok',
            'tenant' => [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'schema_name' => $tenant->schema_name,
                'razon_social' => $tenant->razon_social,
                'estado' => $tenant->estado,
                'trial_ends_at' => optional($tenant->trial_ends_at)->toIso8601String(),
            ],
            'login_url' => $loginUrl,
            'academy_url' => $loginUrl,
            'tenant_slug' => $tenant->slug,
        ];

        return $this->storeAndReturn($idempotencyKey, $tenant->id, 201, $body);
    }

    public function renew(RenewTenantRequest $request): JsonResponse
    {
        $idempotencyKey = (string) $request->attributes->get('orvae.idempotency_key');

        if ($cached = $this->findCachedResponse($idempotencyKey)) {
            return response()->json($cached['body'], $cached['status']);
        }

        $tenant = Tenant::query()->where('slug', $request->validated('tenant_slug'))->first();

        if ($tenant === null) {
            return $this->storeAndReturn($idempotencyKey, null, 404, [
                'error' => 'not_found',
                'message' => 'Tenant no encontrado.',
            ]);
        }

        try {
            $subscription = $this->renewalService->renew($tenant, $request->validated());
        } catch (\InvalidArgumentException $e) {
            return $this->storeAndReturn($idempotencyKey, $tenant->id, 422, [
                'error' => 'invalid_payload',
                'message' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            Log::error('orvae.renew: fallo al renovar tenant', [
                'tenant_slug' => $request->validated('tenant_slug'),
                'exception' => $e->getMessage(),
                'idempotency_key' => $idempotencyKey,
            ]);

            return response()->json([
                'error' => 'renewal_failed',
                'message' => app()->hasDebugModeEnabled() ? $e->getMessage() : 'No se pudo renovar la suscripción.',
            ], 500);
        }

        $tenant->refresh();
        $loginUrl = $this->provisioner->buildLoginUrl($tenant);

        Log::info('orvae.renew: suscripción renovada', [
            'tenant_id' => $tenant->id,
            'slug' => $tenant->slug,
            'subscription_id' => $subscription->id,
            'external_order_id' => $request->validated('external_order_id'),
            'idempotency_key' => $idempotencyKey,
        ]);

        $body = [
            'status' => 'ok',
            'renewed' => true,
            'tenant' => [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'estado' => $tenant->estado,
            ],
            'subscription' => [
                'id' => $subscription->id,
                'estado' => $subscription->estado,
                'current_period_start' => optional($subscription->current_period_start)->toIso8601String(),
                'current_period_end' => optional($subscription->current_period_end)->toIso8601String(),
                'proximo_cobro_at' => optional($subscription->proximo_cobro_at)->toIso8601String(),
            ],
            'login_url' => $loginUrl,
            'tenant_slug' => $tenant->slug,
        ];

        return $this->storeAndReturn($idempotencyKey, $tenant->id, 200, $body);
    }

    public function status(Request $request, string $slug): JsonResponse
    {
        $tenant = Tenant::where('slug', $slug)->first();

        if (! $tenant) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'estado' => $tenant->estado,
                'trial_ends_at' => optional($tenant->trial_ends_at)->toIso8601String(),
            ],
            'login_url' => $this->provisioner->buildLoginUrl($tenant),
        ]);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}|null
     */
    private function findCachedResponse(string $key): ?array
    {
        $row = DB::table('provision_idempotency_keys')->where('key', $key)->first();

        if ($row === null) {
            return null;
        }

        if ($row->expires_at !== null && now()->greaterThan($row->expires_at)) {
            DB::table('provision_idempotency_keys')->where('id', $row->id)->delete();

            return null;
        }

        return [
            'status' => (int) $row->status_code,
            'body' => json_decode((string) $row->response_body, true) ?: [],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function storeAndReturn(string $key, ?string $tenantId, int $status, array $body): JsonResponse
    {
        $ttlDays = (int) config('orvae.provision.idempotency_ttl_days', 30);

        DB::table('provision_idempotency_keys')->updateOrInsert(
            ['key' => $key],
            [
                'source' => 'orvae',
                'tenant_id' => $tenantId,
                'status_code' => $status,
                'response_body' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'expires_at' => $ttlDays > 0 ? now()->addDays($ttlDays) : null,
            ]
        );

        return response()->json($body, $status);
    }
}
