import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Bug, Loader2, MoreHorizontal, Receipt, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { toastManager } from '@/lib/toast';
import { cn } from '@/lib/utils';
import { ConsultaDictationBar } from '@/pages/clinica/historias-clinicas/components/consulta-dictation-bar';
import { ConstantesCompactas, soloNumero } from './components/constantes-compactas';
import { FichaRecetas, type FichaRecetaItem } from './components/ficha-recetas';

type Tri = 'disminuido' | 'normal' | 'aumentado' | '';

type Aplicado = {
    producto_id: string | null;
    nombre: string;
    especificaciones: string;
    proxima_at: string;
    proxima_nombre: string;
    rol: 'principal' | 'complementario';
};

type RecetaLinea = {
    producto_id?: string | null;
    nombre: string;
    especificaciones: string;
    cantidad: string;
};

type Dictado = {
    anamnesis?: string | null;
    apetito?: Tri | null;
    ingesta_agua?: Tri | null;
    vomitos_frecuencia?: string | null;
    vomitos_descripcion?: string | null;
    heces_frecuencia?: string | null;
    heces_descripcion?: string | null;
    orina_frecuencia?: string | null;
    orina_color?: string | null;
    orina_olor?: string | null;
    ultimo_celo?: string | null;
    peso_kg?: string | null;
    temperatura_c?: string | null;
    fc_lpm?: string | null;
    fr_rpm?: string | null;
    tlc?: string | null;
    pa?: string | null;
    hidratacion?: string | null;
    producto_nombre?: string | null;
    especificaciones?: string | null;
    proxima_nombre?: string | null;
    comentarios?: string | null;
};

type Registro = {
    id?: string;
    recetas?: FichaRecetaItem[];
    anamnesis: string | null;
    apetito: Tri | null;
    ingesta_agua: Tri | null;
    vomitos_frecuencia: string | null;
    vomitos_descripcion: string | null;
    heces_frecuencia: string | null;
    heces_descripcion: string | null;
    orina_frecuencia: string | null;
    orina_color: string | null;
    orina_olor: string | null;
    ultimo_celo: string | null;
    peso_kg: string | number | null;
    temperatura_c: string | number | null;
    fc_lpm: string | number | null;
    fr_rpm: string | number | null;
    tlc: string | null;
    pa: string | null;
    hidratacion: string | null;
    aplicados: Aplicado[];
    receta: RecetaLinea[];
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
    productos_url: string;
    dictar_url: string;
    cargos_url?: string | null;
    recargar_url?: string | null;
    onVolver: () => void;
};

function str(value: string | number | null | undefined): string {
    if (value == null) {
        return '';
    }

    return String(value);
}

function vacioAplicado(): Aplicado {
    return {
        producto_id: null,
        nombre: '',
        especificaciones: '',
        proxima_at: '',
        proxima_nombre: '',
        rol: 'principal',
    };
}

export function DesparasitacionForm({
    paciente,
    registro,
    atendido_at,
    puede_editar,
    guardar_url,
    method,
    productos_url,
    dictar_url,
    cargos_url = null,
    recargar_url = null,
    onVolver,
}: Props) {
    const { t } = useTranslation('pacientes');
    const [detalleOpen, setDetalleOpen] = useState(false);
    const [buscar, setBuscar] = useState('');
    const [sugerencias, setSugerencias] = useState<{ id: string; nombre: string; sku: string | null }[]>([]);

    const form = useForm({
        atendido_at,
        anamnesis: registro?.anamnesis ?? '',
        apetito: (registro?.apetito ?? '') as Tri,
        ingesta_agua: (registro?.ingesta_agua ?? '') as Tri,
        vomitos_frecuencia: registro?.vomitos_frecuencia ?? '',
        vomitos_descripcion: registro?.vomitos_descripcion ?? '',
        heces_frecuencia: registro?.heces_frecuencia ?? '',
        heces_descripcion: registro?.heces_descripcion ?? '',
        orina_frecuencia: registro?.orina_frecuencia ?? '',
        orina_color: registro?.orina_color ?? '',
        orina_olor: registro?.orina_olor ?? '',
        ultimo_celo: registro?.ultimo_celo ?? '',
        peso_kg: soloNumero(str(registro?.peso_kg), 'decimal'),
        temperatura_c: soloNumero(str(registro?.temperatura_c), 'decimal'),
        fc_lpm: soloNumero(str(registro?.fc_lpm), 'entero'),
        fr_rpm: soloNumero(str(registro?.fr_rpm), 'entero'),
        tlc: soloNumero(registro?.tlc, 'decimal'),
        pa: soloNumero(registro?.pa, 'entero'),
        hidratacion: soloNumero(registro?.hidratacion, 'entero'),
        comentarios: registro?.comentarios ?? '',
        aplicados: (registro?.aplicados ?? []).map((linea) => ({
            ...vacioAplicado(),
            ...linea,
            especificaciones: linea.especificaciones ?? '',
            proxima_at: linea.proxima_at ?? '',
            proxima_nombre: linea.proxima_nombre ?? '',
        })),
        receta: (registro?.receta ?? []).map((linea) => ({
            producto_id: linea.producto_id ?? null,
            nombre: linea.nombre ?? '',
            especificaciones: linea.especificaciones ?? '',
            cantidad: linea.cantidad ?? '',
        })),
    });

    useEffect(() => {
        const q = buscar.trim();
        if (q.length < 1) {
            setSugerencias([]);
            return;
        }
        const timer = window.setTimeout(() => {
            void fetch(`${productos_url}?q=${encodeURIComponent(q)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then((res) => res.json())
                .then((body: { data?: { id: string; nombre: string; sku: string | null }[] }) => {
                    setSugerencias(body.data ?? []);
                })
                .catch(() => setSugerencias([]));
        }, 250);

        return () => window.clearTimeout(timer);
    }, [buscar, productos_url]);

    const aplicarDictado = (fields: Dictado) => {
        const next = { ...form.data };
        const fill = <K extends keyof typeof next>(key: K, value: (typeof next)[K] | null | undefined) => {
            if (value == null || value === '') {
                return;
            }
            next[key] = value as (typeof next)[K];
        };
        fill('anamnesis', fields.anamnesis);
        fill('apetito', fields.apetito ?? '');
        fill('ingesta_agua', fields.ingesta_agua ?? '');
        fill('vomitos_frecuencia', soloDigitos(fields.vomitos_frecuencia));
        fill('vomitos_descripcion', fields.vomitos_descripcion);
        fill('heces_frecuencia', soloDigitos(fields.heces_frecuencia));
        fill('heces_descripcion', fields.heces_descripcion);
        fill('orina_frecuencia', soloDigitos(fields.orina_frecuencia));
        fill('orina_color', fields.orina_color);
        fill('orina_olor', fields.orina_olor);
        fill('ultimo_celo', fields.ultimo_celo);
        fill('peso_kg', soloNumero(fields.peso_kg, 'decimal'));
        fill('temperatura_c', soloNumero(fields.temperatura_c, 'decimal'));
        fill('fc_lpm', soloNumero(fields.fc_lpm, 'entero'));
        fill('fr_rpm', soloNumero(fields.fr_rpm, 'entero'));
        fill('tlc', soloNumero(fields.tlc, 'decimal'));
        fill('pa', soloNumero(fields.pa, 'entero'));
        fill('hidratacion', soloNumero(fields.hidratacion, 'entero'));
        fill('comentarios', fields.comentarios);
        if (fields.producto_nombre) {
            next.aplicados = [
                ...next.aplicados,
                {
                    ...vacioAplicado(),
                    nombre: fields.producto_nombre,
                    especificaciones: fields.especificaciones ?? '',
                    proxima_nombre: fields.proxima_nombre ?? '',
                },
            ];
        }
        form.setData(next);
    };

    const agregarProducto = (nombre: string, productoId: string | null = null) => {
        const limpio = nombre.trim();
        if (limpio === '') {
            return;
        }
        form.setData('aplicados', [
            ...form.data.aplicados,
            { ...vacioAplicado(), nombre: limpio, producto_id: productoId },
        ]);
        setBuscar('');
        setSugerencias([]);
    };

    const tri = (name: 'apetito' | 'ingesta_agua', label: string) => (
        <fieldset className="space-y-2">
            <legend className="text-sm font-medium">{label}</legend>
            <div className="flex flex-wrap gap-3">
                {(['disminuido', 'normal', 'aumentado'] as const).map((opcion) => (
                    <label key={opcion} className="inline-flex items-center gap-2 text-sm">
                        <input
                            type="radio"
                            name={name}
                            checked={form.data[name] === opcion}
                            disabled={!puede_editar}
                            onChange={() => form.setData(name, opcion)}
                        />
                        {t(`desparasitacion.${opcion}`)}
                    </label>
                ))}
            </div>
        </fieldset>
    );

    return (
        <div className="flex flex-col gap-3">
                <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                    <div className="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-2">
                        <div className="min-w-0">
                            <h2 className="flex items-center gap-2 text-lg font-semibold tracking-tight">
                                <span className="flex size-8 items-center justify-center rounded-lg bg-lime-500/15 text-lime-700">
                                    <Bug className="size-4" />
                                </span>
                                {t('desparasitacion.title')}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {t('desparasitacion.subtitle', { nombre: paciente.nombre })}
                                {registro?.veterinario ? ` · ${t('desparasitacion.veterinario')}: ${registro.veterinario}` : ''}
                            </p>
                        </div>
                        {puede_editar ? (
                            <ConsultaDictationBar<Dictado>
                                variant="compact"
                                endpoint={dictar_url}
                                onFields={aplicarDictado}
                            />
                        ) : null}
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="flex items-center gap-2">
                            <Label htmlFor="atendido_at" className="shrink-0 text-xs text-muted-foreground">
                                {t('desparasitacion.atendido')}
                            </Label>
                            <Input
                                id="atendido_at"
                                type="datetime-local"
                                className="h-8 w-[12.5rem] px-2 text-sm"
                                value={form.data.atendido_at}
                                disabled={!puede_editar}
                                onChange={(event) => form.setData('atendido_at', event.target.value)}
                            />
                        </div>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button type="button" variant="ghost" size="icon" className="size-8 text-muted-foreground">
                                    <MoreHorizontal className="size-4" />
                                    <span className="sr-only">{t('desparasitacion.precuenta')}</span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-48">
                                <DropdownMenuItem
                                    className="cursor-pointer gap-2"
                                    onSelect={() => {
                                        if (!cargos_url) {
                                            toastManager.add({
                                                type: 'info',
                                                title: t('desparasitacion.precuenta_guardar'),
                                            });
                                            return;
                                        }
                                        router.visit(cargos_url);
                                    }}
                                >
                                    <Receipt className="size-4" />
                                    {t('desparasitacion.precuenta')}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <button
                            type="button"
                            onClick={() => {
                                if (
                                    form.isDirty &&
                                    !window.confirm(t('desparasitacion.confirmar_volver'))
                                ) {
                                    return;
                                }
                                onVolver();
                            }}
                            className="inline-flex cursor-pointer items-center gap-1 px-1 text-sm font-medium text-primary underline-offset-4 hover:underline"
                        >
                            <ArrowLeft className="size-3.5" />
                            {t('desparasitacion.volver')}
                        </button>
                    </div>
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
                                    title: t('desparasitacion.guardado'),
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
                    {form.hasErrors ? (
                        <p className="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                            {Object.values(form.errors)
                                .flatMap((value) => (Array.isArray(value) ? value : [value]))
                                .filter(Boolean)
                                .join(' ')}
                        </p>
                    ) : null}
                    <section className="rounded-xl border bg-card p-3">
                        <div className="mb-2 flex items-center justify-between gap-2">
                            <h2 className="text-sm font-semibold">{t('desparasitacion.anamnesis')}</h2>
                            <Button type="button" variant="outline" size="sm" onClick={() => setDetalleOpen(true)}>
                                + {t('desparasitacion.anamnesis_detallada')}
                            </Button>
                        </div>
                        <Textarea
                            rows={2}
                            placeholder={t('desparasitacion.anamnesis_ph')}
                            value={form.data.anamnesis}
                            disabled={!puede_editar}
                            onChange={(event) => form.setData('anamnesis', event.target.value)}
                        />
                    </section>

                    <ConstantesCompactas
                        title={t('desparasitacion.constantes')}
                        disabled={!puede_editar}
                        labels={{
                            peso_kg: t('desparasitacion.peso'),
                            temperatura_c: t('desparasitacion.temp'),
                            fc_lpm: t('desparasitacion.fc'),
                            fr_rpm: t('desparasitacion.fr'),
                            tlc: t('desparasitacion.tlc'),
                            pa: t('desparasitacion.pa'),
                            hidratacion: t('desparasitacion.hidratacion'),
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

                    <section className="rounded-xl border bg-card p-3">
                        <h2 className="mb-2 text-sm font-semibold">{t('desparasitacion.antiparasitario')}</h2>
                        <div className="relative mb-3">
                            <Search className="pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                className="bg-amber-50/80 pl-9 dark:bg-amber-950/20"
                                placeholder={t('desparasitacion.buscar_desparasitacion')}
                                value={buscar}
                                disabled={!puede_editar}
                                onChange={(event) => setBuscar(event.target.value)}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        event.preventDefault();
                                        agregarProducto(buscar);
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
                                                onClick={() => agregarProducto(item.nombre, item.id)}
                                            >
                                                {item.nombre}
                                                {item.sku ? <span className="ml-2 text-muted-foreground">{item.sku}</span> : null}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            ) : null}
                        </div>
                        {form.data.aplicados.length === 0 ? (
                            <p className="text-sm text-muted-foreground">{t('desparasitacion.sin_lineas')}</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[720px] text-left text-sm">
                                    <thead className="text-xs text-muted-foreground">
                                        <tr>
                                            <th className="pb-2 font-medium">{t('desparasitacion.aplicado')}</th>
                                            <th className="pb-2 font-medium">{t('desparasitacion.especificaciones')}</th>
                                            <th className="pb-2 font-medium">{t('desparasitacion.proxima_fecha')}</th>
                                            <th className="pb-2 font-medium">{t('desparasitacion.proxima_nombre')}</th>
                                            <th className="pb-2 font-medium">{t('desparasitacion.principal')}</th>
                                            <th className="pb-2" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {form.data.aplicados.map((linea, index) => (
                                            <tr key={`${linea.producto_id ?? 'n'}-${index}`} className="align-top">
                                                <td className="py-1 pr-2 font-medium">{linea.nombre}</td>
                                                <td className="py-1 pr-2">
                                                    <Input
                                                        value={linea.especificaciones}
                                                        disabled={!puede_editar}
                                                        onChange={(event) => {
                                                            const next = [...form.data.aplicados];
                                                            next[index] = { ...linea, especificaciones: event.target.value };
                                                            form.setData('aplicados', next);
                                                        }}
                                                    />
                                                </td>
                                                <td className="py-1 pr-2">
                                                    <Input
                                                        type="date"
                                                        value={linea.proxima_at}
                                                        disabled={!puede_editar}
                                                        onChange={(event) => {
                                                            const next = [...form.data.aplicados];
                                                            next[index] = { ...linea, proxima_at: event.target.value };
                                                            form.setData('aplicados', next);
                                                        }}
                                                    />
                                                </td>
                                                <td className="py-1 pr-2">
                                                    <Input
                                                        value={linea.proxima_nombre}
                                                        disabled={!puede_editar}
                                                        onChange={(event) => {
                                                            const next = [...form.data.aplicados];
                                                            next[index] = { ...linea, proxima_nombre: event.target.value };
                                                            form.setData('aplicados', next);
                                                        }}
                                                    />
                                                </td>
                                                <td className="py-1 pr-2">
                                                    <select
                                                        className={cn(
                                                            'h-9 rounded-md border bg-transparent px-2 text-sm',
                                                        )}
                                                        value={linea.rol}
                                                        disabled={!puede_editar}
                                                        onChange={(event) => {
                                                            const next = [...form.data.aplicados];
                                                            next[index] = {
                                                                ...linea,
                                                                rol: event.target.value === 'complementario' ? 'complementario' : 'principal',
                                                            };
                                                            form.setData('aplicados', next);
                                                        }}
                                                    >
                                                        <option value="principal">{t('desparasitacion.principal')}</option>
                                                        <option value="complementario">{t('desparasitacion.complementario')}</option>
                                                    </select>
                                                </td>
                                                <td className="py-1">
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        disabled={!puede_editar}
                                                        aria-label={t('desparasitacion.quitar')}
                                                        onClick={() =>
                                                            form.setData(
                                                                'aplicados',
                                                                form.data.aplicados.filter((_, i) => i !== index),
                                                            )
                                                        }
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

                    <FichaRecetas
                        ns="desparasitacion"
                        paciente={paciente}
                        registroId={registro?.id ?? null}
                        origen="desparasitacion"
                        recetasIniciales={registro?.recetas ?? []}
                        recargarUrl={recargar_url}
                        puedeEditar={puede_editar}
                    />

                    <section className="rounded-xl border bg-card p-3">
                        <h2 className="mb-2 text-sm font-semibold">{t('desparasitacion.comentarios')}</h2>
                        <Textarea
                            rows={2}
                            value={form.data.comentarios}
                            disabled={!puede_editar}
                            onChange={(event) => form.setData('comentarios', event.target.value)}
                        />
                    </section>

                    {puede_editar ? (
                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? <Loader2 className="size-4 animate-spin" /> : null}
                                {form.processing ? t('desparasitacion.guardando') : t('desparasitacion.guardar')}
                            </Button>
                        </div>
                    ) : null}
                </form>

            <Dialog open={detalleOpen} onOpenChange={setDetalleOpen}>
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{t('desparasitacion.anamnesis_detallada')}</DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {tri('apetito', t('desparasitacion.apetito'))}
                        {tri('ingesta_agua', t('desparasitacion.ingesta'))}
                        <div className="space-y-1 sm:col-span-2">
                            <p className="text-sm font-medium">{t('desparasitacion.vomitos')}</p>
                        </div>
                        <Campo
                            label={t('desparasitacion.frecuencia')}
                            value={form.data.vomitos_frecuencia}
                            disabled={!puede_editar}
                            numeric
                            onChange={(value) => form.setData('vomitos_frecuencia', value)}
                        />
                        <Campo
                            label={t('desparasitacion.descripcion')}
                            value={form.data.vomitos_descripcion}
                            disabled={!puede_editar}
                            onChange={(value) => form.setData('vomitos_descripcion', value)}
                        />
                        <div className="space-y-1 sm:col-span-2">
                            <p className="text-sm font-medium">{t('desparasitacion.heces')}</p>
                        </div>
                        <Campo
                            label={t('desparasitacion.frecuencia')}
                            value={form.data.heces_frecuencia}
                            disabled={!puede_editar}
                            numeric
                            onChange={(value) => form.setData('heces_frecuencia', value)}
                        />
                        <Campo
                            label={t('desparasitacion.descripcion')}
                            value={form.data.heces_descripcion}
                            disabled={!puede_editar}
                            onChange={(value) => form.setData('heces_descripcion', value)}
                        />
                        <div className="space-y-1 sm:col-span-2">
                            <p className="text-sm font-medium">{t('desparasitacion.orina')}</p>
                        </div>
                        <Campo
                            label={t('desparasitacion.frecuencia')}
                            value={form.data.orina_frecuencia}
                            disabled={!puede_editar}
                            numeric
                            onChange={(value) => form.setData('orina_frecuencia', value)}
                        />
                        <Campo
                            label={t('desparasitacion.color')}
                            value={form.data.orina_color}
                            disabled={!puede_editar}
                            onChange={(value) => form.setData('orina_color', value)}
                        />
                        <Campo
                            label={t('desparasitacion.olor')}
                            value={form.data.orina_olor}
                            disabled={!puede_editar}
                            onChange={(value) => form.setData('orina_olor', value)}
                        />
                        <div className="space-y-1">
                            <Label htmlFor="ultimo_celo">{t('desparasitacion.ultimo_celo')}</Label>
                            <Input
                                id="ultimo_celo"
                                type="date"
                                value={form.data.ultimo_celo}
                                disabled={!puede_editar}
                                onChange={(event) => form.setData('ultimo_celo', event.target.value)}
                            />
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function soloDigitos(value: string | null | undefined): string | null {
    if (value == null || value === '') {
        return null;
    }
    const digits = String(value).replace(/\D/g, '');

    return digits === '' ? null : digits;
}

function Campo({
    label,
    value,
    disabled,
    numeric = false,
    onChange,
}: {
    label: string;
    value: string;
    disabled: boolean;
    numeric?: boolean;
    onChange: (value: string) => void;
}) {
    return (
        <div className="space-y-1">
            <Label>{label}</Label>
            <Input
                inputMode={numeric ? 'numeric' : undefined}
                value={value}
                disabled={disabled}
                onChange={(event) =>
                    onChange(numeric ? event.target.value.replace(/\D/g, '') : event.target.value)
                }
            />
        </div>
    );
}

export type DesparasitacionCreateLinks = {
    store_url: string;
    productos_url: string;
    dictar_url: string;
    atendido_at: string;
};

type DesparasitacionPayload = Omit<Props, 'onVolver'>;

export function DesparasitacionEmbed({
    paciente,
    create,
    editUrl,
    onVolver,
}: {
    paciente: { id: string; nombre: string };
    create: DesparasitacionCreateLinks | null;
    editUrl: string | null;
    onVolver: () => void;
}) {
    const { t } = useTranslation('pacientes');
    const [payload, setPayload] = useState<DesparasitacionPayload | null>(null);
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
                    throw new Error('No se pudo abrir la desparasitación.');
                }

                return (await res.json()) as DesparasitacionPayload;
            })
            .then((body) => {
                if (!cancel) {
                    setPayload(body);
                }
            })
            .catch((reason: unknown) => {
                if (!cancel) {
                    setError(reason instanceof Error ? reason.message : 'No se pudo abrir la desparasitación.');
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
                        {t('desparasitacion.volver')}
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

        return <DesparasitacionForm {...payload} recargar_url={editUrl} onVolver={onVolver} />;
    }

    if (!create) {
        return null;
    }

    return (
        <DesparasitacionForm
            paciente={paciente}
            registro={null}
            atendido_at={create.atendido_at}
            puede_editar
            guardar_url={create.store_url}
            method="post"
            productos_url={create.productos_url}
            dictar_url={create.dictar_url}
            onVolver={onVolver}
        />
    );
}

type PageProps = Omit<Props, 'onVolver'> & { volver_url: string };

export default function DesparasitacionPage({ volver_url, ...props }: PageProps) {
    const { t } = useTranslation('pacientes');

    return (
        <>
            <Head title={t('desparasitacion.title')} />
            <div className="flex flex-1 flex-col p-4 sm:p-6">
                <DesparasitacionForm {...props} onVolver={() => router.visit(volver_url)} />
            </div>
        </>
    );
}
