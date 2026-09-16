import { Head, Link } from '@inertiajs/react';
import { Megaphone, Plus } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import {
    DataPagination,
    DataTable,
    EmptyState,
    PageHeader,
    StatBadge,
} from '@/components/data-page';
import type { DataTableColumn } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/hooks/use-permission';
import type { Paginated } from '@/types';

const ROUTE_URL = '/comunicaciones/campanas';

type CampanaRow = {
    id: string;
    nombre: string;
    estado: string;
    tope_diario: number;
    hora_inicio: string;
    hora_fin: string;
    pendientes_count: number;
    enviados_count: number;
    total_count: number;
};

type Props = {
    items?: Paginated<CampanaRow>;
    filters?: { per_page: number };
};

const EMPTY: Paginated<CampanaRow> = {
    data: [],
    current_page: 1,
    last_page: 1,
    per_page: 15,
    from: null,
    to: null,
    total: 0,
    path: ROUTE_URL,
    first_page_url: null,
    last_page_url: null,
    next_page_url: null,
    prev_page_url: null,
    links: [],
};

function estadoVariant(estado: string): 'muted' | 'info' | 'warning' | 'success' {
    if (estado === 'enviando') return 'info';
    if (estado === 'pausada') return 'warning';
    if (estado === 'terminada') return 'success';

    return 'muted';
}

export default function CampanasIndex({ items: paginated = EMPTY }: Props) {
    const { t } = useTranslation(['comunicaciones', 'common']);
    const { can } = usePermission();

    const columns = useMemo<DataTableColumn<CampanaRow>[]>(
        () => [
            {
                key: 'nombre',
                header: t('campanas.columns.nombre'),
                cell: (row) => (
                    <Link
                        href={`${ROUTE_URL}/${row.id}`}
                        className="font-medium text-foreground hover:underline"
                    >
                        {row.nombre}
                    </Link>
                ),
            },
            {
                key: 'estado',
                header: t('campanas.columns.estado'),
                cell: (row) => (
                    <StatBadge
                        label={t(`campanas.estado.${row.estado}`)}
                        value=""
                        variant={estadoVariant(row.estado)}
                    />
                ),
            },
            {
                key: 'progreso',
                header: t('campanas.columns.progreso'),
                cell: (row) =>
                    t('campanas.progress', {
                        enviados: row.enviados_count,
                        total: row.total_count,
                    }),
            },
            {
                key: 'horario',
                header: t('campanas.columns.horario'),
                cell: (row) => (
                    <span className="text-sm text-muted-foreground">
                        {String(row.hora_inicio).slice(0, 5)}–{String(row.hora_fin).slice(0, 5)}
                    </span>
                ),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('campanas.title')} />
            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('campanas.title')}
                    description={t('campanas.description')}
                    action={
                        can('comunicaciones-campanas.create') ? (
                            <Button asChild size="sm" className="cursor-pointer gap-2">
                                <Link href={`${ROUTE_URL}/create`}>
                                    <Plus className="size-4" />
                                    {t('campanas.new')}
                                </Link>
                            </Button>
                        ) : null
                    }
                />

                {paginated.data.length === 0 ? (
                    <EmptyState
                        icon={Megaphone}
                        title={t('campanas.empty')}
                        description={t('campanas.empty_hint')}
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={paginated.data}
                        rowKey={(row) => row.id}
                        footer={
                            <DataPagination
                                meta={paginated}
                                preservedQuery={{ per_page: paginated.per_page }}
                            />
                        }
                    />
                )}
            </div>
        </>
    );
}

CampanasIndex.layout = {
    breadcrumbs: [
        { title: 'Comunicaciones', href: '#' },
        { title: 'Campañas', href: ROUTE_URL },
    ],
};
