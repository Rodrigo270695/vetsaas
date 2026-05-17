import { Head, Link } from '@inertiajs/react';
import { Headset } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DataPagination,
    DataTable,
    EmptyState,
    PageHeader,
} from '@/components/data-page';
import type { DataTableColumn } from '@/components/data-page';
import AppLayout from '@/layouts/app-layout';
import type { Paginated } from '@/types';

type AuditLogRow = {
    id: string;
    superadmin_name: string;
    superadmin_email: string;
    tenant_slug: string;
    tenant_label: string;
    ip_address: string | null;
    central_origin: string | null;
    started_at: string | null;
    ended_at: string | null;
    is_active: boolean;
};

type Props = {
    logs: Paginated<AuditLogRow>;
    perPageOptions: number[];
};

const formatWhen = (value: string | null): string => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleString('es-PE', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};

export default function PlataformaAuditoriaSoporteIndex({ logs }: Props) {
    const { t } = useTranslation('plataforma-auditoria-soporte');

    const columns: DataTableColumn<AuditLogRow>[] = [
        {
            key: 'started_at',
            header: t('columns.started_at'),
            cell: (row) => formatWhen(row.started_at),
        },
        {
            key: 'ended_at',
            header: t('columns.ended_at'),
            cell: (row) =>
                row.is_active ? (
                    <span className="font-medium text-warning">
                        {t('status.active')}
                    </span>
                ) : (
                    formatWhen(row.ended_at)
                ),
        },
        {
            key: 'superadmin',
            header: t('columns.superadmin'),
            cell: (row) => (
                <div className="min-w-0">
                    <p className="truncate font-medium">{row.superadmin_name}</p>
                    <p className="truncate text-xs text-muted-foreground">
                        {row.superadmin_email}
                    </p>
                </div>
            ),
        },
        {
            key: 'tenant',
            header: t('columns.tenant'),
            cell: (row) => (
                <div className="min-w-0">
                    <p className="truncate font-medium">{row.tenant_label}</p>
                    <p className="truncate text-xs text-muted-foreground">
                        {row.tenant_slug}
                    </p>
                </div>
            ),
        },
        {
            key: 'ip_address',
            header: t('columns.ip'),
            cell: (row) => row.ip_address ?? '—',
        },
    ];

    return (
        <>
            <Head title={t('title')} />
            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                />

                {logs.data.length === 0 ? (
                    <div className="rounded-xl border border-border/70 bg-card p-6 shadow-sm">
                        <EmptyState
                            icon={Headset}
                            title={t('empty.title')}
                            description={t('empty.description')}
                            action={
                                <Button asChild>
                                    <Link href="/plataforma/tenants">
                                        {t('empty.cta_tenants')}
                                    </Link>
                                </Button>
                            }
                        />
                        <div className="mx-auto mt-4 max-w-md text-left text-sm">
                            <p className="mb-2 font-medium text-foreground">
                                {t('empty.steps_title')}
                            </p>
                            <ol className="list-decimal space-y-1.5 pl-5 text-muted-foreground">
                                <li>{t('empty.step_1')}</li>
                                <li>{t('empty.step_2')}</li>
                                <li>{t('empty.step_3')}</li>
                            </ol>
                        </div>
                    </div>
                ) : (
                    <>
                        <DataTable
                            columns={columns}
                            data={logs.data}
                            rowKey={(row) => row.id}
                        />
                        <DataPagination meta={logs} />
                    </>
                )}
            </div>
        </>
    );
}

PlataformaAuditoriaSoporteIndex.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma' },
            {
                title: 'Auditoría soporte',
                href: '/plataforma/auditoria-soporte',
            },
        ]}
    >
        {page}
    </AppLayout>
);
