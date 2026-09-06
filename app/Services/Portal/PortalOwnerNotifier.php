<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Models\Paciente;
use App\Models\PortalAviso;
use App\Models\PortalPropietario;
use App\Models\PortalPushSubscription;
use App\Models\Propietario;
use App\Services\Push\WebPushSender;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class PortalOwnerNotifier
{
    public function __construct(
        private readonly WebPushSender $webPush,
    ) {}

    public function notify(
        Propietario $propietario,
        string $tipo,
        string $titulo,
        string $cuerpo,
        ?string $pacienteId = null,
    ): void {
        try {
            if (! Schema::hasTable('portal_propietarios')) {
                return;
            }

            $portal = PortalPropietario::query()
                ->where('propietario_id', $propietario->id)
                ->first();

            if ($portal === null) {
                return;
            }

            $url = $pacienteId
                ? route('tenant.portal.home', ['mascota' => $pacienteId])
                : route('tenant.portal.home');

            if (Schema::hasTable('portal_avisos')) {
                PortalAviso::query()->create([
                    'portal_propietario_id' => $portal->id,
                    'paciente_id' => $pacienteId,
                    'tipo' => $tipo,
                    'titulo' => $titulo,
                    'cuerpo' => $cuerpo,
                    'url' => $url,
                ]);
            }

            $this->push($portal, $titulo, $cuerpo, $url);
        } catch (Throwable $e) {
            Log::warning('Portal dueño: no se pudo notificar', [
                'propietario_id' => $propietario->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function notifyPaciente(
        Paciente $paciente,
        string $tipo,
        string $titulo,
        string $cuerpo,
    ): void {
        $owner = $paciente->propietario;
        if ($owner === null) {
            $paciente->loadMissing('propietario');
            $owner = $paciente->propietario;
        }
        if ($owner === null) {
            return;
        }

        $this->notify($owner, $tipo, $titulo, $cuerpo, $paciente->id);
    }

    private function push(PortalPropietario $portal, string $titulo, string $cuerpo, string $url): void
    {
        if (! Schema::hasTable('portal_push_subscriptions') || ! $this->webPush->isConfigured()) {
            return;
        }

        $subs = PortalPushSubscription::query()
            ->where('portal_propietario_id', $portal->id)
            ->get();

        if ($subs->isEmpty()) {
            return;
        }

        $this->webPush->sendToRawSubscriptions($subs, [
            'title' => $titulo,
            'body' => $cuerpo,
            'url' => $url,
            'tag' => 'portal-'.$portal->id,
        ]);
    }
}
