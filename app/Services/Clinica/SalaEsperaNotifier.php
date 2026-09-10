<?php

declare(strict_types=1);

namespace App\Services\Clinica;

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
     * @param  array{paciente?: string, hora?: string}  $item
     */
    public function notify(Tenant $tenant, User $actor, string $tipo, array $item): void
    {
        $permission = $tipo === SalaEsperaHoyService::TIPO_GROOMING
            ? 'sala-espera.grooming'
            : 'sala-espera.consulta';

        $users = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->permission($permission)
            ->whereKeyNot($actor->id)
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        $nombre = (string) ($item['paciente'] ?? 'Paciente');
        $hora = (string) ($item['hora'] ?? '');
        $title = $tipo === SalaEsperaHoyService::TIPO_GROOMING
            ? 'Sala peluquería'
            : 'Sala consulta';
        $body = trim($nombre.($hora !== '' ? ' · '.$hora : ''));

        try {
            $this->push->sendToUsers($users, [
                'title' => $title,
                'body' => $body,
                'url' => '/',
                'tag' => 'sala-espera-'.$tipo,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Sala espera push failed', ['error' => $e->getMessage()]);
        }
    }
}
