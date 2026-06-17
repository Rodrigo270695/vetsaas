import { Head, router } from '@inertiajs/react';
import {
    Ban,
    BedDouble,
    CheckCircle2,
    LayoutGrid,
    Plus,
    Scissors,
    ShieldCheck,
    Tags,
} from 'lucide-react';
import type { LucideIcon, ReactNode } from 'react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
import { DataPagination, DataTable, DataToolbar, EmptyState, PageHeader, StatBadge } from '@/components/data-page';
import type { DataTableColumn } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { CatalogoClinicaPanel } from './components/catalogo-clinica-panel';
import { TarifaDeleteDialog } from './components/tarifa-delete-dialog';
import { TarifaFormModal } from './components/tarifa-form-modal';
import { TarifaRowActions } from './components/tarifa-row-actions';
import type {
    GroomingTarifa,
    HotelTarifa,
    TarifaIndexProps,
    TarifaTab,
} from './types';

type ModalState =
    | { type: 'idle' }
    | { type: 'create'; kind: TarifaTab }
    | { type: 'edit'; kind: TarifaTab; tarifa: GroomingTarifa | HotelTarifa }
    | { type: 'delete'; kind: TarifaTab; tarifa: GroomingTarifa | HotelTarifa };

const SEARCH_DEBOUNCE_MS = 350;

function formatPrecio(amount: string, moneda: string) {
    const n = Number(amount);
    const cur = moneda === 'USD' ? 'USD' : 'PEN';

    return Number.isNaN(n)
        ? amount
        : new Intl.NumberFormat(undefined, { style: 'currency', currency: cur }).format(n);
}

export default function Index({
    tab,
    grooming_catalogo_personalizado,
    hotel_catalogo_personalizado,
    groomingServicios,
    hotelTipos,
    catalogoGrooming,
    catalogoHotel,
    groomingTarifas,
    hotelTarifas,
    filters,
}: TarifaIndexProps) {
    const { t } = useTranslation(['tarifas-servicios', 'grooming', 'hotel', 'common']);
    const { can } = usePermission();
    const canCreate = can('tarifas.create');
    const canUpdate = can('tarifas.update');
    const canDelete = can('tarifas.delete');

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });
    const [isSearching, setIsSearching] = useState(false);
    const [groomingSearch, setGroomingSearch] = useState(filters.grooming_search);
    const [hotelSearch, setHotelSearch] = useState(filters.hotel_search);

    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);

    const labelGrooming = useCallback(
        (slug: string) => t(`grooming:tipos_servicio.${slug}`, { defaultValue: slug }),
        [t],
    );

    const labelHotel = useCallback(
        (slug: string) => t(`hotel:tipos_estancia.${slug}`, { defaultValue: slug }),
        [t],
    );

    const setTab = useCallback((value: string) => {
        router.get(
            '/configuracion/tarifas',
            {
                tab: value,
                grooming_search: filters.grooming_search || undefined,
                hotel_search: filters.hotel_search || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                only: [
                    'tab',
                    'groomingTarifas',
                    'hotelTarifas',
                    'groomingServicios',
                    'hotelTipos',
                    'filters',
                    'grooming_catalogo_personalizado',
                    'hotel_catalogo_personalizado',
                ],
            },
        );
    }, [filters.grooming_search, filters.hotel_search]);

    useEffect(() => {
        setGroomingSearch(filters.grooming_search);
        setHotelSearch(filters.hotel_search);
        setIsSearching(false);
    }, [filters.grooming_search, filters.hotel_search]);

    useEffect(() => {
        const query = tab === 'grooming' ? groomingSearch : hotelSearch;
        const serverQuery = tab === 'grooming' ? filters.grooming_search : filters.hotel_search;

        if (query === serverQuery) {
            return;
        }

        const timer = window.setTimeout(() => {
            setIsSearching(true);
            router.get(
                '/configuracion/tarifas',
                {
                    tab,
                    grooming_search: tab === 'grooming' ? query || undefined : filters.grooming_search || undefined,
                    hotel_search: tab === 'hotel' ? query || undefined : filters.hotel_search || undefined,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    only: ['groomingTarifas', 'hotelTarifas', 'groomingServicios', 'hotelTipos', 'filters'],
                    onFinish: () => setIsSearching(false),
                },
            );
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
    }, [groomingSearch, hotelSearch, tab, filters.grooming_search, filters.hotel_search]);

    const groomingPersonalizado = grooming_catalogo_personalizado;
    const hotelPersonalizado = hotel_catalogo_personalizado;
    const isPersonalizedTab = tab === 'grooming' ? groomingPersonalizado : hotelPersonalizado;

    const activeCatalogRows = tab === 'grooming' ? groomingServicios : hotelTipos;
    const activePaginated = tab === 'grooming' ? groomingTarifas : hotelTarifas;
    const activeRows = isPersonalizedTab ? activeCatalogRows : (activePaginated?.data ?? []);
    const activeTotal = isPersonalizedTab ? activeCatalogRows.length : (activePaginated?.total ?? 0);
    const activeOnScreen = activeRows.length;
    const activeCount = activeRows.filter((row) => row.activo).length;
    const inactiveCount = activeRows.filter((row) => !row.activo).length;

    const showLegacyCreate = tab === 'grooming' ? !groomingPersonalizado : !hotelPersonalizado;

    const headerStats = useMemo(
        () => [
            {
                label: t('stats.total'),
                value: activeTotal,
                variant: 'info' as const,
                icon: Tags,
            },
            {
                label: t('stats.active'),
                value: activeCount,
                variant: 'success' as const,
                icon: ShieldCheck,
            },
            {
                label: t('stats.inactive'),
                value: inactiveCount,
                variant: 'danger' as const,
                icon: Ban as LucideIcon,
            },
            {
                label: t('stats.on_screen'),
                value: activeOnScreen,
                variant: 'primary' as const,
                icon: LayoutGrid,
            },
        ],
        [t, activeTotal, activeCount, inactiveCount, activeOnScreen],
    );

    const groomingColumns = useMemo<DataTableColumn<GroomingTarifa>[]>(
        () => [
            {
                key: 'activo',
                header: t('columns.activo'),
                className: 'w-28',
                cell: (row) =>
                    row.activo ? (
                        <StatBadge label={t('common:filters.active')} value="" variant="success" icon={CheckCircle2} />
                    ) : (
                        <StatBadge label={t('common:filters.inactive')} value="" variant="muted" icon={Ban as LucideIcon} />
                    ),
            },
            {
                key: 'servicio',
                header: t('columns.servicio'),
                cell: (row) => (
                    <div className="flex flex-col gap-0.5">
                        <span className="font-medium text-foreground">{labelGrooming(row.servicio)}</span>
                        <span className="font-mono text-[0.7rem] text-muted-foreground">{row.servicio}</span>
                    </div>
                ),
            },
            {
                key: 'precio_lista',
                header: t('columns.precio'),
                className: 'w-36',
                cell: (row) => (
                    <span className="tabular-nums font-semibold text-foreground">
                        {formatPrecio(row.precio_lista, row.moneda)}
                    </span>
                ),
            },
            {
                key: 'moneda',
                header: t('columns.moneda'),
                className: 'w-24',
                cell: (row) => (
                    <span className="inline-flex rounded-md bg-muted/60 px-2 py-0.5 font-mono text-xs text-muted-foreground">
                        {row.moneda}
                    </span>
                ),
            },
            ...(canUpdate || canDelete
                ? [
                      {
                          key: 'actions',
                          header: <span className="md:sr-only">{t('columns.acciones')}</span>,
                          align: 'right' as const,
                          className: 'w-24',
                          cell: (row: GroomingTarifa) => (
                              <TarifaRowActions
                                  canUpdate={canUpdate}
                                  canDelete={canDelete}
                                  onEdit={() => setModal({ type: 'edit', kind: 'grooming', tarifa: row })}
                                  onDelete={() => setModal({ type: 'delete', kind: 'grooming', tarifa: row })}
                              />
                          ),
                      },
                  ]
                : []),
        ],
        [t, labelGrooming, canUpdate, canDelete],
    );

    const hotelColumns = useMemo<DataTableColumn<HotelTarifa>[]>(
        () => [
            {
                key: 'activo',
                header: t('columns.activo'),
                className: 'w-28',
                cell: (row) =>
                    row.activo ? (
                        <StatBadge label={t('common:filters.active')} value="" variant="success" icon={CheckCircle2} />
                    ) : (
                        <StatBadge label={t('common:filters.inactive')} value="" variant="muted" icon={Ban as LucideIcon} />
                    ),
            },
            {
                key: 'tipo_estancia',
                header: t('columns.tipo'),
                cell: (row) => (
                    <div className="flex flex-col gap-0.5">
                        <span className="font-medium text-foreground">{labelHotel(row.tipo_estancia)}</span>
                        <span className="font-mono text-[0.7rem] text-muted-foreground">{row.tipo_estancia}</span>
                    </div>
                ),
            },
            {
                key: 'precio_lista',
                header: t('columns.precio'),
                className: 'w-36',
                cell: (row) => (
                    <span className="tabular-nums font-semibold text-foreground">
                        {formatPrecio(row.precio_lista, row.moneda)}
                    </span>
                ),
            },
            {
                key: 'moneda',
                header: t('columns.moneda'),
                className: 'w-24',
                cell: (row) => (
                    <span className="inline-flex rounded-md bg-muted/60 px-2 py-0.5 font-mono text-xs text-muted-foreground">
                        {row.moneda}
                    </span>
                ),
            },
            ...(canUpdate || canDelete
                ? [
                      {
                          key: 'actions',
                          header: <span className="md:sr-only">{t('columns.acciones')}</span>,
                          align: 'right' as const,
                          className: 'w-24',
                          cell: (row: HotelTarifa) => (
                              <TarifaRowActions
                                  canUpdate={canUpdate}
                                  canDelete={canDelete}
                                  onEdit={() => setModal({ type: 'edit', kind: 'hotel', tarifa: row })}
                                  onDelete={() => setModal({ type: 'delete', kind: 'hotel', tarifa: row })}
                              />
                          ),
                      },
                  ]
                : []),
        ],
        [t, labelHotel, canUpdate, canDelete],
    );

    const deleteNombre =
        modal.type === 'delete'
            ? modal.kind === 'grooming' && 'servicio' in modal.tarifa
                ? labelGrooming(modal.tarifa.servicio)
                : modal.kind === 'hotel' && 'tipo_estancia' in modal.tarifa
                  ? labelHotel(modal.tarifa.tipo_estancia)
                  : ''
            : '';

    const createButton = (kind: TarifaTab) => (
        <Can permission="tarifas.create">
            <Button
                type="button"
                className="cursor-pointer gap-2"
                onClick={() => setModal({ type: 'create', kind })}
            >
                <Plus className="size-4" strokeWidth={2.5} />
                <span className="hidden sm:inline">
                    {kind === 'hotel' ? t('actions.nueva_hotel') : t('actions.nueva_grooming')}
                </span>
                <span className="sm:hidden">{t('actions.new_short')}</span>
            </Button>
        </Can>
    );

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    stats={headerStats}
                    action={canCreate && showLegacyCreate ? createButton(tab) : undefined}
                />

                <Tabs value={tab} onValueChange={setTab} className="gap-4">
                    <Card className="gap-0 overflow-hidden py-0 shadow-sm">
                        <CardHeader className="gap-4 border-b border-border/60 bg-muted/20 px-4 py-4 sm:px-6">
                            <TabsList className="h-auto w-full justify-start gap-1 bg-background/80 p-1 sm:w-auto">
                                <TabsTrigger value="grooming" className="cursor-pointer gap-2 px-4">
                                    <Scissors className="size-4" />
                                    {t('tabs.grooming')}
                                </TabsTrigger>
                                <TabsTrigger value="hotel" className="cursor-pointer gap-2 px-4">
                                    <BedDouble className="size-4" />
                                    {t('tabs.hotel')}
                                </TabsTrigger>
                            </TabsList>
                        </CardHeader>

                        <CardContent className="p-0">
                            <TabsContent value="grooming" className="mt-0">
                                {groomingPersonalizado ? (
                                    <>
                                        <div className="border-b border-border/60 px-4 py-3 sm:px-6">
                                            <DataToolbar
                                                search={groomingSearch}
                                                onSearchChange={setGroomingSearch}
                                                isSearching={tab === 'grooming' && isSearching}
                                                placeholder={t('search.grooming')}
                                                searchWrapperClassName="sm:max-w-md"
                                            />
                                        </div>
                                        <CatalogoClinicaPanel
                                            kind="grooming"
                                            rows={groomingServicios}
                                            canCreate={canCreate}
                                            canUpdate={canUpdate}
                                            canDelete={canDelete}
                                        />
                                    </>
                                ) : groomingTarifas ? (
                                    <DataTable
                                        columns={groomingColumns}
                                        data={groomingTarifas.data}
                                        rowKey={(row) => row.id}
                                        isLoading={tab === 'grooming' && isSearching}
                                        toolbar={
                                            <DataToolbar
                                                search={groomingSearch}
                                                onSearchChange={setGroomingSearch}
                                                isSearching={tab === 'grooming' && isSearching}
                                                placeholder={t('search.grooming')}
                                            />
                                        }
                                        footer={
                                            <DataPagination
                                                meta={groomingTarifas}
                                                pageQueryKey="grooming_page"
                                                preservedQuery={{
                                                    tab: 'grooming',
                                                    grooming_search: filters.grooming_search || undefined,
                                                    hotel_search: filters.hotel_search || undefined,
                                                }}
                                            />
                                        }
                                        emptyState={
                                            <EmptyState
                                                icon={Scissors}
                                                title={t('empty.grooming_title')}
                                                description={t('empty.grooming')}
                                                action={canCreate ? createButton('grooming') : undefined}
                                            />
                                        }
                                    />
                                ) : null}
                            </TabsContent>

                            <TabsContent value="hotel" className="mt-0">
                                {hotelPersonalizado ? (
                                    <>
                                        <div className="border-b border-border/60 px-4 py-3 sm:px-6">
                                            <DataToolbar
                                                search={hotelSearch}
                                                onSearchChange={setHotelSearch}
                                                isSearching={tab === 'hotel' && isSearching}
                                                placeholder={t('search.hotel')}
                                                searchWrapperClassName="sm:max-w-md"
                                            />
                                        </div>
                                        <CatalogoClinicaPanel
                                            kind="hotel"
                                            rows={hotelTipos}
                                            canCreate={canCreate}
                                            canUpdate={canUpdate}
                                            canDelete={canDelete}
                                        />
                                    </>
                                ) : hotelTarifas ? (
                                    <DataTable
                                        columns={hotelColumns}
                                        data={hotelTarifas.data}
                                        rowKey={(row) => row.id}
                                        isLoading={tab === 'hotel' && isSearching}
                                        toolbar={
                                            <DataToolbar
                                                search={hotelSearch}
                                                onSearchChange={setHotelSearch}
                                                isSearching={tab === 'hotel' && isSearching}
                                                placeholder={t('search.hotel')}
                                            />
                                        }
                                        footer={
                                            <DataPagination
                                                meta={hotelTarifas}
                                                pageQueryKey="hotel_page"
                                                preservedQuery={{
                                                    tab: 'hotel',
                                                    grooming_search: filters.grooming_search || undefined,
                                                    hotel_search: filters.hotel_search || undefined,
                                                }}
                                            />
                                        }
                                        emptyState={
                                            <EmptyState
                                                icon={BedDouble}
                                                title={t('empty.hotel_title')}
                                                description={t('empty.hotel')}
                                                action={canCreate ? createButton('hotel') : undefined}
                                            />
                                        }
                                    />
                                ) : null}
                            </TabsContent>
                        </CardContent>
                    </Card>
                </Tabs>
            </div>

            {(modal.type === 'create' || modal.type === 'edit') &&
            (modal.kind === 'grooming' ? !groomingPersonalizado : !hotelPersonalizado) ? (
                <TarifaFormModal
                    kind={modal.type === 'create' || modal.type === 'edit' ? modal.kind : tab}
                    open={modal.type === 'create' || modal.type === 'edit'}
                    onOpenChange={(open) => {
                        if (!open) closeModal();
                    }}
                    tarifa={modal.type === 'edit' ? modal.tarifa : null}
                    catalogo={
                        modal.type === 'create' || modal.type === 'edit'
                            ? modal.kind === 'grooming'
                                ? catalogoGrooming
                                : catalogoHotel
                            : tab === 'grooming'
                              ? catalogoGrooming
                              : catalogoHotel
                    }
                />
            ) : null}

            {modal.type === 'delete' &&
            (modal.kind === 'grooming' ? !groomingPersonalizado : !hotelPersonalizado) ? (
                <TarifaDeleteDialog
                    open={modal.type === 'delete'}
                    onOpenChange={(open) => {
                        if (!open) closeModal();
                    }}
                    kind={modal.type === 'delete' ? modal.kind : null}
                    tarifa={modal.type === 'delete' ? modal.tarifa : null}
                    nombre={deleteNombre}
                />
            ) : null}
        </>
    );
}

Index.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Configuración', href: '#' },
            { title: 'Tarifas', href: '/configuracion/tarifas' },
        ]}
    >
        {page}
    </AppLayout>
);
