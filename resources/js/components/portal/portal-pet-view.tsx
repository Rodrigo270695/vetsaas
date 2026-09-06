import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft, FileDown } from 'lucide-react';
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
        { id: 'citas' as const, label: t('home.tab_citas') },
        { id: 'hc' as const, label: t('home.tab_hc') },
        { id: 'banos' as const, label: t('home.tab_banos') },
        { id: 'vacunas' as const, label: t('home.tab_vacunas') },
    ];

    return (
        <div className="mx-auto max-w-5xl space-y-5 px-4 pb-16 sm:px-6">
            <button
                type="button"
                className="inline-flex cursor-pointer items-center gap-2 text-sm text-teal-800 hover:underline dark:text-teal-200"
                onClick={() => router.get(homeUrl)}
            >
                <ArrowLeft className="size-4" />
                {t('home.back')}
            </button>

            <div className="flex flex-col gap-4 overflow-hidden rounded-4xl bg-white/80 shadow-lg ring-1 ring-teal-900/5 sm:flex-row dark:bg-slate-900/60">
                <div className="relative h-52 sm:h-auto sm:w-72 shrink-0">
                    {m.foto_url ? (
                        <img src={m.foto_url} alt="" className="size-full object-cover sm:min-h-56" />
                    ) : (
                        <div className="flex size-full min-h-52 items-center justify-center bg-linear-to-br from-teal-400 to-emerald-700 text-6xl">
                            🐾
                        </div>
                    )}
                </div>
                <div className="flex flex-1 flex-col justify-center p-5">
                    <h1 className="text-3xl font-semibold tracking-tight">{m.nombre}</h1>
                    <p className="text-muted-foreground">
                        {[m.especie, m.raza, m.sexo, m.color].filter(Boolean).join(' · ')}
                    </p>
                    {m.fecha_nacimiento ? (
                        <p className="mt-1 text-sm text-muted-foreground">
                            {formatFecha(m.fecha_nacimiento)}
                            {m.peso_kg ? ` · ${m.peso_kg} kg` : ''}
                        </p>
                    ) : null}
                </div>
            </div>

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-wrap gap-2">
                    {tabs.map((tab) => (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => visit({ tab: tab.id })}
                            className={`cursor-pointer rounded-full px-4 py-2 text-sm font-medium ${
                                filters.tab === tab.id
                                    ? 'bg-teal-700 text-white'
                                    : 'bg-white/80 text-teal-900 shadow-sm dark:bg-white/10 dark:text-teal-50'
                            }`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>
                <AtencionDateRangeFilter
                    desde={filters.desde}
                    hasta={filters.hasta}
                    defaultDesde={filters.default_desde}
                    defaultHasta={filters.default_hasta}
                    translationNs="historias-clinicas"
                    onApply={(desde, hasta) => visit({ desde, hasta, todo: undefined })}
                    onClear={() =>
                        router.get(
                            homeUrl,
                            { mascota: m.id, tab: filters.tab, todo: 1 },
                            { preserveState: true },
                        )
                    }
                />
            </div>

            {filters.tab === 'citas' && (
                <section className="rounded-3xl bg-teal-50/90 p-5 ring-1 ring-teal-100 dark:bg-teal-950/40 dark:ring-teal-800">
                    {pet.citas.proxima ? (
                        <p className="mb-4 text-lg font-medium">
                            {t('home.next_visit')}: {formatCita(pet.citas.proxima.inicio_at)}
                            {pet.citas.proxima.motivo ? ` · ${pet.citas.proxima.motivo}` : ''}
                        </p>
                    ) : (
                        <p className="mb-4 text-muted-foreground">{t('home.no_visit')}</p>
                    )}
                    <ul className="space-y-2">
                        {pet.citas.historial.map((c) => (
                            <li
                                key={c.id}
                                className="flex justify-between gap-3 rounded-2xl bg-white/70 px-4 py-3 text-sm dark:bg-white/5"
                            >
                                <span>
                                    {formatCita(c.inicio_at)}
                                    {c.motivo ? ` · ${c.motivo}` : ''}
                                </span>
                                <span className="shrink-0 text-muted-foreground">{estado(c.estado)}</span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {filters.tab === 'hc' && (
                <section className="space-y-4">
                    {pet.historial.pdf_url ? (
                        <a
                            href={pet.historial.pdf_url}
                            className="inline-flex items-center gap-2 rounded-full bg-sky-600 px-4 py-2 text-sm font-semibold text-white"
                        >
                            <FileDown className="size-4" />
                            {t('home.download_hc')}
                        </a>
                    ) : null}
                    {pet.historial.timeline.length === 0 ? (
                        <p className="text-sm text-muted-foreground">{t('home.no_hc')}</p>
                    ) : (
                        <div className="rounded-3xl bg-white/80 p-3 ring-1 ring-sky-100 dark:bg-slate-900/50 dark:ring-sky-900">
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
                <section className="rounded-3xl bg-rose-50/90 p-5 ring-1 ring-rose-100 dark:bg-rose-950/30 dark:ring-rose-800">
                    {pet.grooming.length === 0 ? (
                        <p className="text-sm text-muted-foreground">{t('home.no_grooming')}</p>
                    ) : (
                        <ul className="space-y-5">
                            {pet.grooming.map((g) => (
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
                                                            className="h-32 w-32 rounded-2xl object-cover ring-2 ring-white"
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
                </section>
            )}

            {filters.tab === 'vacunas' && (
                <section className="rounded-3xl bg-lime-50/90 p-5 ring-1 ring-lime-100 dark:bg-lime-950/30 dark:ring-lime-800">
                    {pet.vacunas.proxima ? (
                        <p className="mb-3 font-medium">
                            {t('home.vaccine')}: {pet.vacunas.proxima.nombre}
                            {pet.vacunas.proxima.fecha
                                ? ` · ${formatFecha(pet.vacunas.proxima.fecha)}`
                                : ''}
                        </p>
                    ) : (
                        <p className="mb-3 text-muted-foreground">{t('home.no_vaccine')}</p>
                    )}
                    <ul className="space-y-2 text-sm">
                        {pet.vacunas.historial.map((v) => (
                            <li key={v.nombre + v.aplicada_at}>
                                {v.nombre} · {formatFecha(v.aplicada_at)}
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </div>
    );
}
