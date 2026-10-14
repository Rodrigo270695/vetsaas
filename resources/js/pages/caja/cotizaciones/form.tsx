import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Loader2,
    Package,
    Plus,
    Save,
    Search,
    Stethoscope,
    Trash2,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import type { ComboboxOption } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { calcularTotales, lineaTotal } from './cotizacion-totales';
import type {
    ClinicaCotizacionConfig,
    CotizacionFormData,
    CotizacionLineaInput,
    ProductoHit,
    PropietarioDetalle,
    ServicioHit,
    TipoLinea,
} from './types';
import { formatMoney } from './types';

type Props = {
    cotizacion: CotizacionFormData | null;
    defaults: { sede_id: string | null; fecha: string; vigencia_hasta: string } | null;
    sedes: { id: string; nombre: string }[];
    clinica: ClinicaCotizacionConfig;
};

type PropietarioOpcion = { id: string; label: string; doc: string | null };

const NONE = '__none__';

async function getJson<T>(url: string, signal?: AbortSignal): Promise<T> {
    const res = await fetch(url, {
        credentials: 'same-origin',
        signal,
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
    }

    return (await res.json()) as T;
}

function newKey(): string {
    return window.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random()}`;
}

function toNumber(value: string): number {
    const n = Number(value.replace(',', '.'));

    return Number.isFinite(n) ? n : 0;
}

export default function CotizacionForm({ cotizacion, defaults, sedes, clinica }: Props) {
    const editing = cotizacion !== null;
    const igvPct = Number(cotizacion?.igv_porcentaje ?? clinica.igv_porcentaje) || 0;
    const incluyeIgv = cotizacion?.precio_incluye_igv ?? clinica.precio_incluye_igv;
    const moneda = clinica.moneda;

    const [sedeId, setSedeId] = useState<string | null>(cotizacion?.sede_id ?? defaults?.sede_id ?? null);
    const [fecha, setFecha] = useState(cotizacion?.fecha ?? defaults?.fecha ?? '');
    const [vigencia, setVigencia] = useState(cotizacion?.vigencia_hasta ?? defaults?.vigencia_hasta ?? '');
    const [propietarioId, setPropietarioId] = useState<string | null>(cotizacion?.propietario_id ?? null);
    const [pacienteId, setPacienteId] = useState<string | null>(cotizacion?.paciente_id ?? null);
    const [pacientes, setPacientes] = useState<{ id: string; nombre: string }[]>([]);
    const [clienteNombre, setClienteNombre] = useState(cotizacion?.cliente_nombre ?? '');
    const [clienteDoc, setClienteDoc] = useState(cotizacion?.cliente_documento ?? '');
    const [clienteTel, setClienteTel] = useState(cotizacion?.cliente_telefono ?? '');
    const [clienteEmail, setClienteEmail] = useState(cotizacion?.cliente_email ?? '');
    const [notas, setNotas] = useState(cotizacion?.notas ?? '');
    const [lineas, setLineas] = useState<CotizacionLineaInput[]>(
        () => (cotizacion?.lineas ?? []).map((l) => ({ ...l, key: newKey() })),
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const [propOptions, setPropOptions] = useState<PropietarioOpcion[]>(
        cotizacion?.propietario ? [cotizacion.propietario] : [],
    );
    const [propSearch, setPropSearch] = useState('');
    const [propLoading, setPropLoading] = useState(false);

    const [tab, setTab] = useState<TipoLinea>('producto');
    const [q, setQ] = useState('');
    const [productoHits, setProductoHits] = useState<ProductoHit[]>([]);
    const [servicioHits, setServicioHits] = useState<ServicioHit[]>([]);
    const [buscando, setBuscando] = useState(false);
    const searchRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        const term = propSearch.trim();
        if (term.length < 2) {
            return;
        }
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setPropLoading(true);
            getJson<{ data: PropietarioOpcion[] }>(
                `/caja/cotizaciones/buscar-propietarios?q=${encodeURIComponent(term)}`,
                controller.signal,
            )
                .then((res) => {
                    setPropOptions((prev) => {
                        const selected = prev.find((o) => o.id === propietarioId);
                        const data = res.data ?? [];

                        return selected && !data.some((o) => o.id === selected.id) ? [selected, ...data] : data;
                    });
                })
                .catch(() => undefined)
                .finally(() => {
                    if (!controller.signal.aborted) {
                        setPropLoading(false);
                    }
                });
        }, 280);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [propSearch, propietarioId]);

    const propietarioInicial = cotizacion?.propietario_id ?? null;
    useEffect(() => {
        if (!propietarioInicial) {
            return;
        }
        const controller = new AbortController();
        getJson<{ data: PropietarioDetalle }>(`/caja/cotizaciones/propietarios/${propietarioInicial}`, controller.signal)
            .then((res) => setPacientes(res.data.pacientes ?? []))
            .catch(() => undefined);

        return () => controller.abort();
    }, [propietarioInicial]);

    useEffect(() => {
        const term = q.trim();
        if (tab === 'producto' && term.length < 1) {
            setProductoHits([]);

            return;
        }
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setBuscando(true);
            const url =
                tab === 'producto'
                    ? `/caja/cotizaciones/buscar-productos?q=${encodeURIComponent(term)}`
                    : `/caja/cotizaciones/buscar-servicios?q=${encodeURIComponent(term)}`;
            getJson<{ data: ProductoHit[] | ServicioHit[] }>(url, controller.signal)
                .then((res) => {
                    if (tab === 'producto') {
                        setProductoHits((res.data as ProductoHit[]) ?? []);
                    } else {
                        setServicioHits((res.data as ServicioHit[]) ?? []);
                    }
                })
                .catch(() => undefined)
                .finally(() => {
                    if (!controller.signal.aborted) {
                        setBuscando(false);
                    }
                });
        }, 250);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [q, tab]);

    const elegirPropietario = (id: string | null) => {
        setPropietarioId(id);
        setPacienteId(null);
        setPacientes([]);
        if (!id) {
            return;
        }
        getJson<{ data: PropietarioDetalle }>(`/caja/cotizaciones/propietarios/${id}`)
            .then((res) => {
                const d = res.data;
                setClienteNombre(d.nombre ?? '');
                setClienteDoc(d.documento ?? '');
                setClienteTel(d.telefono ?? '');
                setClienteEmail(d.email ?? '');
                setPacientes(d.pacientes ?? []);
                if ((d.pacientes ?? []).length === 1) {
                    setPacienteId(d.pacientes[0].id);
                }
            })
            .catch(() => undefined);
    };

    const agregarProducto = (p: ProductoHit) => {
        setLineas((prev) => {
            const existente = prev.find((l) => l.producto_id === p.id);
            if (existente) {
                return prev.map((l) => (l.key === existente.key ? { ...l, cantidad: l.cantidad + 1 } : l));
            }

            return [
                ...prev,
                {
                    key: newKey(),
                    tipo_linea: 'producto',
                    producto_id: p.id,
                    descripcion: p.nombre,
                    unidad: p.unidad,
                    cantidad: 1,
                    precio_unitario: p.precio_venta ? Number(p.precio_venta).toFixed(2) : '0.00',
                    descuento_pct: 0,
                },
            ];
        });
        setQ('');
        searchRef.current?.focus();
    };

    const agregarServicio = (s: ServicioHit) => {
        setLineas((prev) => [
            ...prev,
            {
                key: newKey(),
                tipo_linea: 'servicio',
                producto_id: null,
                descripcion: s.nombre,
                unidad: null,
                cantidad: 1,
                precio_unitario: Number(s.precio_lista || 0).toFixed(2),
                descuento_pct: 0,
            },
        ]);
        setQ('');
        searchRef.current?.focus();
    };

    const agregarLibre = () => {
        setLineas((prev) => [
            ...prev,
            {
                key: newKey(),
                tipo_linea: 'servicio',
                producto_id: null,
                descripcion: q.trim(),
                unidad: null,
                cantidad: 1,
                precio_unitario: '0.00',
                descuento_pct: 0,
            },
        ]);
        setQ('');
    };

    const actualizar = (key: string, patch: Partial<CotizacionLineaInput>) => {
        setLineas((prev) => prev.map((l) => (l.key === key ? { ...l, ...patch } : l)));
    };

    const quitar = (key: string) => setLineas((prev) => prev.filter((l) => l.key !== key));

    const totales = useMemo(() => calcularTotales(lineas, igvPct, incluyeIgv), [lineas, igvPct, incluyeIgv]);

    const propComboOptions: ComboboxOption[] = useMemo(
        () => propOptions.map((o) => ({ value: o.id, label: o.doc ? `${o.label} · ${o.doc}` : o.label })),
        [propOptions],
    );

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        const payload = {
            sede_id: sedeId,
            propietario_id: propietarioId,
            paciente_id: pacienteId,
            cliente_nombre: clienteNombre,
            cliente_documento: clienteDoc || null,
            cliente_telefono: clienteTel || null,
            cliente_email: clienteEmail || null,
            fecha,
            vigencia_hasta: vigencia,
            notas: notas || null,
            lineas: lineas.map((l) => ({
                tipo_linea: l.tipo_linea,
                producto_id: l.producto_id,
                descripcion: l.descripcion,
                unidad: l.unidad,
                cantidad: l.cantidad,
                precio_unitario: toNumber(l.precio_unitario),
                descuento_pct: l.descuento_pct,
            })),
        };

        const options = {
            preserveScroll: true,
            onError: (errs: Record<string, string>) => setErrors(errs),
            onFinish: () => setProcessing(false),
        };

        if (editing && cotizacion) {
            router.put(`/caja/cotizaciones/${cotizacion.id}`, payload, options);
        } else {
            router.post('/caja/cotizaciones', payload, options);
        }
    };

    const lineError = (i: number): string | null => {
        const prefix = `lineas.${i}.`;
        const key = Object.keys(errors).find((k) => k.startsWith(prefix));

        return key ? errors[key] : null;
    };

    const title = editing ? `Editar cotización ${cotizacion?.numero}` : 'Nueva cotización';

    return (
        <>
            <Head title={title} />

            <form onSubmit={onSubmit} className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <Button asChild variant="ghost" size="icon" className="size-9">
                            <Link href="/caja/cotizaciones" aria-label="Volver a cotizaciones">
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-xl font-semibold tracking-tight">{title}</h1>
                            <p className="text-sm text-muted-foreground">
                                No mueve stock ni registra cobro. Después puedes enviarla en PDF por WhatsApp o correo.
                            </p>
                        </div>
                    </div>
                </div>

                <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <div className="flex min-w-0 flex-col gap-5">
                        <section className="rounded-xl border bg-card p-4 sm:p-5">
                            <h2 className="mb-4 text-sm font-semibold">Cliente</h2>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="flex flex-col gap-1.5 sm:col-span-2">
                                    <Label>Cliente registrado (opcional)</Label>
                                    <Combobox
                                        options={propComboOptions}
                                        value={propietarioId}
                                        onChange={elegirPropietario}
                                        onSearchChange={setPropSearch}
                                        loading={propLoading}
                                        placeholder="Buscar por nombre, DNI o teléfono"
                                        searchPlaceholder="Escribe al menos 2 letras"
                                        emptyMessage={propSearch.trim().length < 2 ? 'Escribe al menos 2 letras' : 'Sin coincidencias'}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        También puedes cotizar a alguien que aún no es cliente: llena solo los datos de abajo.
                                    </p>
                                </div>
                                <div className="flex flex-col gap-1.5 sm:col-span-2">
                                    <Label htmlFor="cot-nombre">Nombre o razón social *</Label>
                                    <Input
                                        id="cot-nombre"
                                        value={clienteNombre}
                                        maxLength={200}
                                        onChange={(e) => setClienteNombre(e.target.value)}
                                        aria-invalid={Boolean(errors.cliente_nombre)}
                                        required
                                    />
                                    {errors.cliente_nombre ? <p className="text-xs text-destructive">{errors.cliente_nombre}</p> : null}
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="cot-doc">DNI / RUC</Label>
                                    <Input id="cot-doc" value={clienteDoc} maxLength={30} onChange={(e) => setClienteDoc(e.target.value)} />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="cot-tel">WhatsApp / teléfono</Label>
                                    <Input
                                        id="cot-tel"
                                        type="tel"
                                        inputMode="tel"
                                        value={clienteTel}
                                        maxLength={30}
                                        onChange={(e) => setClienteTel(e.target.value)}
                                    />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="cot-email">Correo</Label>
                                    <Input
                                        id="cot-email"
                                        type="email"
                                        value={clienteEmail}
                                        maxLength={150}
                                        onChange={(e) => setClienteEmail(e.target.value)}
                                        aria-invalid={Boolean(errors.cliente_email)}
                                    />
                                    {errors.cliente_email ? <p className="text-xs text-destructive">{errors.cliente_email}</p> : null}
                                </div>
                                {pacientes.length > 0 ? (
                                    <div className="flex flex-col gap-1.5">
                                        <Label>Mascota</Label>
                                        <Select value={pacienteId ?? NONE} onValueChange={(v) => setPacienteId(v === NONE ? null : v)}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={NONE}>Sin mascota</SelectItem>
                                                {pacientes.map((p) => (
                                                    <SelectItem key={p.id} value={p.id}>
                                                        {p.nombre}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                ) : null}
                            </div>
                        </section>

                        <section className="rounded-xl border bg-card p-4 sm:p-5">
                            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                                <h2 className="text-sm font-semibold">Productos y servicios</h2>
                                <div className="inline-flex rounded-lg border bg-muted/40 p-0.5">
                                    {(
                                        [
                                            { value: 'producto', label: 'Productos', icon: Package },
                                            { value: 'servicio', label: 'Servicios', icon: Stethoscope },
                                        ] as const
                                    ).map((opt) => (
                                        <button
                                            key={opt.value}
                                            type="button"
                                            onClick={() => {
                                                setTab(opt.value);
                                                setQ('');
                                            }}
                                            className={cn(
                                                'inline-flex cursor-pointer items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition',
                                                tab === opt.value
                                                    ? 'bg-background text-foreground shadow-sm'
                                                    : 'text-muted-foreground hover:text-foreground',
                                            )}
                                        >
                                            <opt.icon className="size-3.5" />
                                            {opt.label}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    ref={searchRef}
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    placeholder={tab === 'producto' ? 'Buscar producto por nombre, SKU o código' : 'Buscar servicio o tarifa'}
                                    className="pl-9"
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            e.preventDefault();
                                        }
                                    }}
                                />
                                {buscando ? (
                                    <Loader2 className="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />
                                ) : null}
                            </div>

                            {(tab === 'producto' ? q.trim() !== '' : true) ? (
                                <div className="mt-2 max-h-64 overflow-y-auto rounded-lg border">
                                    {tab === 'producto' && productoHits.length === 0 && !buscando ? (
                                        <p className="px-3 py-2.5 text-sm text-muted-foreground">Ningún producto coincide.</p>
                                    ) : null}
                                    {tab === 'servicio' && servicioHits.length === 0 && !buscando ? (
                                        <p className="px-3 py-2.5 text-sm text-muted-foreground">Ningún servicio coincide.</p>
                                    ) : null}
                                    {tab === 'producto'
                                        ? productoHits.map((p) => (
                                              <button
                                                  key={p.id}
                                                  type="button"
                                                  onClick={() => agregarProducto(p)}
                                                  className="flex w-full cursor-pointer items-center justify-between gap-3 border-b px-3 py-2 text-left text-sm last:border-b-0 hover:bg-muted/60"
                                              >
                                                  <span className="min-w-0">
                                                      <span className="block truncate font-medium">{p.nombre}</span>
                                                      {p.sku ? <span className="text-xs text-muted-foreground">{p.sku}</span> : null}
                                                  </span>
                                                  <span className="shrink-0 tabular-nums text-sm">
                                                      {formatMoney(p.precio_venta ?? 0, moneda)}
                                                  </span>
                                              </button>
                                          ))
                                        : servicioHits.map((s, i) => (
                                              <button
                                                  key={`${s.nombre}-${i}`}
                                                  type="button"
                                                  onClick={() => agregarServicio(s)}
                                                  className="flex w-full cursor-pointer items-center justify-between gap-3 border-b px-3 py-2 text-left text-sm last:border-b-0 hover:bg-muted/60"
                                              >
                                                  <span className="min-w-0">
                                                      <span className="block truncate font-medium">{s.nombre}</span>
                                                      {s.categoria ? <span className="text-xs text-muted-foreground">{s.categoria}</span> : null}
                                                  </span>
                                                  <span className="shrink-0 tabular-nums text-sm">{formatMoney(s.precio_lista, moneda)}</span>
                                              </button>
                                          ))}
                                </div>
                            ) : null}

                            <div className="mt-2">
                                <Button type="button" variant="ghost" size="sm" className="gap-1.5" onClick={agregarLibre}>
                                    <Plus className="size-4" />
                                    Agregar ítem libre{q.trim() ? `: “${q.trim()}”` : ''}
                                </Button>
                            </div>

                            <div className="mt-4 overflow-x-auto">
                                {lineas.length === 0 ? (
                                    <p
                                        className={cn(
                                            'rounded-lg border border-dashed px-4 py-8 text-center text-sm',
                                            errors.lineas ? 'border-destructive text-destructive' : 'text-muted-foreground',
                                        )}
                                    >
                                        {errors.lineas ?? 'Busca y agrega productos o servicios a la cotización.'}
                                    </p>
                                ) : (
                                    <table className="w-full min-w-[640px] text-sm">
                                        <thead>
                                            <tr className="border-b text-left text-xs text-muted-foreground uppercase">
                                                <th className="py-2 pr-2 font-medium">Descripción</th>
                                                <th className="w-24 px-2 py-2 font-medium">Cant.</th>
                                                <th className="w-28 px-2 py-2 font-medium">Precio</th>
                                                <th className="w-20 px-2 py-2 font-medium">Dcto %</th>
                                                <th className="w-28 px-2 py-2 text-right font-medium">Importe</th>
                                                <th className="w-10" />
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {lineas.map((l, i) => (
                                                <tr key={l.key} className="border-b align-top last:border-b-0">
                                                    <td className="py-2 pr-2">
                                                        <Input
                                                            value={l.descripcion}
                                                            maxLength={300}
                                                            onChange={(e) => actualizar(l.key, { descripcion: e.target.value })}
                                                            aria-invalid={Boolean(lineError(i))}
                                                        />
                                                        <span className="mt-1 inline-block text-[0.7rem] text-muted-foreground uppercase">
                                                            {l.tipo_linea === 'producto' ? 'Producto' : 'Servicio'}
                                                            {l.unidad ? ` · ${l.unidad}` : ''}
                                                        </span>
                                                        {lineError(i) ? <p className="text-xs text-destructive">{lineError(i)}</p> : null}
                                                    </td>
                                                    <td className="px-2 py-2">
                                                        <Input
                                                            type="number"
                                                            min="0.001"
                                                            step="any"
                                                            value={l.cantidad}
                                                            onChange={(e) => actualizar(l.key, { cantidad: Math.max(0, toNumber(e.target.value)) })}
                                                            className="tabular-nums"
                                                        />
                                                    </td>
                                                    <td className="px-2 py-2">
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            value={l.precio_unitario}
                                                            onChange={(e) => actualizar(l.key, { precio_unitario: e.target.value })}
                                                            className="tabular-nums"
                                                        />
                                                    </td>
                                                    <td className="px-2 py-2">
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            max="100"
                                                            step="0.01"
                                                            value={l.descuento_pct}
                                                            onChange={(e) =>
                                                                actualizar(l.key, {
                                                                    descuento_pct: Math.min(100, Math.max(0, toNumber(e.target.value))),
                                                                })
                                                            }
                                                            className="tabular-nums"
                                                        />
                                                    </td>
                                                    <td className="px-2 py-2 pt-4 text-right font-medium tabular-nums">
                                                        {formatMoney(
                                                            lineaTotal(toNumber(l.precio_unitario), l.cantidad, l.descuento_pct),
                                                            moneda,
                                                        )}
                                                    </td>
                                                    <td className="py-2 pl-1">
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-9 text-muted-foreground hover:text-destructive"
                                                            onClick={() => quitar(l.key)}
                                                            aria-label="Quitar ítem"
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </div>
                        </section>

                        <section className="rounded-xl border bg-card p-4 sm:p-5">
                            <Label htmlFor="cot-notas" className="text-sm font-semibold">
                                Observaciones y condiciones
                            </Label>
                            <Textarea
                                id="cot-notas"
                                className="mt-2"
                                rows={3}
                                maxLength={2000}
                                value={notas}
                                onChange={(e) => setNotas(e.target.value)}
                                placeholder="Ej.: Incluye anestesia y control post operatorio. Precios sujetos a evaluación clínica."
                            />
                        </section>
                    </div>

                    <aside className="flex flex-col gap-5 lg:sticky lg:top-4 lg:self-start">
                        <section className="rounded-xl border bg-card p-4 sm:p-5">
                            <h2 className="mb-4 text-sm font-semibold">Datos de la cotización</h2>
                            <div className="flex flex-col gap-4">
                                <div className="flex flex-col gap-1.5">
                                    <Label>Sede</Label>
                                    <Select value={sedeId ?? NONE} onValueChange={(v) => setSedeId(v === NONE ? null : v)}>
                                        <SelectTrigger aria-invalid={Boolean(errors.sede_id)}>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>Datos generales de la clínica</SelectItem>
                                            {sedes.map((s) => (
                                                <SelectItem key={s.id} value={s.id}>
                                                    {s.nombre}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-xs text-muted-foreground">
                                        El PDF usa el logo de la clínica y la dirección y teléfono de esta sede.
                                    </p>
                                    {errors.sede_id ? <p className="text-xs text-destructive">{errors.sede_id}</p> : null}
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="cot-fecha">Fecha</Label>
                                        <Input id="cot-fecha" type="date" value={fecha} onChange={(e) => setFecha(e.target.value)} required />
                                    </div>
                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="cot-vigencia">Válida hasta</Label>
                                        <Input
                                            id="cot-vigencia"
                                            type="date"
                                            value={vigencia}
                                            min={fecha}
                                            onChange={(e) => setVigencia(e.target.value)}
                                            aria-invalid={Boolean(errors.vigencia_hasta)}
                                            required
                                        />
                                    </div>
                                </div>
                                {errors.vigencia_hasta ? <p className="text-xs text-destructive">{errors.vigencia_hasta}</p> : null}
                            </div>
                        </section>

                        <section className="rounded-xl border bg-card p-4 sm:p-5">
                            <dl className="flex flex-col gap-2 text-sm">
                                {totales.descuento > 0 ? (
                                    <div className="flex justify-between text-muted-foreground">
                                        <dt>Descuentos</dt>
                                        <dd className="tabular-nums">- {formatMoney(totales.descuento, moneda)}</dd>
                                    </div>
                                ) : null}
                                {igvPct > 0 ? (
                                    <>
                                        <div className="flex justify-between text-muted-foreground">
                                            <dt>Op. gravada</dt>
                                            <dd className="tabular-nums">{formatMoney(totales.subtotal, moneda)}</dd>
                                        </div>
                                        <div className="flex justify-between text-muted-foreground">
                                            <dt>IGV ({igvPct}%)</dt>
                                            <dd className="tabular-nums">{formatMoney(totales.igv, moneda)}</dd>
                                        </div>
                                    </>
                                ) : null}
                                <div className="mt-1 flex justify-between border-t pt-3 text-base font-semibold">
                                    <dt>Total</dt>
                                    <dd className="tabular-nums">{formatMoney(totales.total, moneda)}</dd>
                                </div>
                            </dl>
                            <Button type="submit" className="mt-5 w-full gap-2" disabled={processing || lineas.length === 0}>
                                {processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                                {editing ? 'Guardar cambios' : 'Crear cotización'}
                            </Button>
                        </section>
                    </aside>
                </div>
            </form>
        </>
    );
}
