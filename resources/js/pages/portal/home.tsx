import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

type Mascota = {
    id: string;
    nombre: string;
    foto_url: string | null;
    especie: string | null;
    raza: string | null;
};

type Home = {
    saludo: string;
    mascotas: Mascota[];
    mascota: Mascota | null;
    cita: { inicio_at: string; motivo: string | null; estado: string } | null;
    vacuna: { nombre: string; fecha: string | null; categoria: string | null } | null;
};

type Props = {
    clinic: { nombre: string; logo_url: string | null };
    home: Home;
    urls: { home: string; logout: string };
};

function formatCita(iso: string): string {
    try {
        return new Intl.DateTimeFormat('es-PE', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(iso));
    } catch {
        return iso;
    }
}

function formatFecha(iso: string | null): string {
    if (!iso) {
        return '';
    }
    try {
        return new Intl.DateTimeFormat('es-PE', {
            day: 'numeric',
            month: 'short',
        }).format(new Date(`${iso}T12:00:00`));
    } catch {
        return iso;
    }
}

export default function PortalHome({ clinic, home, urls }: Props) {
    const { t } = useTranslation('portal-propietario');
    const pet = home.mascota;

    return (
        <>
            <Head title={pet?.nombre ?? clinic.nombre} />
            <div className="mx-auto flex min-h-dvh max-w-lg flex-col">
                <div className="relative min-h-[52vh] overflow-hidden bg-neutral-200 dark:bg-neutral-800">
                    {pet?.foto_url ? (
                        <img
                            src={pet.foto_url}
                            alt=""
                            className="absolute inset-0 size-full object-cover"
                        />
                    ) : (
                        <div className="flex h-full min-h-[52vh] items-end justify-center bg-linear-to-b from-primary/40 to-[hsl(40_33%_97%)] pb-8 text-8xl">
                            🐾
                        </div>
                    )}
                    <div className="absolute inset-x-0 bottom-0 h-32 bg-linear-to-t from-[hsl(40_33%_97%)] to-transparent dark:from-[hsl(30_10%_8%)]" />
                    {clinic.logo_url ? (
                        <img
                            src={clinic.logo_url}
                            alt=""
                            className="absolute top-5 left-5 h-8 w-auto rounded-md bg-white/80 px-1 py-0.5 object-contain shadow-sm"
                        />
                    ) : null}
                </div>

                <div className="relative -mt-8 flex flex-1 flex-col gap-5 px-6 pb-10">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            {t('home.hello', { name: home.saludo })}
                        </p>
                        <h1 className="text-4xl font-semibold tracking-tight">
                            {pet?.nombre ?? '—'}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {[pet?.especie, pet?.raza].filter(Boolean).join(' · ') ||
                                t('home.clinic', { clinic: clinic.nombre })}
                        </p>
                    </div>

                    {home.mascotas.length > 1 ? (
                        <div className="flex gap-2 overflow-x-auto pb-1">
                            {home.mascotas.map((m) => (
                                <button
                                    key={m.id}
                                    type="button"
                                    onClick={() =>
                                        router.get(urls.home, { mascota: m.id }, { preserveState: true })
                                    }
                                    className={`rounded-full px-4 py-1.5 text-sm font-medium whitespace-nowrap transition ${
                                        pet?.id === m.id
                                            ? 'bg-foreground text-background'
                                            : 'bg-white/80 text-foreground shadow-sm dark:bg-white/10'
                                    }`}
                                >
                                    {m.nombre}
                                </button>
                            ))}
                        </div>
                    ) : null}

                    {home.mascotas.length === 0 ? (
                        <p className="text-sm text-muted-foreground">{t('home.no_pets')}</p>
                    ) : (
                        <div className="grid gap-3">
                            <article className="rounded-3xl bg-white/90 p-5 shadow-sm dark:bg-white/5">
                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    {t('home.next_visit')}
                                </p>
                                {home.cita ? (
                                    <>
                                        <p className="mt-1 text-lg font-medium">
                                            {formatCita(home.cita.inicio_at)}
                                        </p>
                                        {home.cita.motivo ? (
                                            <p className="text-sm text-muted-foreground">
                                                {home.cita.motivo}
                                            </p>
                                        ) : null}
                                    </>
                                ) : (
                                    <p className="mt-1 text-muted-foreground">{t('home.no_visit')}</p>
                                )}
                            </article>
                            <article className="rounded-3xl bg-white/90 p-5 shadow-sm dark:bg-white/5">
                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    {t('home.vaccine')}
                                </p>
                                {home.vacuna ? (
                                    <p className="mt-1 text-lg font-medium">
                                        {home.vacuna.nombre}
                                        {home.vacuna.fecha
                                            ? ` · ${formatFecha(home.vacuna.fecha)}`
                                            : ''}
                                    </p>
                                ) : (
                                    <p className="mt-1 text-muted-foreground">
                                        {t('home.no_vaccine')}
                                    </p>
                                )}
                            </article>
                        </div>
                    )}

                    <p className="text-center text-xs leading-relaxed text-muted-foreground">
                        {t('home.add_home')}
                    </p>

                    <button
                        type="button"
                        className="text-center text-xs text-muted-foreground underline-offset-4 hover:underline"
                        onClick={() => router.post(urls.logout)}
                    >
                        {t('home.logout')}
                    </button>
                </div>
            </div>
        </>
    );
}
