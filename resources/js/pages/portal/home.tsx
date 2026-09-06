import { Head, router } from '@inertiajs/react';
import { CalendarDays, ChevronDown, ChevronRight, IdCard, Mail, MapPin, Phone } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PortalAppShell } from '@/components/portal/portal-app-shell';
import { PortalPetCover } from '@/components/portal/portal-pet-cover';
import { PortalPetView, type PortalPetPayload } from '@/components/portal/portal-pet-view';

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
            <PortalAppShell
                clinicName={clinic.nombre}
                clinicLogo={clinic.logo_url}
                greeting={t('home.hello', { name: overview.saludo })}
                logoutUrl={urls.logout}
                push={push}
            >
                {pet ? (
                    <PortalPetView pet={pet} filters={filters} homeUrl={urls.home} />
                ) : (
                    <Dashboard overview={overview} homeUrl={urls.home} />
                )}
            </PortalAppShell>
        </>
    );
}

function Dashboard({ overview, homeUrl }: { overview: Overview; homeUrl: string }) {
    const { t } = useTranslation('portal-propietario');
    const o = overview.titular;
    const [open, setOpen] = useState(false);

    return (
        <main className="mx-auto max-w-6xl">
            <section className="relative overflow-hidden bg-linear-to-br from-brand-800 via-brand-600 to-brand-400 px-4 pb-6 pt-4 text-white sm:mx-5 sm:mt-5 sm:rounded-[1.75rem] sm:px-7 sm:shadow-xl">
                <div className="pointer-events-none absolute -top-16 -right-10 size-48 rounded-full bg-white/15 blur-2xl" />
                <div className="pointer-events-none absolute -bottom-20 -left-8 size-56 rounded-full bg-brand-300/30 blur-2xl" />
                <button
                    type="button"
                    onClick={() => setOpen((v) => !v)}
                    className="relative flex w-full cursor-pointer items-center gap-3 text-left"
                >
                    <div className="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-white/20 text-lg font-bold tracking-wide ring-2 ring-white/25">
                        {initials(o.nombre)}
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="text-[10px] font-bold tracking-[0.2em] text-brand-100 uppercase">
                            {t('home.owner')}
                        </p>
                        <h1 className="truncate text-xl font-bold tracking-tight">{o.nombre}</h1>
                        {o.telefono ? (
                            <p className="mt-0.5 flex items-center gap-1.5 text-sm text-white/85">
                                <Phone className="size-3.5" />
                                {o.telefono}
                            </p>
                        ) : null}
                    </div>
                    <ChevronDown
                        className={`size-5 shrink-0 opacity-80 transition ${open ? 'rotate-180' : ''}`}
                    />
                </button>
                {open ? (
                    <div className="relative mt-4 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                        {o.email ? (
                            <p className="flex items-center gap-2 overflow-hidden rounded-2xl bg-white/12 px-3 py-2.5">
                                <Mail className="size-4 shrink-0 opacity-80" />
                                <span className="truncate">{o.email}</span>
                            </p>
                        ) : null}
                        {o.documento ? (
                            <p className="flex items-center gap-2 rounded-2xl bg-white/12 px-3 py-2.5">
                                <IdCard className="size-4 shrink-0 opacity-80" />
                                {o.documento}
                            </p>
                        ) : null}
                        {o.direccion ? (
                            <p className="flex items-center gap-2 rounded-2xl bg-white/12 px-3 py-2.5 sm:col-span-2">
                                <MapPin className="size-4 shrink-0 opacity-80" />
                                <span className="truncate">{o.direccion}</span>
                            </p>
                        ) : null}
                    </div>
                ) : null}
            </section>

            {overview.avisos.length > 0 ? (
                <section className="mx-4 mt-4 rounded-3xl bg-brand-50 p-4 ring-1 ring-brand-200/70 sm:mx-5 dark:bg-brand-950/40">
                    <h2 className="mb-2 text-xs font-bold tracking-wide text-brand-900 uppercase dark:text-brand-200">
                        {t('home.avisos')}
                    </h2>
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

            <section className="px-4 pt-5 sm:px-5">
                <div className="mb-3 flex items-end justify-between">
                    <h2 className="text-lg font-bold tracking-tight">{t('home.pets')}</h2>
                    <p className="text-xs font-medium text-slate-500">
                        {t('home.pets_count', { count: overview.mascotas.length })}
                    </p>
                </div>
                {overview.mascotas.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t('home.no_pets')}</p>
                ) : (
                    <div className="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-3">
                        {overview.mascotas.map((m) => (
                            <button
                                key={m.id}
                                type="button"
                                onClick={() => router.get(homeUrl, { mascota: m.id, tab: 'citas' })}
                                className="group relative h-56 cursor-pointer overflow-hidden rounded-[1.65rem] text-left shadow-[0_12px_32px_rgba(15,60,50,0.14)] ring-1 ring-black/5 active:scale-[0.985] sm:h-64"
                            >
                                <PortalPetCover
                                    nombre={m.nombre}
                                    fotoUrl={m.foto_url}
                                    especie={m.especie}
                                />
                                <div className="absolute inset-0 bg-linear-to-t from-black/80 via-black/25 to-black/10" />
                                {m.proxima_cita ? (
                                    <span className="absolute top-3 right-3 inline-flex items-center gap-1 rounded-full bg-brand-400 px-2.5 py-1 text-[11px] font-bold text-brand-950 shadow">
                                        <CalendarDays className="size-3" />
                                        {formatCita(m.proxima_cita.inicio_at)}
                                    </span>
                                ) : (
                                    <span className="absolute top-3 right-3 rounded-full bg-white/18 px-2.5 py-1 text-[11px] font-semibold text-white backdrop-blur-md">
                                        {t('home.no_visit')}
                                    </span>
                                )}
                                <div className="absolute inset-x-0 bottom-0 flex items-end justify-between gap-3 p-4">
                                    <div className="min-w-0">
                                        <p className="truncate text-2xl font-bold text-white drop-shadow">
                                            {m.nombre}
                                        </p>
                                        <p className="truncate text-sm text-white/80">
                                            {[m.especie, m.raza].filter(Boolean).join(' · ') ||
                                                t('home.open_pet')}
                                        </p>
                                        {m.ultima_consulta ? (
                                            <p className="mt-1 text-[11px] text-white/65">
                                                {t('home.last_visit')} ·{' '}
                                                {formatCita(m.ultima_consulta.atendido_at)}
                                            </p>
                                        ) : null}
                                    </div>
                                    <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-white/95 text-brand-700 shadow">
                                        <ChevronRight className="size-5" />
                                    </span>
                                </div>
                            </button>
                        ))}
                    </div>
                )}
            </section>
        </main>
    );
}
