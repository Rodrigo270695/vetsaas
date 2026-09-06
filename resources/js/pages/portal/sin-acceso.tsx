import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

type Props = {
    clinic: { nombre: string; logo_url: string | null };
};

export default function PortalSinAcceso({ clinic }: Props) {
    const { t } = useTranslation('portal-propietario');

    return (
        <>
            <Head title={clinic.nombre} />
            <div className="mx-auto flex min-h-dvh max-w-md flex-col items-center justify-center px-8 text-center">
                {clinic.logo_url ? (
                    <img src={clinic.logo_url} alt="" className="mb-6 h-12 w-auto object-contain" />
                ) : (
                    <div className="mb-6 text-5xl">🐾</div>
                )}
                <h1 className="text-2xl font-semibold tracking-tight">{t('sin_acceso.title')}</h1>
                <p className="mt-3 text-sm leading-relaxed text-muted-foreground">
                    {t('sin_acceso.body')}
                </p>
                <p className="mt-8 text-xs text-muted-foreground">{clinic.nombre}</p>
            </div>
        </>
    );
}
