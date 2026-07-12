import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    Database,
    HardDrive,
    KeyRound,
    Loader2,
    MessageSquare,
    RefreshCw,
    Repeat,
    Server,
    Store,
    Users,
    Wallet,
    XCircle,
} from 'lucide-react';
import { useMemo, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader, StatBadge } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { SectionCard } from '@/pages/configuracion/general/components/section-card';
import cobros from '@/routes/plataforma/cobros';
import configuracion from '@/routes/plataforma/configuracion';
import operaciones from '@/routes/plataforma/operaciones';
import suscripciones from '@/routes/plataforma/suscripciones';
import tenants from '@/routes/plataforma/tenants';

type Snapshot = {
    health: {
        ok: boolean;
        database: boolean;
        checked_at: string;
        queue_default: string;
    };
    credentials: {
        openwa: boolean;
        twilio: boolean;
        brevo: boolean;
    };
    tenants: {
        total: number;
        trial: number;
        active: number;
        suspended: number;
        cancelled: number;
    };
    whatsapp: {
        openwa_configured: boolean;
        platform: {
            status: string | null;
            phone: string | null;
            last_error: string | null;
            last_synced_at: string | null;
            ready: boolean;
        };
        tenants_ready: number;
        tenants_not_ready: number;
        tenants_with_error: number;
        broken: Array<{
            tenant_id: string;
            tenant_slug: string;
            tenant_label: string;
            status: string;
            phone: string | null;
            last_error: string | null;
            last_synced_at: string | null;
        }>;
    };
    presence: {
        online_users: number;
        online_tenants: number;
        open_sessions: number;
        session_users: number;
        superadmins_online: number;
        online_window_minutes: number;
        session_lifetime_minutes: number;
        by_tenant: Array<{
            tenant_id: string;
            tenant_slug: string;
            tenant_label: string;
            online_users: number;
            open_sessions: number;
            session_users: number;
        }>;
    };
    backups: {
        ok: boolean | null;
        started_at: string | null;
        finished_at: string | null;
        duration_seconds: number | null;
        directory: string | null;
        full_size_bytes: number;
        schemas: string[];
        schema_count: number;
        error: string | null;
        age_hours: number | null;
        stale: boolean;
        enabled: boolean;
        retention_days: number;
        path: string;
        remote_enabled: boolean;
        remote_ok: boolean | null;
        remote_path: string | null;
        remote_error: string | null;
        remote_files: number;
        remote_configured: boolean;
    };
    subscriptions: {
        grace: number;
        suspended: number;
        proximo_cobro_7d: number;
    };
    cobros: {
        fallidos_7d: number;
        pendientes: number;
    };
    failed_jobs: {
        total: number;
        recent: Array<{
            id: number;
            uuid: string;
            connection: string;
            queue: string;
            failed_at: string | null;
            exception_preview: string;
            job_name: string | null;
        }>;
    };
};

type Props = {
    snapshot: Snapshot;
    can_manage: boolean;
};

const formatWhen = (value: string | null): string => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleString(undefined, {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};

const formatBytes = (bytes: number): string => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

function CredentialPill({
    ok,
    label,
    okLabel,
    missingLabel,
}: {
    ok: boolean;
    label: string;
    okLabel: string;
    missingLabel: string;
}) {
    return (
        <div className="flex items-center justify-between gap-3 rounded-lg border border-border/50 bg-muted/30 px-3 py-2.5">
            <span className="text-sm font-medium text-foreground">{label}</span>
            <StatBadge
                label={ok ? okLabel : missingLabel}
                value=""
                variant={ok ? 'success' : 'danger'}
                icon={ok ? CheckCircle2 : XCircle}
            />
        </div>
    );
}

export default function Index({ snapshot, can_manage }: Props) {
    const { t } = useTranslation(['plataforma-operaciones', 'common']);
    const { can } = usePermission();
    const canManage = can_manage && can('plataforma-operaciones.manage');
    const [retryingUuid, setRetryingUuid] = useState<string | null>(null);
    const [retryingAll, setRetryingAll] = useState(false);
    const [backupRunning, setBackupRunning] = useState(false);

    const alertCount = useMemo(() => {
        let n = 0;
        if (!snapshot.health.ok) n += 1;
        if (!snapshot.credentials.openwa) n += 1;
        if (!snapshot.credentials.twilio) n += 1;
        if (!snapshot.credentials.brevo) n += 1;
        n += snapshot.whatsapp.tenants_with_error;
        n += snapshot.subscriptions.grace;
        n += snapshot.cobros.fallidos_7d;
        n += snapshot.failed_jobs.total;
        if (snapshot.backups.stale || snapshot.backups.ok === false) n += 1;
        return n;
    }, [snapshot]);

    const runBackup = () => {
        setBackupRunning(true);
        router.post(operaciones.backups.run.url(), {}, {
            preserveScroll: true,
            onFinish: () => setBackupRunning(false),
        });
    };

    const retryJob = (uuid: string) => {
        setRetryingUuid(uuid);
        router.post(operaciones.failedJobs.retry.url(uuid), {}, {
            preserveScroll: true,
            onFinish: () => setRetryingUuid(null),
        });
    };

    const retryAll = () => {
        setRetryingAll(true);
        router.post(operaciones.failedJobs.retryAll.url(), {}, {
            preserveScroll: true,
            onFinish: () => setRetryingAll(false),
        });
    };

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    stats={[
                        {
                            label: snapshot.health.ok
                                ? t('health.ok')
                                : t('health.down'),
                            value: '',
                            variant: snapshot.health.ok ? 'success' : 'danger',
                            icon: snapshot.health.ok ? Activity : AlertTriangle,
                        },
                        ...(alertCount > 0
                            ? [
                                  {
                                      label: t('alerts'),
                                      value: alertCount,
                                      variant: 'warning' as const,
                                      icon: AlertTriangle,
                                  },
                              ]
                            : []),
                        {
                            label: t('checked_at', {
                                when: formatWhen(snapshot.health.checked_at),
                            }),
                            value: '',
                            variant: 'muted',
                        },
                    ]}
                />

                <div className="grid gap-4 lg:grid-cols-2">
                    <SectionCard
                        title={t('health.title')}
                        description={t('health.description')}
                        icon={Server}
                        badge={
                            <StatBadge
                                label={
                                    snapshot.health.ok
                                        ? t('health.ok')
                                        : t('health.down')
                                }
                                value=""
                                variant={
                                    snapshot.health.ok ? 'success' : 'danger'
                                }
                            />
                        }
                    >
                        <div className="flex flex-wrap gap-2">
                            <StatBadge
                                label={t('health.database')}
                                value={
                                    snapshot.health.database
                                        ? t('health.ok')
                                        : t('health.down')
                                }
                                variant={
                                    snapshot.health.database
                                        ? 'success'
                                        : 'danger'
                                }
                                icon={Database}
                            />
                            <StatBadge
                                label={t('health.queue')}
                                value={snapshot.health.queue_default}
                                variant="info"
                            />
                        </div>
                    </SectionCard>

                    <SectionCard
                        title={t('credentials.title')}
                        description={t('credentials.description')}
                        icon={KeyRound}
                        badge={
                            <Button variant="outline" size="sm" asChild>
                                <Link href={configuracion.show().url}>
                                    {t('credentials.cta_settings')}
                                </Link>
                            </Button>
                        }
                    >
                        <div className="flex flex-col gap-2">
                            <CredentialPill
                                ok={snapshot.credentials.openwa}
                                label={t('credentials.openwa')}
                                okLabel={t('credentials.configured')}
                                missingLabel={t('credentials.missing')}
                            />
                            <CredentialPill
                                ok={snapshot.credentials.twilio}
                                label={t('credentials.twilio')}
                                okLabel={t('credentials.configured')}
                                missingLabel={t('credentials.missing')}
                            />
                            <CredentialPill
                                ok={snapshot.credentials.brevo}
                                label={t('credentials.brevo')}
                                okLabel={t('credentials.configured')}
                                missingLabel={t('credentials.missing')}
                            />
                        </div>
                    </SectionCard>

                    <SectionCard
                        title={t('tenants.title')}
                        description={t('tenants.description')}
                        icon={Store}
                        badge={
                            <Button variant="outline" size="sm" asChild>
                                <Link href={tenants.index().url}>
                                    {t('tenants.cta')}
                                </Link>
                            </Button>
                        }
                    >
                        <div className="flex flex-wrap gap-2">
                            <StatBadge
                                label={t('tenants.total')}
                                value={snapshot.tenants.total}
                            />
                            <StatBadge
                                label={t('tenants.trial')}
                                value={snapshot.tenants.trial}
                                variant="info"
                            />
                            <StatBadge
                                label={t('tenants.active')}
                                value={snapshot.tenants.active}
                                variant="success"
                            />
                            <StatBadge
                                label={t('tenants.suspended')}
                                value={snapshot.tenants.suspended}
                                variant="warning"
                            />
                            <StatBadge
                                label={t('tenants.cancelled')}
                                value={snapshot.tenants.cancelled}
                                variant="danger"
                            />
                        </div>
                    </SectionCard>

                    <SectionCard
                        title={t('presence.title')}
                        description={t('presence.description', {
                            minutes: snapshot.presence.online_window_minutes,
                            session: snapshot.presence.session_lifetime_minutes,
                        })}
                        icon={Users}
                    >
                        <div className="mb-3 flex flex-wrap gap-2">
                            <StatBadge
                                label={t('presence.online_users')}
                                value={snapshot.presence.online_users}
                                variant={
                                    snapshot.presence.online_users > 0
                                        ? 'success'
                                        : 'muted'
                                }
                            />
                            <StatBadge
                                label={t('presence.online_tenants')}
                                value={snapshot.presence.online_tenants}
                                variant="info"
                            />
                            <StatBadge
                                label={t('presence.open_sessions')}
                                value={snapshot.presence.open_sessions}
                                variant="default"
                            />
                            <StatBadge
                                label={t('presence.session_users')}
                                value={snapshot.presence.session_users}
                                variant="default"
                            />
                            <StatBadge
                                label={t('presence.superadmins_online')}
                                value={snapshot.presence.superadmins_online}
                                variant={
                                    snapshot.presence.superadmins_online > 0
                                        ? 'success'
                                        : 'muted'
                                }
                            />
                        </div>

                        {snapshot.presence.by_tenant.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('presence.empty')}
                            </p>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border border-border/50">
                                <table className="w-full min-w-[480px] text-left text-sm">
                                    <thead className="border-b border-border/60 bg-muted/40 text-xs text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">
                                                {t('presence.columns.tenant')}
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                {t('presence.columns.online')}
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                {t('presence.columns.sessions')}
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                {t('presence.columns.session_users')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {snapshot.presence.by_tenant.map(
                                            (row) => (
                                                <tr
                                                    key={row.tenant_id}
                                                    className="border-b border-border/40 last:border-0"
                                                >
                                                    <td className="px-3 py-2">
                                                        <div className="flex flex-col leading-tight">
                                                            <span className="font-medium">
                                                                {
                                                                    row.tenant_label
                                                                }
                                                            </span>
                                                            <span className="font-mono text-xs text-muted-foreground">
                                                                {
                                                                    row.tenant_slug
                                                                }
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td className="px-3 py-2 tabular-nums">
                                                        {row.online_users}
                                                    </td>
                                                    <td className="px-3 py-2 tabular-nums">
                                                        {row.open_sessions}
                                                    </td>
                                                    <td className="px-3 py-2 tabular-nums">
                                                        {row.session_users}
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </SectionCard>

                    <SectionCard
                        title={t('backups.title')}
                        description={t('backups.description')}
                        icon={HardDrive}
                        badge={
                            <div className="flex items-center gap-2">
                                <StatBadge
                                    label={
                                        !snapshot.backups.enabled
                                            ? t('backups.disabled')
                                            : snapshot.backups.ok === true &&
                                                !snapshot.backups.stale
                                              ? t('backups.ok')
                                              : snapshot.backups.ok === false
                                                ? t('backups.failed')
                                                : snapshot.backups.stale
                                                  ? t('backups.stale')
                                                  : t('backups.missing')
                                    }
                                    value=""
                                    variant={
                                        snapshot.backups.ok === true &&
                                        !snapshot.backups.stale
                                            ? 'success'
                                            : 'danger'
                                    }
                                />
                                {canManage ? (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={backupRunning}
                                        onClick={runBackup}
                                    >
                                        {backupRunning ? (
                                            <Loader2 className="mr-1.5 size-3.5 animate-spin" />
                                        ) : (
                                            <HardDrive className="mr-1.5 size-3.5" />
                                        )}
                                        {backupRunning
                                            ? t('backups.running')
                                            : t('backups.run')}
                                    </Button>
                                ) : null}
                            </div>
                        }
                    >
                        <div className="flex flex-wrap gap-2">
                            {snapshot.backups.age_hours !== null ? (
                                <StatBadge
                                    label={t('backups.age', {
                                        hours: snapshot.backups.age_hours,
                                    })}
                                    value=""
                                    variant="muted"
                                />
                            ) : null}
                            <StatBadge
                                label={t('backups.size')}
                                value={formatBytes(
                                    snapshot.backups.full_size_bytes,
                                )}
                                variant="info"
                            />
                            <StatBadge
                                label={t('backups.schemas')}
                                value={snapshot.backups.schema_count}
                                variant="default"
                            />
                            <StatBadge
                                label={t('backups.retention', {
                                    days: snapshot.backups.retention_days,
                                })}
                                value=""
                                variant="muted"
                            />
                            <StatBadge
                                label={
                                    !snapshot.backups.remote_enabled
                                        ? t('backups.remote_off')
                                        : !snapshot.backups.remote_configured
                                          ? t('backups.remote_missing_creds')
                                          : snapshot.backups.remote_ok === true
                                            ? t('backups.remote_ok')
                                            : t('backups.remote_failed')
                                }
                                value={
                                    snapshot.backups.remote_ok === true
                                        ? snapshot.backups.remote_files
                                        : ''
                                }
                                variant={
                                    !snapshot.backups.remote_enabled
                                        ? 'muted'
                                        : snapshot.backups.remote_ok === true
                                          ? 'success'
                                          : 'danger'
                                }
                            />
                        </div>
                        {snapshot.backups.remote_path ? (
                            <p className="mt-2 font-mono text-xs text-muted-foreground">
                                {t('backups.remote_path')}:{' '}
                                {snapshot.backups.remote_path}
                            </p>
                        ) : null}
                        {snapshot.backups.remote_error ? (
                            <p className="mt-2 text-xs text-destructive">
                                {snapshot.backups.remote_error}
                            </p>
                        ) : null}
                        {snapshot.backups.error ? (
                            <p className="mt-3 text-xs text-destructive">
                                {t('backups.error')}: {snapshot.backups.error}
                            </p>
                        ) : null}
                        {snapshot.backups.finished_at ? (
                            <p className="mt-2 text-xs text-muted-foreground">
                                {formatWhen(snapshot.backups.finished_at)}
                            </p>
                        ) : null}
                    </SectionCard>

                    <SectionCard
                        title={t('subscriptions.title')}
                        description={t('subscriptions.description')}
                        icon={Repeat}
                        badge={
                            <Button variant="outline" size="sm" asChild>
                                <Link href={suscripciones.index().url}>
                                    {t('subscriptions.cta')}
                                </Link>
                            </Button>
                        }
                    >
                        <div className="flex flex-wrap gap-2">
                            <StatBadge
                                label={t('subscriptions.grace')}
                                value={snapshot.subscriptions.grace}
                                variant={
                                    snapshot.subscriptions.grace > 0
                                        ? 'warning'
                                        : 'success'
                                }
                            />
                            <StatBadge
                                label={t('subscriptions.suspended')}
                                value={snapshot.subscriptions.suspended}
                                variant={
                                    snapshot.subscriptions.suspended > 0
                                        ? 'danger'
                                        : 'muted'
                                }
                            />
                            <StatBadge
                                label={t('subscriptions.proximo_cobro_7d')}
                                value={
                                    snapshot.subscriptions.proximo_cobro_7d
                                }
                                variant="info"
                            />
                        </div>
                    </SectionCard>

                    <SectionCard
                        title={t('cobros.title')}
                        description={t('cobros.description')}
                        icon={Wallet}
                        badge={
                            <Button variant="outline" size="sm" asChild>
                                <Link href={cobros.index().url}>
                                    {t('cobros.cta')}
                                </Link>
                            </Button>
                        }
                    >
                        <div className="flex flex-wrap gap-2">
                            <StatBadge
                                label={t('cobros.fallidos_7d')}
                                value={snapshot.cobros.fallidos_7d}
                                variant={
                                    snapshot.cobros.fallidos_7d > 0
                                        ? 'danger'
                                        : 'success'
                                }
                            />
                            <StatBadge
                                label={t('cobros.pendientes')}
                                value={snapshot.cobros.pendientes}
                                variant={
                                    snapshot.cobros.pendientes > 0
                                        ? 'warning'
                                        : 'muted'
                                }
                            />
                        </div>
                    </SectionCard>

                    <SectionCard
                        title={t('whatsapp.title')}
                        description={t('whatsapp.description')}
                        icon={MessageSquare}
                        badge={
                            <StatBadge
                                label={t('whatsapp.platform')}
                                value={
                                    snapshot.whatsapp.platform.ready
                                        ? t('whatsapp.ready')
                                        : t('whatsapp.not_ready')
                                }
                                variant={
                                    snapshot.whatsapp.platform.ready
                                        ? 'success'
                                        : 'warning'
                                }
                            />
                        }
                    >
                        <div className="mb-3 flex flex-wrap gap-2">
                            <StatBadge
                                label={t('whatsapp.tenants_ready')}
                                value={snapshot.whatsapp.tenants_ready}
                                variant="success"
                            />
                            <StatBadge
                                label={t('whatsapp.tenants_not_ready')}
                                value={snapshot.whatsapp.tenants_not_ready}
                                variant={
                                    snapshot.whatsapp.tenants_not_ready > 0
                                        ? 'warning'
                                        : 'muted'
                                }
                            />
                            <StatBadge
                                label={t('whatsapp.tenants_with_error')}
                                value={snapshot.whatsapp.tenants_with_error}
                                variant={
                                    snapshot.whatsapp.tenants_with_error > 0
                                        ? 'danger'
                                        : 'muted'
                                }
                            />
                        </div>

                        {snapshot.whatsapp.platform.last_error ? (
                            <p className="mb-3 text-xs text-destructive">
                                {snapshot.whatsapp.platform.last_error}
                            </p>
                        ) : null}

                        <h4 className="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            {t('whatsapp.broken_title')}
                        </h4>

                        {snapshot.whatsapp.broken.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('whatsapp.broken_empty')}
                            </p>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border border-border/50">
                                <table className="w-full min-w-[520px] text-left text-sm">
                                    <thead className="border-b border-border/60 bg-muted/40 text-xs text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">
                                                {t('whatsapp.columns.tenant')}
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                {t('whatsapp.columns.status')}
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                {t('whatsapp.columns.error')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {snapshot.whatsapp.broken.map((row) => (
                                            <tr
                                                key={`${row.tenant_id}-${row.status}-${row.last_synced_at ?? ''}`}
                                                className="border-b border-border/40 last:border-0"
                                            >
                                                <td className="px-3 py-2">
                                                    <div className="flex flex-col leading-tight">
                                                        <span className="font-medium">
                                                            {row.tenant_label}
                                                        </span>
                                                        <span className="font-mono text-xs text-muted-foreground">
                                                            {row.tenant_slug}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-3 py-2">
                                                    <span className="font-mono text-xs">
                                                        {row.status}
                                                    </span>
                                                </td>
                                                <td className="max-w-[240px] truncate px-3 py-2 text-xs text-muted-foreground">
                                                    {row.last_error || '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </SectionCard>
                </div>

                <SectionCard
                    title={t('failed_jobs.title')}
                    description={t('failed_jobs.description')}
                    icon={AlertTriangle}
                    badge={
                        <div className="flex items-center gap-2">
                            <StatBadge
                                label={t('failed_jobs.total')}
                                value={snapshot.failed_jobs.total}
                                variant={
                                    snapshot.failed_jobs.total > 0
                                        ? 'danger'
                                        : 'success'
                                }
                            />
                            {canManage && snapshot.failed_jobs.total > 0 ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={retryingAll}
                                    onClick={retryAll}
                                >
                                    {retryingAll ? (
                                        <Loader2 className="mr-1.5 size-3.5 animate-spin" />
                                    ) : (
                                        <RefreshCw className="mr-1.5 size-3.5" />
                                    )}
                                    {retryingAll
                                        ? t('failed_jobs.retrying')
                                        : t('failed_jobs.retry_all')}
                                </Button>
                            ) : null}
                        </div>
                    }
                >
                    {snapshot.failed_jobs.recent.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('failed_jobs.empty')}
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border border-border/50">
                            <table className="w-full min-w-[720px] text-left text-sm">
                                <thead className="border-b border-border/60 bg-muted/40 text-xs text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            {t('failed_jobs.columns.job')}
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            {t('failed_jobs.columns.queue')}
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            {t('failed_jobs.columns.failed_at')}
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            {t('failed_jobs.columns.exception')}
                                        </th>
                                        {canManage ? (
                                            <th className="px-3 py-2 font-medium">
                                                {t(
                                                    'failed_jobs.columns.actions',
                                                )}
                                            </th>
                                        ) : null}
                                    </tr>
                                </thead>
                                <tbody>
                                    {snapshot.failed_jobs.recent.map((job) => (
                                        <tr
                                            key={job.uuid}
                                            className="border-b border-border/40 last:border-0"
                                        >
                                            <td className="px-3 py-2">
                                                <div className="flex flex-col leading-tight">
                                                    <span className="font-medium">
                                                        {job.job_name ?? '—'}
                                                    </span>
                                                    <span className="font-mono text-[10px] text-muted-foreground">
                                                        {job.uuid.slice(0, 8)}…
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-3 py-2 font-mono text-xs">
                                                {job.queue}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-xs text-muted-foreground">
                                                {formatWhen(job.failed_at)}
                                            </td>
                                            <td className="max-w-[320px] truncate px-3 py-2 text-xs text-muted-foreground">
                                                {job.exception_preview}
                                            </td>
                                            {canManage ? (
                                                <td className="px-3 py-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        disabled={
                                                            retryingUuid ===
                                                            job.uuid
                                                        }
                                                        onClick={() =>
                                                            retryJob(job.uuid)
                                                        }
                                                    >
                                                        {retryingUuid ===
                                                        job.uuid ? (
                                                            <Loader2 className="mr-1 size-3.5 animate-spin" />
                                                        ) : (
                                                            <RefreshCw className="mr-1 size-3.5" />
                                                        )}
                                                        {t('failed_jobs.retry')}
                                                    </Button>
                                                </td>
                                            ) : null}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </SectionCard>
            </div>
        </>
    );
}

Index.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma' },
            { title: 'Operaciones', href: '/plataforma/operaciones' },
        ]}
    >
        {page}
    </AppLayout>
);
