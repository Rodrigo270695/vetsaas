import { Head, router } from '@inertiajs/react';
import {
    Activity,
    Building2,
    LayoutGrid,
    Radio,
    RefreshCw,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    DataTable,
    PageHeader,
    StatBadge,
} from '@/components/data-page';
import type { DataTableColumn } from '@/components/data-page';
import { useAutoRefresh } from '@/hooks/use-auto-refresh';
import AppLayout from '@/layouts/app-layout';
import { SectionCard } from '@/pages/configuracion/general/components/section-card';
import { AtencionDateRangeFilter } from '@/pages/clinica/historias-clinicas/components/atencion-date-range-filter';
import sesionesLogin from '@/routes/plataforma/sesiones-login';

type SessionTab = 'en_vivo' | 'flujo';

type OnlineUserRow = {
    user_id: string;
    user_name: string;
    user_email: string;
    tenant_id: string | null;
    tenant_slug: string | null;
    tenant_label: string | null;
    plan_codigo: string | null;
    is_free: boolean | null;
    last_path: string | null;
    last_module: string | null;
    last_seen_at: string | null;
    last_path_at: string | null;
};

type PresencePayload = {
    online_window_minutes: number;
    online: OnlineUserRow[];
    modules_now: Array<{ module: string; users: number }>;
    modules_range: Array<{ module: string; hits: number }>;
    tenants_range: Array<{
        tenant_id: string;
        tenant_slug: string;
        tenant_label: string;
        hits: number;
        users: number;
    }>;
};

type Props = {
    filters: {
        fecha_desde: string;
        fecha_hasta: string;
    };
    stats: {
        online: number;
    };
    presence: PresencePayload;
    fecha_filtro_ui: {
        default_desde: string;
        default_hasta: string;
    };
};

const formatWhen = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleString('es-PE', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};

export default function PlataformaSesionesLoginIndex({
    filters,
    stats,
    presence,
    fecha_filtro_ui,
}: Props) {
    const { t } = useTranslation(['plataforma-sesiones-login', 'common']);
    const [tab, setTab] = useState<SessionTab>('en_vivo');

    const { secondsSince, isRefreshing, refresh } = useAutoRefresh({
        only: ['filters', 'stats', 'presence', 'fecha_filtro_ui'],
        enabled: true,
    });

    const applyFecha = (desde: string, hasta: string) => {
        router.get(
            sesionesLogin.index().url,
            { fecha_desde: desde, fecha_hasta: hasta },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['filters', 'stats', 'presence', 'fecha_filtro_ui'],
            },
        );
    };

    const onlineColumns: DataTableColumn<OnlineUserRow>[] = [
        {
            id: 'user',
            header: t('columns.user'),
            cell: (row) => (
                <div className="min-w-0">
                    <p className="truncate font-medium">{row.user_name}</p>
                    <p className="truncate text-xs text-muted-foreground">{row.user_email}</p>
                </div>
            ),
        },
        {
            id: 'tenant',
            header: t('columns.clinic'),
            cell: (row) => (
                <div className="min-w-0">
                    <p className="truncate text-sm">{row.tenant_label ?? '—'}</p>
                    <p className="truncate font-mono text-xs text-muted-foreground">
                        {row.tenant_slug ?? '—'}
                    </p>
                </div>
            ),
        },
        {
            id: 'module',
            header: t('columns.module'),
            cell: (row) => (
                <span className="text-sm">{row.last_module ?? row.last_path ?? '—'}</span>
            ),
        },
        {
            id: 'seen',
            header: t('columns.last_seen'),
            cell: (row) => (
                <span className="whitespace-nowrap text-xs tabular-nums text-muted-foreground">
                    {formatWhen(row.last_seen_at)}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description_live_only')}
                    icon={Radio}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-xs text-muted-foreground">
                                {t('common:auto_refresh.updated_seconds', {
                                    seconds: secondsSince,
                                })}
                            </span>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="gap-1.5"
                                disabled={isRefreshing}
                                onClick={() => refresh()}
                            >
                                <RefreshCw
                                    className={`size-3.5 ${isRefreshing ? 'animate-spin' : ''}`}
                                />
                                {t('common:auto_refresh.now')}
                            </Button>
                        </div>
                    }
                    stats={[
                        {
                            label: t('stats.online'),
                            value: stats.online,
                            variant: 'success',
                            icon: Users,
                        },
                    ]}
                />

                <Tabs
                    value={tab}
                    onValueChange={(value) => setTab(value as SessionTab)}
                    className="flex flex-col gap-4"
                >
                    <TabsList className="grid h-auto w-full max-w-md grid-cols-2 gap-1 p-1">
                        <TabsTrigger value="en_vivo" className="cursor-pointer gap-1.5 text-xs sm:text-sm">
                            <Users className="size-3.5 shrink-0" />
                            <span className="truncate">{t('tabs.en_vivo')}</span>
                            {presence.online.length > 0 ? (
                                <span className="rounded-full bg-emerald-500/15 px-1.5 text-[10px] font-semibold text-emerald-700 tabular-nums dark:text-emerald-300">
                                    {presence.online.length}
                                </span>
                            ) : null}
                        </TabsTrigger>
                        <TabsTrigger value="flujo" className="cursor-pointer gap-1.5 text-xs sm:text-sm">
                            <Activity className="size-3.5 shrink-0" />
                            <span className="truncate">{t('tabs.flujo')}</span>
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="en_vivo" className="mt-0 flex flex-col gap-4">
                        <div className="grid gap-4 xl:grid-cols-3">
                            <SectionCard
                                title={t('presence.title')}
                                description={t('presence.description', {
                                    minutes: presence.online_window_minutes,
                                })}
                                icon={Users}
                                className="xl:col-span-2"
                            >
                                {presence.online.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('presence.empty', {
                                            minutes: presence.online_window_minutes,
                                        })}
                                    </p>
                                ) : (
                                    <DataTable
                                        columns={onlineColumns}
                                        data={presence.online}
                                        rowKey={(row) => row.user_id}
                                        isLoading={isRefreshing}
                                    />
                                )}
                            </SectionCard>

                            <SectionCard title={t('presence.modules_now_title')} icon={LayoutGrid}>
                                {presence.modules_now.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('presence.empty', {
                                            minutes: presence.online_window_minutes,
                                        })}
                                    </p>
                                ) : (
                                    <ul className="flex flex-col gap-1.5">
                                        {presence.modules_now.map((row) => (
                                            <li
                                                key={row.module}
                                                className="flex items-center justify-between gap-2 text-sm"
                                            >
                                                <span className="truncate font-medium">{row.module}</span>
                                                <StatBadge label={t('presence.users')} value={row.users} />
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </SectionCard>
                        </div>
                    </TabsContent>

                    <TabsContent value="flujo" className="mt-0 flex flex-col gap-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <AtencionDateRangeFilter
                                desde={filters.fecha_desde}
                                hasta={filters.fecha_hasta}
                                defaultDesde={fecha_filtro_ui.default_desde}
                                defaultHasta={fecha_filtro_ui.default_hasta}
                                translationNs="plataforma-sesiones-login"
                                triggerClassName="h-9"
                                onApply={applyFecha}
                            />
                            <p className="text-xs text-muted-foreground">{t('tabs.flujo_hint')}</p>
                        </div>

                        <div className="grid gap-4 lg:grid-cols-2">
                            <SectionCard title={t('presence.modules_range_title')} icon={Activity}>
                                {presence.modules_range.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('presence.empty_flow')}
                                    </p>
                                ) : (
                                    <ul className="flex max-h-[28rem] flex-col gap-1.5 overflow-y-auto pr-1">
                                        {presence.modules_range.map((row) => (
                                            <li
                                                key={row.module}
                                                className="flex items-center justify-between gap-2 text-sm"
                                            >
                                                <span className="truncate font-medium">{row.module}</span>
                                                <StatBadge
                                                    label={t('presence.hits')}
                                                    value={row.hits}
                                                    variant="info"
                                                />
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </SectionCard>

                            <SectionCard title={t('presence.tenants_range_title')} icon={Building2}>
                                {presence.tenants_range.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('presence.empty_flow')}
                                    </p>
                                ) : (
                                    <div className="flex max-h-[28rem] flex-col gap-2 overflow-y-auto pr-1">
                                        {presence.tenants_range.map((row) => (
                                            <div
                                                key={row.tenant_id}
                                                className="flex flex-col gap-1 rounded-lg border border-border/50 bg-muted/20 px-3 py-2"
                                            >
                                                <span className="truncate text-sm font-semibold">
                                                    {row.tenant_label}
                                                </span>
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    {row.tenant_slug}
                                                </span>
                                                <div className="mt-1 flex flex-wrap gap-1.5">
                                                    <StatBadge
                                                        label={t('presence.hits')}
                                                        value={row.hits}
                                                        variant="info"
                                                    />
                                                    <StatBadge
                                                        label={t('presence.users')}
                                                        value={row.users}
                                                    />
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </SectionCard>
                        </div>
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

PlataformaSesionesLoginIndex.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma' },
            {
                title: 'Sesiones de login',
                href: '/plataforma/sesiones-login',
            },
        ]}
    >
        {page}
    </AppLayout>
);
