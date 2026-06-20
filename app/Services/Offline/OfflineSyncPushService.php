<?php

declare(strict_types=1);

namespace App\Services\Offline;

use App\Http\Requests\StoreVentaRequest;
use App\Models\OfflineSyncEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Venta;
use App\Services\Venta\VentaCheckoutService;
use App\Tenancy\TenantManager;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OfflineSyncPushService
{
    private const MAX_WAIT_SECONDS = 30;

    public function __construct(
        private readonly VentaCheckoutService $checkout,
        private readonly TenantManager $tenants,
    ) {}

    /**
     * @param  array{uuid: string, type: string, payload: array<string, mixed>}  $item
     * @return array<string, mixed>
     */
    public function process(User $user, array $item): array
    {
        $uuid = (string) ($item['uuid'] ?? '');
        $type = (string) ($item['type'] ?? '');
        $payload = is_array($item['payload'] ?? null) ? $item['payload'] : [];

        if ($uuid === '' || $type === '') {
            return $this->failed($uuid, 'Payload inválido.');
        }

        [$event, $isOwner] = $this->acquireOrLoad($user, $uuid, $type, $payload);

        if ($event === null) {
            return $this->failed($uuid, __('offline.sync.error_generico'));
        }

        if (in_array($event->status, ['synced', 'failed'], true)) {
            return $this->resultFromEvent($event);
        }

        if (! $isOwner) {
            return $this->waitForCompletion($uuid);
        }

        return match ($type) {
            'caja.venta.create' => $this->completeVenta($user, $event, $payload),
            default => $this->markFailed($event, __('offline.sync.tipo_no_soportado')),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: OfflineSyncEvent|null, 1: bool}
     */
    private function acquireOrLoad(
        User $user,
        string $uuid,
        string $type,
        array $payload,
    ): array {
        try {
            $event = OfflineSyncEvent::query()->create([
                'client_uuid' => $uuid,
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'type' => $type,
                'payload' => $payload,
                'status' => 'processing',
                'synced_at' => now(),
            ]);

            return [$event, true];
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }

            $existing = OfflineSyncEvent::query()
                ->where('client_uuid', $uuid)
                ->first();

            return [$existing, false];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function completeVenta(User $user, OfflineSyncEvent $event, array $payload): array
    {
        try {
            $validated = $this->validateVentaPayload($payload, $user);
            $tenant = $this->tenants->current()?->tenant
                ?? Tenant::query()->find($user->tenant_id);

            $venta = DB::transaction(fn () => $this->checkout->registrar($validated, $user, $tenant));

            $event->update([
                'status' => 'synced',
                'resource_type' => Venta::class,
                'resource_id' => $venta->id,
                'resource_label' => $venta->numero,
                'error_message' => null,
                'synced_at' => now(),
            ]);

            return $this->resultFromEvent($event->fresh());
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();

            return $this->markFailed($event, $message);
        } catch (\Throwable $e) {
            report($e);

            return $this->markFailed($event, __('offline.sync.error_generico'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function waitForCompletion(string $uuid): array
    {
        $deadline = now()->addSeconds(self::MAX_WAIT_SECONDS);

        while (now()->lessThan($deadline)) {
            $event = OfflineSyncEvent::query()->where('client_uuid', $uuid)->first();

            if ($event === null) {
                break;
            }

            if ($event->status !== 'processing') {
                return $this->resultFromEvent($event);
            }

            usleep(200_000);
        }

        return $this->failed($uuid, __('offline.sync.error_generico'));
    }

    /**
     * @return array<string, mixed>
     */
    private function markFailed(OfflineSyncEvent $event, string $message): array
    {
        $event->update([
            'status' => 'failed',
            'error_message' => $message,
            'synced_at' => now(),
        ]);

        return $this->failed($event->client_uuid, $message);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateVentaPayload(array $payload, User $user): array
    {
        $base = Request::create('/caja/ventas', 'POST', $payload);
        $base->setUserResolver(static fn () => $user);

        /** @var StoreVentaRequest $form */
        $form = StoreVentaRequest::createFrom($base);
        $form->setContainer(app());
        $form->setRedirector(app('redirect'));
        $form->validateResolved();

        return $form->validated();
    }

    /**
     * @return array<string, mixed>
     */
    private function resultFromEvent(OfflineSyncEvent $event): array
    {
        if ($event->status === 'failed') {
            return $this->failed(
                $event->client_uuid,
                (string) ($event->error_message ?? __('offline.sync.error_generico')),
            );
        }

        if ($event->status === 'processing') {
            return $this->failed($event->client_uuid, __('offline.sync.error_generico'));
        }

        return [
            'uuid' => $event->client_uuid,
            'status' => 'synced',
            'type' => $event->type,
            'venta_id' => $event->resource_id,
            'numero' => $event->resource_label,
        ];
    }

    /**
     * @return array{uuid: string, status: 'failed', error: string}
     */
    private function failed(string $uuid, string $error): array
    {
        return [
            'uuid' => $uuid,
            'status' => 'failed',
            'error' => $error,
        ];
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $code = (string) $e->getCode();

        return str_contains($e->getMessage(), 'offline_sync_events_client_uuid_unique')
            || $code === '23505'
            || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
