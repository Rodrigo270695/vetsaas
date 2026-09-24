import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, BedDouble, ClipboardPlus, Loader2, LogOut, Pill, Stethoscope } from 'lucide-react';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PacienteHcLink } from '@/components/clinica/paciente-hc-link';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { usePermission } from '@/hooks/use-permission';
import { dashboard } from '@/routes';
import { formatAtendidoInAppTimezone } from '../historias-clinicas/format-atendido';
import { RecetaFormModal } from '../recetas/components/receta-form-modal';
import type { ConsultaRecetaOpcion, PacienteRecetaOpcion, SedeRecetaOpcion } from '../recetas/types';
import { ConstantesFisiologicas } from './components/constantes-fisiologicas';
import { EvolucionDeleteDialog } from './components/evolucion-delete-dialog';
import { EvolucionFormModal } from './components/evolucion-form-modal';
import { FluidosInternamiento } from './components/fluidos-internamiento';
import { NotasInternamiento } from './components/notas-internamiento';
import { SignoDeleteDialog } from './components/signo-delete-dialog';
import { SignoFormModal } from './components/signo-form-modal';
import { SignosClinicos } from './components/signos-clinicos';
import { TratamientosInternamiento } from './components/tratamientos-internamiento';
import { zonaPeru } from './fecha-clinica';
import type {
    InternamientoEvolucionRow,
    InternamientoShow,
    InternamientoSignoRow,
    RecetaInternamientoRow,
    ServicioTratamientoOpcion,
    UsuarioHospitalizacionOpcion,
} from './types';

const LIST_URL = '/clinica/hospitalizacion';

type Props = {
    internamiento: InternamientoShow;
    usuarios_opciones: readonly UsuarioHospitalizacionOpcion[];
    servicios_tratamiento?: readonly ServicioTratamientoOpcion[];
    puede_receta?: boolean;
    sedes_receta?: readonly SedeRecetaOpcion[];
    consultas_receta?: readonly ConsultaRecetaOpcion[];
    recetas_paciente?: readonly RecetaInternamientoRow[];
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

function pacienteReceta(internamiento: InternamientoShow): PacienteRecetaOpcion {
    const propietario = internamiento.paciente.propietario;

    return {
        id: internamiento.paciente.id,
        nombre: internamiento.paciente.nombre,
        propietario: propietario
            ? {
                  id: propietario.id,
                  nombres: propietario.nombres,
                  apellidos: propietario.apellidos,
                  razon_social: propietario.razon_social,
              }
            : undefined,
    };
}

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

export default function Show({
    internamiento,
    servicios_tratamiento = [],
    puede_receta = false,
    sedes_receta = [],
    consultas_receta = [],
    recetas_paciente = [],
}: Props) {
    const { t, i18n } = useTranslation(['hospitalizacion', 'common', 'recetas']);
    const { timezone: appTz } = usePage().props;
    const { can } = usePermission();
    const canUpdate = can('hospitalizacion.update');

    const [evoModal, setEvoModal] = useState<EvoModal>({ type: 'idle' });
    const [signoModal, setSignoModal] = useState<SignoModal>({ type: 'idle' });
    const [recetaOpen, setRecetaOpen] = useState(false);
    const [altaOpen, setAltaOpen] = useState(false);
    const closeEvo = useCallback(() => setEvoModal({ type: 'idle' }), []);
    const closeSigno = useCallback(() => setSignoModal({ type: 'idle' }), []);
    const altaForm = useForm({});
    const zona = zonaPeru(appTz);

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

                <NotasInternamiento
                    internamientoId={internamiento.id}
                    notas={internamiento.notas_bitacora ?? []}
                    canUpdate={canUpdate}
                    timeZone={appTz}
                />

                <FluidosInternamiento
                    internamientoId={internamiento.id}
                    fluidos={internamiento.fluidos ?? []}
                    canUpdate={canUpdate}
                    timeZone={appTz}
                />

                <TratamientosInternamiento
                    internamientoId={internamiento.id}
                    tratamientos={internamiento.tratamientos ?? []}
                    servicios={servicios_tratamiento}
                    canUpdate={canUpdate}
                    timeZone={appTz}
                />

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-3 space-y-0 pb-3">
                        <CardTitle className="text-base">{t('receta_card.title')}</CardTitle>
                        {puede_receta ? (
                            <Button
                                type="button"
                                size="sm"
                                className="cursor-pointer gap-2"
                                onClick={() => setRecetaOpen(true)}
                            >
                                <Pill className="size-4" strokeWidth={2.25} />
                                {t('receta_card.add')}
                            </Button>
                        ) : null}
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {recetas_paciente.length === 0 ? (
                            <p className="text-sm text-muted-foreground">{t('receta_card.empty')}</p>
                        ) : (
                            recetas_paciente.map((receta) => (
                                <article key={receta.id} className="rounded-lg border border-border/60 px-3 py-2.5">
                                    <p className="text-sm font-medium text-foreground">
                                        {t(`recetas:estado.${receta.estado}`, { defaultValue: receta.estado })}
                                    </p>
                                    {receta.observaciones ? (
                                        <p className="mt-1 text-sm text-muted-foreground">{receta.observaciones}</p>
                                    ) : null}
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {formatAtendidoInAppTimezone(receta.emitida_at, i18n.language, zona)}
                                        {receta.creado_por?.name ? ` · ${receta.creado_por.name}` : ''}
                                    </p>
                                </article>
                            ))
                        )}
                    </CardContent>
                </Card>

                {canUpdate && internamiento.estado !== 'alta' ? (
                    <div className="flex justify-end">
                        <Button
                            type="button"
                            size="lg"
                            className="cursor-pointer gap-2"
                            onClick={() => setAltaOpen(true)}
                        >
                            <LogOut className="size-4" strokeWidth={2.25} />
                            {t('alta.action')}
                        </Button>
                    </div>
                ) : internamiento.alta_at ? (
                    <p className="text-right text-sm text-muted-foreground">
                        {t('alta.ya')} · {formatAtendidoInAppTimezone(internamiento.alta_at, i18n.language, zona)}
                    </p>
                ) : null}
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

            {puede_receta ? (
                <RecetaFormModal
                    open={recetaOpen}
                    onOpenChange={setRecetaOpen}
                    receta={null}
                    prefillPacienteId={internamiento.paciente.id}
                    pacientesOpciones={[pacienteReceta(internamiento)]}
                    sedesOpciones={sedes_receta}
                    consultasOpciones={consultas_receta}
                />
            ) : null}

            <Dialog open={altaOpen} onOpenChange={setAltaOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{t('alta.title')}</DialogTitle>
                        <DialogDescription>{t('alta.description')}</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setAltaOpen(false)} disabled={altaForm.processing}>
                            {t('common:actions.cancel')}
                        </Button>
                        <Button
                            type="button"
                            className="gap-2"
                            disabled={altaForm.processing}
                            onClick={() => {
                                altaForm.post(`/clinica/hospitalizacion/${internamiento.id}/alta`, {
                                    preserveScroll: true,
                                    onSuccess: () => setAltaOpen(false),
                                });
                            }}
                        >
                            {altaForm.processing && <Loader2 className="size-4 animate-spin" />}
                            {t('alta.confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
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
