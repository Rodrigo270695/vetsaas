<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Cita;
use App\Models\GroomingTurno;
use App\Models\HotelEstancia;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Services\Agenda\AgendaOwnerRsvpService;
use App\Services\ClinicBot\ClinicBotClientResolver;
use App\Support\Agenda\AgendaRsvpIntent;
use App\Support\Tenancy\ActiveTenantIterator;
use Illuminate\Console\Command;

/**
 * Diagnóstico de SI/NO por WhatsApp: ¿llega el teléfono? ¿hay cita en espera?
 */
class AgendaRsvpProbeCommand extends Command
{
    protected $signature = 'vetsaas:agenda-rsvp-probe
                            {phone : Teléfono o chatId (ej. 51999999999 o 999999999)}
                            {--body=SI : Texto a simular}
                            {--apply : Confirma o cancela de verdad (igual que el webhook)}';

    protected $description = 'Prueba SI/NO de agenda contra todas las clínicas (sin WhatsApp)';

    public function handle(ActiveTenantIterator $tenants, ClinicBotClientResolver $clients): int
    {
        $phone = trim((string) $this->argument('phone'));
        $body = (string) $this->option('body');
        $apply = (bool) $this->option('apply');

        $intent = AgendaRsvpIntent::parse($body);
        $this->line('Texto: '.$body);
        $this->line('Intent: '.($intent ?? 'null (no se interpreta como SI/NO)'));

        if ($intent === null) {
            return self::FAILURE;
        }

        $hits = 0;

        $tenants->each(function (Tenant $tenant) use ($clients, $phone, $body, $apply, $intent, &$hits): void {
            $slug = (string) $tenant->slug;
            $prop = $clients->findPropietarioByPhone($phone);
            if ($prop === null) {
                return;
            }

            $hits++;
            $this->newLine();
            $this->info("Clínica: {$slug}");
            $this->line('  propietario: '.trim($prop->nombres.' '.($prop->apellidos ?? '')).' ('.$prop->id.')');
            $this->line('  telefono ficha: '.($prop->telefono ?? '—'));

            $pacienteIds = Paciente::query()
                ->where('propietario_id', $prop->id)
                ->pluck('id');

            $citas = Cita::query()
                ->whereIn('paciente_id', $pacienteIds)
                ->whereIn('estado', [Cita::ESTADO_PROGRAMADA, Cita::ESTADO_CONFIRMADA])
                ->where('inicio_at', '>=', now()->subHours(2))
                ->orderBy('inicio_at')
                ->get(['id', 'inicio_at', 'estado', 'motivo']);

            $this->line('  citas próximas (programada/confirmada): '.$citas->count());
            foreach ($citas as $cita) {
                $this->line(sprintf(
                    '    - %s  %s  %s  %s',
                    $cita->inicio_at?->timezone((string) config('app.timezone'))->format('d/m/Y H:i'),
                    $cita->estado,
                    $cita->id,
                    (string) ($cita->motivo ?? ''),
                ));
            }

            $grooming = GroomingTurno::query()
                ->whereIn('paciente_id', $pacienteIds)
                ->whereIn('estado', [GroomingTurno::ESTADO_PROGRAMADA, GroomingTurno::ESTADO_CONFIRMADA])
                ->where('inicio_at', '>=', now()->subHours(2))
                ->count();
            $hotel = HotelEstancia::query()
                ->whereIn('paciente_id', $pacienteIds)
                ->whereIn('estado', [HotelEstancia::ESTADO_PROGRAMADA, HotelEstancia::ESTADO_CONFIRMADA])
                ->where('ingreso_at', '>=', now()->subHours(2))
                ->count();
            $this->line("  grooming pendientes: {$grooming}  hotel pendientes: {$hotel}");

            if (! $apply) {
                $this->comment('  (sin --apply: no se cambia estado)');

                return;
            }

            $result = app(AgendaOwnerRsvpService::class)->tryHandle($phone, $body);
            if ($result === null) {
                $this->warn('  --apply: tryHandle devolvió null (no confirmó). Intent='.$intent);

                return;
            }

            $this->info('  --apply: '.$result['kind'].' '.$result['id'].' → '.$result['intent']);
            $this->line('  respuesta: '.$result['reply']);
        });

        if ($hits === 0) {
            $this->newLine();
            $this->error('Ninguna clínica tiene un propietario activo con ese teléfono.');
            $this->line('Revisa la ficha del titular (teléfono) vs el número desde el que escribes en WhatsApp.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line("Clínicas con ese teléfono: {$hits}");

        return self::SUCCESS;
    }
}
