import { Head, router } from '@inertiajs/react';
import { CheckCircle2, Info, Loader2, MessageSquareText, Save, ShieldCheck } from 'lucide-react';
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
import configuracion from '@/routes/plataforma/configuracion';
import { SectionCard } from '../../configuracion/general/components/section-card';

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
};

type FormState = {
    in_app_assistant_daily_limit: string;
};

const buildInitialState = (setting: PlatformSetting): FormState => ({
    in_app_assistant_daily_limit: String(setting.in_app_assistant_daily_limit ?? 40),
});

export default function Index({ setting }: PageProps) {
    const { t } = useTranslation(['platform', 'common']);
    const { can } = usePermission();
    const canUpdate = can('platform-settings.update');

    const [data, setDataInternal] = useState<FormState>(() => buildInitialState(setting));
    const [errors, setErrors] = useState<Partial<Record<string, string>>>({});
    const [processing, setProcessing] = useState(false);
    const [recentlySuccessful, setRecentlySuccessful] = useState(false);
    const recentSuccessTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        setDataInternal(buildInitialState(setting));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [setting.updated_at]);

    useEffect(() => {
        return () => {
            if (recentSuccessTimerRef.current) {
                clearTimeout(recentSuccessTimerRef.current);
            }
        };
    }, []);

    const setData = useCallback(<K extends keyof FormState>(key: K, value: FormState[K]) => {
        setDataInternal((current) => ({ ...current, [key]: value }));
    }, []);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setProcessing(true);
        router.put(
            configuracion.update().url,
            {
                in_app_assistant_daily_limit: Number(data.in_app_assistant_daily_limit),
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

    return (
        <>
            <Head title={t('title')} />

            <form
                onSubmit={handleSubmit}
                className="flex flex-1 flex-col gap-5 p-4 sm:p-6"
                noValidate
            >
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
                                        <Loader2 className="size-4 animate-spin" aria-hidden="true" />
                                    ) : recentlySuccessful ? (
                                        <CheckCircle2 className="size-4" strokeWidth={2.5} />
                                    ) : (
                                        <Save className="size-4" strokeWidth={2.5} />
                                    )}
                                    {recentlySuccessful ? t('actions.saved') : t('actions.save')}
                                </Button>
                                <span className="flex max-w-56 items-center gap-1 text-[11px] text-muted-foreground">
                                    <ShieldCheck className="size-3 shrink-0 text-primary/70" strokeWidth={2.25} />
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
                                value={data.in_app_assistant_daily_limit}
                                onChange={(e) =>
                                    setData('in_app_assistant_daily_limit', e.target.value)
                                }
                                disabled={!canUpdate}
                                className="tabular-nums"
                            />
                        </FormField>
                    </FormSection>
                </SectionCard>
            </form>
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
