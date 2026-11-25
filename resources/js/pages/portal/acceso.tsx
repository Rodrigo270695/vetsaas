import { Head, useForm } from '@inertiajs/react';
import { PawPrint } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PortalThemeToggle } from '@/components/portal/portal-theme-toggle';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Clinic = { nombre: string; logo_url: string | null };

type Props = {
    clinic: Clinic;
    step: 'document' | 'otp';
    telefono_mascara: string | null;
    urls: {
        document: string;
        otp: string;
    };
};

export default function PortalAcceso({ clinic, step, telefono_mascara, urls }: Props) {
    const { t } = useTranslation('portal-propietario');
    const documentForm = useForm({ numero_documento: '' });
    const otpForm = useForm({ code: '' });

    return (
        <>
            <Head title={clinic.nombre} />
            <div className="relative min-h-dvh bg-brand-700 lg:bg-linear-to-br lg:from-brand-50 lg:via-brand-100 lg:to-white dark:lg:from-brand-950 dark:lg:via-slate-950 dark:lg:to-slate-900">
                <div className="relative mx-auto flex min-h-dvh max-w-md flex-col justify-center px-5 py-10">
                    <div className="mb-6 flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            {clinic.logo_url ? (
                                <img src={clinic.logo_url} alt="" className="h-10 w-auto object-contain" />
                            ) : (
                                <PawPrint className="size-9 text-brand-700 dark:text-brand-300" />
                            )}
                            <p className="text-sm font-semibold text-brand-900 dark:text-brand-100">{clinic.nombre}</p>
                        </div>
                        <PortalThemeToggle />
                    </div>

                    <div className="rounded-[1.75rem] bg-white p-6 shadow-xl dark:bg-slate-900">
                        {step === 'document' ? (
                            <form
                                className="space-y-4"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    documentForm.post(urls.document);
                                }}
                            >
                                <div className="space-y-1 text-center">
                                    <h1 className="text-xl font-bold tracking-tight">{t('landing.title')}</h1>
                                    <p className="text-sm text-muted-foreground">{t('landing.document_hint')}</p>
                                </div>
                                <div>
                                    <Input
                                        inputMode="numeric"
                                        autoComplete="off"
                                        placeholder={t('landing.document_placeholder')}
                                        value={documentForm.data.numero_documento}
                                        onChange={(e) =>
                                            documentForm.setData('numero_documento', e.target.value)
                                        }
                                        aria-invalid={Boolean(documentForm.errors.numero_documento)}
                                    />
                                    {documentForm.errors.numero_documento ? (
                                        <p className="mt-1.5 text-sm text-destructive">
                                            {documentForm.errors.numero_documento}
                                        </p>
                                    ) : null}
                                </div>
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={documentForm.processing}
                                >
                                    {documentForm.processing ? t('landing.sending') : t('landing.continue')}
                                </Button>
                                <p className="text-center text-xs text-muted-foreground">{t('landing.invite_note')}</p>
                            </form>
                        ) : (
                            <form
                                className="space-y-4"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    otpForm.post(urls.otp);
                                }}
                            >
                                <div className="space-y-1 text-center">
                                    <h1 className="text-xl font-bold tracking-tight">{t('landing.otp_title')}</h1>
                                    <p className="text-sm text-muted-foreground">
                                        {t('landing.otp_hint', { phone: telefono_mascara ?? '•••' })}
                                    </p>
                                </div>
                                <Input
                                    inputMode="numeric"
                                    maxLength={6}
                                    placeholder="000000"
                                    value={otpForm.data.code}
                                    onChange={(e) => otpForm.setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))}
                                    aria-invalid={Boolean(otpForm.errors.code)}
                                />
                                {otpForm.errors.code ? (
                                    <p className="text-sm text-destructive">{otpForm.errors.code}</p>
                                ) : null}
                                <Button type="submit" className="w-full" disabled={otpForm.processing}>
                                    {otpForm.processing ? t('landing.verifying') : t('landing.verify')}
                                </Button>
                            </form>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}