import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, BedDouble, ClipboardPlus } from 'lucide-react';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PacienteHcLink } from '@/components/clinica/paciente-hc-link';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermission } from '@/hooks/use-permission';
import { dashboard } from '@/routes';
import { ConstantesFisiologicas } from './components/constantes-fisiologicas';
import { EvolucionDeleteDialog } from './components/evolucion-delete-dialog';
import { EvolucionFormModal } from './components/evolucion-form-modal';
import type { InternamientoEvolucionRow, InternamientoShow, UsuarioHospitalizacionOpcion } from './types';

const LIST_URL = '/clinica/hospitalizacion';

type Props = {
    internamiento: InternamientoShow;
    usuarios_opciones: readonly UsuarioHospitalizacionOpcion[];
};

type EvoModal =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; evolucion: InternamientoEvolucionRow }
    | { type: 'delete'; evolucion: InternamientoEvolucionRow };

function displayPropietario(
    p: InternamientoShow['paciente']['propietario'],
): string {
    if (!p) {
        return '—';
    }

    if (p.razon_social) {
        return p.razon_social;
    }

    return [p.nombres, p.apellidos].filter(Boolean).join(' ') || '—';
}

export default function Show({ internamiento }: Props) {
    const { t } = useTranslation(['hospitalizacion', 'common']);
    const { timezone: appTz } = usePage().props;
    const { can } = usePermission();
    const canUpdate = can('hospitalizacion.update');

    const [evoModal, setEvoModal] = useState<EvoModal>({ type: 'idle' });
    const closeEvo = useCallback(() => setEvoModal({ type: 'idle' }), []);

    return (
        <>
            <Head title={`${t('title')} · ${internamiento.paciente.nombre}`} />
            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex flex-col gap-2">
                        <Button variant="ghost" size="sm" className="h-8 w-fit gap-1.5 px-2" asChild>
                            <Link href={LIST_URL}>
                                <ArrowLeft className="size-4" strokeWidth={2.25} />
                                {t('show.back')}
                            </Link>
                        </Button>
                        <div className="flex flex-wrap items-center gap-2">
                            <BedDouble className="size-6 text-primary" strokeWidth={2} />
                            <h1 className="text-xl font-semibold tracking-tight">
                                <PacienteHcLink pacienteId={internamiento.paciente.id} className="font-semibold">
                                    {internamiento.paciente.nombre}
                                </PacienteHcLink>
                            </h1>
                            <Badge variant="outline" className="font-normal">
                                {t(`estado.${internamiento.estado}`, { defaultValue: internamiento.estado })}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {displayPropietario(internamiento.paciente.propietario)}
                            {internamiento.ubicacion ? ` · ${internamiento.ubicacion}` : ''}
                        </p>
                        <p className="text-sm font-medium text-foreground">{internamiento.motivo_ingreso}</p>
                    </div>
                    {canUpdate ? (
                        <Button
                            type="button"
                            className="cursor-pointer gap-2 shadow-sm transition-transform duration-200 active:scale-[0.98]"
                            onClick={() => setEvoModal({ type: 'create' })}
                        >
                            <ClipboardPlus className="size-4" strokeWidth={2.5} />
                            {t('show.constantes_add')}
                        </Button>
                    ) : null}
                </div>

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base">{t('show.section_constantes')}</CardTitle>
                        <CardDescription>{t('show.constantes_hint')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ConstantesFisiologicas
                            evoluciones={internamiento.evoluciones}
                            timeZone={appTz}
                            canUpdate={canUpdate}
                            onEdit={(evolucion) => setEvoModal({ type: 'edit', evolucion })}
                            onDelete={(evolucion) => setEvoModal({ type: 'delete', evolucion })}
                        />
                    </CardContent>
                </Card>
            </div>

            <EvolucionFormModal
                open={evoModal.type === 'create' || evoModal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeEvo();
                    }
                }}
                internamientoId={internamiento.id}
                evolucion={evoModal.type === 'edit' ? evoModal.evolucion : null}
            />

            <EvolucionDeleteDialog
                open={evoModal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeEvo();
                    }
                }}
                internamientoId={internamiento.id}
                evolucion={evoModal.type === 'delete' ? evoModal.evolucion : null}
            />
        </>
    );
}

Show.layout = {
    breadcrumbs: [
        { title: 'Clínica', href: dashboard().url },
        { title: 'Hospitalización', href: LIST_URL },
        { title: 'Detalle', href: '#' },
    ],
};
