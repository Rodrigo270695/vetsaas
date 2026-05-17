import { Head, router } from '@inertiajs/react';
import {
    CheckCircle2,
    Info,
    Loader2,
    Mail,
    MessageSquare,
    Save,
    ShieldCheck,
    XCircle,
} from 'lucide-react';
import {
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
    type FormEvent,
    type ReactNode,
} from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader, StatBadge } from '@/components/data-page';
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
    twilio_default_from: string | null;
    twilio_configurado: boolean;
    brevo_default_from_email: string | null;
    brevo_default_from_name: string | null;
    brevo_configurado: boolean;
    updated_at: string | null;
    actualizado_por: AuditUser;
};

type PageProps = {
    setting: PlatformSetting;
};

/**
 * Shape del formulario (lo que se envía al backend).
 *
 * Las credenciales sensibles (SID, Token, API key) viajan EN CLARO una
 * sola vez; el controller las cifra antes de persistir. Tras guardar,
 * el backend re-renderiza la pantalla solo con los flags `*_configurado`
 * a true.
 */
type FormState = {
    twilio_sid: string;
    twilio_token: string;
    twilio_default_from: string;
    clear_twilio: boolean;
    brevo_api_key: string;
    brevo_default_from_email: string;
    brevo_default_from_name: string;
    clear_brevo: boolean;
};

const buildInitialState = (setting: PlatformSetting): FormState => ({
    twilio_sid: '',
    twilio_token: '',
    twilio_default_from: setting.twilio_default_from ?? '',
    clear_twilio: false,
    brevo_api_key: '',
    brevo_default_from_email: setting.brevo_default_from_email ?? '',
    brevo_default_from_name: setting.brevo_default_from_name ?? '',
    clear_brevo: false,
});

/**
 * Página de Plataforma → Configuración (singleton global del SaaS).
 *
 * Solo accesible al `superadmin` desde el host central. Permite cargar
 * las credenciales de Twilio (WhatsApp) y Brevo (correo transaccional)
 * que la plataforma usa para enviar mensajes en nombre de todas las
 * clínicas.
 *
 * Importante para el operador del SaaS:
 *   - Esta pantalla NO se muestra a las clínicas; ellas solo ven la
 *     versión "remitente comercial visible" en `configuracion/general`.
 *   - Las claves quedan cifradas con AES (Crypt::encryptString) en BD.
 *     Una vez guardadas, no vuelven al frontend: solo se muestran los
 *     flags `*_configurado` en verde/rojo.
 */
export default function Index({ setting }: PageProps) {
    const { t } = useTranslation(['platform', 'common']);
    const { can } = usePermission();
    const canUpdate = can('platform-settings.update');

    const [data, setDataInternal] = useState<FormState>(() =>
        buildInitialState(setting),
    );
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

    const setData = useCallback(
        <K extends keyof FormState>(key: K, value: FormState[K]) => {
            setDataInternal((current) => ({ ...current, [key]: value }));
        },
        [],
    );

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setProcessing(true);
        router.put(configuracion.update().url, data, {
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
        });
    };

    const stats = useMemo(
        () => ({
            twilio: setting.twilio_configurado,
            brevo: setting.brevo_configurado,
        }),
        [setting],
    );

    return (
        <>
            <Head title={t('title')} />

            <form
                onSubmit={handleSubmit}
                className="flex flex-1 flex-col gap-5 p-4 pb-24 sm:p-6 sm:pb-24"
                noValidate
            >
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    stats={[
                        {
                            label: t('stats.twilio'),
                            value: stats.twilio
                                ? t('common:state.configured')
                                : t('common:state.not_configured'),
                            variant: stats.twilio ? 'success' : 'muted',
                            icon: stats.twilio ? CheckCircle2 : XCircle,
                        },
                        {
                            label: t('stats.brevo'),
                            value: stats.brevo
                                ? t('common:state.configured')
                                : t('common:state.not_configured'),
                            variant: stats.brevo ? 'success' : 'muted',
                            icon: stats.brevo ? CheckCircle2 : XCircle,
                        },
                    ]}
                />

                {/* Aviso de contexto: para qué sirve esta pantalla */}
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

                {/* ───── Twilio · WhatsApp ───── */}
                <SectionCard
                    icon={MessageSquare}
                    title={t('sections.twilio.title')}
                    description={t('sections.twilio.description')}
                    badge={
                        <IntegrationStatus
                            configured={setting.twilio_configurado}
                            cleared={data.clear_twilio}
                            onToggle={() => setData('clear_twilio', !data.clear_twilio)}
                            canUpdate={canUpdate}
                        />
                    }
                >
                    <FormSection
                        index={0}
                        title=""
                        columns={2}
                        className="gap-0"
                    >
                        <FormField
                            id="platform-twilio-sid"
                            label={t('fields.twilio_sid')}
                            error={errors.twilio_sid}
                            hint={t('fields.twilio_sid_hint')}
                        >
                            <Input
                                id="platform-twilio-sid"
                                type="password"
                                value={data.twilio_sid}
                                onChange={(e) => setData('twilio_sid', e.target.value)}
                                placeholder={
                                    setting.twilio_configurado
                                        ? '••••••••'
                                        : 'ACxxxxxxxxxxxxxxxx'
                                }
                                autoComplete="new-password"
                                disabled={!canUpdate || data.clear_twilio}
                            />
                        </FormField>

                        <FormField
                            id="platform-twilio-token"
                            label={t('fields.twilio_token')}
                            error={errors.twilio_token}
                            hint={
                                setting.twilio_configurado
                                    ? t('fields.twilio_token_hint_stored')
                                    : t('fields.twilio_token_hint')
                            }
                        >
                            <Input
                                id="platform-twilio-token"
                                type="password"
                                value={data.twilio_token}
                                onChange={(e) =>
                                    setData('twilio_token', e.target.value)
                                }
                                placeholder={
                                    setting.twilio_configurado
                                        ? '••••••••'
                                        : 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'
                                }
                                autoComplete="new-password"
                                disabled={!canUpdate || data.clear_twilio}
                            />
                        </FormField>

                        <FormField
                            id="platform-twilio-from"
                            label={t('fields.twilio_default_from')}
                            error={errors.twilio_default_from}
                            hint={t('fields.twilio_default_from_hint')}
                            className="sm:col-span-2"
                        >
                            <Input
                                id="platform-twilio-from"
                                value={data.twilio_default_from}
                                onChange={(e) =>
                                    setData('twilio_default_from', e.target.value)
                                }
                                placeholder="+14155238886"
                                disabled={!canUpdate}
                                className="font-mono tabular-nums"
                            />
                        </FormField>
                    </FormSection>
                </SectionCard>

                {/* ───── Brevo · Correo ───── */}
                <SectionCard
                    icon={Mail}
                    title={t('sections.brevo.title')}
                    description={t('sections.brevo.description')}
                    badge={
                        <IntegrationStatus
                            configured={setting.brevo_configurado}
                            cleared={data.clear_brevo}
                            onToggle={() => setData('clear_brevo', !data.clear_brevo)}
                            canUpdate={canUpdate}
                        />
                    }
                >
                    <FormSection
                        index={1}
                        title=""
                        columns={2}
                        className="gap-0"
                    >
                        <FormField
                            id="platform-brevo-api-key"
                            label={t('fields.brevo_api_key')}
                            error={errors.brevo_api_key}
                            hint={
                                setting.brevo_configurado
                                    ? t('fields.brevo_api_key_hint_stored')
                                    : t('fields.brevo_api_key_hint')
                            }
                            className="sm:col-span-2"
                        >
                            <Input
                                id="platform-brevo-api-key"
                                type="password"
                                value={data.brevo_api_key}
                                onChange={(e) =>
                                    setData('brevo_api_key', e.target.value)
                                }
                                placeholder={
                                    setting.brevo_configurado
                                        ? '••••••••••••'
                                        : 'xkeysib-xxxx...'
                                }
                                autoComplete="new-password"
                                disabled={!canUpdate || data.clear_brevo}
                            />
                        </FormField>

                        <FormField
                            id="platform-brevo-from-email"
                            label={t('fields.brevo_default_from_email')}
                            error={errors.brevo_default_from_email}
                            hint={t('fields.brevo_default_from_email_hint')}
                        >
                            <Input
                                id="platform-brevo-from-email"
                                type="email"
                                value={data.brevo_default_from_email}
                                onChange={(e) =>
                                    setData(
                                        'brevo_default_from_email',
                                        e.target.value,
                                    )
                                }
                                placeholder="no-reply@vetsaas.com"
                                autoComplete="email"
                                disabled={!canUpdate}
                            />
                        </FormField>

                        <FormField
                            id="platform-brevo-from-name"
                            label={t('fields.brevo_default_from_name')}
                            error={errors.brevo_default_from_name}
                            hint={t('fields.brevo_default_from_name_hint')}
                        >
                            <Input
                                id="platform-brevo-from-name"
                                value={data.brevo_default_from_name}
                                onChange={(e) =>
                                    setData('brevo_default_from_name', e.target.value)
                                }
                                placeholder="VetSaaS"
                                disabled={!canUpdate}
                            />
                        </FormField>
                    </FormSection>
                </SectionCard>

                {canUpdate && (
                    <div className="fixed inset-x-0 bottom-0 z-40 border-t border-border/60 bg-card/95 px-4 py-3 backdrop-blur-md sm:px-6">
                        <div className="mx-auto flex max-w-7xl items-center justify-between gap-3">
                            <div className="flex min-w-0 items-center gap-2 text-xs text-muted-foreground">
                                <ShieldCheck
                                    className="size-4 shrink-0 text-primary/70"
                                    strokeWidth={2.25}
                                />
                                <span className="truncate">
                                    {setting.actualizado_por
                                        ? t('footer.last_updated_by', {
                                              name: setting.actualizado_por.name,
                                          })
                                        : t('footer.never_updated')}
                                </span>
                            </div>

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
                                    <CheckCircle2
                                        className="size-4"
                                        strokeWidth={2.5}
                                    />
                                ) : (
                                    <Save className="size-4" strokeWidth={2.5} />
                                )}
                                {recentlySuccessful
                                    ? t('actions.saved')
                                    : t('actions.save')}
                            </Button>
                        </div>
                    </div>
                )}
            </form>
        </>
    );
}

type IntegrationStatusProps = {
    configured: boolean;
    cleared: boolean;
    onToggle: () => void;
    canUpdate: boolean;
};

/**
 * Estado de una integración global + botón para limpiar credenciales.
 *
 * Si la integración no está configurada se oculta el botón de borrar
 * (no hay nada que borrar). Si está configurada y el usuario puede
 * editar, mostramos el toggle "borrar/conservar" para que pueda
 * marcar el cambio antes de guardar.
 */
function IntegrationStatus({
    configured,
    cleared,
    onToggle,
    canUpdate,
}: IntegrationStatusProps): ReactNode {
    const { t } = useTranslation(['platform', 'common']);

    return (
        <div className="flex items-center gap-2">
            <StatBadge
                label=""
                value={
                    configured
                        ? t('common:state.configured')
                        : t('common:state.not_configured')
                }
                variant={configured ? 'success' : 'muted'}
                icon={configured ? CheckCircle2 : XCircle}
            />
            {configured && canUpdate && (
                <Button
                    type="button"
                    variant={cleared ? 'destructive' : 'outline'}
                    size="sm"
                    onClick={onToggle}
                    className="h-7 cursor-pointer text-xs"
                >
                    {cleared
                        ? t('integrations.keep_credentials')
                        : t('integrations.clear_credentials')}
                </Button>
            )}
        </div>
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
