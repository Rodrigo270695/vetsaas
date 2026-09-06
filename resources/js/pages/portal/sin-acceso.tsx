import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { PortalThemeToggle } from '@/components/portal/portal-theme-toggle';

type Props = {
    clinic: { nombre: string; logo_url: string | null };
};

export default function PortalSinAcceso({ clinic }: Props) {
    const { t } = useTranslation('portal-propietario');

    return (
        <>
            <Head title={clinic.nombre} />
            <div className="relative flex min-h-dvh flex-col items-center justify-center bg-linear-to-br from-brand-50 via-brand-100 to-white px-8 text-center dark:from-slate-950 dark:via-brand-950 dark:to-slate-900">
                <div className="absolute top-4 right-4">
                    <PortalThemeToggle />
                </div>
                {clinic.logo_url ? (
                    <img src={clinic.logo_url} alt="" className="mb-6 h-14 w-auto object-contain" />
                ) : (
                    <div className="mb-6 text-6xl">🐾</div>
                )}
                <h1 className="max-w-md text-3xl font-semibold tracking-tight text-brand-950 dark:text-brand-50">
                    {t('sin_acceso.title')}
                </h1>
                <p className="mt-3 max-w-md text-sm leading-relaxed text-muted-foreground">
                    {t('sin_acceso.body')}
                </p>
                <p className="mt-8 text-xs text-muted-foreground">{clinic.nombre}</p>
            </div>
        </>
    );
}
