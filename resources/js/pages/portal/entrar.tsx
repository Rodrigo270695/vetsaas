import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PortalPinInput } from '@/components/portal/portal-pin-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Clinic = { nombre: string; logo_url: string | null };

type Props = {
    token: string;
    step: 'setup' | 'pin';
    clinic: Clinic;
    saludo: string;
    mascota: { nombre: string; foto_url: string | null } | null;
    telefono_mascara: string;
    urls: {
        setup: string;
        unlock: string;
        reset_send: string;
        reset_confirm: string;
    };
};

export default function PortalEntrar({
    step: initialStep,
    clinic,
    saludo,
    mascota,
    telefono_mascara,
    urls,
}: Props) {
    const { t } = useTranslation('portal-propietario');
    const [mode, setMode] = useState<'setup' | 'pin' | 'reset'>(
        initialStep === 'setup' ? 'setup' : 'pin',
    );

    const setup = useForm({ pin: '', pin_confirmation: '' });
    const unlock = useForm({ pin: '' });
    const resetSend = useForm({});
    const resetConfirm = useForm({ code: '', pin: '', pin_confirmation: '' });

    return (
        <>
            <Head title={clinic.nombre} />
            <div className="mx-auto flex min-h-dvh max-w-md flex-col px-6 pb-10 pt-8">
                <header className="mb-8 flex flex-col items-center gap-3 text-center">
                    {clinic.logo_url ? (
                        <img
                            src={clinic.logo_url}
                            alt=""
                            className="h-10 w-auto object-contain"
                        />
                    ) : null}
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {clinic.nombre}
                    </p>
                </header>

                {mascota?.foto_url ? (
                    <img
                        src={mascota.foto_url}
                        alt=""
                        className="mx-auto mb-6 size-36 rounded-full object-cover shadow-lg ring-4 ring-white dark:ring-white/10"
                    />
                ) : (
                    <div className="mx-auto mb-6 flex size-36 items-center justify-center rounded-full bg-primary/15 text-5xl shadow-inner">
                        🐾
                    </div>
                )}

                {mode === 'setup' && (
                    <div className="space-y-6 text-center">
                        <div>
                            <h1 className="text-3xl font-semibold tracking-tight">
                                {t('entrar.setup_title')}
                            </h1>
                            <p className="mt-2 text-sm text-muted-foreground">
                                {t('entrar.setup_subtitle')}
                            </p>
                        </div>
                        <PortalPinInput
                            value={setup.data.pin}
                            onChange={(pin) => setup.setData('pin', pin)}
                            ariaLabel={t('entrar.setup_title')}
                        />
                        <p className="text-xs text-muted-foreground">{t('entrar.confirm')}</p>
                        <PortalPinInput
                            value={setup.data.pin_confirmation}
                            onChange={(pin) => setup.setData('pin_confirmation', pin)}
                            autoFocus={false}
                            ariaLabel={t('entrar.confirm')}
                        />
                        {setup.errors.pin ? (
                            <p className="text-sm text-destructive">{setup.errors.pin}</p>
                        ) : null}
                        {setup.errors.pin_confirmation ? (
                            <p className="text-sm text-destructive">
                                {setup.errors.pin_confirmation}
                            </p>
                        ) : null}
                        <Button
                            className="h-12 w-full rounded-2xl text-base"
                            disabled={
                                setup.processing ||
                                setup.data.pin.length !== 4 ||
                                setup.data.pin_confirmation.length !== 4
                            }
                            onClick={() => setup.post(urls.setup)}
                        >
                            {setup.processing ? t('entrar.creating') : t('entrar.continue')}
                        </Button>
                    </div>
                )}

                {mode === 'pin' && (
                    <div className="space-y-6 text-center">
                        <div>
                            <h1 className="text-3xl font-semibold tracking-tight">
                                {t('entrar.pin_title')}
                            </h1>
                            <p className="mt-2 text-sm text-muted-foreground">
                                {mascota
                                    ? t('entrar.pin_subtitle', {
                                          name: saludo,
                                          pet: mascota.nombre,
                                      })
                                    : t('entrar.pin_subtitle_generic', { name: saludo })}
                            </p>
                        </div>
                        <PortalPinInput
                            value={unlock.data.pin}
                            onChange={(pin) => unlock.setData('pin', pin)}
                            ariaLabel={t('entrar.pin_title')}
                        />
                        {unlock.errors.pin ? (
                            <p className="text-sm text-destructive">{unlock.errors.pin}</p>
                        ) : null}
                        <Button
                            className="h-12 w-full rounded-2xl text-base"
                            disabled={unlock.processing || unlock.data.pin.length !== 4}
                            onClick={() => unlock.post(urls.unlock)}
                        >
                            {t('entrar.continue')}
                        </Button>
                        <button
                            type="button"
                            className="text-sm text-muted-foreground underline-offset-4 hover:underline"
                            onClick={() => setMode('reset')}
                        >
                            {t('entrar.forgot')}
                        </button>
                    </div>
                )}

                {mode === 'reset' && (
                    <div className="space-y-5 text-center">
                        <h1 className="text-3xl font-semibold tracking-tight">
                            {t('entrar.reset_title')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('entrar.reset_hint', { phone: telefono_mascara })}
                        </p>
                        {resetSend.errors.reset ? (
                            <p className="text-sm text-destructive">{resetSend.errors.reset}</p>
                        ) : null}
                        <Button
                            variant="outline"
                            className="h-12 w-full rounded-2xl"
                            disabled={resetSend.processing}
                            onClick={() => resetSend.post(urls.reset_send)}
                        >
                            {resetSend.processing ? t('entrar.sending') : t('entrar.send_code')}
                        </Button>
                        <Input
                            inputMode="numeric"
                            maxLength={6}
                            placeholder={t('entrar.code_label')}
                            className="h-12 rounded-2xl text-center text-lg tracking-[0.4em]"
                            value={resetConfirm.data.code}
                            onChange={(e) =>
                                resetConfirm.setData(
                                    'code',
                                    e.target.value.replace(/\D/g, '').slice(0, 6),
                                )
                            }
                        />
                        <PortalPinInput
                            value={resetConfirm.data.pin}
                            onChange={(pin) => resetConfirm.setData('pin', pin)}
                            autoFocus={false}
                            ariaLabel={t('entrar.new_pin')}
                        />
                        <p className="text-xs text-muted-foreground">{t('entrar.confirm')}</p>
                        <PortalPinInput
                            value={resetConfirm.data.pin_confirmation}
                            onChange={(pin) =>
                                resetConfirm.setData('pin_confirmation', pin)
                            }
                            autoFocus={false}
                            ariaLabel={t('entrar.confirm')}
                        />
                        {resetConfirm.errors.code ? (
                            <p className="text-sm text-destructive">
                                {resetConfirm.errors.code}
                            </p>
                        ) : null}
                        <Button
                            className="h-12 w-full rounded-2xl text-base"
                            disabled={
                                resetConfirm.processing ||
                                resetConfirm.data.code.length !== 6 ||
                                resetConfirm.data.pin.length !== 4
                            }
                            onClick={() => resetConfirm.post(urls.reset_confirm)}
                        >
                            {t('entrar.save_pin')}
                        </Button>
                        <button
                            type="button"
                            className="text-sm text-muted-foreground underline-offset-4 hover:underline"
                            onClick={() => setMode('pin')}
                        >
                            {t('entrar.back_to_pin')}
                        </button>
                    </div>
                )}
            </div>
        </>
    );
}
