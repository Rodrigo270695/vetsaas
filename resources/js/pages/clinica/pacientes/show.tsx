import { Head, Link, usePage } from '@inertiajs/react';
import { CalendarDays, FolderOpen, History } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { GroomingFormModal } from '@/pages/servicios/grooming/components/grooming-form-modal';
import type {
    GroomingServicioGrupo,
    GroomingServicioRow,
    PacienteGroomingOpcion,
    SedeGroomingOpcion,
    UsuarioGroomingOpcion,
} from '@/pages/servicios/grooming/types';
import { HotelFormModal } from '@/pages/servicios/hotel/components/hotel-form-modal';
import type {
    HotelTipoGrupo,
    HotelTipoRow,
    PacienteHotelOpcion,
    SedeHotelOpcion,
} from '@/pages/servicios/hotel/types';
import { dashboard } from '@/routes';
import clinica from '@/routes/clinica';
import { CitaFormModal } from '../citas/components/cita-form-modal';
import type { PacienteCitaOpcion, SedeCitaOpcion } from '../citas/types';
import { CirugiaFormModal } from '../cirugias/components/cirugia-form-modal';
import type { ConsultaCirugiaOpcion, PacienteCirugiaOpcion, SedeCirugiaOpcion } from '../cirugias/types';
import type { CatalogoOpcion } from '../historias-clinicas/components/consulta-form-modal';
import { ConsultaFormModal } from '../historias-clinicas/components/consulta-form-modal';
import type { ConsultaHistoriaRow, PacienteHistoriaOpcion } from '../historias-clinicas/types';
import { InternamientoFormModal } from '../hospitalizacion/components/internamiento-form-modal';
import type {
    ConsultaHospitalizacionOpcion,
    PacienteHospitalizacionOpcion,
    SedeHospitalizacionOpcion,
} from '../hospitalizacion/types';
import { RecetaFormModal } from '../recetas/components/receta-form-modal';
import type { ConsultaRecetaOpcion, PacienteRecetaOpcion, SedeRecetaOpcion } from '../recetas/types';
import { dateKeyInAppTimezone } from '../historias-clinicas/format-atendido';
import type { Paciente } from '../propietarios/types';
import { VacunaFormModal } from '../vacunaciones/components/vacuna-form-modal';
import type {
    PacienteVacunaOpcion,
    SedeVacunaOpcion,
    ServicioVacunaOpcion,
    VacunaAplicadaRow,
} from '../vacunaciones/types';
import { ClinicalHistoryWhatsAppDialog } from './components/clinical-history-whatsapp-dialog';
import type { ClinicalHistoryShareTarget } from './components/clinical-history-whatsapp-dialog';
import { DocumentoAutorizacionSendDialog } from './components/documento-autorizacion-send-dialog';
import { HistorialArchivoPreview } from './components/historial-archivo-preview';
import { LaboratorioRapidoModal } from './components/laboratorio-rapido-modal';
import { PacienteHistorialHero } from './components/paciente-historial-hero';
import type { HistorialNuevoAccion } from './components/paciente-historial-hero';
import { PacienteTimelineRow } from './components/paciente-timeline-row';
import { ConsultaDeleteDialog } from '../historias-clinicas/components/consulta-delete-dialog';

export type TimelineLabLinea = {
    id: string;
    nombre_examen: string;
    resultado: string | null;
    resultado_at: string | null;
    resultado_archivo_url: string | null;
    resultado_archivo_original_name: string | null;
    archivo_kind?: 'pdf' | 'image' | 'other';
};

export type HistorialArchivoSubido = {
    id: string;
    nombre_examen: string;
    resultado_at: string | null;
    resultado_archivo_url: string | null;
    resultado_archivo_original_name: string | null;
    archivo_kind: 'pdf' | 'image' | 'other';
};

export type TimelineConsultaVinculos = {
    recetas: readonly { id: string; estado: string; lineas_count: number; url: string }[];
    laboratorio: readonly {
        id: string;
        estado: string;
        lineas_count: number;
        url: string;
        lineas: readonly TimelineLabLinea[];
    }[];
    cirugias: readonly { id: string; estado: string; titulo: string; url: string }[];
    internamientos: readonly { id: string; estado: string; titulo: string; url: string }[];
    documentos_autorizacion?: readonly TimelineLabLinea[];
};

export type TimelinePlanLinea = {
    id: string;
    medicamento: string;
    dosis: string | null;
    unidad: string | null;
    via: string | null;
    frecuencia: string | null;
    cantidad: string | null;
    notas: string | null;
};

export type TimelinePlanSeguimiento = {
    id: string;
    registrado_at: string | null;
    nota: string;
    autor: string | null;
};

export type TimelinePlanMedicacion = {
    fecha_inicio: string | null;
    fecha_fin: string | null;
    estado: string;
    indicaciones: string | null;
    lineas: readonly TimelinePlanLinea[];
    seguimientos: readonly TimelinePlanSeguimiento[];
};

export type TimelineConsultaDetalle = {
    peso_kg: string | null;
    temperatura_c: string | null;
    fc_lpm: number | null;
    fr_rpm: number | null;
    subjetivo: string | null;
    objetivo: string | null;
    analisis: string | null;
    plan: string | null;
    examenes?: readonly string[];
    motivo?: string | null;
    anotaciones?: string | null;
    medico_tratante?: string | null;
    plan_medicacion?: TimelinePlanMedicacion | null;
    vinculos: TimelineConsultaVinculos;
};

export type TimelineCobroVenta = {
    id: string;
    numero: string;
    total: string;
    tipo_comprobante_sunat: 0 | 1 | 2;
    show_url: string | null;
    ticket_url: string | null;
    fel_pdf_url: string | null;
    fel_numero: string | null;
};

export type TimelineCobro = {
    estado: 'cobrado';
    ventas: readonly TimelineCobroVenta[];
};

export type TimelineAplicacionDetalle = {
    producto_nombre: string | null | undefined;
    producto_sku: string | null | undefined;
    lote: string | null;
    numero_dosis: number | null;
    fecha_proxima_sugerida: string | null;
    esquema_antigenos: string | null;
    notas: string | null;
};

export type TimelineEventKind =
    | 'laboratorio'
    | 'cirugia'
    | 'internamiento'
    | 'grooming'
    | 'hotel';

export type TimelineEventItem = {
    kind: TimelineEventKind;
    id: string;
    ocurrido_at: string;
    titulo: string;
    estado: string;
    href: string;
    detalle_corto?: string | null;
    archivos?: TimelineLabLinea[];
};

export type TimelineItem =
    | {
          kind: 'consulta';
          id: string;
          ocurrido_at: string;
          titulo: string;
          cerrada: boolean;
          veterinario: string | null | undefined;
          historia_url: string;
          form_url?: string;
          pdf_url: string;
          whatsapp_url: string;
          cobro?: TimelineCobro | null;
          detalle: TimelineConsultaDetalle;
      }
    | {
          kind: 'aplicacion';
          id: string;
          ocurrido_at: string;
          titulo: string;
          categoria: string;
          consulta_id: string | null;
          veterinario: string | null | undefined;
          vacunaciones_url: string;
          pdf_url: string;
          can_edit?: boolean;
          cobro?: TimelineCobro | null;
          registro?: VacunaAplicadaRow;
          detalle: TimelineAplicacionDetalle;
      }
    | TimelineEventItem;

export function timelineLane(kind: TimelineItem['kind']): 'clinico' | 'servicio' {
    return kind === 'grooming' || kind === 'hotel' ? 'servicio' : 'clinico';
}

type Props = {
    paciente: Paciente;
    timeline: readonly TimelineItem[];
    consultas_para_lab?: readonly { id: string; label: string; abierta: boolean }[];
    archivos_subidos?: readonly HistorialArchivoSubido[];
    pacientes_opciones?: readonly PacienteVacunaOpcion[] | readonly PacienteHistoriaOpcion[];
    sedes_opciones?: readonly SedeVacunaOpcion[];
    servicios_vacuna_opciones?: readonly ServicioVacunaOpcion[];
    servicios_clinicos_opciones?: readonly CatalogoOpcion[];
    farmacos_opciones?: readonly CatalogoOpcion[];
    consultas_opciones?: readonly ConsultaRecetaOpcion[];
    usuarios_opciones?: readonly UsuarioGroomingOpcion[];
    grooming_nuevo?: {
        catalogo_personalizado: boolean;
        servicios: readonly GroomingServicioRow[];
        grupos: readonly GroomingServicioGrupo[];
        duraciones: Readonly<Record<string, number>>;
    } | null;
    hotel_nuevo?: {
        catalogo_personalizado: boolean;
        tipos: readonly HotelTipoRow[];
        grupos: readonly HotelTipoGrupo[];
    } | null;
    medico_tratante_default?: string;
    links: {
        nueva_consulta: string;
        nueva_aplicacion: string;
        historial_pdf: string | null;
        historial_whatsapp: string | null;
        laboratorio_rapido: string | null;
    };
    permisos: {
        consultas_ver: boolean;
        consultas_crear: boolean;
        consultas_editar?: boolean;
        vacunas_ver: boolean;
        vacunas_crear: boolean;
        vacunas_editar?: boolean;
        laboratorio_crear: boolean;
        laboratorio_eliminar?: boolean;
        consultas_eliminar?: boolean;
        citas_crear?: boolean;
        autorizacion_enviar?: boolean;
    };
    plantillas_autorizacion?: readonly { id: string; nombre: string; descripcion: string | null }[];
};
function readCsrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export default function PacienteShow({
    paciente,
    timeline,
    consultas_para_lab = [],
    archivos_subidos = [],
    pacientes_opciones = [],
    sedes_opciones = [],
    servicios_vacuna_opciones = [],
    servicios_clinicos_opciones = [],
    farmacos_opciones = [],
    consultas_opciones = [],
    usuarios_opciones = [],
    grooming_nuevo = null,
    hotel_nuevo = null,
    medico_tratante_default = '',
    links,
    permisos,
    plantillas_autorizacion = [],
}: Props) {
    const { t } = useTranslation(['pacientes', 'common']);
    const { timezone: appTz } = usePage().props;
    const [timelineLaneFilter, setTimelineLaneFilter] = useState<
        'todo' | 'clinico' | 'servicio'
    >('todo');
    const [shareTarget, setShareTarget] = useState<ClinicalHistoryShareTarget>(null);
    const [labOpen, setLabOpen] = useState(false);
    const [labPrefillConsultaId, setLabPrefillConsultaId] = useState<string | null>(
        null,
    );
    const [vacunaEdit, setVacunaEdit] = useState<VacunaAplicadaRow | null>(null);
    const [vacunaCreateOpen, setVacunaCreateOpen] = useState(false);
    const [consultaEdit, setConsultaEdit] = useState<ConsultaHistoriaRow | null>(null);
    const [consultaCreateOpen, setConsultaCreateOpen] = useState(false);
    const [consultaLoadingId, setConsultaLoadingId] = useState<string | null>(null);
    const [consultaToDelete, setConsultaToDelete] = useState<{ id: string } | null>(null);
    const [citaOpen, setCitaOpen] = useState(false);
    const [recetaOpen, setRecetaOpen] = useState(false);
    const [cirugiaOpen, setCirugiaOpen] = useState(false);
    const [hospitalOpen, setHospitalOpen] = useState(false);
    const [groomingOpen, setGroomingOpen] = useState(false);
    const [hotelOpen, setHotelOpen] = useState(false);
    const [autorizacionConsultaId, setAutorizacionConsultaId] = useState<string | null>(null);

    const openLaboratorio = (consultaId: string | null = null) => {
        setLabPrefillConsultaId(consultaId);
        setLabOpen(true);
    };

    const pacientesCitaOpciones = useMemo((): readonly PacienteCitaOpcion[] => {
        const fromProps = pacientes_opciones as readonly PacienteCitaOpcion[];
        if (fromProps.some((p) => p.id === paciente.id)) {
            return fromProps;
        }

        const prop = paciente.propietario;

        return [
            {
                id: paciente.id,
                nombre: paciente.nombre,
                propietario: prop
                    ? {
                          id: prop.id,
                          nombres: prop.nombres,
                          apellidos: prop.apellidos,
                          razon_social: prop.razon_social,
                      }
                    : undefined,
            },
            ...fromProps,
        ];
    }, [paciente, pacientes_opciones]);

    const sedesCitaOpciones = sedes_opciones as readonly SedeCitaOpcion[];

    const title = useMemo(() => `${paciente.nombre} · ${t('historial.title_suffix')}`, [paciente.nombre, t]);

    const propietarioNombre = useMemo(() => {
        const p = paciente.propietario;

        if (!p) {
            return '—';
        }

        if (p.razon_social) {
            return p.razon_social;
        }

        return [p.nombres, p.apellidos].filter(Boolean).join(' ') || '—';
    }, [paciente.propietario]);

    const visibleTimeline = useMemo(() => {
        if (timelineLaneFilter === 'todo') {
            return timeline;
        }

        return timeline.filter(
            (item) => timelineLane(item.kind) === timelineLaneFilter,
        );
    }, [timeline, timelineLaneFilter]);

    const hasClinico = useMemo(
        () => timeline.some((item) => timelineLane(item.kind) === 'clinico'),
        [timeline],
    );
    const hasServicio = useMemo(
        () => timeline.some((item) => timelineLane(item.kind) === 'servicio'),
        [timeline],
    );

    const timelineStats = useMemo(
        () => ({
            consultas: timeline.filter((i) => i.kind === 'consulta').length,
            aplicaciones: timeline.filter((i) => i.kind === 'aplicacion').length,
            servicios: timeline.filter((i) => timelineLane(i.kind) === 'servicio')
                .length,
            total: timeline.length,
        }),
        [timeline],
    );

    const timelineDateHeaders = useMemo(() => {
        const tz = appTz ?? 'UTC';
        let prevDayKey = '';

        return visibleTimeline.map((item) => {
            const dayKey = dateKeyInAppTimezone(item.ocurrido_at, tz);
            const isNewDay = dayKey !== prevDayKey;
            prevDayKey = dayKey;
            return isNewDay;
        });
    }, [visibleTimeline, appTz]);

    const openVacunaRegistro = useCallback((item: Extract<TimelineItem, { kind: 'aplicacion' }>) => {
        if (item.registro) {
            setVacunaCreateOpen(false);
            setVacunaEdit(item.registro);
        }
    }, []);

    const openConsultaRegistro = useCallback(
        async (item: Extract<TimelineItem, { kind: 'consulta' }>) => {
            if (!item.form_url) {
                return;
            }

            setConsultaLoadingId(item.id);

            try {
                const res = await fetch(item.form_url, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': readCsrfToken(),
                    },
                    credentials: 'same-origin',
                });

                if (!res.ok) {
                    return;
                }

                const payload = (await res.json()) as { consulta?: ConsultaHistoriaRow };
                if (payload.consulta) {
                    setConsultaEdit(payload.consulta);
                }
            } finally {
                setConsultaLoadingId(null);
            }
        },
        [],
    );

    return (
        <>
            <Head title={title} />
            <div className="flex flex-1 flex-col gap-4 p-4 sm:gap-5 sm:p-6">
                <PacienteHistorialHero
                    paciente={paciente}
                    propietarioNombre={propietarioNombre}
                    links={links}
                    permisos={permisos}
                    timelineStats={timelineStats}
                    hasTimeline={timeline.length > 0}
                    onShareHistory={() => {
                        if (links.historial_whatsapp) {
                            setShareTarget({
                                url: links.historial_whatsapp,
                                label: t('historial.document_general'),
                            });
                        }
                    }}
                    onNuevo={(accion: HistorialNuevoAccion) => {
                        if (accion === 'consulta') {
                            setConsultaEdit(null);
                            setConsultaCreateOpen(true);
                            return;
                        }

                        if (accion === 'cita') {
                            setCitaOpen(true);
                            return;
                        }

                        if (accion === 'vacuna') {
                            setVacunaEdit(null);
                            setVacunaCreateOpen(true);
                            return;
                        }

                        if (accion === 'receta') {
                            setRecetaOpen(true);
                            return;
                        }

                        if (accion === 'cirugia') {
                            setCirugiaOpen(true);
                            return;
                        }

                        if (accion === 'hospitalizacion') {
                            setHospitalOpen(true);
                            return;
                        }

                        if (accion === 'grooming') {
                            setGroomingOpen(true);
                            return;
                        }

                        if (accion === 'hotel') {
                            setHotelOpen(true);
                            return;
                        }

                        openLaboratorio(null);
                    }}
                />

                {archivos_subidos.length > 0 ? (
                    <section className="overflow-hidden rounded-2xl border border-border/70 bg-card shadow-sm ring-1 ring-black/[0.03] dark:ring-white/5">
                        <header className="flex flex-col gap-2 border-b border-border/50 bg-muted/20 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                            <div className="flex items-center gap-2.5">
                                <span className="flex size-9 items-center justify-center rounded-xl bg-sky-500/12 text-sky-700 dark:text-sky-200">
                                    <FolderOpen className="size-4" strokeWidth={2.25} />
                                </span>
                                <div>
                                    <h2 className="text-base font-semibold text-foreground">
                                        {t('historial.archivos_subidos_title')}
                                    </h2>
                                    <p className="text-xs text-muted-foreground">
                                        {t('historial.archivos_subidos_hint')}
                                    </p>
                                </div>
                            </div>
                        </header>
                        <div className="flex flex-wrap gap-2 p-3 sm:p-4">
                            {archivos_subidos.map((archivo) => (
                                <HistorialArchivoPreview
                                    key={archivo.id}
                                    archivo={archivo}
                                    canDelete={Boolean(permisos.laboratorio_eliminar)}
                                />
                            ))}
                        </div>
                    </section>
                    ) : null}

                <section className="overflow-hidden rounded-2xl border border-border/70 bg-card shadow-sm ring-1 ring-black/[0.03] dark:ring-white/5">
                    <header className="flex flex-col gap-3 border-b border-border/50 bg-muted/20 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                        <div className="flex items-center gap-2.5">
                            <span className="flex size-9 items-center justify-center rounded-xl bg-primary/12 text-primary">
                                <CalendarDays className="size-4" strokeWidth={2.25} />
                            </span>
                            <div>
                                <h2 className="text-base font-semibold text-foreground">{t('historial.timeline_title')}</h2>
                                <p className="text-xs text-muted-foreground">{t('historial.timeline_hint')}</p>
                            </div>
                        </div>
                        {hasClinico || hasServicio ? (
                            <div className="flex flex-wrap gap-1.5">
                                {(
                                    [
                                        { id: 'todo' as const, show: true },
                                        { id: 'clinico' as const, show: hasClinico },
                                        { id: 'servicio' as const, show: hasServicio },
                                    ] as const
                                )
                                    .filter((opt) => opt.show)
                                    .map((opt) => (
                                        <Button
                                            key={opt.id}
                                            type="button"
                                            size="sm"
                                            variant={timelineLaneFilter === opt.id ? 'default' : 'outline'}
                                            className={cn(
                                                'h-8 cursor-pointer px-3 text-xs',
                                                timelineLaneFilter !== opt.id && 'bg-background',
                                            )}
                                            onClick={() => setTimelineLaneFilter(opt.id)}
                                        >
                                            {t(`historial.lane_${opt.id}`)}
                                        </Button>
                                    ))}
                            </div>
                        ) : null}
                    </header>

                    <div className="p-4 sm:p-5">
                        {timeline.length === 0 ? (
                            <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border/80 bg-muted/15 px-6 py-12 text-center">
                                <span className="flex size-14 items-center justify-center rounded-2xl bg-muted/50 text-muted-foreground">
                                    <History className="size-7 opacity-60" strokeWidth={1.75} />
                                </span>
                                <p className="max-w-md text-sm text-muted-foreground">{t('historial.timeline_empty')}</p>
                            </div>
                        ) : visibleTimeline.length === 0 ? (
                            <p className="text-sm text-muted-foreground">{t('historial.timeline_empty_filter')}</p>
                        ) : (
                            <ul className="relative m-0 list-none p-0">
                                {visibleTimeline.map((item, index) => (
                                    <PacienteTimelineRow
                                        key={`${item.kind}-${item.id}`}
                                        item={item}
                                        index={index}
                                        showDateHeader={timelineDateHeaders[index]}
                                        appTz={appTz}
                                        permisos={permisos}
                                        isLast={index === visibleTimeline.length - 1}
                                        consultaOpeningId={consultaLoadingId}
                                        onOpenConsulta={openConsultaRegistro}
                                        onOpenAplicacion={openVacunaRegistro}
                                        onShareConsulta={(consulta) =>
                                            setShareTarget({
                                                url: consulta.whatsapp_url,
                                                label: t('historial.document_consulta'),
                                            })
                                        }
                                        onUploadLaboratorio={
                                            permisos.laboratorio_crear &&
                                            links.laboratorio_rapido
                                                ? (consultaId) =>
                                                      openLaboratorio(consultaId)
                                                : undefined
                                        }
                                        onDeleteConsulta={
                                            permisos.consultas_eliminar
                                                ? (consulta) =>
                                                      setConsultaToDelete({ id: consulta.id })
                                                : undefined
                                        }
                                        onSendAutorizacion={
                                            permisos.autorizacion_enviar
                                                ? (consultaId) =>
                                                      setAutorizacionConsultaId(consultaId)
                                                : undefined
                                        }
                                    />
                                ))}
                            </ul>
                        )}
                    </div>
                </section>

                <Can permission="propietarios.view">
                    <p className="text-center text-xs text-muted-foreground sm:text-left">
                        <Link
                            href={clinica.propietarios.show.url({ propietario: paciente.propietario_id })}
                            className="font-medium text-primary underline-offset-4 hover:underline"
                        >
                            {t('historial.ver_titular')}
                        </Link>
                    </p>
                </Can>
            </div>

            <ClinicalHistoryWhatsAppDialog
                target={shareTarget}
                defaultPhone={paciente.propietario?.telefono ?? ''}
                onOpenChange={(open) => {
                    if (!open) {
                        setShareTarget(null);
                    }
                }}
            />

            <DocumentoAutorizacionSendDialog
                open={autorizacionConsultaId !== null}
                consultaId={autorizacionConsultaId}
                plantillas={plantillas_autorizacion}
                defaultPhone={paciente.propietario?.telefono ?? ''}
                defaultEmail={paciente.propietario?.email ?? ''}
                onOpenChange={(open) => {
                    if (!open) {
                        setAutorizacionConsultaId(null);
                    }
                }}
            />

            <ConsultaDeleteDialog
                open={consultaToDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setConsultaToDelete(null);
                    }
                }}
                consulta={consultaToDelete}
            />

            {links.laboratorio_rapido ? (
                <LaboratorioRapidoModal
                    open={labOpen}
                    onOpenChange={(open) => {
                        setLabOpen(open);
                        if (!open) {
                            setLabPrefillConsultaId(null);
                        }
                    }}
                    storeUrl={links.laboratorio_rapido}
                    consultas={consultas_para_lab}
                    prefillConsultaId={labPrefillConsultaId}
                />
            ) : null}

            <VacunaFormModal
                open={vacunaEdit !== null || vacunaCreateOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setVacunaEdit(null);
                        setVacunaCreateOpen(false);
                    }
                }}
                vacuna={vacunaEdit}
                pacientesOpciones={pacientes_opciones as readonly PacienteVacunaOpcion[]}
                sedesOpciones={sedes_opciones}
                serviciosVacunaOpciones={servicios_vacuna_opciones}
                prefillCreate={
                    vacunaCreateOpen
                        ? { paciente_id: paciente.id, consulta_id: null }
                        : null
                }
            />

            <ConsultaFormModal
                open={consultaEdit !== null || consultaCreateOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setConsultaEdit(null);
                        setConsultaCreateOpen(false);
                    }
                }}
                consulta={consultaEdit}
                pacientesOpciones={pacientes_opciones as readonly PacienteHistoriaOpcion[]}
                serviciosClinicosOpciones={servicios_clinicos_opciones}
                farmacosOpciones={farmacos_opciones}
                medicoTratanteDefault={medico_tratante_default}
                pacienteIdPrefillNueva={
                    consultaCreateOpen && consultaEdit === null ? paciente.id : null
                }
                puedeCerrarConsulta={Boolean(permisos.consultas_editar)}
            />

            <RecetaFormModal
                open={recetaOpen}
                onOpenChange={setRecetaOpen}
                receta={null}
                prefillPacienteId={paciente.id}
                pacientesOpciones={pacientes_opciones as readonly PacienteRecetaOpcion[]}
                sedesOpciones={sedes_opciones as readonly SedeRecetaOpcion[]}
                consultasOpciones={consultas_opciones}
            />

            <CirugiaFormModal
                open={cirugiaOpen}
                onOpenChange={setCirugiaOpen}
                cirugia={null}
                prefillPacienteId={paciente.id}
                pacientesOpciones={pacientes_opciones as readonly PacienteCirugiaOpcion[]}
                sedesOpciones={sedes_opciones as readonly SedeCirugiaOpcion[]}
                consultasOpciones={consultas_opciones as readonly ConsultaCirugiaOpcion[]}
            />

            <InternamientoFormModal
                open={hospitalOpen}
                onOpenChange={setHospitalOpen}
                internamiento={null}
                prefillPacienteId={paciente.id}
                pacientesOpciones={pacientes_opciones as readonly PacienteHospitalizacionOpcion[]}
                sedesOpciones={sedes_opciones as readonly SedeHospitalizacionOpcion[]}
                consultasOpciones={consultas_opciones as readonly ConsultaHospitalizacionOpcion[]}
            />

            {grooming_nuevo ? (
                <GroomingFormModal
                    open={groomingOpen}
                    onOpenChange={setGroomingOpen}
                    turno={null}
                    catalogoPersonalizado={grooming_nuevo.catalogo_personalizado}
                    serviciosOpciones={grooming_nuevo.servicios}
                    servicioGrupos={grooming_nuevo.grupos}
                    servicioDuraciones={grooming_nuevo.duraciones}
                    pacientesOpciones={pacientes_opciones as readonly PacienteGroomingOpcion[]}
                    usuariosOpciones={usuarios_opciones}
                    sedesOpciones={sedes_opciones as readonly SedeGroomingOpcion[]}
                    prefill={{ paciente_id: paciente.id }}
                />
            ) : null}

            {hotel_nuevo ? (
                <HotelFormModal
                    open={hotelOpen}
                    onOpenChange={setHotelOpen}
                    estancia={null}
                    catalogoPersonalizado={hotel_nuevo.catalogo_personalizado}
                    hotelTipos={hotel_nuevo.tipos}
                    tipoGrupos={hotel_nuevo.grupos}
                    pacientesOpciones={pacientes_opciones as readonly PacienteHotelOpcion[]}
                    sedesOpciones={sedes_opciones as readonly SedeHotelOpcion[]}
                    prefill={{ paciente_id: paciente.id }}
                />
            ) : null}

            {permisos.citas_crear ? (
                <CitaFormModal
                    open={citaOpen}
                    onOpenChange={setCitaOpen}
                    cita={null}
                    prefill={{
                        paciente_id: paciente.id,
                        lockPaciente: true,
                    }}
                    pacientesOpciones={pacientesCitaOpciones}
                    sedesOpciones={sedesCitaOpciones}
                />
            ) : null}
        </>
    );
}

PacienteShow.layout = {
    breadcrumbs: [
        { title: 'Clínica', href: dashboard().url },
        { title: 'Pacientes', href: clinica.pacientes.index().url },
        { title: 'Historial', href: '#' },
    ],
};
