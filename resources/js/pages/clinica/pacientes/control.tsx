import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarPlus, ClipboardCheck, Loader2, Search, Trash2, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { toastManager } from '@/lib/toast';
import { cn } from '@/lib/utils';
import { ConstantesCompactas, soloNumero } from './components/constantes-compactas';

type Linea = {
    producto_id: string | null;
    nombre: string;
    especificaciones: string;
    cantidad: string;
    rol: 'principal' | 'complementario';
};

type Registro = {
    id: string;
    motivo: string | null;
    anamnesis: string | null;
    anamnesis_detalle: string | null;
    peso_kg: string | number | null;
    temperatura_c: string | number | null;
    fc_lpm: string | number | null;
    fr_rpm: string | number | null;
    tlc: string | null;
    pa: string | null;
    hidratacion: string | null;
    examen_clinico: string | null;
    examen_detalle: string | null;
    diagnosticos: string[];
    examenes: Partial<Linea>[];
    tratamiento: Partial<Linea>[];
    receta: Partial<Linea>[];
    comentarios: string | null;
    veterinario?: string | null;
};

type Props = {
    paciente: { id: string; nombre: string };
    registro: Registro | null;
    atendido_at: string;
    puede_editar: boolean;
    guardar_url: string;
    method: 'post' | 'put';
    productos_url: string;
    onVolver: () => void;
    onRegistrarCita?: () => void;
};

type Sugerencia = { id: string; nombre: string; sku: string | null };

function str(value: string | number | null | undefined): string {
    return value == null ? '' : String(value);
}

function lineaDesde(raw: Partial<Linea> | undefined): Linea {
    return {
        producto_id: raw?.producto_id ?? null,
        nombre: raw?.nombre ?? '',
        especificaciones: raw?.especificaciones ?? '',
        cantidad: raw?.cantidad ?? '',
        rol: raw?.rol === 'complementario' ? 'complementario' : 'principal',
    };
}

function BuscadorLineas({
    titulo,
    productosUrl,
    lineas,
    disabled,
    conCantidad,
    onChange,
}: {
    titulo: string;
    productosUrl: string;
    lineas: Linea[];
    disabled: boolean;
    conCantidad: boolean;
    onChange: (lineas: Linea[]) => void;
}) {
    const { t } = useTranslation('pacientes');
    const [buscar, setBuscar] = useState('');
    const [rol, setRol] = useState<'principal' | 'complementario'>('principal');
    const [sugerencias, setSugerencias] = useState<Sugerencia[]>([]);

    useEffect(() => {
        const q = buscar.trim();
        if (q.length < 1) {
            setSugerencias([]);

            return;
        }

        const timer = window.setTimeout(() => {
            void fetch(`${productosUrl}?q=${encodeURIComponent(q)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then((res) => res.json())
                .then((body: { data?: Sugerencia[] }) => setSugerencias(body.data ?? []))
                .catch(() => setSugerencias([]));
        }, 250);

        return () => window.clearTimeout(timer);
    }, [buscar, productosUrl]);

    const agregar = (nombre: string, productoId: string | null) => {
        const limpio = nombre.trim();
        if (limpio === '') {
            return;
        }

        onChange([
            ...lineas,
            {
                producto_id: productoId,
                nombre: limpio,
                especificaciones: '',
                cantidad: '',
                rol,
            },
        ]);
        setBuscar('');
        setSugerencias([]);
    };

    return (
        <section className="rounded-xl border bg-card px-3 py-2.5">
            <h2 className="mb-2 text-sm font-semibold">{titulo}</h2>
            <div className="relative mb-2 flex gap-2">
                <div className="relative min-w-0 flex-1">
                    <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                    <Input
                        value={buscar}
                        disabled={disabled}
                        placeholder={t('control.buscar')}
                        className="bg-amber-50/80 pl-8 dark:bg-amber-950/20"
                        onChange={(event) => setBuscar(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                agregar(buscar, null);
                            }
                        }}
                    />
                    {sugerencias.length > 0 ? (
                        <ul className="absolute z-20 mt-1 max-h-48 w-full overflow-auto rounded-md border bg-popover p-1 shadow-md">
                            {sugerencias.map((item) => (
                                <li key={item.id}>
                                    <button
                                        type="button"
                                        className="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                        onClick={() => agregar(item.nombre, item.id)}
                                    >
                                        {item.nombre}
                                        {item.sku ? <span className="ml-2 text-muted-foreground">{item.sku}</span> : null}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    ) : null}
                </div>
                <select
                    className="h-9 shrink-0 rounded-md border bg-transparent px-2 text-sm"
                    value={rol}
                    disabled={disabled}
                    onChange={(event) => setRol(event.target.value === 'complementario' ? 'complementario' : 'principal')}
                >
                    <option value="principal">{t('control.principal')}</option>
                    <option value="complementario">{t('control.complementario')}</option>
                </select>
            </div>
            {lineas.length === 0 ? null : (
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[640px] text-left text-sm">
                        <thead className="text-xs text-muted-foreground">
                            <tr>
                                <th className="pb-2 font-medium">{t('control.concepto')}</th>
                                <th className="pb-2 font-medium">{t('control.especificaciones')}</th>
                                {conCantidad ? <th className="pb-2 font-medium">{t('control.cantidad')}</th> : null}
                                <th className="pb-2 font-medium">{t('control.principal')}</th>
                                <th className="pb-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {lineas.map((linea, index) => (
                                <tr key={`${linea.producto_id ?? 'n'}-${index}`} className="align-top">
                                    <td className="py-1 pr-2 font-medium">{linea.nombre}</td>
                                    <td className="py-1 pr-2">
                                        <Input
                                            value={linea.especificaciones}
                                            disabled={disabled}
                                            onChange={(event) => {
                                                const next = [...lineas];
                                                next[index] = { ...linea, especificaciones: event.target.value };
                                                onChange(next);
                                            }}
                                        />
                                    </td>
                                    {conCantidad ? (
                                        <td className="py-1 pr-2">
                                            <Input
                                                value={linea.cantidad}
                                                disabled={disabled}
                                                onChange={(event) => {
                                                    const next = [...lineas];
                                                    next[index] = { ...linea, cantidad: event.target.value };
                                                    onChange(next);
                                                }}
                                            />
                                        </td>
                                    ) : null}
                                    <td className="py-1 pr-2">
                                        <select
                                            className="h-9 rounded-md border bg-transparent px-2 text-sm"
                                            value={linea.rol}
                                            disabled={disabled}
                                            onChange={(event) => {
                                                const next = [...lineas];
                                                next[index] = {
                                                    ...linea,
                                                    rol: event.target.value === 'complementario' ? 'complementario' : 'principal',
                                                };
                                                onChange(next);
                                            }}
                                        >
                                            <option value="principal">{t('control.principal')}</option>
                                            <option value="complementario">{t('control.complementario')}</option>
                                        </select>
                                    </td>
                                    <td className="py-1">
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            disabled={disabled}
                                            aria-label={t('control.quitar')}
                                            onClick={() => onChange(lineas.filter((_, i) => i !== index))}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}

export function ControlForm({
    registro,
    atendido_at,
    puede_editar,
    guardar_url,
    method,
    productos_url,
    onVolver,
    onRegistrarCita,
}: Props) {
    const { t } = useTranslation('pacientes');
    const [detalle, setDetalle] = useState<'anamnesis' | 'examen' | null>(null);
    const [diagnostico, setDiagnostico] = useState('');

    const form = useForm({
        atendido_at,
        motivo: registro?.motivo ?? 'Control',
        anamnesis: registro?.anamnesis ?? '',
        anamnesis_detalle: registro?.anamnesis_detalle ?? '',
        peso_kg: soloNumero(str(registro?.peso_kg), 'decimal'),
        temperatura_c: soloNumero(str(registro?.temperatura_c), 'decimal'),
        fc_lpm: soloNumero(str(registro?.fc_lpm), 'entero'),
        fr_rpm: soloNumero(str(registro?.fr_rpm), 'entero'),
        tlc: soloNumero(registro?.tlc, 'decimal'),
        pa: soloNumero(registro?.pa, 'entero'),
        hidratacion: soloNumero(registro?.hidratacion, 'entero'),
        examen_clinico: registro?.examen_clinico ?? '',
        examen_detalle: registro?.examen_detalle ?? '',
        diagnosticos: registro?.diagnosticos ?? [],
        examenes: (registro?.examenes ?? []).map((linea) => lineaDesde(linea)),
        tratamiento: (registro?.tratamiento ?? []).map((linea) => lineaDesde(linea)),
        receta: (registro?.receta ?? []).map((linea) => lineaDesde(linea)),
        comentarios: registro?.comentarios ?? '',
    });

    const agregarDiagnostico = () => {
        const nombre = diagnostico.trim();
        if (nombre === '' || form.data.diagnosticos.includes(nombre)) {
            setDiagnostico('');

            return;
        }

        form.setData('diagnosticos', [...form.data.diagnosticos, nombre]);
        setDiagnostico('');
    };

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                    <span className="flex size-9 items-center justify-center rounded-xl bg-teal-500/15 text-teal-700 dark:text-teal-200">
                        <ClipboardCheck className="size-4" />
                    </span>
                    <div>
                        <h2 className="text-base font-semibold">{t('control.title')}</h2>
                        {registro?.veterinario ? (
                            <p className="text-xs text-muted-foreground">{registro.veterinario}</p>
                        ) : null}
                    </div>
                </div>
                <button
                    type="button"
                    onClick={() => {
                        if (form.isDirty && !window.confirm(t('control.confirmar_volver'))) {
                            return;
                        }
                        onVolver();
                    }}
                    className="inline-flex cursor-pointer items-center gap-1 px-1 text-sm font-medium text-primary underline-offset-4 hover:underline"
                >
                    <ArrowLeft className="size-3.5" />
                    {t('control.volver')}
                </button>
            </div>

            <form
                className="flex flex-col gap-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    const options = {
                        preserveScroll: true,
                        preserveState: true,
                        onSuccess: () => {
                            toastManager.add({
                                type: 'success' as const,
                                title: t('control.guardado'),
                            });
                            onVolver();
                        },
                    };
                    if (method === 'post') {
                        form.post(guardar_url, options);

                        return;
                    }
                    form.put(guardar_url, options);
                }}
            >
                <section className="grid gap-3 rounded-xl border bg-card px-3 py-2.5 sm:grid-cols-2">
                    <div className="grid gap-1.5">
                        <Label htmlFor="control-atendido">{t('control.atendido')}</Label>
                        <Input
                            id="control-atendido"
                            type="datetime-local"
                            value={form.data.atendido_at}
                            disabled={!puede_editar}
                            onChange={(event) => form.setData('atendido_at', event.target.value)}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="control-motivo">{t('control.motivo')}</Label>
                        <Input
                            id="control-motivo"
                            value={form.data.motivo}
                            disabled={!puede_editar}
                            className="bg-amber-50/80 dark:bg-amber-950/20"
                            onChange={(event) => form.setData('motivo', event.target.value)}
                        />
                    </div>
                </section>

                <section className="rounded-xl border bg-card px-3 py-2.5">
                    <div className="mb-2 flex items-center justify-between gap-2">
                        <h2 className="text-sm font-semibold">{t('control.anamnesis')}</h2>
                        <Button type="button" size="sm" variant="outline" onClick={() => setDetalle('anamnesis')}>
                            {t('control.anamnesis_detallada')}
                        </Button>
                    </div>
                    <Textarea
                        value={form.data.anamnesis}
                        disabled={!puede_editar}
                        rows={4}
                        onChange={(event) => form.setData('anamnesis', event.target.value)}
                    />
                </section>

                <ConstantesCompactas
                    title={t('control.constantes')}
                    disabled={!puede_editar}
                    labels={{
                        peso_kg: t('control.peso'),
                        temperatura_c: t('control.temp'),
                        fc_lpm: t('control.fc'),
                        fr_rpm: t('control.fr'),
                        tlc: t('control.tlc'),
                        pa: t('control.pa'),
                        hidratacion: t('control.hidratacion'),
                    }}
                    values={{
                        peso_kg: form.data.peso_kg,
                        temperatura_c: form.data.temperatura_c,
                        fc_lpm: form.data.fc_lpm,
                        fr_rpm: form.data.fr_rpm,
                        tlc: form.data.tlc,
                        pa: form.data.pa,
                        hidratacion: form.data.hidratacion,
                    }}
                    onChange={(key, value) => form.setData(key, value)}
                />

                <section className="rounded-xl border bg-card px-3 py-2.5">
                    <div className="mb-2 flex items-center justify-between gap-2">
                        <h2 className="text-sm font-semibold">{t('control.examen')}</h2>
                        <Button type="button" size="sm" variant="outline" onClick={() => setDetalle('examen')}>
                            {t('control.formulario_detallado')}
                        </Button>
                    </div>
                    <Textarea
                        value={form.data.examen_clinico}
                        disabled={!puede_editar}
                        rows={4}
                        onChange={(event) => form.setData('examen_clinico', event.target.value)}
                    />
                </section>

                <section className="rounded-xl border bg-card px-3 py-2.5">
                    <h2 className="mb-2 text-sm font-semibold">{t('control.diagnostico')}</h2>
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                        <Input
                            value={diagnostico}
                            disabled={!puede_editar}
                            placeholder={t('control.diagnostico_placeholder')}
                            className="bg-amber-50/80 pl-8 dark:bg-amber-950/20"
                            onChange={(event) => setDiagnostico(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    agregarDiagnostico();
                                }
                            }}
                        />
                    </div>
                    {form.data.diagnosticos.length > 0 ? (
                        <ul className="mt-2 flex flex-wrap gap-1.5">
                            {form.data.diagnosticos.map((item) => (
                                <li
                                    key={item}
                                    className="inline-flex items-center gap-1 rounded-full bg-teal-500/12 px-2 py-0.5 text-xs font-medium text-teal-900 dark:text-teal-100"
                                >
                                    {item}
                                    {puede_editar ? (
                                        <button
                                            type="button"
                                            className="cursor-pointer rounded-full p-0.5 hover:bg-teal-500/20"
                                            aria-label={t('control.quitar')}
                                            onClick={() =>
                                                form.setData(
                                                    'diagnosticos',
                                                    form.data.diagnosticos.filter((nombre) => nombre !== item),
                                                )
                                            }
                                        >
                                            <X className="size-3" />
                                        </button>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    ) : null}
                </section>

                <BuscadorLineas
                    titulo={t('control.examenes')}
                    productosUrl={productos_url}
                    lineas={form.data.examenes}
                    disabled={!puede_editar}
                    conCantidad={false}
                    onChange={(lineas) => form.setData('examenes', lineas)}
                />
                <BuscadorLineas
                    titulo={t('control.tratamiento')}
                    productosUrl={productos_url}
                    lineas={form.data.tratamiento}
                    disabled={!puede_editar}
                    conCantidad
                    onChange={(lineas) => form.setData('tratamiento', lineas)}
                />
                <BuscadorLineas
                    titulo={t('control.receta')}
                    productosUrl={productos_url}
                    lineas={form.data.receta}
                    disabled={!puede_editar}
                    conCantidad
                    onChange={(lineas) => form.setData('receta', lineas)}
                />

                <section className="rounded-xl border bg-card px-3 py-2.5">
                    <h2 className="mb-2 text-sm font-semibold">{t('control.comentarios')}</h2>
                    <Textarea
                        value={form.data.comentarios}
                        disabled={!puede_editar}
                        rows={3}
                        onChange={(event) => form.setData('comentarios', event.target.value)}
                    />
                </section>

                <section className="rounded-xl border bg-card px-3 py-4 text-center">
                    <h2 className="mb-3 text-sm font-semibold">{t('control.registrar_cita_titulo')}</h2>
                    <p className="mb-3 text-sm text-muted-foreground">{t('control.registrar_cita_vacio')}</p>
                    {onRegistrarCita ? (
                        <Button type="button" className="bg-emerald-600 hover:bg-emerald-700" onClick={onRegistrarCita}>
                            <CalendarPlus className="size-4" />
                            {t('control.registrar_cita')}
                        </Button>
                    ) : null}
                </section>

                {puede_editar ? (
                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? <Loader2 className={cn('size-4 animate-spin')} /> : null}
                            {t('control.guardar')}
                        </Button>
                    </div>
                ) : null}
            </form>

            <Dialog open={detalle !== null} onOpenChange={(open) => !open && setDetalle(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {detalle === 'examen' ? t('control.formulario_detallado') : t('control.anamnesis_detallada')}
                        </DialogTitle>
                    </DialogHeader>
                    <Textarea
                        rows={8}
                        disabled={!puede_editar}
                        value={detalle === 'examen' ? form.data.examen_detalle : form.data.anamnesis_detalle}
                        onChange={(event) =>
                            form.setData(
                                detalle === 'examen' ? 'examen_detalle' : 'anamnesis_detalle',
                                event.target.value,
                            )
                        }
                    />
                </DialogContent>
            </Dialog>
        </div>
    );
}

export type ControlCreateLinks = {
    store_url: string;
    productos_url: string;
    atendido_at: string;
};

type Payload = Omit<Props, 'onVolver' | 'onRegistrarCita'>;

export function ControlEmbed({
    paciente,
    create,
    editUrl,
    onVolver,
    onRegistrarCita,
}: {
    paciente: { id: string; nombre: string };
    create: ControlCreateLinks | null;
    editUrl: string | null;
    onVolver: () => void;
    onRegistrarCita?: () => void;
}) {
    const { t } = useTranslation('pacientes');
    const [payload, setPayload] = useState<Payload | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!editUrl) {
            return;
        }

        let cancel = false;
        setError(null);
        setPayload(null);
        void fetch(editUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(async (res) => {
                if (!res.ok) {
                    throw new Error('No se pudo abrir el control.');
                }

                return (await res.json()) as Payload;
            })
            .then((body) => {
                if (!cancel) {
                    setPayload(body);
                }
            })
            .catch((reason: unknown) => {
                if (!cancel) {
                    setError(reason instanceof Error ? reason.message : 'No se pudo abrir el control.');
                }
            });

        return () => {
            cancel = true;
        };
    }, [editUrl]);

    if (editUrl) {
        if (error) {
            return (
                <div className="flex flex-col items-start gap-3">
                    <p className="text-sm text-destructive">{error}</p>
                    <Button type="button" variant="outline" onClick={onVolver}>
                        <ArrowLeft className="size-4" />
                        {t('control.volver')}
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

        return <ControlForm {...payload} onVolver={onVolver} onRegistrarCita={onRegistrarCita} />;
    }

    if (!create) {
        return null;
    }

    return (
        <ControlForm
            paciente={paciente}
            registro={null}
            atendido_at={create.atendido_at}
            puede_editar
            guardar_url={create.store_url}
            method="post"
            productos_url={create.productos_url}
            onVolver={onVolver}
            onRegistrarCita={onRegistrarCita}
        />
    );
}

type PageProps = Omit<Props, 'onVolver' | 'onRegistrarCita'> & { volver_url: string };

export default function ControlPage({ volver_url, ...props }: PageProps) {
    const { t } = useTranslation('pacientes');

    return (
        <>
            <Head title={t('control.title')} />
            <div className="flex flex-1 flex-col p-4 sm:p-6">
                <ControlForm {...props} onVolver={() => router.visit(volver_url)} />
            </div>
        </>
    );
}
