import { Head, router, useForm } from '@inertiajs/react';
import { PawPrint } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PortalPetCover } from '@/components/portal/portal-pet-cover';
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
    mascota: { nombre: string; foto_url: string | null; especie?: string | null } | null;
    mascotas_count?: number;
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
    mascotas_count = 0,
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
    const [pinError, setPinError] = useState<string | null>(null);
    const [pinBusy, setPinBusy] = useState(false);
    const onlyOnePet = mascotas_count === 1 && Boolean(mascota?.nombre);
    const headline = onlyOnePet ? mascota!.nombre : t('entrar.pets_title');
    const pinHint = onlyOnePet
        ? t('entrar.pin_subtitle', { name: saludo, pet: mascota!.nombre })
        : t('entrar.pin_subtitle_generic', { name: saludo });

    return (
        <>
            <Head title={clinic.nombre} />
            <div className="relative min-h-dvh bg-brand-700 lg:bg-linear-to-br lg:from-brand-50 lg:via-brand-100 lg:to-white dark:lg:from-brand-950 dark:lg:via-slate-950 dark:lg:to-slate-900">
                <div className="pointer-events-none absolute inset-0 hidden overflow-hidden lg:block" aria-hidden>
                    <div className="absolute -top-24 -right-16 size-80 rounded-full bg-brand-300/40 blur-3xl dark:bg-brand-500/20" />
                    <div className="absolute -bottom-20 -left-10 size-96 rounded-full bg-brand-200/40 blur-3xl dark:bg-brand-600/15" />
                </div>

                <div className="relative mx-auto grid min-h-dvh max-w-6xl lg:grid-cols-2">
                    <section className="hidden flex-col justify-between p-10 lg:flex xl:p-14">
                        <div className="flex items-center gap-3">
                            {clinic.logo_url ? (
                                <img src={clinic.logo_url} alt="" className="h-12 w-auto object-contain" />
                            ) : (
                                <PawPrint className="size-10 text-brand-700 dark:text-brand-300" />
                            )}
                            <p className="text-sm font-semibold tracking-wide text-brand-900 uppercase dark:text-brand-100">
                                {clinic.nombre}
                            </p>
                        </div>
                        <div className="relative overflow-hidden rounded-[2.5rem] shadow-2xl ring-4 ring-white/60 dark:ring-white/10">
                            <div className="aspect-4/5 w-full">
                                <PortalPetCover
                                    nombre={mascota?.nombre ?? clinic.nombre}
                                    fotoUrl={mascota?.foto_url ?? null}
                                    especie={mascota?.especie}
                                />
                            </div>
                            <div className="absolute inset-x-0 bottom-0 bg-linear-to-t from-brand-950/85 to-transparent p-8 text-white">
                                <p className="text-sm text-brand-100">{t('entrar.kicker')}</p>
                                <p className="mt-1 text-4xl font-semibold">{headline}</p>
                            </div>
                        </div>
                    </section>

                    <section className="flex min-h-dvh flex-col lg:justify-center lg:px-10 lg:py-12">
                        <div className="relative flex flex-col items-center px-5 pb-5 pt-[max(0.75rem,env(safe-area-inset-top))] text-center text-white lg:hidden">
                            <div className="absolute top-[max(0.5rem,env(safe-area-inset-top))] right-3 z-20">
                                <PortalThemeToggle />
                            </div>
                            {clinic.logo_url ? (
                                <div className="mb-3 rounded-full bg-white/95 px-3 py-1.5 shadow-sm">
                                    <img src={clinic.logo_url} alt="" className="h-7 w-auto object-contain" />
                                </div>
                            ) : (
                                <PawPrint className="mb-3 size-8 text-white/90" />
                            )}
                            <div className="size-[5.75rem] overflow-hidden rounded-full ring-4 ring-white/40 shadow-xl">
                                <PortalPetCover
                                    nombre={mascota?.nombre ?? clinic.nombre}
                                    fotoUrl={mascota?.foto_url ?? null}
                                    especie={mascota?.especie}
                                />
                            </div>
                            <p className="mt-3 text-[11px] font-bold tracking-[0.18em] text-white/70 uppercase">
                                {clinic.nombre}
                            </p>
                            <h1 className="mt-1 max-w-[16rem] truncate text-2xl font-bold tracking-tight">
                                {headline}
                            </h1>
                            <p className="mt-1 text-sm text-white/80">
                                {t('entrar.hello_short', { name: saludo })}
                            </p>
                        </div>

                        <div className="relative z-10 mx-auto flex w-full max-w-md flex-1 flex-col rounded-t-[1.85rem] bg-white px-6 pt-6 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-[0_-18px_40px_rgba(0,40,35,0.18)] lg:flex-none lg:rounded-[2rem] lg:p-8 lg:shadow-xl dark:bg-slate-900">
                            <div className="mb-5 hidden items-center justify-between lg:flex">
                                {clinic.logo_url ? (
                                    <img src={clinic.logo_url} alt="" className="h-9 w-auto object-contain" />
                                ) : (
                                    <span />
                                )}
                                <PortalThemeToggle />
                            </div>

                            {mode === 'setup' && (
                                <form
                                    className="space-y-5 text-center"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        if (
                                            setup.data.pin.length === 4 &&
                                            setup.data.pin === setup.data.pin_confirmation
                                        ) {
                                            setup.post(urls.setup);
                                        }
                                    }}
                                >
                                    <h2 className="text-2xl font-bold tracking-tight text-brand-950 dark:text-brand-50">
                                        {t('entrar.setup_title')}
                                    </h2>
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
                                        className="h-12 w-full cursor-pointer rounded-2xl bg-brand-600 text-base text-white hover:bg-brand-700"
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
                                    className="flex flex-1 flex-col text-center"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        if (unlock.data.pin.length === 4) {
                                            unlock.post(urls.unlock);
                                        }
                                    }}
                                >
                                    <h2 className="text-xl font-bold tracking-tight text-slate-900 dark:text-white">
                                        {t('entrar.pin_title')}
                                    </h2>
                                    <p className="mt-1 mb-5 text-sm text-slate-500 dark:text-slate-400">
                                        {pinHint}
                                    </p>
                                    <PortalPinInput
                                        variant="lock"
                                        keypad
                                        invalid={Boolean(pinError)}
                                        value={unlock.data.pin}
                                        onChange={(pin) => {
                                            unlock.setData('pin', pin);
                                            if (pinError) {
                                                setPinError(null);
                                            }
                                        }}
                                        disabled={pinBusy || unlock.processing}
                                        onComplete={(pin) => {
                                            if (pinBusy || unlock.processing || pin.length !== 4) {
                                                return;
                                            }
                                            setPinBusy(true);
                                            setPinError(null);
                                            unlock.setData('pin', pin);
                                            router.post(urls.unlock, { pin }, {
                                                preserveScroll: true,
                                                onError: (errors) => {
                                                    const raw = errors.pin;
                                                    const msg = Array.isArray(raw) ? raw[0] : raw;
                                                    setPinError(
                                                        typeof msg === 'string' && msg !== ''
                                                            ? msg
                                                            : t('entrar.pin_wrong'),
                                                    );
                                                    unlock.setData('pin', '');
                                                },
                                                onFinish: () => setPinBusy(false),
                                            });
                                        }}
                                        ariaLabel={t('entrar.pin_title')}
                                    />
                                    {pinError ? (
                                        <p
                                            className="mt-3 text-sm font-medium text-red-600 dark:text-red-400"
                                            role="alert"
                                        >
                                            {pinError === 'PIN incorrecto.'
                                                ? t('entrar.pin_wrong')
                                                : pinError}
                                        </p>
                                    ) : null}
                                    <button
                                        type="button"
                                        className="mt-auto cursor-pointer pt-6 text-sm font-semibold text-brand-700 dark:text-brand-300"
                                        onClick={() => setMode('reset')}
                                    >
                                        {t('entrar.forgot')}
                                    </button>
                                </form>
                            )}

                            {mode === 'reset' && (
                                <div className="space-y-4 text-center">
                                    <h2 className="text-2xl font-bold tracking-tight">
                                        {t('entrar.reset_title')}
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {t('entrar.reset_hint', { phone: telefono_mascara })}
                                    </p>
                                    {resetSend.errors.reset ? (
                                        <p className="text-sm text-destructive">{resetSend.errors.reset}</p>
                                    ) : null}
                                    <Button
                                        variant="outline"
                                        className="h-12 w-full cursor-pointer rounded-2xl"
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
                                        className="h-12 w-full cursor-pointer rounded-2xl bg-brand-600 text-white hover:bg-brand-700"
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
                                        className="cursor-pointer text-sm font-medium text-muted-foreground"
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
