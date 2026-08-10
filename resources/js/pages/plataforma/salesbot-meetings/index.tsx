import { Head, router } from '@inertiajs/react';
import {
    CalendarDays,
    CheckCircle2,
    ExternalLink,
    MoreHorizontal,
    RotateCcw,
    UserX,
    Video,
    XCircle,
} from 'lucide-react';
import { useMemo, useState, type FormEvent } from 'react';
import {
    DataPagination,
    DataTable,
    DataToolbar,
    EmptyState,
    FilterChips,
    PageHeader,
    StatBadge,
} from '@/components/data-page';
import type { DataTableColumn, FilterChip } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

type MeetStatus = 'proposed' | 'confirmed' | 'completed' | 'no_show' | 'cancelled' | string | null;

type Meeting = {
    id: string;
    phone: string;
    prospect_name: string | null;
    meet_status: MeetStatus;
    meet_at: string | null;
    meet_proposed_at: string | null;
    meet_link: string | null;
    google_event_id: string | null;
    meet_notified_at: string | null;
    meet_completed_at: string | null;
    meet_outcome_note: string | null;
    last_message_at: string | null;
    needs_close: boolean;
};

type CloseStatus = 'completed' | 'no_show' | 'cancelled';

type EstadoFilter =
    | 'confirmadas'
    | 'propuestas'
    | 'proximas'
    | 'por_cerrar'
    | 'realizadas'
    | 'todas';

type Props = {
    meetings: Paginated<Meeting>;
    filters: {
        search: string;
        estado: EstadoFilter;
        per_page: number;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
    stats: {
        confirmadas: number;
        propuestas: number;
        proximas: number;
        por_cerrar: number;
        realizadas: number;
        coincidencias: number;
    };
};

const DEFAULT_PER_PAGE = 15;

const formatWhen = (iso: string | null): string => {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('es-PE', {
        timeZone: 'America/Lima',
        dateStyle: 'medium',
        timeStyle: 'short',
    });
};

const formatPhone = (phone: string): string => {
    const d = phone.replace(/\D/g, '');
    if (d.length === 11 && d.startsWith('51')) {
        return `+51 ${d.slice(2, 3)} ${d.slice(3, 6)} ${d.slice(6, 9)} ${d.slice(9)}`;
    }
    return phone;
};

const isClosed = (status: MeetStatus): boolean =>
    status === 'completed' || status === 'no_show' || status === 'cancelled';

const statusLabel = (row: Meeting): { label: string; variant: 'success' | 'warning' | 'info' | 'default' | 'danger' } => {
    if (row.meet_status === 'completed') {
        return { label: 'Realizada', variant: 'success' };
    }
    if (row.meet_status === 'no_show') {
        return { label: 'No asistió', variant: 'danger' };
    }
    if (row.meet_status === 'cancelled') {
        return { label: 'Cancelada', variant: 'default' };
    }
    if (row.meet_status === 'proposed') {
        return { label: 'Pendiente confirmación', variant: 'warning' };
    }
    if (row.needs_close) {
        return { label: 'Por cerrar', variant: 'warning' };
    }
    if (row.meet_link && (row.meet_status === 'confirmed' || !row.meet_status)) {
        return { label: 'Confirmada', variant: 'success' };
    }

    return { label: row.meet_status || '—', variant: 'default' };
};

const closeTitles: Record<CloseStatus, string> = {
    completed: 'Marcar como realizada',
    no_show: 'Marcar como no asistió',
    cancelled: 'Cancelar reunión',
};

const closeDescriptions: Record<CloseStatus, string> = {
    completed: 'El tour se llevó a cabo. Puedes dejar una nota breve del resultado.',
    no_show: 'La hora llegó y el lead no entró a la reunión.',
    cancelled: 'La reunión no se hará / se canceló.',
};

export default function SalesBotMeetingsIndex({ meetings, filters, stats }: Props) {
    const { search, setSearch, isLoading, setPerPage, applyFilter } = useDataTablePage<{
        estado: EstadoFilter;
    }>({
        routeUrl: '/plataforma/salesbot-meetings',
        initialFilters: filters,
        only: ['meetings', 'filters', 'stats'],
        errorMessage: 'Error al cargar las reuniones',
        storageKey: 'vetsaas.plataforma.salesbot-meetings.prefs',
        defaults: { per_page: DEFAULT_PER_PAGE, sort: null, direction: null },
    });

    const [dialogMeeting, setDialogMeeting] = useState<Meeting | null>(null);
    const [dialogStatus, setDialogStatus] = useState<CloseStatus>('completed');
    const [note, setNote] = useState('');
    const [processing, setProcessing] = useState(false);
    const [formError, setFormError] = useState<string | null>(null);

    const openCloseDialog = (meeting: Meeting, status: CloseStatus): void => {
        setDialogMeeting(meeting);
        setDialogStatus(status);
        setNote(meeting.meet_outcome_note ?? '');
        setFormError(null);
    };

    const submitStatus = (meetingId: string, status: string, outcomeNote: string | null): void => {
        setProcessing(true);
        setFormError(null);
        router.post(
            `/plataforma/salesbot-meetings/${meetingId}/status`,
            { status, note: outcomeNote },
            {
                preserveScroll: true,
                only: ['meetings', 'filters', 'stats'],
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setDialogMeeting(null);
                    setNote('');
                },
                onError: (errs) => {
                    setFormError(errs.status ?? errs.note ?? 'No se pudo actualizar el estado.');
                },
            },
        );
    };

    const onDialogSubmit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        if (!dialogMeeting) return;
        submitStatus(dialogMeeting.id, dialogStatus, note.trim() === '' ? null : note.trim());
    };

    const estadoOptions: FilterChip[] = useMemo(
        () => [
            { value: 'confirmadas', label: `Confirmadas ${stats.confirmadas}` },
            { value: 'propuestas', label: `Propuestas ${stats.propuestas}` },
            { value: 'proximas', label: `Próximas ${stats.proximas}` },
            { value: 'por_cerrar', label: `Por cerrar ${stats.por_cerrar}` },
            { value: 'realizadas', label: `Cerradas ${stats.realizadas}` },
            { value: 'todas', label: 'Todas' },
        ],
        [stats],
    );

    const columns = useMemo<DataTableColumn<Meeting>[]>(
        () => [
            {
                key: 'prospect_name',
                header: 'Lead',
                cell: (row) => (
                    <div className="min-w-0">
                        <p className="truncate font-medium text-foreground">
                            {row.prospect_name || 'Sin nombre'}
                        </p>
                        <p className="font-mono text-xs text-muted-foreground">
                            {formatPhone(row.phone)}
                        </p>
                    </div>
                ),
            },
            {
                key: 'meet_at',
                header: 'Fecha / hora',
                cell: (row) => (
                    <div className="text-sm">
                        <p>{formatWhen(row.meet_at ?? row.meet_proposed_at)}</p>
                        <p className="text-xs text-muted-foreground">Hora Perú</p>
                        {row.meet_completed_at && (
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                Cerrada: {formatWhen(row.meet_completed_at)}
                            </p>
                        )}
                    </div>
                ),
            },
            {
                key: 'meet_status',
                header: 'Estado',
                cell: (row) => {
                    const badge = statusLabel(row);
                    return (
                        <div className="space-y-1">
                            <StatBadge label={badge.label} value="" variant={badge.variant} />
                            {row.meet_outcome_note && (
                                <p className="max-w-56 truncate text-xs text-muted-foreground" title={row.meet_outcome_note}>
                                    {row.meet_outcome_note}
                                </p>
                            )}
                        </div>
                    );
                },
            },
            {
                key: 'meet_link',
                header: 'Meet',
                cell: (row) =>
                    row.meet_link ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="h-8 gap-1.5 text-xs"
                            asChild
                        >
                            <a href={row.meet_link} target="_blank" rel="noreferrer">
                                <Video className="size-3.5" />
                                Abrir
                                <ExternalLink className="size-3" />
                            </a>
                        </Button>
                    ) : (
                        <span className="text-xs text-muted-foreground">Sin link aún</span>
                    ),
            },
            {
                key: 'actions',
                header: 'Acciones',
                cell: (row) => {
                    const canClose = Boolean(row.meet_link) && !isClosed(row.meet_status);
                    const canReopen = isClosed(row.meet_status);

                    if (!canClose && !canReopen) {
                        return <span className="text-xs text-muted-foreground">—</span>;
                    }

                    return (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className={cn(
                                        'size-8',
                                        row.needs_close && 'text-amber-600 dark:text-amber-400',
                                    )}
                                    aria-label="Acciones de reunión"
                                >
                                    <MoreHorizontal className="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-52">
                                {canClose && (
                                    <>
                                        <DropdownMenuItem
                                            className="cursor-pointer gap-2"
                                            onClick={() => openCloseDialog(row, 'completed')}
                                        >
                                            <CheckCircle2 className="size-4" />
                                            Realizada
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            className="cursor-pointer gap-2"
                                            onClick={() => openCloseDialog(row, 'no_show')}
                                        >
                                            <UserX className="size-4" />
                                            No asistió
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            className="cursor-pointer gap-2"
                                            onClick={() => openCloseDialog(row, 'cancelled')}
                                        >
                                            <XCircle className="size-4" />
                                            Cancelar
                                        </DropdownMenuItem>
                                    </>
                                )}
                                {canReopen && (
                                    <>
                                        {canClose && <DropdownMenuSeparator />}
                                        <DropdownMenuItem
                                            className="cursor-pointer gap-2"
                                            onClick={() =>
                                                submitStatus(row.id, 'confirmed', row.meet_outcome_note)
                                            }
                                        >
                                            <RotateCcw className="size-4" />
                                            Reabrir
                                        </DropdownMenuItem>
                                    </>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    );
                },
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Reuniones SalesBot" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Reuniones agendadas"
                    description="Tours Meet propuestos, confirmados y cerrados por el bot de ventas."
                    stats={[
                        {
                            label: 'Confirmadas',
                            value: String(stats.confirmadas),
                            variant: 'success',
                        },
                        {
                            label: 'Por cerrar',
                            value: String(stats.por_cerrar),
                            variant: 'warning',
                        },
                        {
                            label: 'Cerradas',
                            value: String(stats.realizadas),
                            variant: 'info',
                        },
                        {
                            label: 'Filtro',
                            value: String(stats.coincidencias),
                            variant: 'default',
                        },
                    ]}
                />

                <DataTable
                    columns={columns}
                    data={meetings.data}
                    rowKey={(m) => m.id}
                    isLoading={isLoading}
                    ariaLiveMessage={`${stats.coincidencias} reuniones`}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder="Buscar por nombre, teléfono o link…"
                        >
                            <FilterChips
                                ariaLabel="Filtrar reuniones"
                                value={filters.estado}
                                onChange={(estado) => applyFilter({ estado: estado as EstadoFilter })}
                                options={estadoOptions}
                            />
                        </DataToolbar>
                    }
                    footer={
                        <DataPagination
                            meta={meetings}
                            onPerPageChange={setPerPage}
                            preservedQuery={{
                                search: filters.search || undefined,
                                per_page: filters.per_page,
                                estado:
                                    filters.estado !== 'confirmadas' ? filters.estado : undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={CalendarDays}
                            title="Sin reuniones todavía"
                            description="Cuando un lead confirme un tour, aparecerá aquí con el link de Meet."
                        />
                    }
                />
            </div>

            <Dialog
                open={dialogMeeting !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDialogMeeting(null);
                        setFormError(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <form onSubmit={onDialogSubmit} className="flex flex-col gap-4">
                        <DialogHeader>
                            <DialogTitle>{closeTitles[dialogStatus]}</DialogTitle>
                            <DialogDescription>
                                {dialogMeeting
                                    ? `${dialogMeeting.prospect_name || 'Lead'} · ${formatWhen(dialogMeeting.meet_at)}`
                                    : ''}
                                <span className="mt-1 block">{closeDescriptions[dialogStatus]}</span>
                            </DialogDescription>
                        </DialogHeader>

                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="meet-outcome-note">Nota (opcional)</Label>
                            <Textarea
                                id="meet-outcome-note"
                                value={note}
                                onChange={(e) => setNote(e.target.value)}
                                maxLength={500}
                                rows={3}
                                placeholder="Ej. Interesado, pide demo / No contestó…"
                            />
                            {formError && (
                                <p className="text-xs text-red-600 dark:text-red-400">{formError}</p>
                            )}
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDialogMeeting(null)}
                                disabled={processing}
                            >
                                Volver
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Guardando…' : 'Confirmar'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

SalesBotMeetingsIndex.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma', href: '/plataforma/operaciones' },
            { title: 'Reuniones', href: '/plataforma/salesbot-meetings' },
        ]}
    >
        {page}
    </AppLayout>
);
