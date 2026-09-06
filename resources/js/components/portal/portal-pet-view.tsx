import { router, usePage } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, FileDown, Scissors, Stethoscope, Syringe } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { AtencionDateRangeFilter } from '@/pages/clinica/historias-clinicas/components/atencion-date-range-filter';
import { dateKeyInAppTimezone } from '@/pages/clinica/historias-clinicas/format-atendido';
import { PacienteTimelineRow } from '@/pages/clinica/pacientes/components/paciente-timeline-row';
import type { TimelineItem } from '@/pages/clinica/pacientes/show';

type Cita = { id: string; inicio_at: string; motivo: string | null; estado: string };

export type PortalPetPayload = {
    mascota: {
        id: string;
        nombre: string;
        foto_url: string | null;
        especie: string | null;
        raza: string | null;
        sexo: string | null;
        fecha_nacimiento: string | null;
        color: string | null;
        peso_kg: string | null;
    };
    citas: { proxima: Cita | null; historial: Cita[] };
    grooming: Array<{
        id: string;
        inicio_at: string;
        estado: string;
        servicio: string | null;
        notas: string | null;
        fotos: Array<{ id: string; tipo: string; url: string | null }>;
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
    historial: {
        timeline: TimelineItem[];
        pdf_url: string | null;
        permisos: {
            consultas_ver: boolean;
            vacunas_ver: boolean;
            consultas_crear: boolean;
            vacunas_crear: boolean;
            laboratorio_crear: boolean;
        };
    };
};

type Filters = {
    tab: 'citas' | 'hc' | 'banos' | 'vacunas';
    desde: string | null;
    hasta: string | null;
    default_desde: string;
    default_hasta: string;
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

function ageLabel(iso: string | null): string | null {
    if (!iso) {
        return null;
    }
    const birth = new Date(`${iso}T12:00:00`);
    if (Number.isNaN(birth.getTime())) {
        return null;
    }
    const now = new Date();
    let months = (now.getFullYear() - birth.getFullYear()) * 12 + (now.getMonth() - birth.getMonth());
    if (now.getDate() < birth.getDate()) {
        months -= 1;
    }
    if (months < 0) {
        return null;
    }
    if (months < 12) {
        return `${months} m`;
    }
    const years = Math.floor(months / 12);
    const rest = months % 12;
    return rest > 0 ? `${years} a ${rest} m` : `${years} años`;
}

export function PortalPetView({
    pet,
    filters,
    homeUrl,
}: {
    pet: PortalPetPayload;
    filters: Filters;
    homeUrl: string;
}) {
    const { t } = useTranslation('portal-propietario');
    const { timezone: appTz } = usePage().props;
    const m = pet.mascota;
    const estado = (key: string) => t(`home.estado.${key}`, { defaultValue: key });

    const visit = (extra: Record<string, string | undefined>) => {
        const query: Record<string, string> = {
            mascota: m.id,
            tab: extra.tab ?? filters.tab,
        };
        const desde = extra.desde ?? filters.desde ?? undefined;
        const hasta = extra.hasta ?? filters.hasta ?? undefined;
        if (desde) {
            query.desde = desde;
        }
        if (hasta) {
            query.hasta = hasta;
        }
        router.get(homeUrl, query, { preserveState: true, preserveScroll: true });
    };

    const dateHeaders = useMemo(() => {
        const tz = typeof appTz === 'string' ? appTz : 'UTC';
        let prev = '';
        return pet.historial.timeline.map((item) => {
            const dayKey = dateKeyInAppTimezone(item.ocurrido_at, tz);
            const isNew = dayKey !== prev;
            prev = dayKey;
            return isNew;
        });
    }, [pet.historial.timeline, appTz]);

    const tabs = [
        { id: 'citas' as const, label: t('home.tab_citas'), icon: CalendarDays },
        { id: 'hc' as const, label: t('home.tab_hc'), icon: Stethoscope },
        { id: 'banos' as const, label: t('home.tab_banos'), icon: Scissors },
        { id: 'vacunas' as const, label: t('home.tab_vacunas'), icon: Syringe },
    ];

    return (
        <div className="mx-auto max-w-5xl">
            <div className="relative h-[min(52vh,26rem)] w-full overflow-hidden bg-linear-to-br from-teal-400 to-emerald-700 sm:mx-6 sm:mt-4 sm:h-80 sm:rounded-[1.75rem] sm:shadow-xl">
                {m.foto_url ? (
                    <img src={m.foto_url} alt="" className="absolute inset-0 size-full object-cover" />
                ) : (
                    <div className="flex size-full items-center justify-center text-8xl">🐾</div>
                )}
                <div className="absolute inset-0 bg-linear-to-t from-black/75 via-black/20 to-black/10" />
                <button
                    type="button"
                    className="absolute top-3 left-3 z-10 inline-flex size-11 cursor-pointer items-center justify-center rounded-full bg-white/95 text-slate-800 shadow-md"
                    onClick={() => router.get(homeUrl)}
                    aria-label={t('home.back')}
                >
                    <ArrowLeft className="size-5" />
                </button>
                <div className="absolute inset-x-0 bottom-0 p-5 pb-8 text-white">
                    <h1 className="text-3xl font-bold tracking-tight drop-shadow-sm">{m.nombre}</h1>
                    <p className="mt-1 text-sm text-white/85">
                        {[m.especie, m.raza, m.sexo, m.color].filter(Boolean).join(' · ')}
                    </p>
                    <div className="mt-3 flex flex-wrap gap-2">
                        {ageLabel(m.fecha_nacimiento) ? (
                            <span className="rounded-full bg-white/18 px-3 py-1 text-xs font-semibold backdrop-blur-sm">
                                {t('home.age')}: {ageLabel(m.fecha_nacimiento)}
                            </span>
                        ) : null}
                        {m.peso_kg ? (
                            <span className="rounded-full bg-white/18 px-3 py-1 text-xs font-semibold backdrop-blur-sm">
                                {t('home.weight')}: {m.peso_kg} kg
                            </span>
                        ) : null}
                    </div>
                </div>
            </div>

            <div className="relative z-10 -mt-4 grid grid-cols-2 gap-2 px-4 sm:mx-6 sm:grid-cols-3">
                <button
                    type="button"
                    onClick={() => visit({ tab: 'citas' })}
                    className="cursor-pointer rounded-2xl bg-white p-3 text-left shadow-md ring-1 ring-black/5 dark:bg-slate-900"
                >
                    <p className="text-[11px] font-bold tracking-wide text-teal-700 uppercase dark:text-teal-300">
                        {t('home.next_visit')}
                    </p>
                    <p className="mt-0.5 line-clamp-2 text-sm font-semibold">
                        {pet.citas.proxima ? formatCita(pet.citas.proxima.inicio_at) : t('home.no_visit')}
                    </p>
                </button>
                <button
                    type="button"
                    onClick={() => visit({ tab: 'vacunas' })}
                    className="cursor-pointer rounded-2xl bg-white p-3 text-left shadow-md ring-1 ring-black/5 dark:bg-slate-900"
                >
                    <p className="text-[11px] font-bold tracking-wide text-lime-700 uppercase dark:text-lime-300">
                        {t('home.vaccine')}
                    </p>
                    <p className="mt-0.5 line-clamp-2 text-sm font-semibold">
                        {pet.vacunas.proxima
                            ? `${pet.vacunas.proxima.nombre}${pet.vacunas.proxima.fecha ? ` · ${formatFecha(pet.vacunas.proxima.fecha)}` : ''}`
                            : t('home.no_vaccine')}
                    </p>
                </button>
                <button
                    type="button"
                    onClick={() => visit({ tab: 'hc' })}
                    className="col-span-2 cursor-pointer rounded-2xl bg-white p-3 text-left shadow-md ring-1 ring-black/5 sm:col-span-1 dark:bg-slate-900"
                >
                    <p className="text-[11px] font-bold tracking-wide text-sky-700 uppercase dark:text-sky-300">
                        {t('home.hc')}
                    </p>
                    <p className="mt-0.5 text-sm font-semibold">
                        {pet.historial.timeline.length > 0
                            ? t('home.hc_count', { count: pet.historial.timeline.length })
                            : t('home.no_hc')}
                    </p>
                </button>
            </div>

            <div className="sticky top-14 z-20 -mt-1 bg-[#f3f7f6]/95 px-2 py-2 backdrop-blur-md sm:top-16 sm:mx-6 sm:rounded-b-3xl dark:bg-slate-950/90">
                <div className="grid grid-cols-4 gap-1">
                    {tabs.map((tab) => {
                        const Icon = tab.icon;
                        const active = filters.tab === tab.id;
                        return (
                            <button
                                key={tab.id}
                                type="button"
                                onClick={() => visit({ tab: tab.id })}
                                className={`flex cursor-pointer flex-col items-center gap-1 rounded-2xl py-2.5 text-[11px] font-semibold ${
                                    active
                                        ? 'bg-teal-600 text-white shadow-md'
                                        : 'text-slate-600 dark:text-slate-300'
                                }`}
                            >
                                <Icon className="size-5" />
                                {tab.label}
                            </button>
                        );
                    })}
                </div>
            </div>

            <div className="space-y-4 px-4 py-4 sm:px-6">
                <AtencionDateRangeFilter
                    desde={filters.desde}
                    hasta={filters.hasta}
                    defaultDesde={filters.default_desde}
                    defaultHasta={filters.default_hasta}
                    translationNs="historias-clinicas"
                    triggerClassName="h-11 w-full cursor-pointer sm:w-auto"
                    onApply={(desde, hasta) => visit({ desde, hasta })}
                    onClear={() =>
                        router.get(
                            homeUrl,
                            { mascota: m.id, tab: filters.tab, todo: 1 },
                            { preserveState: true },
                        )
                    }
                />

                {filters.tab === 'citas' && (
                    <section className="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-black/5 dark:bg-slate-900">
                        <div className="border-b border-black/5 bg-teal-50 px-4 py-3 dark:bg-teal-950/40">
                            {pet.citas.proxima ? (
                                <p className="font-semibold text-teal-900 dark:text-teal-100">
                                    {t('home.next_visit')}: {formatCita(pet.citas.proxima.inicio_at)}
                                    {pet.citas.proxima.motivo ? ` · ${pet.citas.proxima.motivo}` : ''}
                                </p>
                            ) : (
                                <p className="text-slate-500">{t('home.no_visit')}</p>
                            )}
                        </div>
                        <ul className="divide-y divide-black/5">
                            {pet.citas.historial.map((c) => (
                                <li key={c.id} className="flex items-start justify-between gap-3 px-4 py-3.5 text-sm">
                                    <span>
                                        <span className="block font-medium">{formatCita(c.inicio_at)}</span>
                                        {c.motivo ? (
                                            <span className="text-slate-500">{c.motivo}</span>
                                        ) : null}
                                    </span>
                                    <span className="shrink-0 rounded-full bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-800 dark:bg-teal-950 dark:text-teal-200">
                                        {estado(c.estado)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {filters.tab === 'hc' && (
                    <section className="space-y-3">
                        {pet.historial.pdf_url ? (
                            <a
                                href={pet.historial.pdf_url}
                                className="inline-flex h-11 cursor-pointer items-center gap-2 rounded-2xl bg-sky-600 px-4 text-sm font-semibold text-white"
                            >
                                <FileDown className="size-4" />
                                {t('home.download_hc')}
                            </a>
                        ) : null}
                        {pet.historial.timeline.length === 0 ? (
                            <p className="rounded-3xl bg-white p-6 text-sm text-slate-500 shadow-sm dark:bg-slate-900">
                                {t('home.no_hc')}
                            </p>
                        ) : (
                            <div className="overflow-hidden rounded-3xl bg-white p-2 shadow-sm ring-1 ring-black/5 dark:bg-slate-900">
                                {pet.historial.timeline.map((item, index) => (
                                    <PacienteTimelineRow
                                        key={`${item.kind}-${item.id}`}
                                        item={item}
                                        index={index}
                                        showDateHeader={dateHeaders[index]}
                                        appTz={typeof appTz === 'string' ? appTz : undefined}
                                        permisos={pet.historial.permisos}
                                        isLast={index === pet.historial.timeline.length - 1}
                                        variant="public"
                                    />
                                ))}
                            </div>
                        )}
                    </section>
                )}

                {filters.tab === 'banos' && (
                    <section className="space-y-3">
                        {pet.grooming.length === 0 ? (
                            <p className="rounded-3xl bg-white p-6 text-sm text-slate-500 shadow-sm dark:bg-slate-900">
                                {t('home.no_grooming')}
                            </p>
                        ) : (
                            pet.grooming.map((g) => (
                                <article
                                    key={g.id}
                                    className="overflow-hidden rounded-3xl bg-white p-4 shadow-sm ring-1 ring-black/5 dark:bg-slate-900"
                                >
                                    <p className="font-semibold">
                                        {formatCita(g.inicio_at)}
                                        <span className="ml-2 text-sm font-medium text-rose-700">
                                            {estado(g.estado)}
                                        </span>
                                    </p>
                                    {g.servicio ? (
                                        <p className="text-sm text-slate-500">{g.servicio}</p>
                                    ) : null}
                                    {g.fotos.length > 0 ? (
                                        <div className="-mx-1 mt-3 flex gap-2 overflow-x-auto pb-1">
                                            {g.fotos.map((f) =>
                                                f.url ? (
                                                    <figure key={f.id} className="shrink-0">
                                                        <img
                                                            src={f.url}
                                                            alt=""
                                                            className="h-36 w-36 rounded-2xl object-cover"
                                                        />
                                                        <figcaption className="mt-1 text-center text-[10px] text-slate-500">
                                                            {f.tipo === 'final'
                                                                ? t('home.photo_final')
                                                                : t('home.photo_proceso')}
                                                        </figcaption>
                                                    </figure>
                                                ) : null,
                                            )}
                                        </div>
                                    ) : null}
                                </article>
                            ))
                        )}
                    </section>
                )}

                {filters.tab === 'vacunas' && (
                    <section className="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-black/5 dark:bg-slate-900">
                        <div className="border-b border-black/5 bg-lime-50 px-4 py-3 dark:bg-lime-950/30">
                            {pet.vacunas.proxima ? (
                                <p className="font-semibold">
                                    {t('home.vaccine')}: {pet.vacunas.proxima.nombre}
                                    {pet.vacunas.proxima.fecha
                                        ? ` · ${formatFecha(pet.vacunas.proxima.fecha)}`
                                        : ''}
                                </p>
                            ) : (
                                <p className="text-slate-500">{t('home.no_vaccine')}</p>
                            )}
                        </div>
                        <ul className="divide-y divide-black/5">
                            {pet.vacunas.historial.map((v) => (
                                <li key={v.nombre + v.aplicada_at} className="px-4 py-3 text-sm">
                                    {v.nombre} · {formatFecha(v.aplicada_at)}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </div>
    );
}
