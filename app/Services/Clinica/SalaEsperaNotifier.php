<?php

declare(strict_types=1);

namespace App\Services\Clinica;

use App\Events\Clinica\SalaEsperaUpdated;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Push\WebPushSender;
use Illuminate\Support\Facades\Log;

final class SalaEsperaNotifier
{
    public function __construct(
        private readonly WebPushSender $push,
    ) {}

    /**
     * @param  array<string, mixed>  $item
     */
    public function ping(Tenant $tenant, User $actor, string $action, string $tipo, array $item = []): void
    {
        try {
            SalaEsperaUpdated::dispatch(
                (string) $tenant->id,
                $action,
                $tipo,
                $item,
                (string) $actor->id,
            );
        } catch (\Throwable $e) {
            Log::warning('Sala espera broadcast failed', ['error' => $e->getMessage()]);
        }

        if (! in_array($action, ['enviar', 'asignar'], true)) {
            return;
        }

        $this->notify($tenant, $actor, $tipo, $item);
    }

    /**
     * @param  array{paciente?: string, hora?: string}  $item
     */
    public function notify(Tenant $tenant, User $actor, string $tipo, array $item): void
    {
        $tratanteId = isset($item['tratante_id']) && is_string($item['tratante_id']) && $item['tratante_id'] !== ''
            ? $item['tratante_id']
            : null;

        if ($tratanteId !== null) {
            if ($tratanteId === (string) $actor->id) {
                return;
            }

            $users = User::query()
                ->whereKey($tratanteId)
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->get();
        } else {
            $permission = $tipo === SalaEsperaHoyService::TIPO_GROOMING
                ? 'sala-espera.grooming'
                : 'sala-espera.consulta';

            $users = User::query()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->permission($permission)
                ->whereKeyNot($actor->id)
                ->get();
        }

        if ($users->isEmpty()) {
            return;
        }

        $nombre = (string) ($item['paciente'] ?? 'Paciente');
        $hora = (string) ($item['hora'] ?? '');
        $numero = $item['numero'] ?? null;
        $title = $tipo === SalaEsperaHoyService::TIPO_GROOMING
            ? 'Sala peluquería'
            : 'Sala consulta';
        $turno = is_numeric($numero) ? 'Turno '.((int) $numero).' · ' : '';
        $body = trim($turno.$nombre.($hora !== '' ? ' · '.$hora : ''));

        try {
            $this->push->sendToUsers($users, [
                'title' => $title,
                'body' => $body,
                'url' => '/clinica/sala-espera',
                'tag' => 'sala-espera-'.$tipo,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Sala espera push failed', ['error' => $e->getMessage()]);
        }
    }
}
