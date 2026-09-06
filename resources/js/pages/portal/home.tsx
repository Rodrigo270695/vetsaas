import { Head, router } from '@inertiajs/react';
import { PawPrint } from 'lucide-react';
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

export default function PortalHome({ clinic, overview, pet, filters, push, urls }: Props) {
    const { t } = useTranslation('portal-propietario');

    return (
        <>
            <Head title={pet?.mascota.nombre ?? clinic.nombre} />
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
                                {t('home.hello', { name: overview.saludo })}
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

                {pet ? (
                    <PortalPetView pet={pet} filters={filters} homeUrl={urls.home} />
                ) : (
                    <Dashboard overview={overview} push={push} homeUrl={urls.home} />
                )}
            </div>
        </>
    );
}

function Dashboard({
    overview,
    push,
    homeUrl,
}: {
    overview: Overview;
    push: Props['push'];
    homeUrl: string;
}) {
    const { t } = useTranslation('portal-propietario');
    const o = overview.titular;

    return (
        <main className="mx-auto max-w-6xl space-y-8 px-4 pb-16 sm:px-8">
            <section className="rounded-4xl bg-white/80 p-6 shadow-lg ring-1 ring-teal-900/5 dark:bg-slate-900/60">
                <p className="text-xs font-semibold tracking-wide text-teal-700 uppercase dark:text-teal-300">
                    {t('home.owner')}
                </p>
                <h1 className="mt-1 text-3xl font-semibold tracking-tight">{o.nombre}</h1>
                <dl className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                    {o.telefono ? (
                        <div>
                            <dt className="text-muted-foreground">{t('home.phone')}</dt>
                            <dd className="font-medium">{o.telefono}</dd>
                        </div>
                    ) : null}
                    {o.email ? (
                        <div>
                            <dt className="text-muted-foreground">{t('home.email')}</dt>
                            <dd className="font-medium">{o.email}</dd>
                        </div>
                    ) : null}
                    {o.documento ? (
                        <div>
                            <dt className="text-muted-foreground">{t('home.document')}</dt>
                            <dd className="font-medium">{o.documento}</dd>
                        </div>
                    ) : null}
                    {o.direccion ? (
                        <div className="sm:col-span-2">
                            <dt className="text-muted-foreground">{t('home.address')}</dt>
                            <dd className="font-medium">{o.direccion}</dd>
                        </div>
                    ) : null}
                </dl>
            </section>

            <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <PortalInstallButtons />
                <PortalPushToggle
                    enabled={push.enabled}
                    vapid={push.vapid}
                    subscribeUrl={push.subscribe_url}
                />
            </div>

            {overview.avisos.length > 0 ? (
                <section className="rounded-3xl bg-amber-50/90 p-5 ring-1 ring-amber-100 dark:bg-amber-950/30">
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
                <h2 className="mb-4 text-lg font-semibold">{t('home.pets')}</h2>
                {overview.mascotas.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t('home.no_pets')}</p>
                ) : (
                    <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                        {overview.mascotas.map((m) => (
                            <button
                                key={m.id}
                                type="button"
                                onClick={() => router.get(homeUrl, { mascota: m.id, tab: 'citas' })}
                                className="overflow-hidden rounded-4xl bg-white text-left shadow-md ring-1 ring-teal-900/8 transition hover:-translate-y-0.5 hover:shadow-xl dark:bg-slate-900/70"
                            >
                                <div className="relative h-44 bg-linear-to-br from-teal-300 to-emerald-600">
                                    {m.foto_url ? (
                                        <img src={m.foto_url} alt="" className="size-full object-cover" />
                                    ) : (
                                        <div className="flex size-full items-center justify-center text-6xl">🐾</div>
                                    )}
                                </div>
                                <div className="space-y-2 p-4">
                                    <p className="text-xl font-semibold">{m.nombre}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {[m.especie, m.raza].filter(Boolean).join(' · ') || '—'}
                                    </p>
                                    {m.proxima_cita ? (
                                        <p className="text-sm text-teal-800 dark:text-teal-200">
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
                                    <p className="pt-1 text-sm font-medium text-teal-700 dark:text-teal-300">
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
