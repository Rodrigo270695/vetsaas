import { Head, useForm } from '@inertiajs/react';
import { PawPrint } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PortalPinInput } from '@/components/portal/portal-pin-input';
import { PortalThemeToggle } from '@/components/portal/portal-theme-toggle';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Clinic = { nombre: string; logo_url: string | null };

type Props = {
    token: string | null;
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
            <div className="relative min-h-dvh overflow-hidden bg-linear-to-br from-teal-50 via-amber-50 to-rose-50 dark:from-teal-950 dark:via-slate-950 dark:to-rose-950">
                <div
                    className="pointer-events-none absolute -top-24 -right-16 size-80 rounded-full bg-teal-300/40 blur-3xl dark:bg-teal-500/20"
                    aria-hidden
                />
                <div
                    className="pointer-events-none absolute -bottom-20 -left-10 size-96 rounded-full bg-rose-300/30 blur-3xl dark:bg-rose-600/15"
                    aria-hidden
                />
                <div className="absolute top-4 right-4 z-20">
                    <PortalThemeToggle />
                </div>

                <div className="relative mx-auto grid min-h-dvh max-w-6xl lg:grid-cols-2">
                    <section className="hidden flex-col justify-between p-10 lg:flex xl:p-14">
                        <div className="flex items-center gap-3">
                            {clinic.logo_url ? (
                                <img src={clinic.logo_url} alt="" className="h-12 w-auto object-contain" />
                            ) : (
                                <PawPrint className="size-10 text-teal-700 dark:text-teal-300" />
                            )}
                            <p className="text-sm font-semibold tracking-wide text-teal-900 uppercase dark:text-teal-100">
                                {clinic.nombre}
                            </p>
                        </div>
                        <div className="relative overflow-hidden rounded-[2.5rem] shadow-2xl ring-4 ring-white/60 dark:ring-white/10">
                            {mascota?.foto_url ? (
                                <img
                                    src={mascota.foto_url}
                                    alt=""
                                    className="aspect-4/5 w-full object-cover"
                                />
                            ) : (
                                <div className="flex aspect-4/5 items-center justify-center bg-linear-to-br from-teal-400 to-emerald-600 text-8xl">
                                    🐾
                                </div>
                            )}
                            <div className="absolute inset-x-0 bottom-0 bg-linear-to-t from-teal-950/80 to-transparent p-8 text-white">
                                <p className="text-sm text-teal-100">{t('entrar.kicker')}</p>
                                <p className="mt-1 text-4xl font-semibold">
                                    {mascota?.nombre ?? clinic.nombre}
                                </p>
                            </div>
                        </div>
                    </section>

                    <section className="flex flex-col justify-center px-5 py-12 sm:px-10">
                        <div className="mx-auto w-full max-w-md rounded-4xl bg-white/80 p-8 shadow-xl ring-1 ring-teal-900/5 backdrop-blur dark:bg-slate-900/70 dark:ring-white/10">
                            <header className="mb-6 flex flex-col items-center gap-3 text-center lg:hidden">
                                {clinic.logo_url ? (
                                    <img src={clinic.logo_url} alt="" className="h-10 w-auto object-contain" />
                                ) : null}
                                {mascota?.foto_url ? (
                                    <img
                                        src={mascota.foto_url}
                                        alt=""
                                        className="size-28 rounded-full object-cover ring-4 ring-teal-200 dark:ring-teal-700"
                                    />
                                ) : (
                                    <div className="flex size-28 items-center justify-center rounded-full bg-teal-100 text-5xl dark:bg-teal-900">
                                        🐾
                                    </div>
                                )}
                            </header>

                            {mode === 'setup' && (
                                <form
                                    className="space-y-5 text-center"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        if (setup.data.pin.length === 4 && setup.data.pin === setup.data.pin_confirmation) {
                                            setup.post(urls.setup);
                                        }
                                    }}
                                >
                                    <h1 className="text-3xl font-semibold tracking-tight text-teal-950 dark:text-teal-50">
                                        {t('entrar.setup_title')}
                                    </h1>
                                    <p className="text-sm text-muted-foreground">{t('entrar.setup_subtitle')}</p>
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
                                        onComplete={() => {
                                            if (
                                                setup.data.pin.length === 4 &&
                                                setup.data.pin_confirmation.length === 4
                                            ) {
                                                setup.post(urls.setup);
                                            }
                                        }}
                                    />
                                    {setup.errors.pin ? (
                                        <p className="text-sm text-destructive">{setup.errors.pin}</p>
                                    ) : null}
                                    <Button
                                        type="submit"
                                        className="h-12 w-full rounded-2xl bg-teal-600 text-base text-white hover:bg-teal-700"
                                        disabled={
                                            setup.processing ||
                                            setup.data.pin.length !== 4 ||
                                            setup.data.pin_confirmation.length !== 4
                                        }
                                    >
                                        {setup.processing ? t('entrar.creating') : t('entrar.continue')}
                                    </Button>
                                </form>
                            )}

                            {mode === 'pin' && (
                                <form
                                    className="space-y-5 text-center"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        if (unlock.data.pin.length === 4) {
                                            unlock.post(urls.unlock);
                                        }
                                    }}
                                >
                                    <h1 className="text-3xl font-semibold tracking-tight text-teal-950 dark:text-teal-50">
                                        {t('entrar.pin_title')}
                                    </h1>
                                    <p className="text-sm text-muted-foreground">
                                        {mascota
                                            ? t('entrar.pin_subtitle', {
                                                  name: saludo,
                                                  pet: mascota.nombre,
                                              })
                                            : t('entrar.pin_subtitle_generic', { name: saludo })}
                                    </p>
                                    <PortalPinInput
                                        value={unlock.data.pin}
                                        onChange={(pin) => unlock.setData('pin', pin)}
                                        onComplete={(pin) => {
                                            if (!unlock.processing && pin.length === 4) {
                                                unlock.setData('pin', pin);
                                                unlock.post(urls.unlock);
                                            }
                                        }}
                                        ariaLabel={t('entrar.pin_title')}
                                    />
                                    {unlock.errors.pin ? (
                                        <p className="text-sm text-destructive">{unlock.errors.pin}</p>
                                    ) : null}
                                    <Button
                                        type="submit"
                                        className="h-12 w-full rounded-2xl bg-teal-600 text-base text-white hover:bg-teal-700"
                                        disabled={unlock.processing || unlock.data.pin.length !== 4}
                                    >
                                        {t('entrar.continue')}
                                    </Button>
                                    <button
                                        type="button"
                                        className="text-sm text-teal-800 underline-offset-4 hover:underline dark:text-teal-200"
                                        onClick={() => setMode('reset')}
                                    >
                                        {t('entrar.forgot')}
                                    </button>
                                </form>
                            )}

                            {mode === 'reset' && (
                                <div className="space-y-4 text-center">
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
                                        className="h-12 w-full rounded-2xl bg-teal-600 text-white hover:bg-teal-700"
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
                    </section>
                </div>
            </div>
        </>
    );
}
