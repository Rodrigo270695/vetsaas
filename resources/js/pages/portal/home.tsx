import { Head, router } from '@inertiajs/react';
import { CalendarDays, PawPrint, Scissors, Stethoscope, Syringe } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { PortalInstallButtons } from '@/components/portal/portal-install-buttons';
import { PortalPushToggle } from '@/components/portal/portal-push-toggle';
import { PortalThemeToggle } from '@/components/portal/portal-theme-toggle';

type Mascota = {
    id: string;
    nombre: string;
    foto_url: string | null;
    especie: string | null;
    raza: string | null;
};

type Cita = { id: string; inicio_at: string; motivo: string | null; estado: string };

type Home = {
    saludo: string;
    mascotas: Mascota[];
    mascota: Mascota | null;
    citas: { proxima: Cita | null; historial: Cita[] };
    grooming: Array<{
        id: string;
        inicio_at: string;
        estado: string;
        servicio: string | null;
        notas: string | null;
        fotos: Array<{ id: string; tipo: string; url: string | null }>;
    }>;
    consultas: Array<{
        id: string;
        atendido_at: string;
        motivo: string | null;
        plan: string | null;
        analisis: string | null;
        medico: string | null;
        peso_kg: string | null;
    }>;
    vacunas: {
        proxima: { nombre: string; fecha: string | null; categoria: string | null } | null;
        historial: Array<{
            nombre: string;
            aplicada_at: string;
            proxima: string | null;
            categoria: string | null;
        }>;
    };
    avisos: Array<{
        id: string;
        tipo: string;
        titulo: string;
        cuerpo: string;
        created_at: string | null;
        leido: boolean;
    }>;
};

type Props = {
    clinic: { nombre: string; logo_url: string | null };
    home: Home;
    push: { enabled: boolean; vapid: string; subscribe_url: string; unsubscribe_url: string };
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
            year: 'numeric',
        }).format(new Date(iso.includes('T') ? iso : `${iso}T12:00:00`));
    } catch {
        return iso;
    }
}

export default function PortalHome({ clinic, home, push, urls }: Props) {
    const { t } = useTranslation('portal-propietario');
    const pet = home.mascota;
    const estado = (key: string) => t(`home.estado.${key}`, { defaultValue: key });

    return (
        <>
            <Head title={pet?.nombre ?? clinic.nombre} />
            <div className="min-h-dvh bg-linear-to-br from-teal-50 via-sky-50 to-amber-50 dark:from-slate-950 dark:via-teal-950 dark:to-slate-900">
                <header className="flex items-center justify-between gap-3 px-4 py-3 sm:px-8">
                    <div className="flex items-center gap-3">
                        {clinic.logo_url ? (
                            <img src={clinic.logo_url} alt="" className="h-9 w-auto object-contain" />
                        ) : (
                            <PawPrint className="size-8 text-teal-700 dark:text-teal-300" />
                        )}
                        <div>
                            <p className="text-xs font-medium tracking-wide text-teal-800 uppercase dark:text-teal-200">
                                {clinic.nombre}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {t('home.hello', { name: home.saludo })}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <PortalThemeToggle />
                        <button
                            type="button"
                            className="text-xs text-muted-foreground underline-offset-4 hover:underline"
                            onClick={() => router.post(urls.logout)}
                        >
                            {t('home.logout')}
                        </button>
                    </div>
                </header>

                <main className="mx-auto grid max-w-7xl gap-6 px-4 pb-16 lg:grid-cols-12 lg:gap-10 lg:px-8">
                    <section className="lg:col-span-5">
                        <div className="overflow-hidden rounded-4xl bg-teal-900 shadow-2xl ring-4 ring-white/70 dark:ring-white/10">
                            <div className="relative min-h-[48vh] lg:min-h-[calc(100dvh-8rem)]">
                                {pet?.foto_url ? (
                                    <img
                                        src={pet.foto_url}
                                        alt=""
                                        className="absolute inset-0 size-full object-cover"
                                    />
                                ) : (
                                    <div className="flex h-full min-h-[48vh] items-center justify-center bg-linear-to-br from-teal-400 to-emerald-700 text-9xl lg:min-h-[calc(100dvh-8rem)]">
                                        🐾
                                    </div>
                                )}
                                <div className="absolute inset-x-0 bottom-0 bg-linear-to-t from-teal-950 via-teal-950/70 to-transparent p-6 text-white">
                                    <h1 className="text-4xl font-semibold tracking-tight sm:text-5xl">
                                        {pet?.nombre ?? '—'}
                                    </h1>
                                    <p className="mt-1 text-teal-100">
                                        {[pet?.especie, pet?.raza].filter(Boolean).join(' · ')}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="flex flex-col gap-5 lg:col-span-7">
                        {home.mascotas.length > 1 ? (
                            <div className="flex gap-2 overflow-x-auto pb-1">
                                {home.mascotas.map((m) => (
                                    <button
                                        key={m.id}
                                        type="button"
                                        onClick={() =>
                                            router.get(urls.home, { mascota: m.id }, { preserveState: true })
                                        }
                                        className={`rounded-full px-4 py-2 text-sm font-medium whitespace-nowrap ${
                                            pet?.id === m.id
                                                ? 'bg-teal-700 text-white'
                                                : 'bg-white/80 text-teal-900 shadow-sm dark:bg-white/10 dark:text-teal-50'
                                        }`}
                                    >
                                        {m.nombre}
                                    </button>
                                ))}
                            </div>
                        ) : null}

                        <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                            <PortalInstallButtons />
                            <PortalPushToggle
                                enabled={push.enabled}
                                vapid={push.vapid}
                                subscribeUrl={push.subscribe_url}
                            />
                        </div>

                        {home.avisos.length > 0 ? (
                            <Card tone="amber" title={t('home.avisos')} icon={<CalendarDays className="size-4" />}>
                                <ul className="space-y-2">
                                    {home.avisos.slice(0, 5).map((a) => (
                                        <li key={a.id}>
                                            <p className="font-medium">{a.titulo}</p>
                                            <p className="text-sm text-muted-foreground">{a.cuerpo}</p>
                                        </li>
                                    ))}
                                </ul>
                            </Card>
                        ) : null}

                        {home.mascotas.length === 0 ? (
                            <p className="text-sm text-muted-foreground">{t('home.no_pets')}</p>
                        ) : (
                            <>
                                <Card tone="teal" title={t('home.visits')} icon={<CalendarDays className="size-4" />}>
                                    {home.citas.proxima ? (
                                        <p className="mb-3 text-lg font-medium">
                                            {formatCita(home.citas.proxima.inicio_at)}
                                            <span className="ml-2 text-sm font-normal text-teal-800 dark:text-teal-200">
                                                {estado(home.citas.proxima.estado)}
                                            </span>
                                        </p>
                                    ) : (
                                        <p className="mb-3 text-muted-foreground">{t('home.no_visit')}</p>
                                    )}
                                    <ul className="space-y-2 border-t border-teal-100 pt-3 dark:border-teal-800">
                                        {home.citas.historial.length === 0 ? (
                                            <li className="text-sm text-muted-foreground">{t('home.no_visit')}</li>
                                        ) : (
                                            home.citas.historial.map((c) => (
                                                <li key={c.id} className="flex justify-between gap-3 text-sm">
                                                    <span>
                                                        {formatCita(c.inicio_at)}
                                                        {c.motivo ? ` · ${c.motivo}` : ''}
                                                    </span>
                                                    <span className="shrink-0 text-muted-foreground">
                                                        {estado(c.estado)}
                                                    </span>
                                                </li>
                                            ))
                                        )}
                                    </ul>
                                </Card>

                                <Card tone="rose" title={t('home.grooming')} icon={<Scissors className="size-4" />}>
                                    {home.grooming.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">{t('home.no_grooming')}</p>
                                    ) : (
                                        <ul className="space-y-4">
                                            {home.grooming.map((g) => (
                                                <li key={g.id}>
                                                    <p className="font-medium">
                                                        {formatCita(g.inicio_at)} · {estado(g.estado)}
                                                    </p>
                                                    {g.servicio ? (
                                                        <p className="text-sm text-muted-foreground">{g.servicio}</p>
                                                    ) : null}
                                                    {g.fotos.length > 0 ? (
                                                        <div className="mt-2 flex gap-2 overflow-x-auto">
                                                            {g.fotos.map((f) =>
                                                                f.url ? (
                                                                    <figure key={f.id} className="shrink-0">
                                                                        <img
                                                                            src={f.url}
                                                                            alt=""
                                                                            className="h-28 w-28 rounded-2xl object-cover ring-2 ring-white"
                                                                        />
                                                                        <figcaption className="mt-1 text-center text-[10px] text-muted-foreground">
                                                                            {f.tipo === 'final'
                                                                                ? t('home.photo_final')
                                                                                : t('home.photo_proceso')}
                                                                        </figcaption>
                                                                    </figure>
                                                                ) : null,
                                                            )}
                                                        </div>
                                                    ) : null}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </Card>

                                <Card tone="sky" title={t('home.hc')} icon={<Stethoscope className="size-4" />}>
                                    {home.consultas.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">{t('home.no_hc')}</p>
                                    ) : (
                                        <ul className="space-y-3">
                                            {home.consultas.map((c) => (
                                                <li key={c.id} className="rounded-2xl bg-white/60 p-3 dark:bg-white/5">
                                                    <p className="text-sm font-medium">
                                                        {formatFecha(c.atendido_at)}
                                                        {c.motivo ? ` · ${c.motivo}` : ''}
                                                    </p>
                                                    {c.medico ? (
                                                        <p className="text-xs text-muted-foreground">{c.medico}</p>
                                                    ) : null}
                                                    {c.plan ? (
                                                        <p className="mt-1 text-sm whitespace-pre-wrap">{c.plan}</p>
                                                    ) : null}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </Card>

                                <Card tone="lime" title={t('home.vaccines')} icon={<Syringe className="size-4" />}>
                                    {home.vacunas.proxima ? (
                                        <p className="mb-2 font-medium">
                                            {home.vacunas.proxima.nombre}
                                            {home.vacunas.proxima.fecha
                                                ? ` · ${formatFecha(home.vacunas.proxima.fecha)}`
                                                : ''}
                                        </p>
                                    ) : (
                                        <p className="mb-2 text-muted-foreground">{t('home.no_vaccine')}</p>
                                    )}
                                    <ul className="space-y-1 text-sm">
                                        {home.vacunas.historial.map((v) => (
                                            <li key={v.nombre + v.aplicada_at}>
                                                {v.nombre} · {formatFecha(v.aplicada_at)}
                                            </li>
                                        ))}
                                    </ul>
                                </Card>
                            </>
                        )}
                    </section>
                </main>
            </div>
        </>
    );
}

function Card({
    title,
    icon,
    tone,
    children,
}: {
    title: string;
    icon: React.ReactNode;
    tone: 'teal' | 'rose' | 'sky' | 'lime' | 'amber';
    children: ReactNode;
}) {
    const tones = {
        teal: 'bg-teal-50/90 ring-teal-100 dark:bg-teal-950/50 dark:ring-teal-800',
        rose: 'bg-rose-50/90 ring-rose-100 dark:bg-rose-950/40 dark:ring-rose-800',
        sky: 'bg-sky-50/90 ring-sky-100 dark:bg-sky-950/40 dark:ring-sky-800',
        lime: 'bg-lime-50/90 ring-lime-100 dark:bg-lime-950/40 dark:ring-lime-800',
        amber: 'bg-amber-50/90 ring-amber-100 dark:bg-amber-950/40 dark:ring-amber-800',
    };

    return (
        <article className={`rounded-3xl p-5 shadow-sm ring-1 ${tones[tone]}`}>
            <div className="mb-3 flex items-center gap-2 text-sm font-semibold tracking-wide uppercase">
                {icon}
                {title}
            </div>
            {children}
        </article>
    );
}
