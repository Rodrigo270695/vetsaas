import { Link, usePage } from '@inertiajs/react';
import { Building2, MapPin } from 'lucide-react';
import { Button } from '@/components/ui/button';

type LocationGate = {
    needs_sede: boolean;
    needs_sede_geo: boolean;
    needs_gps: boolean;
    gps_captured: boolean;
    can_edit_sedes: boolean;
    sedes_url: string;
};

/**
 * Banner bloqueante suave: falta crear sede o completar geo obligatoria.
 */
export function ClinicSedeLocationBanner() {
    const page = usePage<{ clinic_location_gate?: LocationGate | null }>();
    const gate = page.props.clinic_location_gate;

    if (!gate || (!gate.needs_sede && !gate.needs_sede_geo)) {
        return null;
    }

    const title = gate.needs_sede
        ? 'Configura tu primera sede'
        : 'Completa la ubicación de tu sede';
    const body = gate.needs_sede
        ? 'Antes de usar caja, citas e inventario debes crear una sede con departamento, provincia y distrito.'
        : 'Tus sedes activas deben tener departamento, provincia y distrito. Es obligatorio para continuar.';

    return (
        <div className="border-b border-amber-300/60 bg-amber-50 px-4 py-3 text-amber-950 dark:border-amber-800/50 dark:bg-amber-950/40 dark:text-amber-50">
            <div className="mx-auto flex max-w-6xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex min-w-0 items-start gap-3">
                    <div className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">
                        {gate.needs_sede ? (
                            <Building2 className="size-4" aria-hidden />
                        ) : (
                            <MapPin className="size-4" aria-hidden />
                        )}
                    </div>
                    <div className="min-w-0">
                        <p className="text-sm font-semibold">{title}</p>
                        <p className="mt-0.5 text-xs text-amber-900/80 dark:text-amber-100/80">
                            {body}
                        </p>
                    </div>
                </div>
                {gate.can_edit_sedes ? (
                    <Button asChild size="sm" className="shrink-0">
                        <Link href={gate.sedes_url}>
                            {gate.needs_sede ? 'Crear sede' : 'Completar ubicación'}
                        </Link>
                    </Button>
                ) : null}
            </div>
        </div>
    );
}
