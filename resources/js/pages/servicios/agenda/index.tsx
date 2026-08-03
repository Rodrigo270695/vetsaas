import { Head, router, usePage } from '@inertiajs/react';
import {
    BedDouble,
    CalendarDays,
    Plus,
    RefreshCw,
    Scissors,
} from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    AgendaMonthCalendar,
    monthRangeFromMes,
    shiftMes,
    type AgendaEvent,
} from '@/components/agenda/agenda-month-calendar';
import { DataToolbar, PageHeader } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { useAutoRefresh } from '@/hooks/use-auto-refresh';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import servicios from '@/routes/servicios';
import { GroomingFormModal } from '../grooming/components/grooming-form-modal';
import type {
    GroomingServicioGrupo,
    GroomingServicioRow,
    PacienteGroomingOpcion,
    SedeGroomingOpcion,
} from '../grooming/types';
import { HotelFormModal } from '../hotel/components/hotel-form-modal';
import type {
    HotelTipoGrupo,
    HotelTipoRow,
    PacienteHotelOpcion,
    SedeHotelOpcion,
} from '../hotel/types';
import { ServicioTipoPickerDialog } from './components/servicio-tipo-picker-dialog';
import type {
    ServicioAgendaCapabilities,
    ServicioAgendaEvento,
    ServicioAgendaFilters,
    ServicioAgendaFormPrefill,
    ServicioAgendaTipo,
} from './types';

type Props = {
    eventos: readonly ServicioAgendaEvento[];
    filters: ServicioAgendaFilters;
    agenda_filtro_ui: { default_mes: string };
    agenda_horario: { hora_inicio: string; hora_fin: string };
    stats: { total: number; grooming: number; hotel: number };
    capabilities: ServicioAgendaCapabilities;
    pacientes_opciones: readonly PacienteGroomingOpcion[];
    sedes_opciones: readonly SedeGroomingOpcion[];
    grooming_catalogo_personalizado: boolean;
    grooming_servicios: readonly GroomingServicioRow[];
    grooming_servicio_grupos: readonly GroomingServicioGrupo[];
    grooming_servicio_duraciones: Readonly<Record<string, number>>;
    hotel_catalogo_personalizado: boolean;
    hotel_tipos: readonly HotelTipoRow[];
    hotel_tipo_grupos: readonly HotelTipoGrupo[];
};

const GROOMING_ACCENT =
    'border-l-emerald-500 bg-emerald-50/90 text-emerald-900 hover:bg-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-100';
const HOTEL_ACCENT =
    'border-l-sky-500 bg-sky-100/90 text-sky-900 hover:bg-sky-100 dark:bg-sky-950/50 dark:text-sky-100';

type ModalState =
    | { type: 'idle' }
    | { type: 'pick'; prefill?: ServicioAgendaFormPrefill }
    | { type: 'grooming'; prefill?: ServicioAgendaFormPrefill }
    | { type: 'hotel'; prefill?: ServicioAgendaFormPrefill };

type AgendaTableExtra = Pick<ServicioAgendaFilters, 'mes'>;

export default function ServiciosAgendaIndex({
    eventos,
    filters,
    agenda_filtro_ui,
    agenda_horario,
    stats,
    capabilities,
    pacientes_opciones,
    sedes_opciones,
    grooming_catalogo_personalizado,
    grooming_servicios,
    grooming_servicio_grupos,
    grooming_servicio_duraciones,
    hotel_catalogo_personalizado,
    hotel_tipos,
    hotel_tipo_grupos,
}: Props) {
    const { t } = useTranslation(['servicios-agenda', 'common']);
    const { timezone: appTz } = usePage().props;
    const { can } = usePermission();

    const mesActivo = filters.mes || agenda_filtro_ui.default_mes;
    const canCreate =
        (capabilities.grooming_create && can('grooming.create')) ||
        (capabilities.hotel_create && can('hotel.create'));

    const { search, setSearch, isLoading, applyFilter } =
        useDataTablePage<AgendaTableExtra>({
            routeUrl: servicios.agenda.url(),
            initialFilters: filters,
            only: ['eventos', 'filters', 'stats', 'agenda_filtro_ui'],
            errorMessage: t('toast.load_error'),
            storageKey: 'vetsaas.servicios-agenda.prefs',
            defaults: {
                per_page: 10,
                sort: null,
                direction: null,
            },
        });

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });

    const {
        secondsSince,
        isRefreshing,
        refresh: refreshNow,
    } = useAutoRefresh({
        only: ['eventos', 'stats'],
        busy: isLoading,
    });

    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);

    const openCreate = useCallback(() => {
        setModal({ type: 'pick' });
    }, []);

    const openCreateOnDay = useCallback((fecha: string, hora?: string) => {
        setModal({ type: 'pick', prefill: { fecha, hora } });
    }, []);

    const onPickTipo = useCallback(
        (tipo: ServicioAgendaTipo) => {
            const prefill = modal.type === 'pick' ? modal.prefill : undefined;
            setModal({ type: tipo, prefill });
        },
        [modal],
    );

    const onSelectEvent = useCallback(
        (event: AgendaEvent) => {
            const raw = eventos.find((e) => e.id === event.id);
            if (!raw) {
                return;
            }

            if (raw.tipo === 'grooming') {
                router.visit(
                    servicios.grooming.url({
                        query: { editar_grooming_turno: raw.id },
                    }),
                );
                return;
            }

            router.visit(
                servicios.hotel.url({
                    query: { editar_hotel_estancia: raw.id },
                }),
            );
        },
        [eventos],
    );

    const agendaEvents = useMemo(
        (): AgendaEvent[] =>
            eventos.map((e) => ({
                id: e.id,
                inicio_at: e.inicio_at,
                title: e.titulo,
                subtitle: [
                    e.tipo === 'grooming' ? t('tipo.grooming') : t('tipo.hotel'),
                    e.subtitulo,
                ]
                    .filter(Boolean)
                    .join(' · '),
                accentClass:
                    e.tipo === 'grooming' ? GROOMING_ACCENT : HOTEL_ACCENT,
            })),
        [eventos, t],
    );

    const legend = useMemo(() => {
        const items = [];
        if (capabilities.grooming) {
            items.push({
                key: 'grooming',
                swatch: 'bg-emerald-500',
                label: t('tipo.grooming'),
            });
        }
        if (capabilities.hotel) {
            items.push({
                key: 'hotel',
                swatch: 'bg-sky-500',
                label: t('tipo.hotel'),
            });
        }
        return items;
    }, [capabilities.grooming, capabilities.hotel, t]);

    const labels = useMemo(
        () => ({
            today: t('calendar.today'),
            prevMonth: t('calendar.prev_month'),
            nextMonth: t('calendar.next_month'),
            pickMonth: t('calendar.pick_month'),
            pickYear: t('calendar.pick_year'),
            more: t('calendar.more'),
            dayAgenda: t('calendar.day_agenda'),
            dayEmpty: t('calendar.day_empty'),
            dayCount: (count: number) => t('calendar.day_count', { count }),
            scheduleDay: t('calendar.schedule_day'),
            scheduleAt: (hour: string) => t('calendar.schedule_at', { hour }),
            clickDayHint: t('calendar.click_day_hint'),
            weekdays: {
                mon: t('calendar.weekdays.mon'),
                tue: t('calendar.weekdays.tue'),
                wed: t('calendar.weekdays.wed'),
                thu: t('calendar.weekdays.thu'),
                fri: t('calendar.weekdays.fri'),
                sat: t('calendar.weekdays.sat'),
                sun: t('calendar.weekdays.sun'),
            },
        }),
        [t],
    );

    const goMes = useCallback(
        (mes: string) => applyFilter({ mes }),
        [applyFilter],
    );

    const prefill =
        modal.type === 'grooming' ||
        modal.type === 'hotel' ||
        modal.type === 'pick'
            ? modal.prefill
            : null;

    return (
        <>
            <Head title={t('title')} />
            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <div data-tour-id="servicios-agenda-header">
                    <PageHeader
                        title={t('title')}
                        description={
                            <span className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span>{t('description')}</span>
                                <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <span
                                        className={`inline-block size-2 rounded-full ${
                                            isRefreshing
                                                ? 'animate-ping bg-amber-400'
                                                : 'bg-emerald-500'
                                        }`}
                                    />
                                    {isRefreshing
                                        ? t('common:auto_refresh.updating')
                                        : t(
                                              'common:auto_refresh.updated_seconds',
                                              {
                                                  seconds: secondsSince,
                                              },
                                          )}
                                    <button
                                        type="button"
                                        onClick={refreshNow}
                                        disabled={isRefreshing || isLoading}
                                        className="ml-1 cursor-pointer rounded p-0.5 hover:text-foreground disabled:opacity-50"
                                        title={t('common:auto_refresh.now')}
                                    >
                                        <RefreshCw
                                            className={`size-3 ${isRefreshing ? 'animate-spin' : ''}`}
                                        />
                                    </button>
                                </span>
                            </span>
                        }
                        stats={[
                            {
                                label: t('stats.total'),
                                value: stats.total,
                                variant: 'info',
                                icon: CalendarDays,
                            },
                            {
                                label: t('stats.grooming'),
                                value: stats.grooming,
                                variant: 'primary',
                                icon: Scissors,
                            },
                            {
                                label: t('stats.hotel'),
                                value: stats.hotel,
                                variant: 'warning',
                                icon: BedDouble,
                            },
                        ]}
                        actions={
                            canCreate ? (
                                <Button
                                    type="button"
                                    onClick={openCreate}
                                    className="cursor-pointer gap-2"
                                >
                                    <Plus className="size-4" />
                                    <span className="hidden sm:inline">
                                        {t('actions.new')}
                                    </span>
                                    <span className="sm:hidden">
                                        {t('actions.new_short')}
                                    </span>
                                </Button>
                            ) : undefined
                        }
                    />
                </div>

                <DataToolbar
                    search={search}
                    onSearchChange={setSearch}
                    isLoading={isLoading}
                    placeholder={t('search_placeholder')}
                />

                <AgendaMonthCalendar
                    events={agendaEvents}
                    mes={mesActivo}
                    timeZone={
                        typeof appTz === 'string' ? appTz : 'America/Lima'
                    }
                    horaInicio={agenda_horario.hora_inicio}
                    horaFin={agenda_horario.hora_fin}
                    isLoading={isLoading || isRefreshing}
                    canCreate={canCreate}
                    legend={legend}
                    labels={labels}
                    onSelectEvent={onSelectEvent}
                    onScheduleDay={openCreateOnDay}
                    onPrevMonth={() => goMes(shiftMes(mesActivo, -1))}
                    onNextMonth={() => goMes(shiftMes(mesActivo, 1))}
                    onJumpToMonth={goMes}
                    onToday={() => {
                        const { desde } = monthRangeFromMes(
                            agenda_filtro_ui.default_mes,
                        );
                        goMes(desde.slice(0, 7));
                    }}
                />
            </div>

            <ServicioTipoPickerDialog
                open={modal.type === 'pick'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                canGrooming={capabilities.grooming_create}
                canHotel={capabilities.hotel_create}
                onPick={onPickTipo}
            />

            <GroomingFormModal
                open={modal.type === 'grooming'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                turno={null}
                catalogoPersonalizado={grooming_catalogo_personalizado}
                serviciosOpciones={grooming_servicios}
                servicioGrupos={grooming_servicio_grupos}
                servicioDuraciones={grooming_servicio_duraciones}
                pacientesOpciones={pacientes_opciones}
                sedesOpciones={sedes_opciones}
                prefill={prefill}
                fromAgenda
            />

            <HotelFormModal
                open={modal.type === 'hotel'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                estancia={null}
                catalogoPersonalizado={hotel_catalogo_personalizado}
                hotelTipos={hotel_tipos}
                tipoGrupos={hotel_tipo_grupos}
                pacientesOpciones={
                    pacientes_opciones as readonly PacienteHotelOpcion[]
                }
                sedesOpciones={sedes_opciones as readonly SedeHotelOpcion[]}
                prefill={prefill}
                fromAgenda
            />
        </>
    );
}

ServiciosAgendaIndex.layout = {
    breadcrumbs: [
        { title: 'Servicios', href: '#' },
        { title: 'Agenda', href: '/servicios/agenda' },
    ],
};
