import { Head, router } from '@inertiajs/react';
import {
    Building2,
    Contact,
    Loader2,
    Mail,
    MapPin,
    Phone,
    Plus,
    Radar,
    RadioTower,
    Sparkles,
    Stethoscope,
    UserPlus,
} from 'lucide-react';
import { useMemo, useState, type FormEvent, type ReactNode } from 'react';
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
import { FormField, FormModal, FormSection } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { AtencionDateRangeFilter } from '@/pages/clinica/historias-clinicas/components/atencion-date-range-filter';
import type { Paginated } from '@/types';

type Tipo = 'clinica' | 'hospital';
type Origen = 'manual' | 'scraping_auto';
type Estado =
    | 'nuevo'
    | 'contactado'
    | 'conversando'
    | 'demo_agendada'
    | 'cliente'
    | 'no_interesado';

type Prospecto = {
    id: string;
    nombre: string;
    tipo: Tipo;
    telefono: string | null;
    telefono_normalizado: string | null;
    correo: string | null;
    direccion: string | null;
    departamento: string | null;
    provincia: string | null;
    distrito: string | null;
    origen: Origen;
    estado: Estado;
    capturado_at: string;
};

type EstadoFilter = 'todos' | Estado;
type TipoFilter = 'todos' | Tipo;

type Filters = {
    search: string;
    estado: EstadoFilter;
    tipo: TipoFilter;
    departamento: string | null;
    provincia: string | null;
    capturado_desde: string;
    capturado_hasta: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
};

type FechaFiltroUi = {
    default_desde: string;
    default_hasta: string;
};

/** Departamento → lista de provincias realmente presentes en la data. */
type GeoFiltro = Record<string, string[]>;

type Stats = {
    total: number;
    nuevos: number;
    con_telefono: number;
    con_correo: number;
    hoy: number;
    coincidencias: number;
};

type UltimaCorrida = {
    iniciado_at: string | null;
    origen: 'cron' | 'manual';
    estado: 'ok' | 'parcial' | 'error';
    nuevos: number;
    duplicados: number;
    ubicaciones_visitadas: string[];
} | null;

type Props = {
    prospectos: Paginated<Prospecto>;
    filters: Filters;
    stats: Stats;
    estados: Estado[];
    ultima_corrida: UltimaCorrida;
    fecha_filtro_ui: FechaFiltroUi;
    geo_filtro: GeoFiltro;
};

const DEFAULT_PER_PAGE = 25;
const DEFAULT_ESTADO: EstadoFilter = 'todos';
const DEFAULT_TIPO: TipoFilter = 'todos';

const ESTADO_LABELS: Record<Estado, string> = {
    nuevo: 'Nuevo',
    contactado: 'Contactado',
    conversando: 'Conversando',
    demo_agendada: 'Demo agendada',
    cliente: 'Cliente',
    no_interesado: 'No interesado',
};

const ESTADO_VARIANTS: Record<
    Estado,
    'primary' | 'success' | 'warning' | 'danger' | 'info'
> = {
    nuevo: 'info',
    contactado: 'primary',
    conversando: 'warning',
    demo_agendada: 'warning',
    cliente: 'success',
    no_interesado: 'danger',
};

function formatFecha(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('es-PE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function waLink(telefonoNormalizado: string | null): string | null {
    if (!telefonoNormalizado) return null;
    return `https://wa.me/${telefonoNormalizado}`;
}

function ubicacionLabel(p: Prospecto): string {
    return [p.distrito, p.provincia ?? p.departamento]
        .filter((v, i, arr) => Boolean(v) && arr.indexOf(v) === i)
        .join(', ') || p.departamento || '—';
}

function ManualCreateModal({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [nombre, setNombre] = useState('');
    const [tipo, setTipo] = useState<Tipo>('clinica');
    const [telefono, setTelefono] = useState('');
    const [correo, setCorreo] = useState('');
    const [direccion, setDireccion] = useState('');
    const [departamento, setDepartamento] = useState('');
    const [provincia, setProvincia] = useState('');
    const [distrito, setDistrito] = useState('');
    const [fecha, setFecha] = useState(() =>
        new Date().toISOString().slice(0, 10),
    );
    const [sending, setSending] = useState(false);

    const reset = () => {
        setNombre('');
        setTipo('clinica');
        setTelefono('');
        setCorreo('');
        setDireccion('');
        setDepartamento('');
        setProvincia('');
        setDistrito('');
        setFecha(new Date().toISOString().slice(0, 10));
    };

    const handleSubmit = (e?: FormEvent) => {
        e?.preventDefault();
        if (!nombre.trim()) return;
        setSending(true);
        router.post(
            '/plataforma/prospectos-veterinarias',
            {
                nombre: nombre.trim(),
                tipo,
                telefono: telefono.trim() || undefined,
                correo: correo.trim() || undefined,
                direccion: direccion.trim() || undefined,
                departamento: departamento.trim() || undefined,
                provincia: provincia.trim() || undefined,
                distrito: distrito.trim() || undefined,
                capturado_at: fecha || undefined,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    reset();
                    onOpenChange(false);
                },
                onFinish: () => setSending(false),
            },
        );
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title="Registrar prospecto manual"
            description="Agrega a mano una clínica u hospital veterinario que no salió en el scraping."
            size="md"
            onSubmit={handleSubmit}
            footer={
                <div className="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        disabled={sending || !nombre.trim()}
                        className="gap-2"
                    >
                        {sending && (
                            <Loader2 className="size-4 animate-spin" />
                        )}
                        {sending ? 'Guardando…' : 'Registrar'}
                    </Button>
                </div>
            }
        >
            <FormSection title="Datos del prospecto" columns={2}>
                <FormField
                    id="pv-nombre"
                    label="Nombre de la clínica/hospital"
                    required
                    className="sm:col-span-2"
                >
                    <Input
                        id="pv-nombre"
                        placeholder="Clínica Veterinaria San Martín"
                        value={nombre}
                        onChange={(e) => setNombre(e.target.value)}
                    />
                </FormField>
                <FormField id="pv-tipo" label="Tipo">
                    <Select
                        value={tipo}
                        onValueChange={(v) => setTipo(v as Tipo)}
                    >
                        <SelectTrigger id="pv-tipo" className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="clinica">Clínica</SelectItem>
                            <SelectItem value="hospital">Hospital</SelectItem>
                        </SelectContent>
                    </Select>
                </FormField>
                <FormField id="pv-fecha" label="Fecha de captura">
                    <Input
                        id="pv-fecha"
                        type="date"
                        value={fecha}
                        onChange={(e) => setFecha(e.target.value)}
                    />
                </FormField>
                <FormField id="pv-telefono" label="Teléfono / celular">
                    <Input
                        id="pv-telefono"
                        placeholder="+51 987 654 321"
                        value={telefono}
                        onChange={(e) => setTelefono(e.target.value)}
                    />
                </FormField>
                <FormField id="pv-correo" label="Correo">
                    <Input
                        id="pv-correo"
                        type="email"
                        placeholder="contacto@clinica.pe"
                        value={correo}
                        onChange={(e) => setCorreo(e.target.value)}
                    />
                </FormField>
                <FormField
                    id="pv-direccion"
                    label="Dirección"
                    className="sm:col-span-2"
                >
                    <Input
                        id="pv-direccion"
                        placeholder="Av. Ejemplo 123"
                        value={direccion}
                        onChange={(e) => setDireccion(e.target.value)}
                    />
                </FormField>
                <FormField id="pv-departamento" label="Departamento">
                    <Input
                        id="pv-departamento"
                        placeholder="Lima"
                        value={departamento}
                        onChange={(e) => setDepartamento(e.target.value)}
                    />
                </FormField>
                <FormField id="pv-provincia" label="Provincia">
                    <Input
                        id="pv-provincia"
                        placeholder="Lima"
                        value={provincia}
                        onChange={(e) => setProvincia(e.target.value)}
                    />
                </FormField>
                <FormField
                    id="pv-distrito"
                    label="Distrito"
                    className="sm:col-span-2"
                >
                    <Input
                        id="pv-distrito"
                        placeholder="Miraflores"
                        value={distrito}
                        onChange={(e) => setDistrito(e.target.value)}
                    />
                </FormField>
            </FormSection>
        </FormModal>
    );
}

type ProspectosFilterExtras = {
    estado: EstadoFilter;
    tipo: TipoFilter;
    departamento: string | null;
    provincia: string | null;
    capturado_desde: string;
    capturado_hasta: string;
};

export default function ProspectosVeterinariasIndex({
    prospectos,
    filters,
    stats,
    ultima_corrida,
    fecha_filtro_ui,
    geo_filtro,
}: Props) {
    const { can } = usePermission();
    const canCreate = can('plataforma-prospectos.create');
    const canUpdate = can('plataforma-prospectos.update');

    const [scraping, setScraping] = useState(false);
    const [manualOpen, setManualOpen] = useState(false);
    const [updatingId, setUpdatingId] = useState<string | null>(null);

    const { search, setSearch, isLoading, sort, setSort, setPerPage, applyFilter } =
        useDataTablePage<ProspectosFilterExtras>({
            routeUrl: '/plataforma/prospectos-veterinarias',
            initialFilters: filters,
            only: ['prospectos', 'filters', 'stats', 'geo_filtro'],
            errorMessage: 'Error al cargar los prospectos',
            storageKey: 'vetsaas.plataforma.prospectos-veterinarias.prefs',
            defaults: { per_page: DEFAULT_PER_PAGE, sort: null, direction: null },
        });

    const departamentoOptions = useMemo(
        () => Object.keys(geo_filtro).sort((a, b) => a.localeCompare(b)),
        [geo_filtro],
    );

    const provinciaOptions = useMemo(() => {
        if (!filters.departamento) return [];
        return [...(geo_filtro[filters.departamento] ?? [])].sort((a, b) =>
            a.localeCompare(b),
        );
    }, [geo_filtro, filters.departamento]);

    const handleDepartamentoChange = (value: string) => {
        applyFilter({
            departamento: value === 'todos' ? null : value,
            provincia: null,
        });
    };

    const handleProvinciaChange = (value: string) => {
        applyFilter({ provincia: value === 'todos' ? null : value });
    };

    const handleFechaApply = (desde: string, hasta: string) => {
        applyFilter({ capturado_desde: desde, capturado_hasta: hasta });
    };

    const activeFiltersCount = useMemo(() => {
        let n = 0;
        if (filters.search) n++;
        if (filters.estado !== DEFAULT_ESTADO) n++;
        if (filters.tipo !== DEFAULT_TIPO) n++;
        if (filters.departamento) n++;
        if (filters.provincia) n++;
        if (
            filters.capturado_desde !== fecha_filtro_ui.default_desde ||
            filters.capturado_hasta !== fecha_filtro_ui.default_hasta
        ) {
            n++;
        }
        return n;
    }, [
        filters.search,
        filters.estado,
        filters.tipo,
        filters.departamento,
        filters.provincia,
        filters.capturado_desde,
        filters.capturado_hasta,
        fecha_filtro_ui,
    ]);

    const estadoOptions: readonly FilterChip<EstadoFilter>[] = useMemo(
        () => [
            { value: 'todos', label: 'Todos' },
            { value: 'nuevo', label: 'Nuevos' },
            { value: 'contactado', label: 'Contactados' },
            { value: 'conversando', label: 'Conversando' },
            { value: 'demo_agendada', label: 'Demo agendada' },
            { value: 'cliente', label: '✅ Clientes' },
            { value: 'no_interesado', label: 'No interesado' },
        ],
        [],
    );

    const tipoOptions: readonly FilterChip<TipoFilter>[] = useMemo(
        () => [
            { value: 'todos', label: 'Todos' },
            { value: 'clinica', label: 'Clínicas' },
            { value: 'hospital', label: 'Hospitales' },
        ],
        [],
    );

    const handleScrapeNow = () => {
        setScraping(true);
        router.post(
            '/plataforma/prospectos-veterinarias/scrape',
            {},
            {
                preserveScroll: true,
                onFinish: () => setScraping(false),
            },
        );
    };

    const handleEstadoChange = (prospecto: Prospecto, estado: Estado) => {
        setUpdatingId(prospecto.id);
        router.post(
            `/plataforma/prospectos-veterinarias/${prospecto.id}/estado`,
            { estado },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setUpdatingId(null),
            },
        );
    };

    const columns = useMemo<DataTableColumn<Prospecto>[]>(
        () => [
            {
                key: 'nombre',
                header: 'Prospecto',
                sortable: true,
                cell: (p) => (
                    <div className="flex items-center gap-2">
                        <span
                            className={`flex size-8 shrink-0 items-center justify-center rounded-full ${
                                p.tipo === 'hospital'
                                    ? 'bg-rose-500/10 text-rose-600'
                                    : 'bg-primary/10 text-primary'
                            }`}
                        >
                            {p.tipo === 'hospital' ? (
                                <Building2 className="size-4" strokeWidth={2.25} />
                            ) : (
                                <Stethoscope className="size-4" strokeWidth={2.25} />
                            )}
                        </span>
                        <div className="flex min-w-0 flex-col leading-tight">
                            <span className="truncate text-sm font-semibold text-foreground">
                                {p.nombre}
                            </span>
                            <span className="flex items-center gap-1 truncate text-xs text-muted-foreground">
                                <MapPin className="size-3 shrink-0" />
                                {ubicacionLabel(p)}
                            </span>
                        </div>
                    </div>
                ),
            },
            {
                key: 'contacto',
                header: 'Contacto',
                cell: (p) => (
                    <div className="flex min-w-35 flex-col gap-0.5 leading-tight">
                        {p.telefono ? (
                            <a
                                href={`tel:${p.telefono}`}
                                className="flex items-center gap-1 font-mono text-xs text-foreground hover:text-primary"
                            >
                                <Phone className="size-3" />
                                {p.telefono}
                            </a>
                        ) : (
                            <span className="text-xs text-muted-foreground">
                                Sin teléfono
                            </span>
                        )}
                        {p.correo ? (
                            <a
                                href={`mailto:${p.correo}`}
                                className="flex items-center gap-1 truncate text-xs text-muted-foreground hover:text-primary"
                            >
                                <Mail className="size-3 shrink-0" />
                                <span className="truncate">{p.correo}</span>
                            </a>
                        ) : null}
                    </div>
                ),
            },
            {
                key: 'origen',
                header: 'Origen',
                cell: (p) =>
                    p.origen === 'manual' ? (
                        <StatBadge
                            label="Manual"
                            value=""
                            variant="info"
                            icon={UserPlus}
                        />
                    ) : (
                        <StatBadge
                            label="Scraping"
                            value=""
                            variant="primary"
                            icon={Radar}
                        />
                    ),
            },
            {
                key: 'capturado_at',
                header: 'Capturado',
                sortable: true,
                cell: (p) => (
                    <span className="text-xs text-muted-foreground">
                        {formatFecha(p.capturado_at)}
                    </span>
                ),
            },
            {
                key: 'estado',
                header: 'Estado',
                align: 'right',
                className: 'w-44',
                cell: (p) =>
                    canUpdate ? (
                        <Select
                            value={p.estado}
                            onValueChange={(v) =>
                                handleEstadoChange(p, v as Estado)
                            }
                            disabled={updatingId === p.id}
                        >
                            <SelectTrigger className="h-7 w-full text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(ESTADO_LABELS).map(
                                    ([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                    ) : (
                        <StatBadge
                            label={ESTADO_LABELS[p.estado]}
                            value=""
                            variant={ESTADO_VARIANTS[p.estado]}
                        />
                    ),
            },
            {
                key: 'acciones',
                header: <span className="sr-only">Acciones</span>,
                align: 'right',
                className: 'w-16',
                cell: (p) => {
                    const wa = waLink(p.telefono_normalizado);

                    return wa ? (
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            asChild
                            className="size-8 cursor-pointer text-emerald-500 hover:text-emerald-600"
                            title="Escribir por WhatsApp"
                        >
                            <a href={wa} target="_blank" rel="noreferrer">
                                <Contact className="size-4" strokeWidth={2.5} />
                            </a>
                        </Button>
                    ) : null;
                },
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [canUpdate, updatingId],
    );

    return (
        <>
            <Head title="Prospectos veterinarias" />

            <div className="flex flex-1 flex-col gap-3 p-4 sm:p-6">
                <PageHeader
                    title="Prospectos veterinarias"
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            <span>
                                Clínicas y hospitales de todo el Perú para
                                prospección comercial de VetSaaS.
                            </span>
                            {ultima_corrida?.iniciado_at && (
                                <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                    <RadioTower className="size-3" />
                                    Última corrida:{' '}
                                    {formatFecha(ultima_corrida.iniciado_at)} ·{' '}
                                    {ultima_corrida.nuevos} nuevos (
                                    {ultima_corrida.origen === 'cron'
                                        ? 'automática'
                                        : 'manual'}
                                    )
                                </span>
                            )}
                        </span>
                    }
                    stats={[
                        { label: 'Total', value: stats.total, variant: 'info', icon: Contact },
                        { label: 'Nuevos', value: stats.nuevos, variant: 'primary', icon: Sparkles },
                        { label: 'Con teléfono', value: stats.con_telefono, variant: 'success', icon: Phone },
                        { label: 'Con correo', value: stats.con_correo, variant: 'success', icon: Mail },
                        { label: 'Hoy', value: stats.hoy, variant: 'warning', icon: Radar },
                    ]}
                    action={
                        <div className="flex flex-wrap gap-2">
                            {canCreate && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="cursor-pointer gap-1.5"
                                    onClick={() => setManualOpen(true)}
                                >
                                    <Plus className="size-3.5" />
                                    Agregar manual
                                </Button>
                            )}
                            {canCreate && (
                                <Button
                                    type="button"
                                    size="sm"
                                    className="cursor-pointer gap-1.5"
                                    disabled={scraping}
                                    onClick={handleScrapeNow}
                                >
                                    {scraping ? (
                                        <>
                                            <Loader2 className="size-3.5 animate-spin" />
                                            Buscando veterinarias…
                                        </>
                                    ) : (
                                        <>
                                            <Radar className="size-3.5" />
                                            Traer nuevos
                                        </>
                                    )}
                                </Button>
                            )}
                        </div>
                    }
                />

                <DataTable
                    columns={columns}
                    data={prospectos.data}
                    rowKey={(p) => p.id}
                    sort={sort}
                    onSortChange={setSort}
                    isLoading={isLoading || scraping}
                    ariaLiveMessage={`${stats.coincidencias} prospectos`}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder="Buscar por nombre, teléfono, correo o ubicación..."
                            filtersClassName="sm:justify-between"
                        >
                            <div className="flex flex-wrap items-center gap-2">
                                <FilterChips
                                    ariaLabel="Filtrar por estado"
                                    value={filters.estado}
                                    onChange={(estado) => applyFilter({ estado })}
                                    options={estadoOptions}
                                />
                                <FilterChips
                                    ariaLabel="Filtrar por tipo"
                                    value={filters.tipo}
                                    onChange={(tipo) => applyFilter({ tipo })}
                                    options={tipoOptions}
                                />
                                <AtencionDateRangeFilter
                                    desde={filters.capturado_desde}
                                    hasta={filters.capturado_hasta}
                                    defaultDesde={fecha_filtro_ui.default_desde}
                                    defaultHasta={fecha_filtro_ui.default_hasta}
                                    disabled={isLoading}
                                    translationNs="plataforma-prospectos-veterinarias"
                                    triggerClassName="h-9"
                                    onApply={handleFechaApply}
                                />
                                <Select
                                    value={filters.departamento ?? 'todos'}
                                    onValueChange={handleDepartamentoChange}
                                >
                                    <SelectTrigger className="h-9 w-40 text-xs">
                                        <SelectValue placeholder="Departamento" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="todos">
                                            Todos los deptos.
                                        </SelectItem>
                                        {departamentoOptions.map((dep) => (
                                            <SelectItem key={dep} value={dep}>
                                                {dep}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select
                                    value={filters.provincia ?? 'todos'}
                                    onValueChange={handleProvinciaChange}
                                    disabled={!filters.departamento}
                                >
                                    <SelectTrigger className="h-9 w-40 text-xs">
                                        <SelectValue
                                            placeholder={
                                                filters.departamento
                                                    ? 'Provincia'
                                                    : 'Elige depto. primero'
                                            }
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="todos">
                                            Todas las provincias
                                        </SelectItem>
                                        {provinciaOptions.map((prov) => (
                                            <SelectItem key={prov} value={prov}>
                                                {prov}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </DataToolbar>
                    }
                    footer={
                        <DataPagination
                            meta={prospectos}
                            onPerPageChange={setPerPage}
                            preservedQuery={{
                                search: filters.search || undefined,
                                per_page: filters.per_page,
                                estado:
                                    filters.estado !== DEFAULT_ESTADO
                                        ? filters.estado
                                        : undefined,
                                tipo:
                                    filters.tipo !== DEFAULT_TIPO
                                        ? filters.tipo
                                        : undefined,
                                departamento: filters.departamento ?? undefined,
                                provincia: filters.provincia ?? undefined,
                                capturado_desde: filters.capturado_desde,
                                capturado_hasta: filters.capturado_hasta,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={Radar}
                            title="Todavía no hay prospectos"
                            description="Usa «Traer nuevos» para lanzar el scraping ahora, o espera a la corrida automática de la mañana."
                        />
                    }
                />

                {activeFiltersCount > 0 && (
                    <p className="text-xs text-muted-foreground">
                        {activeFiltersCount} filtro(s) activo(s) ·{' '}
                        {stats.coincidencias} coincidencias
                    </p>
                )}
            </div>

            <ManualCreateModal open={manualOpen} onOpenChange={setManualOpen} />
        </>
    );
}

ProspectosVeterinariasIndex.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma' },
            {
                title: 'Prospectos veterinarias',
                href: '/plataforma/prospectos-veterinarias',
            },
        ]}
    >
        {page}
    </AppLayout>
);
