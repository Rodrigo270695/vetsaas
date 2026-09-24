import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, BedDouble, ClipboardPlus, Stethoscope } from 'lucide-react';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PacienteHcLink } from '@/components/clinica/paciente-hc-link';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermission } from '@/hooks/use-permission';
import { dashboard } from '@/routes';
import { ConstantesFisiologicas } from './components/constantes-fisiologicas';
import { EvolucionDeleteDialog } from './components/evolucion-delete-dialog';
import { EvolucionFormModal } from './components/evolucion-form-modal';
import { SignoDeleteDialog } from './components/signo-delete-dialog';
import { SignoFormModal } from './components/signo-form-modal';
import { SignosClinicos } from './components/signos-clinicos';
import type { InternamientoEvolucionRow, InternamientoShow, InternamientoSignoRow, UsuarioHospitalizacionOpcion } from './types';

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

type SignoModal =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; signo: InternamientoSignoRow }
    | { type: 'delete'; signo: InternamientoSignoRow };

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
    const [signoModal, setSignoModal] = useState<SignoModal>({ type: 'idle' });
    const closeEvo = useCallback(() => setEvoModal({ type: 'idle' }), []);
    const closeSigno = useCallback(() => setSignoModal({ type: 'idle' }), []);

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
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-3 space-y-0 pb-3">
                        <CardTitle className="text-base">{t('show.section_constantes')}</CardTitle>
                        {canUpdate ? (
                            <Button
                                type="button"
                                size="sm"
                                className="cursor-pointer gap-2 shadow-sm transition-transform duration-200 active:scale-[0.98]"
                                onClick={() => setEvoModal({ type: 'create' })}
                            >
                                <ClipboardPlus className="size-4" strokeWidth={2.5} />
                                {t('show.constantes_add')}
                            </Button>
                        ) : null}
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

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-3 space-y-0 pb-3">
                        <CardTitle className="text-base">{t('show.section_signos')}</CardTitle>
                        {canUpdate ? (
                            <Button
                                type="button"
                                size="sm"
                                className="cursor-pointer gap-2 shadow-sm transition-transform duration-200 active:scale-[0.98]"
                                onClick={() => setSignoModal({ type: 'create' })}
                            >
                                <Stethoscope className="size-4" strokeWidth={2.5} />
                                {t('show.signos_add')}
                            </Button>
                        ) : null}
                    </CardHeader>
                    <CardContent>
                        <SignosClinicos
                            signos={internamiento.signos_clinicos ?? []}
                            timeZone={appTz}
                            canUpdate={canUpdate}
                            onEdit={(signo) => setSignoModal({ type: 'edit', signo })}
                            onDelete={(signo) => setSignoModal({ type: 'delete', signo })}
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

            <SignoFormModal
                open={signoModal.type === 'create' || signoModal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeSigno();
                    }
                }}
                internamientoId={internamiento.id}
                signo={signoModal.type === 'edit' ? signoModal.signo : null}
            />

            <SignoDeleteDialog
                open={signoModal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeSigno();
                    }
                }}
                internamientoId={internamiento.id}
                signo={signoModal.type === 'delete' ? signoModal.signo : null}
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
