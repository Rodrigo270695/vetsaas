import { Head, router } from '@inertiajs/react';
import { LogOut, Mail, MapPin, PawPrint, Phone, UserRound } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PortalInstallButtons } from '@/components/portal/portal-install-buttons';
import { PortalPetView, type PortalPetPayload } from '@/components/portal/portal-pet-view';
import { PortalPushToggle } from '@/components/portal/portal-push-toggle';
import { PortalThemeToggle } from '@/components/portal/portal-theme-toggle';

type MascotaCard = {
    id: string;
    nombre: string;
    foto_url: string | null;
    especie: string | null;
    raza: string | null;
    sexo: string | null;
    fecha_nacimiento: string | null;
    proxima_cita: { inicio_at: string; motivo: string | null } | null;
    ultima_consulta: { atendido_at: string; motivo: string | null } | null;
};

type Overview = {
    saludo: string;
    titular: {
        nombre: string;
        telefono: string | null;
        email: string | null;
        documento: string | null;
        direccion: string | null;
    };
    mascotas: MascotaCard[];
    avisos: Array<{
        id: string;
        titulo: string;
        cuerpo: string;
        created_at: string | null;
        leido: boolean;
        tipo: string;
    }>;
};

type Props = {
    clinic: { nombre: string; logo_url: string | null };
    overview: Overview;
    pet: PortalPetPayload | null;
    filters: {
        tab: 'citas' | 'hc' | 'banos' | 'vacunas';
        desde: string | null;
        hasta: string | null;
        default_desde: string;
        default_hasta: string;
    };
    push: { enabled: boolean; vapid: string; subscribe_url: string; unsubscribe_url: string };
    urls: { home: string; logout: string };
};

function formatCita(iso: string): string {
    try {
        return new Intl.DateTimeFormat('es-PE', {
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(iso));
    } catch {
        return iso;
    }
}

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase() ?? '')
        .join('');
}

export default function PortalHome({ clinic, overview, pet, filters, push, urls }: Props) {
    const { t } = useTranslation('portal-propietario');

    return (
        <>
            <Head title={pet?.mascota.nombre ?? clinic.nombre} />
            <div className="min-h-dvh bg-linear-to-br from-teal-50 via-sky-50 to-amber-50 dark:from-slate-950 dark:via-teal-950 dark:to-slate-900">
                <header className="sticky top-0 z-20 border-b border-teal-900/8 bg-white/75 px-3 py-2.5 backdrop-blur-md sm:px-6 dark:border-white/10 dark:bg-slate-950/70">
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-3">
                        <div className="flex min-w-0 items-center gap-3">
                            {clinic.logo_url ? (
                                <img src={clinic.logo_url} alt="" className="h-9 w-auto object-contain" />
                            ) : (
                                <PawPrint className="size-8 shrink-0 text-teal-700 dark:text-teal-300" />
                            )}
                            <div className="min-w-0">
                                <p className="truncate text-xs font-semibold tracking-wide text-teal-800 uppercase dark:text-teal-200">
                                    {clinic.nombre}
                                </p>
                                <p className="truncate text-sm text-muted-foreground">
                                    {t('home.hello', { name: overview.saludo })}
                                </p>
                            </div>
                        </div>
                        <div className="flex shrink-0 items-center gap-1.5 sm:gap-2">
                            <PortalInstallButtons />
                            <PortalPushToggle
                                enabled={push.enabled}
                                vapid={push.vapid}
                                subscribeUrl={push.subscribe_url}
                            />
                            <PortalThemeToggle />
                            <button
                                type="button"
                                className="inline-flex cursor-pointer items-center gap-1.5 rounded-full bg-white/90 px-3 py-2 text-xs font-medium text-foreground shadow-sm ring-1 ring-black/5 transition hover:bg-teal-50 dark:bg-white/10 dark:ring-white/10 dark:hover:bg-white/15"
                                onClick={() => router.post(urls.logout)}
                            >
                                <LogOut className="size-3.5" />
                                <span className="max-sm:sr-only">{t('home.logout')}</span>
                            </button>
                        </div>
                    </div>
                </header>

                {pet ? (
                    <div className="pt-4">
                        <PortalPetView pet={pet} filters={filters} homeUrl={urls.home} />
                    </div>
                ) : (
                    <Dashboard overview={overview} homeUrl={urls.home} />
                )}
            </div>
        </>
    );
}

function Dashboard({ overview, homeUrl }: { overview: Overview; homeUrl: string }) {
    const { t } = useTranslation('portal-propietario');
    const o = overview.titular;

    return (
        <main className="mx-auto max-w-6xl space-y-8 px-4 py-6 pb-16 sm:px-8">
            <section className="relative overflow-hidden rounded-4xl bg-linear-to-r from-teal-700 via-teal-600 to-emerald-600 p-6 text-white shadow-xl sm:p-8">
                <div className="pointer-events-none absolute -right-10 -top-10 size-48 rounded-full bg-white/10" />
                <div className="relative flex flex-col gap-5 sm:flex-row sm:items-center">
                    <div className="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-white/20 text-xl font-semibold tracking-wide backdrop-blur">
                        {initials(o.nombre) || <UserRound className="size-7" />}
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="text-xs font-semibold tracking-widest text-teal-100 uppercase">
                            {t('home.owner')}
                        </p>
                        <h1 className="mt-0.5 text-2xl font-semibold tracking-tight sm:text-3xl">{o.nombre}</h1>
                        <ul className="mt-4 flex flex-wrap gap-2">
                            {o.telefono ? (
                                <li className="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-sm backdrop-blur">
                                    <Phone className="size-3.5 opacity-80" />
                                    {o.telefono}
                                </li>
                            ) : null}
                            {o.email ? (
                                <li className="inline-flex max-w-full items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-sm backdrop-blur">
                                    <Mail className="size-3.5 shrink-0 opacity-80" />
                                    <span className="truncate">{o.email}</span>
                                </li>
                            ) : null}
                            {o.documento ? (
                                <li className="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-sm backdrop-blur">
                                    {o.documento}
                                </li>
                            ) : null}
                            {o.direccion ? (
                                <li className="inline-flex max-w-full items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-sm backdrop-blur">
                                    <MapPin className="size-3.5 shrink-0 opacity-80" />
                                    <span className="truncate">{o.direccion}</span>
                                </li>
                            ) : null}
                        </ul>
                    </div>
                </div>
            </section>

            {overview.avisos.length > 0 ? (
                <section className="rounded-3xl bg-amber-50/90 p-5 ring-1 ring-amber-200/80 dark:bg-amber-950/30 dark:ring-amber-800">
                    <h2 className="mb-3 text-sm font-semibold tracking-wide uppercase">{t('home.avisos')}</h2>
                    <ul className="space-y-2">
                        {overview.avisos.map((a) => (
                            <li key={a.id}>
                                <p className="font-medium">{a.titulo}</p>
                                <p className="text-sm text-muted-foreground">{a.cuerpo}</p>
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}

            <section>
                <h2 className="mb-4 text-lg font-semibold tracking-tight">{t('home.pets')}</h2>
                {overview.mascotas.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t('home.no_pets')}</p>
                ) : (
                    <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                        {overview.mascotas.map((m) => (
                            <button
                                key={m.id}
                                type="button"
                                onClick={() => router.get(homeUrl, { mascota: m.id, tab: 'citas' })}
                                className="group cursor-pointer overflow-hidden rounded-4xl bg-white text-left shadow-md ring-1 ring-teal-900/8 transition hover:-translate-y-1 hover:shadow-xl dark:bg-slate-900/70"
                            >
                                <div className="relative aspect-4/3 bg-linear-to-br from-teal-300 to-emerald-600">
                                    {m.foto_url ? (
                                        <img
                                            src={m.foto_url}
                                            alt=""
                                            className="size-full object-cover transition duration-300 group-hover:scale-105"
                                        />
                                    ) : (
                                        <div className="flex size-full items-center justify-center text-6xl">🐾</div>
                                    )}
                                    <div className="absolute inset-x-0 bottom-0 bg-linear-to-t from-black/55 to-transparent p-3 pt-10">
                                        <p className="text-lg font-semibold text-white drop-shadow">{m.nombre}</p>
                                    </div>
                                </div>
                                <div className="space-y-1.5 p-4">
                                    <p className="text-sm text-muted-foreground">
                                        {[m.especie, m.raza].filter(Boolean).join(' · ') || '—'}
                                    </p>
                                    {m.proxima_cita ? (
                                        <p className="text-sm font-medium text-teal-800 dark:text-teal-200">
                                            {t('home.next_visit')}: {formatCita(m.proxima_cita.inicio_at)}
                                        </p>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">{t('home.no_visit')}</p>
                                    )}
                                    {m.ultima_consulta ? (
                                        <p className="text-xs text-muted-foreground">
                                            {t('home.last_visit')}: {formatCita(m.ultima_consulta.atendido_at)}
                                            {m.ultima_consulta.motivo
                                                ? ` · ${m.ultima_consulta.motivo}`
                                                : ''}
                                        </p>
                                    ) : null}
                                    <p className="pt-1 text-sm font-semibold text-teal-700 group-hover:underline dark:text-teal-300">
                                        {t('home.open_pet')} →
                                    </p>
                                </div>
                            </button>
                        ))}
                    </div>
                )}
            </section>
        </main>
    );
}
