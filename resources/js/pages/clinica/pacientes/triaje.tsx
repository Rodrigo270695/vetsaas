import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, ChevronDown, ClipboardList, Loader2, MessageSquare } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { ConstantesCompactas, soloNumero } from './components/constantes-compactas';
import type { ConstanteKey, ConstantesValores } from './components/constantes-compactas';

const SECCIONES = [
    { id: 'piel', opciones: ['bien', 'costras', 'heridas', 'roja', 'eczemas', 'hematomas', 'caspa', 'alergia', 'masa'] },
    { id: 'oidos', opciones: ['bien', 'hematomas', 'mal_olor', 'inflamados', 'dolor', 'sangrado', 'cerumen'] },
    { id: 'ojos', opciones: ['bien', 'leganas', 'enrojecimiento', 'lagrimeo'] },
    { id: 'dientes', opciones: ['bien', 'gingivitis', 'perdida', 'mal_aliento', 'sarro', 'flojos', 'sangrado'] },
    { id: 'pelaje', opciones: ['bien', 'caida', 'opaco', 'brilloso', 'mal_olor', 'motoso', 'manchado', 'alopecia'] },
    { id: 'pulgas', opciones: ['si', 'no', 'gran_cantidad', 'poca_cantidad'] },
    { id: 'garrapatas', opciones: ['si', 'no', 'gran_cantidad', 'poca_cantidad'] },
    { id: 'desparasitacion', opciones: ['si_registra', 'no_registra', 'al_dia', 'pendiente'] },
    { id: 'vacunas', opciones: ['si_registra', 'no_registra', 'pendiente'] },
] as const;

const RECOMENDACIONES = [
    'consulta_medica',
    'aplicar_vacuna',
    'antipulgas',
    'tratamiento',
    'bano_medicado',
    'alimento',
    'bajar_peso',
    'profilaxis',
    'desparasitacion_interna',
    'examenes',
    'bano_antipulgas',
    'control_anual',
    'vitaminas',
    'subir_peso',
] as const;

type Hallazgos = Record<string, string[]>;

type Registro = {
    id?: string;
    peso_kg: string;
    temperatura_c: string;
    fc_lpm: string;
    fr_rpm: string;
    tlc: string;
    pa: string;
    hidratacion: string;
    hallazgos: Hallazgos;
    recomendaciones: string[];
    comentarios: string | null;
    veterinario: string | null;
};

type Props = {
    paciente: { id: string; nombre: string };
    registro: Registro | null;
    atendido_at: string;
    puede_editar: boolean;
    guardar_url: string;
    method: 'post' | 'put';
    onVolver: () => void;
    onSaved?: (url: string) => void;
};

const campoClass =
    'h-10 border-amber-100/80 bg-[#fbf6e4] shadow-none dark:border-amber-900/40 dark:bg-amber-950/25';

function hallazgosIniciales(registro: Registro | null): Hallazgos {
    const base: Hallazgos = {};

    for (const seccion of SECCIONES) {
        const marcadas = registro?.hallazgos?.[seccion.id];
        base[seccion.id] = Array.isArray(marcadas) ? marcadas.filter((item) => typeof item === 'string') : [];
    }

    return base;
}

function constantesIniciales(registro: Registro | null): ConstantesValores {
    return {
        peso_kg: registro?.peso_kg ?? '',
        temperatura_c: registro?.temperatura_c ?? '',
        fc_lpm: registro?.fc_lpm ?? '',
        fr_rpm: registro?.fr_rpm ?? '',
        tlc: registro?.tlc ?? '',
        pa: registro?.pa ?? '',
        hidratacion: registro?.hidratacion ?? '',
    };
}

export function TriajeForm({
    paciente,
    registro,
    atendido_at,
    puede_editar,
    guardar_url,
    method,
    onVolver,
    onSaved,
}: Props) {
    const { t } = useTranslation('pacientes');
    const [abiertas, setAbiertas] = useState<Record<string, boolean>>({});
    const form = useForm({
        atendido_at,
        ...constantesIniciales(registro),
        hallazgos: hallazgosIniciales(registro),
        recomendaciones: registro?.recomendaciones ?? [],
        comentarios: registro?.comentarios ?? '',
    });

    const volver = () => {
        if (form.isDirty && !window.confirm(t('triaje.confirmar_volver'))) {
            return;
        }

        onVolver();
    };

    const toggleSeccion = (id: string) => {
        setAbiertas((current) => ({ ...current, [id]: current[id] === false }));
    };

    const toggleHallazgo = (seccion: string, opcion: string) => {
        const actual = form.data.hallazgos[seccion] ?? [];
        const next = actual.includes(opcion) ? actual.filter((item) => item !== opcion) : [...actual, opcion];
        form.setData('hallazgos', { ...form.data.hallazgos, [seccion]: next });
    };

    const toggleRecomendacion = (opcion: string) => {
        const actual = form.data.recomendaciones;
        form.setData(
            'recomendaciones',
            actual.includes(opcion) ? actual.filter((item) => item !== opcion) : [...actual, opcion],
        );
    };

    const labels: Record<ConstanteKey, string> = {
        peso_kg: t('desparasitacion.peso'),
        temperatura_c: t('desparasitacion.temp'),
        fc_lpm: t('desparasitacion.fc'),
        fr_rpm: t('desparasitacion.fr'),
        tlc: t('desparasitacion.tlc'),
        pa: t('desparasitacion.pa'),
        hidratacion: t('desparasitacion.hidratacion'),
    };

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="min-w-0">
                    <h2 className="flex items-center gap-2 text-lg font-semibold tracking-tight">
                        <span className="flex size-8 items-center justify-center rounded-lg bg-violet-500/15 text-violet-700 dark:text-violet-200">
                            <ClipboardList className="size-4" />
                        </span>
                        {t('triaje.title')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t('triaje.subtitle', { nombre: paciente.nombre })}
                        {registro?.veterinario ? ` · ${t('triaje.veterinario')}: ${registro.veterinario}` : ''}
                    </p>
                </div>
                <button
                    type="button"
                    onClick={volver}
                    className="inline-flex cursor-pointer items-center gap-1 px-1 text-sm font-medium text-primary underline-offset-4 hover:underline"
                >
                    <ArrowLeft className="size-3.5" />
                    {t('triaje.volver')}
                </button>
            </div>

            <form
                className="flex flex-col gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    const options = {
                        preserveScroll: true,
                        onSuccess: (page) => {
                            const flash = page.props.flash as { triaje_editar?: string | null } | null;
                            const url = flash?.triaje_editar;

                            if (typeof url === 'string' && url !== '') {
                                onSaved?.(url);
                            }
                        },
                    };

                    if (method === 'post') {
                        form.post(guardar_url, options);

                        return;
                    }

                    form.put(guardar_url, options);
                }}
            >
                {form.hasErrors ? (
                    <p className="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                        {Object.values(form.errors)
                            .flatMap((value) => (Array.isArray(value) ? value : [value]))
                            .filter(Boolean)
                            .join(' ')}
                    </p>
                ) : null}

                <div className="max-w-sm space-y-1.5">
                    <Label htmlFor="atendido_at">{t('triaje.fecha')}</Label>
                    <Input
                        id="atendido_at"
                        type="datetime-local"
                        disabled={!puede_editar}
                        className={campoClass}
                        value={form.data.atendido_at}
                        onChange={(event) => form.setData('atendido_at', event.target.value)}
                    />
                </div>

                <ConstantesCompactas
                    title={t('desparasitacion.constantes')}
                    labels={labels}
                    disabled={!puede_editar}
                    values={{
                        peso_kg: form.data.peso_kg,
                        temperatura_c: form.data.temperatura_c,
                        fc_lpm: form.data.fc_lpm,
                        fr_rpm: form.data.fr_rpm,
                        tlc: form.data.tlc,
                        pa: form.data.pa,
                        hidratacion: form.data.hidratacion,
                    }}
                    onChange={(key, value) => {
                        const modo = key === 'peso_kg' || key === 'temperatura_c' || key === 'tlc' ? 'decimal' : 'entero';
                        form.setData(key, soloNumero(value, modo));
                    }}
                />

                {SECCIONES.map((seccion) => (
                    <ListaMarcas
                        key={seccion.id}
                        titulo={t(`triaje.secciones.${seccion.id}`)}
                        abierta={abiertas[seccion.id] !== false}
                        onToggle={() => toggleSeccion(seccion.id)}
                        opciones={seccion.opciones.map((opcion) => ({
                            id: opcion,
                            label: t(`triaje.opciones.${seccion.id}.${opcion}`),
                            activo: (form.data.hallazgos[seccion.id] ?? []).includes(opcion),
                        }))}
                        disabled={!puede_editar}
                        onPick={(opcion) => toggleHallazgo(seccion.id, opcion)}
                    />
                ))}

                <ListaMarcas
                    titulo={t('triaje.recomendaciones')}
                    abierta={abiertas.recomendaciones !== false}
                    onToggle={() => toggleSeccion('recomendaciones')}
                    opciones={RECOMENDACIONES.map((opcion) => ({
                        id: opcion,
                        label: t(`triaje.recomendacion.${opcion}`),
                        activo: form.data.recomendaciones.includes(opcion),
                    }))}
                    disabled={!puede_editar}
                    onPick={toggleRecomendacion}
                />

                <section className="rounded-xl border border-border/70 bg-card">
                    <header className="flex items-center gap-2 border-b border-border/60 px-4 py-2.5 text-sm font-medium">
                        <MessageSquare className="size-4 text-muted-foreground" />
                        {t('triaje.comentarios')}
                    </header>
                    <div className="p-4">
                        <Textarea
                            rows={3}
                            disabled={!puede_editar}
                            className="border-amber-100/80 bg-[#fbf6e4] shadow-none dark:border-amber-900/40 dark:bg-amber-950/25"
                            value={form.data.comentarios}
                            onChange={(event) => form.setData('comentarios', event.target.value)}
                        />
                    </div>
                </section>

                <div className="flex flex-wrap items-center justify-end gap-3">
                    <p className="mr-auto text-xs text-muted-foreground">{t('triaje.recordatorio')}</p>
                    <Button type="button" variant="outline" className="cursor-pointer" onClick={volver}>
                        {t('triaje.cancelar')}
                    </Button>
                    <Button type="submit" disabled={!puede_editar || form.processing} className="cursor-pointer gap-2">
                        {form.processing ? <Loader2 className="size-4 animate-spin" /> : null}
                        {t('triaje.guardar')}
                    </Button>
                </div>
            </form>
        </div>
    );
}

function ListaMarcas({
    titulo,
    abierta,
    onToggle,
    opciones,
    disabled,
    onPick,
}: {
    titulo: string;
    abierta: boolean;
    onToggle: () => void;
    opciones: { id: string; label: string; activo: boolean }[];
    disabled: boolean;
    onPick: (id: string) => void;
}) {
    return (
        <section className="overflow-hidden rounded-xl border border-border/70 bg-card">
            <button
                type="button"
                className="flex w-full cursor-pointer items-center justify-between px-4 py-2.5 text-left"
                onClick={onToggle}
            >
                <span className="text-xs font-semibold tracking-wide uppercase">{titulo}</span>
                <ChevronDown className={cn('size-4 text-muted-foreground transition-transform', abierta && 'rotate-180')} />
            </button>
            {abierta ? (
                <div className="grid gap-2 border-t border-border/60 p-3 sm:grid-cols-2">
                    {opciones.map((opcion) => (
                        <button
                            key={opcion.id}
                            type="button"
                            role="checkbox"
                            aria-checked={opcion.activo}
                            disabled={disabled}
                            className={cn(
                                'flex cursor-pointer items-center gap-2.5 rounded-lg border px-3 py-2 text-left text-sm',
                                opcion.activo
                                    ? 'border-violet-400 bg-violet-50 text-violet-950 dark:border-violet-700 dark:bg-violet-950/40 dark:text-violet-100'
                                    : 'border-border/80 bg-background hover:bg-muted/40',
                            )}
                            onClick={() => onPick(opcion.id)}
                        >
                            <span
                                className={cn(
                                    'flex size-4 shrink-0 items-center justify-center rounded border',
                                    opcion.activo
                                        ? 'border-violet-600 bg-violet-600 text-white'
                                        : 'border-muted-foreground/40 bg-background',
                                )}
                            >
                                {opcion.activo ? <Check className="size-3" strokeWidth={3} /> : null}
                            </span>
                            {opcion.label}
                        </button>
                    ))}
                </div>
            ) : null}
        </section>
    );
}

type TriajeCreateLinks = {
    store_url: string;
    atendido_at: string;
};

type TriajePayload = Omit<Props, 'onVolver' | 'onSaved'>;

function TriajeRegistro({
    editUrl,
    onVolver,
    onSaved,
}: {
    editUrl: string;
    onVolver: () => void;
    onSaved?: (url: string) => void;
}) {
    const { t } = useTranslation('pacientes');
    const [payload, setPayload] = useState<TriajePayload | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancel = false;
        void fetch(editUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(async (res) => {
                if (!res.ok) {
                    throw new Error(t('triaje.error_abrir'));
                }

                return (await res.json()) as TriajePayload;
            })
            .then((body) => {
                if (!cancel) {
                    setPayload(body);
                }
            })
            .catch((reason: unknown) => {
                if (!cancel) {
                    setError(reason instanceof Error ? reason.message : t('triaje.error_abrir'));
                }
            });

        return () => {
            cancel = true;
        };
    }, [editUrl, t]);

    if (error) {
        return (
            <div className="flex flex-col items-start gap-3">
                <p className="text-sm text-destructive">{error}</p>
                <Button type="button" variant="outline" className="cursor-pointer" onClick={onVolver}>
                    <ArrowLeft className="size-4" />
                    {t('triaje.volver')}
                </Button>
            </div>
        );
    }

    if (!payload) {
        return (
            <div className="flex justify-center py-12">
                <Loader2 className="size-5 animate-spin text-muted-foreground" />
            </div>
        );
    }

    return <TriajeForm {...payload} onVolver={onVolver} onSaved={onSaved} />;
}

export function TriajeEmbed({
    paciente,
    create,
    editUrl,
    onVolver,
    onSaved,
}: {
    paciente: { id: string; nombre: string };
    create: TriajeCreateLinks | null;
    editUrl: string | null;
    onVolver: () => void;
    onSaved?: (url: string) => void;
}) {
    if (editUrl) {
        return <TriajeRegistro key={editUrl} editUrl={editUrl} onVolver={onVolver} onSaved={onSaved} />;
    }

    if (!create) {
        return null;
    }

    return (
        <TriajeForm
            paciente={paciente}
            registro={null}
            atendido_at={create.atendido_at}
            puede_editar
            guardar_url={create.store_url}
            method="post"
            onVolver={onVolver}
            onSaved={onSaved}
        />
    );
}

type PageProps = Omit<Props, 'onVolver'> & { volver_url: string };

export default function TriajePage({ volver_url, ...props }: PageProps) {
    const { t } = useTranslation('pacientes');

    return (
        <>
            <Head title={t('triaje.title')} />
            <div className="flex flex-1 flex-col p-4 sm:p-6">
                <TriajeForm {...props} onVolver={() => router.visit(volver_url)} />
            </div>
        </>
    );
}
