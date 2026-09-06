import { Head, router } from '@inertiajs/react';
import { Mail, MapPin, Phone, UserRound } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PortalAppShell } from '@/components/portal/portal-app-shell';
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

    return (
        <main className="mx-auto max-w-6xl">
            <section className="bg-linear-to-br from-teal-700 via-teal-600 to-emerald-500 px-4 pb-8 pt-5 text-white sm:mx-6 sm:mt-6 sm:rounded-[1.75rem] sm:px-8 sm:shadow-xl">
                <div className="flex items-center gap-4">
                    <div className="flex size-[3.25rem] shrink-0 items-center justify-center rounded-2xl bg-white/20 text-lg font-bold tracking-wide">
                        {initials(o.nombre) || <UserRound className="size-7" />}
                    </div>
                    <div className="min-w-0">
                        <p className="text-[11px] font-bold tracking-[0.18em] text-teal-100 uppercase">
                            {t('home.owner')}
                        </p>
                        <h1 className="truncate text-xl font-bold tracking-tight sm:text-2xl">{o.nombre}</h1>
                    </div>
                </div>
                <div className="mt-4 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                    {o.telefono ? (
                        <p className="flex items-center gap-2 rounded-2xl bg-white/12 px-3 py-2.5">
                            <Phone className="size-4 opacity-80" />
                            {o.telefono}
                        </p>
                    ) : null}
                    {o.email ? (
                        <p className="flex items-center gap-2 overflow-hidden rounded-2xl bg-white/12 px-3 py-2.5">
                            <Mail className="size-4 shrink-0 opacity-80" />
                            <span className="truncate">{o.email}</span>
                        </p>
                    ) : null}
                    {o.documento ? (
                        <p className="rounded-2xl bg-white/12 px-3 py-2.5">{o.documento}</p>
                    ) : null}
                    {o.direccion ? (
                        <p className="flex items-center gap-2 rounded-2xl bg-white/12 px-3 py-2.5 sm:col-span-2">
                            <MapPin className="size-4 shrink-0 opacity-80" />
                            <span className="truncate">{o.direccion}</span>
                        </p>
                    ) : null}
                </div>
            </section>

            {overview.avisos.length > 0 ? (
                <section className="mx-4 mt-5 rounded-3xl bg-amber-50 p-4 ring-1 ring-amber-200/70 sm:mx-6 dark:bg-amber-950/40">
                    <h2 className="mb-2 text-xs font-bold tracking-wide text-amber-900 uppercase dark:text-amber-200">
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

            <section className="px-4 pt-6 sm:px-6">
                <h2 className="mb-3 text-lg font-bold tracking-tight">{t('home.pets')}</h2>
                {overview.mascotas.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t('home.no_pets')}</p>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {overview.mascotas.map((m) => (
                            <button
                                key={m.id}
                                type="button"
                                onClick={() => router.get(homeUrl, { mascota: m.id, tab: 'citas' })}
                                className="group cursor-pointer overflow-hidden rounded-[1.6rem] bg-white text-left shadow-[0_8px_30px_rgba(15,80,70,0.08)] ring-1 ring-black/4 active:scale-[0.98] dark:bg-slate-900"
                            >
                                <div className="relative h-48 bg-linear-to-br from-teal-300 to-emerald-600 sm:h-52">
                                    {m.foto_url ? (
                                        <img src={m.foto_url} alt="" className="size-full object-cover" />
                                    ) : (
                                        <div className="flex size-full items-center justify-center text-6xl">🐾</div>
                                    )}
                                    <div className="absolute inset-x-0 bottom-0 bg-linear-to-t from-black/70 via-black/25 to-transparent px-4 pb-3 pt-12">
                                        <p className="text-xl font-bold text-white">{m.nombre}</p>
                                        <p className="text-sm text-white/80">
                                            {[m.especie, m.raza].filter(Boolean).join(' · ') || ' '}
                                        </p>
                                    </div>
                                </div>
                                <div className="space-y-1 px-4 py-3.5">
                                    {m.proxima_cita ? (
                                        <p className="text-sm font-semibold text-teal-700 dark:text-teal-300">
                                            {t('home.next_visit')}: {formatCita(m.proxima_cita.inicio_at)}
                                        </p>
                                    ) : (
                                        <p className="text-sm text-slate-500">{t('home.no_visit')}</p>
                                    )}
                                    {m.ultima_consulta ? (
                                        <p className="text-xs text-slate-500">
                                            {t('home.last_visit')}: {formatCita(m.ultima_consulta.atendido_at)}
                                        </p>
                                    ) : null}
                                    <p className="pt-1 text-sm font-bold text-teal-600">{t('home.open_pet')} →</p>
                                </div>
                            </button>
                        ))}
                    </div>
                )}
            </section>
        </main>
    );
}
