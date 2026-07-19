import { Head, router } from '@inertiajs/react';
import {
    CheckCircle2,
    Info,
    Loader2,
    Megaphone,
    MessageSquareText,
    Plus,
    Save,
    ShieldCheck,
} from 'lucide-react';
import {
    useCallback,
    useEffect,
    useRef,
    useState,
    type FormEvent,
    type ReactNode,
} from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/data-page';
import { FormField, FormSection } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import configuracion from '@/routes/plataforma/configuracion';
import { SectionCard } from '../../configuracion/general/components/section-card';
import { InAppAnnouncementDeleteDialog } from './components/in-app-announcement-delete-dialog';
import { InAppAnnouncementFormModal } from './components/in-app-announcement-form-modal';
import { InAppAnnouncementRowActions } from './components/in-app-announcement-row-actions';
import type { InAppAnnouncementRecord } from './types';

type AuditUser = {
    id: string;
    name: string;
    email: string;
} | null;

type PlatformSetting = {
    id: string;
    in_app_assistant_daily_limit: number;
    updated_at: string | null;
    actualizado_por: AuditUser;
};

type PageProps = {
    setting: PlatformSetting;
    announcements: InAppAnnouncementRecord[];
    live_announcement_id: string | null;
};

type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; entry: InAppAnnouncementRecord }
    | { type: 'delete'; entry: InAppAnnouncementRecord };

const formatDate = (value: string | null, locale: string): string => {
    if (!value) {
        return '—';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }
    return date.toLocaleString(locale === 'en' ? 'en-US' : 'es-PE', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

export default function Index({
    setting,
    announcements,
    live_announcement_id: liveAnnouncementId,
}: PageProps) {
    const { t, i18n } = useTranslation(['platform', 'common']);
    const { can } = usePermission();
    const canUpdate = can('platform-settings.update');

    const [dailyLimit, setDailyLimit] = useState(String(setting.in_app_assistant_daily_limit ?? 40));
    const [errors, setErrors] = useState<Partial<Record<string, string>>>({});
    const [processing, setProcessing] = useState(false);
    const [recentlySuccessful, setRecentlySuccessful] = useState(false);
    const recentSuccessTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const [modal, setModal] = useState<ModalState>({ type: 'idle' });

    useEffect(() => {
        setDailyLimit(String(setting.in_app_assistant_daily_limit ?? 40));
    }, [setting.updated_at, setting.in_app_assistant_daily_limit]);

    useEffect(() => {
        return () => {
            if (recentSuccessTimerRef.current) {
                clearTimeout(recentSuccessTimerRef.current);
            }
        };
    }, []);

    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setProcessing(true);
        router.put(
            configuracion.update().url,
            {
                in_app_assistant_daily_limit: Number(dailyLimit),
            },
            {
                preserveScroll: true,
                onError: (errs) => setErrors(errs as Partial<Record<string, string>>),
                onSuccess: () => {
                    setErrors({});
                    setRecentlySuccessful(true);
                    if (recentSuccessTimerRef.current) {
                        clearTimeout(recentSuccessTimerRef.current);
                    }
                    recentSuccessTimerRef.current = setTimeout(() => {
                        setRecentlySuccessful(false);
                    }, 2500);
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const hasLive = liveAnnouncementId !== null;

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <form onSubmit={handleSubmit} noValidate>
                    <PageHeader
                        title={t('title')}
                        description={t('description')}
                        stats={[
                            {
                                label: t('stats.daily_limit'),
                                value: String(setting.in_app_assistant_daily_limit),
                                variant: 'info',
                                icon: MessageSquareText,
                            },
                            {
                                label: t('stats.announcement'),
                                value: hasLive
                                    ? t('stats.announcement_on')
                                    : t('stats.announcement_off'),
                                variant: hasLive ? 'success' : 'muted',
                                icon: Megaphone,
                            },
                        ]}
                        action={
                            canUpdate ? (
                                <div className="flex flex-col items-stretch gap-1.5 sm:items-end">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="cursor-pointer gap-2 disabled:cursor-not-allowed"
                                    >
                                        {processing ? (
                                            <Loader2
                                                className="size-4 animate-spin"
                                                aria-hidden="true"
                                            />
                                        ) : recentlySuccessful ? (
                                            <CheckCircle2 className="size-4" strokeWidth={2.5} />
                                        ) : (
                                            <Save className="size-4" strokeWidth={2.5} />
                                        )}
                                        {recentlySuccessful
                                            ? t('actions.saved')
                                            : t('actions.save')}
                                    </Button>
                                    <span className="flex max-w-56 items-center gap-1 text-[11px] text-muted-foreground">
                                        <ShieldCheck
                                            className="size-3 shrink-0 text-primary/70"
                                            strokeWidth={2.25}
                                        />
                                        <span className="truncate">
                                            {setting.actualizado_por
                                                ? t('footer.last_updated_by', {
                                                      name: setting.actualizado_por.name,
                                                  })
                                                : t('footer.never_updated')}
                                        </span>
                                    </span>
                                </div>
                            ) : undefined
                        }
                    />
                </form>

                <div className="flex items-start gap-3 rounded-lg border border-primary/20 bg-primary/5 p-4 text-sm">
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-primary/15 text-primary ring-1 ring-primary/20">
                        <Info className="size-4" strokeWidth={2.25} />
                    </span>
                    <div className="flex flex-col gap-1">
                        <span className="font-semibold">{t('intro.title')}</span>
                        <span className="text-xs leading-relaxed text-muted-foreground">
                            {t('intro.body')}
                        </span>
                    </div>
                </div>

                <SectionCard
                    icon={MessageSquareText}
                    title={t('sections.assistant.title')}
                    description={t('sections.assistant.description')}
                >
                    <form onSubmit={handleSubmit} noValidate>
                        <FormSection index={0} title="" columns={2} className="gap-0">
                            <FormField
                                id="platform-assistant-daily-limit"
                                label={t('fields.daily_limit')}
                                error={errors.in_app_assistant_daily_limit}
                                hint={t('fields.daily_limit_hint')}
                                className="sm:col-span-2 sm:max-w-sm"
                            >
                                <Input
                                    id="platform-assistant-daily-limit"
                                    type="number"
                                    min={1}
                                    max={1000}
                                    step={1}
                                    value={dailyLimit}
                                    onChange={(e) => setDailyLimit(e.target.value)}
                                    disabled={!canUpdate}
                                    className="tabular-nums"
                                />
                            </FormField>
                        </FormSection>
                    </form>
                </SectionCard>

                <SectionCard
                    icon={Megaphone}
                    title={t('sections.announcement.title')}
                    description={t('sections.announcement.description')}
                    badge={
                        canUpdate ? (
                            <Button
                                type="button"
                                className="gap-2"
                                onClick={() => setModal({ type: 'create' })}
                            >
                                <Plus className="size-4" strokeWidth={2.5} />
                                {t('announcements.new')}
                            </Button>
                        ) : undefined
                    }
                >
                    {announcements.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-border/80 bg-muted/10 px-4 py-8 text-center">
                            <p className="text-sm text-muted-foreground">
                                {t('announcements.empty')}
                            </p>
                            {canUpdate ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="mt-4 gap-2"
                                    onClick={() => setModal({ type: 'create' })}
                                >
                                    <Plus className="size-4" />
                                    {t('announcements.new')}
                                </Button>
                            ) : null}
                        </div>
                    ) : (
                        <div className="overflow-hidden rounded-xl border border-border/70">
                            <ul className="divide-y divide-border/60">
                                {announcements.map((entry) => {
                                    const isLive = liveAnnouncementId === entry.id;
                                    return (
                                        <li
                                            key={entry.id}
                                            className="flex items-start gap-3 px-4 py-3.5 sm:items-center"
                                        >
                                            <div className="min-w-0 flex-1 space-y-1.5">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="truncate text-sm font-medium text-foreground">
                                                        {entry.title}
                                                    </p>
                                                    <span
                                                        className={cn(
                                                            'inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-medium tracking-wide uppercase',
                                                            isLive
                                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                                : entry.is_active
                                                                  ? 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-300'
                                                                  : 'border-border bg-muted/40 text-muted-foreground',
                                                        )}
                                                    >
                                                        {isLive
                                                            ? t('announcements.live')
                                                            : entry.published_at
                                                              ? t('announcements.inactive')
                                                              : t('announcements.draft')}
                                                    </span>
                                                    <span className="text-[11px] text-muted-foreground">
                                                        {t('announcements.version', {
                                                            version: entry.version,
                                                        })}
                                                    </span>
                                                </div>
                                                <p className="line-clamp-2 text-xs leading-relaxed text-muted-foreground">
                                                    {entry.body}
                                                </p>
                                                <p className="text-[11px] text-muted-foreground/80">
                                                    {formatDate(
                                                        entry.published_at ?? entry.created_at,
                                                        i18n.language,
                                                    )}
                                                    {entry.created_by
                                                        ? ` · ${entry.created_by.name}`
                                                        : ''}
                                                </p>
                                            </div>
                                            <InAppAnnouncementRowActions
                                                entry={entry}
                                                liveAnnouncementId={liveAnnouncementId}
                                                canUpdate={canUpdate}
                                                onEdit={(item) =>
                                                    setModal({ type: 'edit', entry: item })
                                                }
                                                onDelete={(item) =>
                                                    setModal({ type: 'delete', entry: item })
                                                }
                                            />
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    )}
                </SectionCard>
            </div>

            <InAppAnnouncementFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                entry={modal.type === 'edit' ? modal.entry : null}
            />

            <InAppAnnouncementDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                entry={modal.type === 'delete' ? modal.entry : null}
            />
        </>
    );
}

Index.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma' },
            { title: 'Configuración', href: '/plataforma/configuracion' },
        ]}
    >
        {page}
    </AppLayout>
);
