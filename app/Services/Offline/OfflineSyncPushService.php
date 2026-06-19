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
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class OfflineSyncPushService
{
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

        $tenantId = (string) $user->tenant_id;
        $existing = OfflineSyncEvent::query()
            ->where('client_uuid', $uuid)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($existing !== null) {
            return $this->resultFromEvent($existing);
        }

        return match ($type) {
            'caja.venta.create' => $this->processVenta($user, $uuid, $payload),
            default => $this->failed($uuid, __('offline.sync.tipo_no_soportado')),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function processVenta(User $user, string $uuid, array $payload): array
    {
        try {
            $validated = $this->validateVentaPayload($payload, $user);
            $tenant = $this->tenants->current()?->tenant
                ?? Tenant::query()->find($user->tenant_id);

            $venta = $this->checkout->registrar($validated, $user, $tenant);

            $event = OfflineSyncEvent::query()->create([
                'client_uuid' => $uuid,
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'type' => 'caja.venta.create',
                'payload' => $payload,
                'status' => 'synced',
                'resource_type' => Venta::class,
                'resource_id' => $venta->id,
                'resource_label' => $venta->numero,
                'synced_at' => now(),
            ]);

            return $this->resultFromEvent($event);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first();
            $this->storeFailure($user, $uuid, 'caja.venta.create', $payload, (string) $message);

            return $this->failed($uuid, (string) $message);
        } catch (\Throwable $e) {
            report($e);
            $this->storeFailure($user, $uuid, 'caja.venta.create', $payload, $e->getMessage());

            return $this->failed($uuid, __('offline.sync.error_generico'));
        }
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
     * @param  array<string, mixed>  $payload
     */
    private function storeFailure(
        User $user,
        string $uuid,
        string $type,
        array $payload,
        string $message,
    ): void {
        OfflineSyncEvent::query()->updateOrCreate(
            ['client_uuid' => $uuid],
            [
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'type' => $type,
                'payload' => $payload,
                'status' => 'failed',
                'error_message' => $message,
                'synced_at' => now(),
            ],
        );
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
}
