import { Head, router } from '@inertiajs/react';
import { Plus, Tags, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
import { DataPagination, DataTable, EmptyState, PageHeader } from '@/components/data-page';
import type { DataTableColumn } from '@/components/data-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { TarifaFormModal } from './components/tarifa-form-modal';
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

export default function Index({
    tab,
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

    const setTab = useCallback((value: string) => {
        router.get(
            '/configuracion/tarifas',
            { tab: value },
            { preserveState: true, preserveScroll: true, only: ['tab', 'groomingTarifas', 'hotelTarifas'] },
        );
    }, []);

    const labelGrooming = useCallback(
        (slug: string) => t(`grooming:tipos_servicio.${slug}`, { defaultValue: slug }),
        [t],
    );

    const labelHotel = useCallback(
        (slug: string) => t(`hotel:tipos_estancia.${slug}`, { defaultValue: slug }),
        [t],
    );

    const formatPrecio = (amount: string, moneda: string) => {
        const n = Number(amount);
        const cur = moneda === 'USD' ? 'USD' : 'PEN';

        return Number.isNaN(n)
            ? amount
            : new Intl.NumberFormat(undefined, { style: 'currency', currency: cur }).format(n);
    };

    const groomingColumns = useMemo<DataTableColumn<GroomingTarifa>[]>(
        () => [
            {
                key: 'servicio',
                header: t('columns.servicio'),
                cell: (row) => labelGrooming(row.servicio),
            },
            {
                key: 'precio_lista',
                header: t('columns.precio'),
                cell: (row) => (
                    <span className="tabular-nums font-medium">{formatPrecio(row.precio_lista, row.moneda)}</span>
                ),
            },
            {
                key: 'activo',
                header: t('columns.activo'),
                cell: (row) => (
                    <Badge variant={row.activo ? 'default' : 'outline'}>
                        {row.activo ? t('common:filters.active') : t('common:filters.inactive')}
                    </Badge>
                ),
            },
            {
                key: 'actions',
                header: '',
                className: 'w-[120px] text-right',
                cell: (row) => (
                    <div className="flex justify-end gap-1">
                        {canUpdate ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => setModal({ type: 'edit', kind: 'grooming', tarifa: row })}
                            >
                                {t('actions.editar')}
                            </Button>
                        ) : null}
                        {canDelete ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="text-destructive"
                                onClick={() => setModal({ type: 'delete', kind: 'grooming', tarifa: row })}
                                aria-label={t('actions.eliminar')}
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        ) : null}
                    </div>
                ),
            },
        ],
        [t, labelGrooming, canUpdate, canDelete],
    );

    const hotelColumns = useMemo<DataTableColumn<HotelTarifa>[]>(
        () => [
            {
                key: 'tipo_estancia',
                header: t('columns.tipo'),
                cell: (row) => labelHotel(row.tipo_estancia),
            },
            {
                key: 'precio_lista',
                header: t('columns.precio'),
                cell: (row) => (
                    <span className="tabular-nums font-medium">{formatPrecio(row.precio_lista, row.moneda)}</span>
                ),
            },
            {
                key: 'activo',
                header: t('columns.activo'),
                cell: (row) => (
                    <Badge variant={row.activo ? 'default' : 'outline'}>
                        {row.activo ? t('common:filters.active') : t('common:filters.inactive')}
                    </Badge>
                ),
            },
            {
                key: 'actions',
                header: '',
                className: 'w-[120px] text-right',
                cell: (row) => (
                    <div className="flex justify-end gap-1">
                        {canUpdate ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => setModal({ type: 'edit', kind: 'hotel', tarifa: row })}
                            >
                                {t('actions.editar')}
                            </Button>
                        ) : null}
                        {canDelete ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="text-destructive"
                                onClick={() => setModal({ type: 'delete', kind: 'hotel', tarifa: row })}
                                aria-label={t('actions.eliminar')}
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        ) : null}
                    </div>
                ),
            },
        ],
        [t, labelHotel, canUpdate, canDelete],
    );

    const confirmDelete = () => {
        if (modal.type !== 'delete') {
            return;
        }
        const url =
            modal.kind === 'grooming'
                ? `/configuracion/tarifas/grooming/${modal.tarifa.id}`
                : `/configuracion/tarifas/hotel/${modal.tarifa.id}`;
        router.delete(url, {
            preserveScroll: true,
            onSuccess: () => setModal({ type: 'idle' }),
        });
    };

    const deleteNombre =
        modal.type === 'delete'
            ? modal.kind === 'grooming' && 'servicio' in modal.tarifa
                ? labelGrooming(modal.tarifa.servicio)
                : modal.kind === 'hotel' && 'tipo_estancia' in modal.tarifa
                  ? labelHotel(modal.tarifa.tipo_estancia)
                  : ''
            : '';

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-col gap-6">
                <PageHeader
                    icon={Tags}
                    title={t('title')}
                    description={t('description')}
                    actions={
                        canCreate ? (
                            <Can permission="tarifas.create">
                                <Button
                                    type="button"
                                    size="sm"
                                    className="gap-1.5"
                                    onClick={() =>
                                        setModal({
                                            type: 'create',
                                            kind: tab === 'hotel' ? 'hotel' : 'grooming',
                                        })
                                    }
                                >
                                    <Plus className="size-4" aria-hidden />
                                    {tab === 'hotel'
                                        ? t('actions.nueva_hotel')
                                        : t('actions.nueva_grooming')}
                                </Button>
                            </Can>
                        ) : null
                    }
                />

                <Tabs value={tab} onValueChange={setTab}>
                    <TabsList>
                        <TabsTrigger value="grooming">{t('tabs.grooming')}</TabsTrigger>
                        <TabsTrigger value="hotel">{t('tabs.hotel')}</TabsTrigger>
                    </TabsList>

                    <TabsContent value="grooming" className="mt-4 space-y-4">
                        <DataTable
                            columns={groomingColumns}
                            data={groomingTarifas.data}
                            rowKey={(row) => row.id}
                            emptyState={
                                <EmptyState
                                    title={t('empty.grooming')}
                                    action={
                                        canCreate ? (
                                            <Button
                                                type="button"
                                                size="sm"
                                                onClick={() => setModal({ type: 'create', kind: 'grooming' })}
                                            >
                                                {t('actions.nueva_grooming')}
                                            </Button>
                                        ) : undefined
                                    }
                                />
                            }
                        />
                        <DataPagination meta={groomingTarifas} pageQueryKey="grooming_page" preservedQuery={{ tab: 'grooming' }} />
                    </TabsContent>

                    <TabsContent value="hotel" className="mt-4 space-y-4">
                        <DataTable
                            columns={hotelColumns}
                            data={hotelTarifas.data}
                            rowKey={(row) => row.id}
                            emptyState={
                                <EmptyState
                                    title={t('empty.hotel')}
                                    action={
                                        canCreate ? (
                                            <Button
                                                type="button"
                                                size="sm"
                                                onClick={() => setModal({ type: 'create', kind: 'hotel' })}
                                            >
                                                {t('actions.nueva_hotel')}
                                            </Button>
                                        ) : undefined
                                    }
                                />
                            }
                        />
                        <DataPagination meta={hotelTarifas} pageQueryKey="hotel_page" preservedQuery={{ tab: 'hotel' }} />
                    </TabsContent>
                </Tabs>
            </div>

            {(modal.type === 'create' || modal.type === 'edit') && (
                <TarifaFormModal
                    kind={modal.kind}
                    open
                    onOpenChange={(open) => !open && setModal({ type: 'idle' })}
                    tarifa={modal.type === 'edit' ? modal.tarifa : null}
                    catalogo={modal.kind === 'grooming' ? catalogoGrooming : catalogoHotel}
                />
            )}

            <Dialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => !open && setModal({ type: 'idle' })}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('delete.title')}</DialogTitle>
                        <DialogDescription>
                            {t('delete.description', { nombre: deleteNombre })}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setModal({ type: 'idle' })}>
                            {t('form.cancelar')}
                        </Button>
                        <Button type="button" variant="destructive" onClick={confirmDelete}>
                            {t('delete.confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
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
