import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CreditCard,
    Loader2,
    Minus,
    PackageSearch,
    Plus,
    Receipt,
    Search,
    ShoppingBag,
    ShoppingCart,
    Stethoscope,
    Trash2,
    UserCircle,
    UserPlus,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/data-page';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
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
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import AppLayout from '@/layouts/app-layout';
import { PropietarioFormModal } from '@/pages/clinica/propietarios/components/propietario-form-modal';
import { toastManager } from '@/lib/toast';
import { cn } from '@/lib/utils';
import caja from '@/routes/caja';
import { PosPanel } from './components/pos-panel';
import type { ProductoBusqueda, ServicioTarifaBusqueda, VentasCreateProps } from './types';
import { calcTotalesVenta, lineTotalLinea } from './venta-pricing';

type CartLine = {
    key: string;
    producto_id: string | null;
    consulta_cargo_linea_id?: string | null;
    tipo_linea?: string;
    nombre: string;
    unidad: string;
    precio_venta: string | null;
    cantidad: number;
    stock_disponible: number;
    omitir_stock: boolean;
};

function parseStock(stockSede: string | undefined): number {
    const n = Number(stockSede ?? 0);

    if (Number.isNaN(n) || n < 0) {
        return 0;
    }

    return Math.round(n * 1000) / 1000;
}

function readXsrfToken(): string {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return m ? decodeURIComponent(m[1]) : '';
}

async function jsonGet(url: string): Promise<unknown> {
    const res = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': readXsrfToken(),
        },
    });

    if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
    }

    return res.json();
}

export default function Create({
    puede_vender,
    mi_sesion,
    clinica,
    propietarios_opciones: propietariosOpciones,
    departamentos,
    desde_cargo: desdeCargo = null,
}: VentasCreateProps) {
    const { t, i18n } = useTranslation(['caja', 'common']);
    const [cart, setCart] = useState<CartLine[]>([]);
    const desdeCargoInicializado = useRef(false);
    const [qProducto, setQProducto] = useState('');
    const [hits, setHits] = useState<ProductoBusqueda[]>([]);
    const [buscando, setBuscando] = useState(false);
    const [qServicio, setQServicio] = useState('');
    const [hitsServicio, setHitsServicio] = useState<ServicioTarifaBusqueda[]>([]);
    const [buscandoServicio, setBuscandoServicio] = useState(false);
    const [servicioConcepto, setServicioConcepto] = useState('');
    const [servicioPrecio, setServicioPrecio] = useState('');
    const [pacientes, setPacientes] = useState<{ id: string; nombre: string }[]>([]);
    const [cargandoPacientes, setCargandoPacientes] = useState(false);
    const [propietariosLocales, setPropietariosLocales] = useState(propietariosOpciones);
    const [nuevoClienteOpen, setNuevoClienteOpen] = useState(false);

    const form = useForm({
        caja_sesion_id: mi_sesion?.id ?? '',
        propietario_id: '',
        tipo_comprobante_sunat: 2 as 1 | 2,
        paciente_id: null as string | null,
        consulta_id: null as string | null,
        consulta_cargo_id: null as string | null,
        grooming_turno_id: null as string | null,
        hotel_estancia_id: null as string | null,
        lineas: [] as { producto_id: string; cantidad: number }[],
        metodo_pago: 'efectivo',
        monto_recibido: '',
        notas: '',
    });

    useEffect(() => {
        setPropietariosLocales(propietariosOpciones);
    }, [propietariosOpciones]);

    const fechaConsultaCargo = useMemo(() => {
        if (!desdeCargo?.consulta_atendido_at) {
            return '—';
        }

        return new Intl.DateTimeFormat(i18n.language, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(desdeCargo.consulta_atendido_at));
    }, [desdeCargo?.consulta_atendido_at, i18n.language]);

    useEffect(() => {
        if (!desdeCargo || desdeCargoInicializado.current) {
            return;
        }

        desdeCargoInicializado.current = true;

        const lineasCart: CartLine[] = desdeCargo.lineas_iniciales.map((ln, idx) => {
            const tieneProducto = Boolean(ln.producto_id);
            const stock = parseStock(ln.stock_sede);
            const groomId = desdeCargo.grooming_turno_id;
            const hotelId = desdeCargo.hotel_estancia_id;
            const lineKey = tieneProducto
                ? `p:${ln.producto_id}`
                : ln.consulta_cargo_linea_id
                  ? `ccl:${ln.consulta_cargo_linea_id}`
                  : groomId
                    ? `groom-svc:${groomId}:${idx}`
                    : hotelId
                      ? `hotel-svc:${hotelId}:${idx}`
                      : `svc:${idx}`;

            return {
                key: lineKey,
                producto_id: ln.producto_id,
                consulta_cargo_linea_id: ln.consulta_cargo_linea_id,
                tipo_linea: ln.tipo_linea,
                nombre: ln.concepto,
                unidad: tieneProducto ? 'und' : t('caja:ventas.desde_cargo.linea_servicio'),
                precio_venta: ln.precio_lista,
                cantidad: Number(ln.cantidad) || 1,
                stock_disponible: tieneProducto ? stock : 999999,
                omitir_stock: !tieneProducto,
            };
        });

        setCart(lineasCart);
        form.setData((prev) => ({
            ...prev,
            propietario_id: desdeCargo.propietario_id,
            paciente_id: desdeCargo.paciente_id,
            consulta_id: desdeCargo.consulta_id,
            consulta_cargo_id: desdeCargo.consulta_cargo_id,
            grooming_turno_id: desdeCargo.grooming_turno_id ?? null,
            hotel_estancia_id: desdeCargo.hotel_estancia_id ?? null,
        }));

        if (desdeCargo.paciente_id && desdeCargo.paciente_nombre) {
            setPacientes([{ id: desdeCargo.paciente_id, nombre: desdeCargo.paciente_nombre }]);
        } else if (desdeCargo.propietario_id) {
            setCargandoPacientes(true);
            const url = caja.ventas.pacientesPorPropietario.url({
                query: { propietario_id: desdeCargo.propietario_id },
            });
            jsonGet(url)
                .then((raw) => {
                    const data = (raw as { data?: { id: string; nombre: string }[] }).data ?? [];
                    setPacientes(data);
                })
                .catch(() => setPacientes([]))
                .finally(() => setCargandoPacientes(false));
        }
    }, [desdeCargo, form, t]);

    const igvPct = Number(clinica.igv_porcentaje);
    const precioIncluyeIgv = clinica.precio_incluye_igv;

    const totales = useMemo(
        () => calcTotalesVenta(cart, igvPct, precioIncluyeIgv),
        [cart, igvPct, precioIncluyeIgv],
    );

    useEffect(() => {
        const tmr = window.setTimeout(() => {
            const q = qProducto.trim();

            if (q.length < 2) {
                setHits([]);

                return;
            }

            setBuscando(true);
            const url = caja.ventas.buscarProductos.url({ query: { q } });
            jsonGet(url)
                .then((raw) => {
                    const data = (raw as { data?: ProductoBusqueda[] }).data ?? [];
                    setHits(data);
                })
                .catch(() => setHits([]))
                .finally(() => setBuscando(false));
        }, 320);

        return () => window.clearTimeout(tmr);
    }, [qProducto]);

    useEffect(() => {
        const tmr = window.setTimeout(() => {
            const q = qServicio.trim();
            setBuscandoServicio(true);
            const url = caja.ventas.buscarServicios.url({
                query: q.length > 0 ? { q } : {},
            });
            jsonGet(url)
                .then((raw) => {
                    const data = (raw as { data?: ServicioTarifaBusqueda[] }).data ?? [];
                    setHitsServicio(data);
                })
                .catch(() => setHitsServicio([]))
                .finally(() => setBuscandoServicio(false));
        }, 280);

        return () => window.clearTimeout(tmr);
    }, [qServicio]);

    const onPropietarioChange = useCallback(
        (propietarioId: string) => {
            form.setData('propietario_id', propietarioId);
            form.setData('paciente_id', null);
            setPacientes([]);

            if (!propietarioId) {
                return;
            }

            setCargandoPacientes(true);
            const url = caja.ventas.pacientesPorPropietario.url({
                query: { propietario_id: propietarioId },
            });
            jsonGet(url)
                .then((raw) => {
                    const data = (raw as { data?: { id: string; nombre: string }[] }).data ?? [];
                    setPacientes(data);
                })
                .catch(() => setPacientes([]))
                .finally(() => setCargandoPacientes(false));
        },
        [form],
    );

    const addProduct = useCallback(
        (p: ProductoBusqueda) => {
            const stock = parseStock(p.stock_sede);

            if (stock <= 0) {
                toastManager.error({
                    title: t('caja:ventas.create.sin_stock_title'),
                    description: t('caja:ventas.create.sin_stock_body', { producto: p.nombre }),
                });

                return;
            }

            setCart((prev) => {
                const idx = prev.findIndex((x) => x.producto_id === p.id);

                if (idx >= 0) {
                    const line = prev[idx]!;

                    if (line.cantidad + 1 > line.stock_disponible + 0.0001) {
                        toastManager.warning({
                            title: t('caja:ventas.create.stock_insuficiente_title'),
                            description: t('caja:ventas.create.stock_insuficiente_body', {
                                producto: p.nombre,
                                stock: line.stock_disponible,
                            }),
                        });

                        return prev;
                    }

                    const next = [...prev];
                    next[idx] = { ...line, cantidad: line.cantidad + 1 };

                    return next;
                }

                return [
                    ...prev,
                    {
                        key: `p:${p.id}`,
                        producto_id: p.id,
                        nombre: p.nombre,
                        unidad: p.unidad,
                        precio_venta: p.precio_venta,
                        cantidad: 1,
                        stock_disponible: stock,
                        omitir_stock: false,
                    },
                ];
            });
            setQProducto('');
            setHits([]);
        },
        [t],
    );

    const addServicioLine = useCallback(
        (nombre: string, precioLista: string) => {
            const concepto = nombre.trim();
            if (concepto === '') {
                toastManager.error({
                    title: t('caja:ventas.create.servicio_sin_concepto'),
                });

                return;
            }

            const precioNorm = precioLista.trim().replace(',', '.');
            const precioNum = precioNorm === '' ? Number.NaN : Number(precioNorm);
            if (Number.isNaN(precioNum) || precioNum < 0) {
                toastManager.error({
                    title: t('caja:ventas.create.servicio_sin_precio'),
                });

                return;
            }

            const precioRedondeado = Math.round(precioNum * 100) / 100;

            setCart((prev) => [
                ...prev,
                {
                    key: `s:${crypto.randomUUID()}`,
                    producto_id: null,
                    nombre: concepto,
                    unidad: t('caja:ventas.desde_cargo.linea_servicio'),
                    precio_venta: String(precioRedondeado),
                    cantidad: 1,
                    stock_disponible: 0,
                    omitir_stock: true,
                    tipo_linea: 'servicio',
                },
            ]);
            setServicioConcepto('');
            setServicioPrecio('');
            setQServicio('');
            setHitsServicio([]);
        },
        [t],
    );

    const addServicioFromTarifa = useCallback(
        (s: ServicioTarifaBusqueda) => {
            addServicioLine(s.nombre, s.precio_lista);
        },
        [addServicioLine],
    );

    const setCantidad = useCallback((lineKey: string, cantidad: number) => {
        setCart((prev) =>
            prev.map((l) => {
                if (l.key !== lineKey) {
                    return l;
                }

                const max = l.omitir_stock ? 999999 : l.stock_disponible;
                const c = Math.min(max, Math.max(0.001, Math.round(cantidad * 1000) / 1000));

                return { ...l, cantidad: c };
            }),
        );
    }, []);

    /** Líneas sin producto de inventario (p. ej. grooming, conceptos): el cajero define el precio lista. */
    const setPrecioListaLinea = useCallback((lineKey: string, value: string) => {
        const t = value.trim().replace(',', '.');
        const n = t === '' || t === '.' ? 0 : Number(t);
        if (Number.isNaN(n) || n < 0) {
            return;
        }
        const clamped = Math.min(999999.99, n);
        const rounded = Math.round(clamped * 100) / 100;
        setCart((prev) =>
            prev.map((l) => (l.key === lineKey ? { ...l, precio_venta: String(rounded) } : l)),
        );
    }, []);

    const removeLine = useCallback((lineKey: string) => {
        setCart((prev) => prev.filter((l) => l.key !== lineKey));
    }, []);

    const formatMoney = useCallback(
        (n: number) =>
            new Intl.NumberFormat(i18n.language, {
                style: 'currency',
                currency: clinica.moneda === 'USD' ? 'USD' : 'PEN',
            }).format(n),
        [clinica.moneda, i18n.language],
    );

    const submit = useCallback(() => {
        if (!puede_vender || !mi_sesion || cart.length === 0) {
            return;
        }

        form.transform((d) => ({
            ...d,
            caja_sesion_id: mi_sesion.id,
            grooming_turno_id: d.grooming_turno_id || null,
            hotel_estancia_id: d.hotel_estancia_id || null,
            lineas: cart.map((l) => ({
                producto_id: l.producto_id,
                concepto: l.producto_id ? null : l.nombre,
                precio_lista:
                    l.precio_venta === '' || l.precio_venta === null ? '0' : String(l.precio_venta),
                tipo_linea: l.tipo_linea ?? (l.producto_id ? 'producto' : 'servicio'),
                consulta_cargo_linea_id: l.consulta_cargo_linea_id ?? null,
                cantidad: l.cantidad,
            })),
            monto_recibido: d.metodo_pago === 'efectivo' ? d.monto_recibido : null,
        }));

        form.post(caja.ventas.store.url(), {
            preserveScroll: true,
            onError: (errors) => {
                const messages = Object.values(errors).filter(
                    (m): m is string => typeof m === 'string' && m.length > 0,
                );
                toastManager.error({
                    title: t('caja:ventas.create.error_guardar_title'),
                    description:
                        messages.length > 0
                            ? messages.join(' · ')
                            : t('caja:ventas.create.error_guardar_generico'),
                });
            },
        });
    }, [cart, form, mi_sesion, puede_vender, t]);

    const cartSinStock = useMemo(
        () =>
            cart.some(
                (l) =>
                    !l.omitir_stock &&
                    (l.cantidad > l.stock_disponible + 0.0001 || l.stock_disponible <= 0),
            ),
        [cart],
    );

    const puedeConfirmar =
        puede_vender &&
        cart.length > 0 &&
        !cartSinStock &&
        form.data.propietario_id &&
        (form.data.metodo_pago !== 'efectivo' ||
            Number(String(form.data.monto_recibido).replace(',', '.')) >= totales.total - 0.0001);

    const headerStats = useMemo(() => {
        if (!puede_vender || !mi_sesion) {
            return [];
        }

        return [
            {
                icon: ShoppingCart,
                label: t('caja:ventas.create.stat_sede'),
                value: mi_sesion.sede_nombre ?? '—',
                variant: 'default' as const,
            },
            {
                icon: Receipt,
                label: t('caja:ventas.create.stat_moneda'),
                value: mi_sesion.moneda ?? clinica.moneda,
                variant: 'muted' as const,
            },
        ];
    }, [clinica.moneda, mi_sesion, puede_vender, t]);

    const esEfectivo = form.data.metodo_pago === 'efectivo';

    const erroresFormulario = useMemo(
        () =>
            Object.entries(form.errors).flatMap(([key, message]) =>
                typeof message === 'string' && message.length > 0 ? [`${key}: ${message}`] : [],
            ),
        [form.errors],
    );

    return (
        <>
            <Head
                title={
                    desdeCargo
                        ? t('caja:ventas.create.desde_cargo_titulo')
                        : t('caja:ventas.create.title')
                }
            />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={
                        desdeCargo
                            ? t('caja:ventas.create.desde_cargo_titulo')
                            : t('caja:ventas.create.title')
                    }
                    description={t('caja:ventas.create.description')}
                    stats={headerStats}
                    action={
                        <Button variant="outline" size="sm" asChild className="gap-1.5">
                            <Link href={caja.ventas.index.url()}>
                                <ArrowLeft className="size-4" aria-hidden />
                                {t('caja:ventas.create.volver')}
                            </Link>
                        </Button>
                    }
                />

                {desdeCargo ? (
                    <Alert className="border-primary/20 bg-primary/5">
                        <Stethoscope className="size-4 text-primary" aria-hidden />
                        <AlertTitle>{t('caja:ventas.desde_cargo.banner_title')}</AlertTitle>
                        <AlertDescription>
                            {t('caja:ventas.desde_cargo.banner_body', {
                                paciente: desdeCargo.paciente_nombre ?? '—',
                                fecha: fechaConsultaCargo,
                                total: t('caja:ventas.desde_cargo.banner_total', {
                                    moneda: clinica.moneda,
                                    total: desdeCargo.cargo_total,
                                }),
                            })}
                        </AlertDescription>
                    </Alert>
                ) : null}

                {!puede_vender ? (
                    <Alert variant="destructive">
                        <AlertTitle>{t('caja:ventas.create.sin_sesion_title')}</AlertTitle>
                        <AlertDescription className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <span>{t('caja:ventas.create.sin_sesion_body')}</span>
                            <Button asChild variant="secondary" size="sm">
                                <Link href={caja.sesiones.index.url()}>{t('caja:ventas.create.ir_sesiones')}</Link>
                            </Button>
                        </AlertDescription>
                    </Alert>
                ) : (
                    <Alert className="border-primary/20 bg-primary/5">
                        <ShoppingCart className="size-4 text-primary" aria-hidden />
                        <AlertTitle>{t('caja:ventas.create.sesion_activa_title')}</AlertTitle>
                        <AlertDescription>
                            {t('caja:ventas.create.sesion_activa_body', {
                                sede: mi_sesion?.sede_nombre ?? '—',
                                moneda: mi_sesion?.moneda ?? clinica.moneda,
                            })}
                        </AlertDescription>
                    </Alert>
                )}

                {clinica.emite_comprobantes_sunat && clinica.plan_permite_factura_electronica ? (
                    <p className="-mt-2 text-xs text-muted-foreground">{t('caja:ventas.create.hint_fel')}</p>
                ) : null}

                <div className="rounded-xl border border-border/40 bg-muted/20 px-3.5 py-3 text-xs leading-relaxed text-muted-foreground sm:text-sm">
                    {precioIncluyeIgv
                        ? t('caja:ventas.create.hint_precio_incluye_igv', { pct: clinica.igv_porcentaje })
                        : t('caja:ventas.create.hint_precio_sin_igv', { pct: clinica.igv_porcentaje })}
                </div>

                <div className="grid gap-5 lg:grid-cols-2 lg:items-stretch">
                    <PosPanel
                        title={t('caja:ventas.create.card_cliente')}
                        description={t('caja:ventas.create.card_cliente_desc')}
                        icon={UserCircle}
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="flex flex-col gap-2 sm:col-span-2">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <Label htmlFor="propietario">{t('caja:ventas.create.propietario')}</Label>
                                    {!desdeCargo ? (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-8 gap-1.5"
                                            disabled={!puede_vender}
                                            onClick={() => setNuevoClienteOpen(true)}
                                        >
                                            <UserPlus className="size-3.5" aria-hidden />
                                            {t('caja:ventas.create.nuevo_cliente')}
                                        </Button>
                                    ) : null}
                                </div>
                                <Combobox
                                    id="propietario"
                                    options={propietariosLocales.map((o) => ({
                                        value: o.id,
                                        label: o.doc ? `${o.label} • ${o.doc}` : o.label,
                                    }))}
                                    value={form.data.propietario_id || null}
                                    onChange={(v) => onPropietarioChange(v ?? '')}
                                    placeholder={t('caja:ventas.create.propietario_ph')}
                                    disabled={!puede_vender || Boolean(desdeCargo)}
                                />
                                {desdeCargo ? (
                                    <p className="text-xs text-muted-foreground">
                                        {t('caja:ventas.desde_cargo.cliente_bloqueado')}
                                    </p>
                                ) : null}
                                {form.errors.propietario_id ? (
                                    <p className="text-xs text-destructive">{form.errors.propietario_id}</p>
                                ) : null}
                            </div>

                            <div className="flex flex-col gap-2 sm:col-span-2">
                                <Label>{t('caja:ventas.create.comprobante_sunat')}</Label>
                                <ToggleGroup
                                    type="single"
                                    variant="outline"
                                    className="w-fit"
                                    value={String(form.data.tipo_comprobante_sunat)}
                                    onValueChange={(v) => {
                                        if (v === '1' || v === '2') {
                                            form.setData(
                                                'tipo_comprobante_sunat',
                                                Number(v) as 1 | 2,
                                            );
                                        }
                                    }}
                                    disabled={!puede_vender}
                                >
                                    <ToggleGroupItem value="2" className="min-w-24 px-4">
                                        {t('caja:ventas.create.comprobante_boleta')}
                                    </ToggleGroupItem>
                                    <ToggleGroupItem value="1" className="min-w-24 px-4">
                                        {t('caja:ventas.create.comprobante_factura')}
                                    </ToggleGroupItem>
                                </ToggleGroup>
                                <p className="text-xs text-muted-foreground">
                                    {t('caja:ventas.create.comprobante_hint')}
                                </p>
                                {form.errors.tipo_comprobante_sunat ? (
                                    <p className="text-xs text-destructive">
                                        {form.errors.tipo_comprobante_sunat}
                                    </p>
                                ) : null}
                            </div>

                            <div className="flex flex-col gap-2 sm:col-span-2">
                                <Label htmlFor="paciente">{t('caja:ventas.create.paciente')}</Label>
                                <Select
                                    value={form.data.paciente_id ?? 'none'}
                                    onValueChange={(v) =>
                                        form.setData('paciente_id', v === 'none' ? null : v)
                                    }
                                    disabled={
                                        !puede_vender ||
                                        Boolean(desdeCargo) ||
                                        !form.data.propietario_id ||
                                        cargandoPacientes
                                    }
                                >
                                    <SelectTrigger id="paciente" className="w-full">
                                        <SelectValue placeholder={t('caja:ventas.create.paciente_ph')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">{t('caja:ventas.create.paciente_ninguno')}</SelectItem>
                                        {pacientes.map((p) => (
                                            <SelectItem key={p.id} value={p.id}>
                                                {p.nombre}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors.paciente_id ? (
                                    <p className="text-xs text-destructive">{form.errors.paciente_id}</p>
                                ) : null}
                            </div>
                        </div>
                    </PosPanel>

                    <PosPanel
                        title={t('caja:ventas.create.card_productos')}
                        description={t('caja:ventas.create.card_productos_desc')}
                        icon={PackageSearch}
                        badge={
                            cart.length > 0 ? (
                                <Badge variant="secondary" className="tabular-nums">
                                    {cart.length}
                                </Badge>
                            ) : null
                        }
                    >
                        <div className="relative">
                            <Search
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden
                            />
                            <Input
                                className="w-full pl-9"
                                placeholder={t('caja:ventas.create.buscar_producto_ph')}
                                value={qProducto}
                                onChange={(e) => setQProducto(e.target.value)}
                                disabled={!puede_vender}
                            />
                            {buscando ? (
                                <Loader2
                                    className="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-muted-foreground"
                                    aria-hidden
                                />
                            ) : null}
                        </div>
                        {hits.length > 0 ? (
                            <ul className="max-h-56 overflow-auto rounded-lg border border-border/60 bg-muted/15 text-sm shadow-inner">
                                {hits.map((p) => {
                                    const stock = parseStock(p.stock_sede);
                                    const sinStock = stock <= 0;

                                    return (
                                        <li
                                            key={p.id}
                                            className={cn(
                                                'border-b border-border/40 last:border-0',
                                                sinStock && 'bg-destructive/5',
                                            )}
                                        >
                                            <button
                                                type="button"
                                                disabled={sinStock || !puede_vender}
                                                className={cn(
                                                    'flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left transition-colors focus-visible:outline-none',
                                                    sinStock
                                                        ? 'cursor-not-allowed text-destructive'
                                                        : 'cursor-pointer hover:bg-muted/50 focus-visible:bg-muted/50',
                                                )}
                                                onClick={() => addProduct(p)}
                                            >
                                                <span className="min-w-0 font-medium">{p.nombre}</span>
                                                <span
                                                    className={cn(
                                                        'flex shrink-0 flex-col items-end gap-0.5 text-xs tabular-nums',
                                                        sinStock
                                                            ? 'font-medium text-destructive'
                                                            : 'text-muted-foreground',
                                                    )}
                                                >
                                                    <span>
                                                        {p.precio_venta
                                                            ? formatMoney(Number(p.precio_venta))
                                                            : '—'}{' '}
                                                        / {p.unidad}
                                                    </span>
                                                    <span>
                                                        {sinStock
                                                            ? t('caja:ventas.create.stock_cero')
                                                            : t('caja:ventas.create.stock_disponible', {
                                                                  stock,
                                                              })}
                                                    </span>
                                                </span>
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        ) : qProducto.trim().length >= 2 && !buscando ? (
                            <p className="text-center text-xs text-muted-foreground">
                                {t('caja:ventas.create.sin_resultados')}
                            </p>
                        ) : null}
                    </PosPanel>

                    <PosPanel
                        title={t('caja:ventas.create.card_servicios')}
                        description={t('caja:ventas.create.card_servicios_desc')}
                        icon={Stethoscope}
                        className="lg:col-span-2"
                    >
                        <div className="relative">
                            <Search
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden
                            />
                            <Input
                                className="w-full pl-9"
                                placeholder={t('caja:ventas.create.buscar_servicio_ph')}
                                value={qServicio}
                                onChange={(e) => setQServicio(e.target.value)}
                                disabled={!puede_vender}
                            />
                            {buscandoServicio ? (
                                <Loader2
                                    className="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-muted-foreground"
                                    aria-hidden
                                />
                            ) : null}
                        </div>
                        {hitsServicio.length > 0 ? (
                            <ul className="max-h-40 overflow-auto rounded-lg border border-border/60 bg-muted/15 text-sm shadow-inner">
                                {hitsServicio.map((s) => (
                                    <li
                                        key={`${s.nombre}:${s.precio_lista}`}
                                        className="border-b border-border/40 last:border-0"
                                    >
                                        <button
                                            type="button"
                                            disabled={!puede_vender}
                                            className="flex w-full cursor-pointer items-center justify-between gap-3 px-3 py-2.5 text-left transition-colors hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none"
                                            onClick={() => addServicioFromTarifa(s)}
                                        >
                                            <span className="min-w-0 font-medium">{s.nombre}</span>
                                            <span className="shrink-0 text-xs tabular-nums text-muted-foreground">
                                                {formatMoney(Number(s.precio_lista))}
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        ) : qServicio.trim().length > 0 && !buscandoServicio ? (
                            <p className="text-center text-xs text-muted-foreground">
                                {t('caja:ventas.create.sin_tarifas_servicio')}
                            </p>
                        ) : null}

                        <div className="grid gap-3 border-t border-border/50 pt-4 sm:grid-cols-[1fr_140px_auto] sm:items-end">
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="servicio-concepto">
                                    {t('caja:ventas.create.servicio_concepto')}
                                </Label>
                                <Input
                                    id="servicio-concepto"
                                    placeholder={t('caja:ventas.create.servicio_concepto_ph')}
                                    value={servicioConcepto}
                                    onChange={(e) => setServicioConcepto(e.target.value)}
                                    disabled={!puede_vender}
                                />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="servicio-precio">
                                    {t('caja:ventas.create.servicio_precio')}
                                </Label>
                                <Input
                                    id="servicio-precio"
                                    type="number"
                                    inputMode="decimal"
                                    min={0}
                                    step={0.01}
                                    placeholder={t('caja:ventas.create.servicio_precio_ph')}
                                    value={servicioPrecio}
                                    onChange={(e) => setServicioPrecio(e.target.value)}
                                    disabled={!puede_vender}
                                />
                            </div>
                            <Button
                                type="button"
                                className="gap-1.5 sm:mb-0.5"
                                disabled={!puede_vender}
                                onClick={() => addServicioLine(servicioConcepto, servicioPrecio)}
                            >
                                <Plus className="size-4" aria-hidden />
                                {t('caja:ventas.create.agregar_servicio')}
                            </Button>
                        </div>
                    </PosPanel>
                </div>

                <div className="grid gap-5 xl:grid-cols-[1fr_minmax(320px,380px)] xl:items-start">
                    <PosPanel
                        title={t('caja:ventas.create.card_carrito')}
                        description={t('caja:ventas.create.card_carrito_desc')}
                        icon={ShoppingBag}
                        badge={
                            cart.length > 0 ? (
                                <Badge variant="outline" className="tabular-nums">
                                    {formatMoney(totales.total)}
                                </Badge>
                            ) : null
                        }
                        className="min-h-[280px]"
                        contentClassName="min-h-0"
                    >
                        {cart.length === 0 ? (
                            <div className="flex flex-1 flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-border/70 bg-muted/10 px-6 py-12 text-center">
                                <span className="flex size-12 items-center justify-center rounded-full bg-muted/50 text-muted-foreground">
                                    <ShoppingBag className="size-6" strokeWidth={1.75} aria-hidden />
                                </span>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-foreground">
                                        {t('caja:ventas.create.carrito_vacio')}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {t('caja:ventas.create.carrito_vacio_hint')}
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border border-border/50">
                                <table className="w-full min-w-[560px] border-collapse text-sm">
                                    <thead className="bg-muted/40">
                                        <tr>
                                            <th className="border-b border-border/60 px-4 py-3 text-left text-xs font-semibold text-muted-foreground">
                                                {t('caja:ventas.create.col_item')}
                                            </th>
                                            <th className="w-36 border-b border-border/60 px-4 py-3 text-left text-xs font-semibold text-muted-foreground">
                                                {t('caja:ventas.create.col_cantidad')}
                                            </th>
                                            <th className="w-32 border-b border-border/60 px-4 py-3 text-right text-xs font-semibold text-muted-foreground">
                                                {t('caja:ventas.create.col_precio_unit')}
                                            </th>
                                            <th className="w-28 border-b border-border/60 px-4 py-3 text-right text-xs font-semibold text-muted-foreground">
                                                {t('caja:ventas.create.col_total')}
                                            </th>
                                            <th className="w-12 border-b border-border/60" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {cart.map((line) => {
                                            const lista = Number(line.precio_venta ?? 0);
                                            const lineTotal = lineTotalLinea(
                                                lista,
                                                line.cantidad,
                                                igvPct,
                                                precioIncluyeIgv,
                                            );
                                            const excedeStock =
                                                !line.omitir_stock &&
                                                line.cantidad > line.stock_disponible + 0.0001;

                                            return (
                                                <tr
                                                    key={line.key}
                                                    className={cn(
                                                        'border-b border-border/40 last:border-b-0 hover:bg-muted/25',
                                                        excedeStock && 'bg-destructive/5',
                                                    )}
                                                >
                                                    <td className="px-4 py-3 align-middle">
                                                        <div className="flex flex-col gap-0.5">
                                                            <span className="font-medium">{line.nombre}</span>
                                                            <span
                                                                className={cn(
                                                                    'text-xs',
                                                                    excedeStock
                                                                        ? 'text-destructive'
                                                                        : 'text-muted-foreground',
                                                                )}
                                                            >
                                                                {line.unidad}
                                                                {line.producto_id !== null && lista > 0
                                                                    ? ` · ${t('caja:ventas.create.precio_unitario', {
                                                                          precio: formatMoney(lista),
                                                                      })}`
                                                                    : ''}
                                                                {line.omitir_stock
                                                                    ? ` · ${t('caja:ventas.desde_cargo.sin_stock_aplica')}`
                                                                    : ` · ${t('caja:ventas.create.stock_disponible', {
                                                                          stock: line.stock_disponible,
                                                                      })}`}
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3 align-middle">
                                                        <div className="flex items-center gap-1">
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="icon"
                                                                className="size-8 shrink-0"
                                                                onClick={() =>
                                                                    setCantidad(line.key, line.cantidad - 1)
                                                                }
                                                                disabled={!puede_vender}
                                                            >
                                                                <Minus className="size-3" aria-hidden />
                                                            </Button>
                                                            <Input
                                                                className="h-8 w-16 text-center text-xs tabular-nums"
                                                                value={String(line.cantidad)}
                                                                onChange={(e) => {
                                                                    const v = Number(
                                                                        e.target.value.replace(',', '.'),
                                                                    );

                                                                    if (!Number.isNaN(v)) {
                                                                        setCantidad(line.key, v);
                                                                    }
                                                                }}
                                                                disabled={!puede_vender}
                                                            />
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="icon"
                                                                className="size-8 shrink-0"
                                                                onClick={() =>
                                                                    setCantidad(line.key, line.cantidad + 1)
                                                                }
                                                                disabled={
                                                                    !puede_vender ||
                                                                    (!line.omitir_stock &&
                                                                        line.cantidad >=
                                                                            line.stock_disponible - 0.0001)
                                                                }
                                                            >
                                                                <Plus className="size-3" aria-hidden />
                                                            </Button>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3 text-right align-middle">
                                                        {line.producto_id === null ? (
                                                            <Input
                                                                type="number"
                                                                inputMode="decimal"
                                                                min={0}
                                                                step={0.01}
                                                                className="ml-auto h-8 w-28 text-right text-xs tabular-nums"
                                                                value={lista}
                                                                onChange={(e) =>
                                                                    setPrecioListaLinea(line.key, e.target.value)
                                                                }
                                                                disabled={!puede_vender}
                                                                aria-label={t('caja:ventas.create.col_precio_unit')}
                                                            />
                                                        ) : (
                                                            <span className="text-sm font-medium tabular-nums">
                                                                {formatMoney(lista)}
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-right align-middle text-sm font-medium tabular-nums">
                                                        {formatMoney(lineTotal)}
                                                    </td>
                                                    <td className="px-2 py-3 align-middle">
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-8 text-destructive hover:text-destructive"
                                                            onClick={() => removeLine(line.key)}
                                                            disabled={!puede_vender}
                                                        >
                                                            <Trash2 className="size-4" aria-hidden />
                                                        </Button>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {form.errors.lineas ? (
                            <p className="text-xs text-destructive">{form.errors.lineas}</p>
                        ) : null}
                    </PosPanel>

                    <PosPanel
                        title={t('caja:ventas.create.card_pago')}
                        description={t('caja:ventas.create.card_pago_desc')}
                        icon={CreditCard}
                        className="xl:sticky xl:top-4"
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="flex flex-col gap-2">
                                <Label>{t('caja:ventas.create.metodo_pago')}</Label>
                                <Select
                                    value={form.data.metodo_pago}
                                    onValueChange={(v) => form.setData('metodo_pago', v)}
                                    disabled={!puede_vender}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="efectivo">{t('caja:ventas.create.mp_efectivo')}</SelectItem>
                                        <SelectItem value="yape">{t('caja:ventas.create.mp_yape')}</SelectItem>
                                        <SelectItem value="plin">{t('caja:ventas.create.mp_plin')}</SelectItem>
                                        <SelectItem value="tarjeta">{t('caja:ventas.create.mp_tarjeta')}</SelectItem>
                                        <SelectItem value="transferencia">
                                            {t('caja:ventas.create.mp_transferencia')}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="monto_recibido">{t('caja:ventas.create.monto_recibido')}</Label>
                                <Input
                                    id="monto_recibido"
                                    className="w-full tabular-nums"
                                    inputMode="decimal"
                                    placeholder={
                                        esEfectivo
                                            ? formatMoney(totales.total)
                                            : t('caja:ventas.create.monto_no_aplica')
                                    }
                                    value={form.data.monto_recibido}
                                    onChange={(e) => form.setData('monto_recibido', e.target.value)}
                                    disabled={!puede_vender || !esEfectivo}
                                />
                                {form.errors.monto_recibido ? (
                                    <p className="text-xs text-destructive">{form.errors.monto_recibido}</p>
                                ) : null}
                            </div>
                        </div>

                        <div
                            className={cn(
                                'flex flex-col gap-2 rounded-xl border border-primary/15 bg-primary/5 p-4 text-sm',
                                cart.length === 0 && 'opacity-60',
                            )}
                        >
                            <div className="flex justify-between gap-4">
                                <span className="text-muted-foreground">{t('caja:ventas.create.res_subtotal')}</span>
                                <span className="tabular-nums font-medium">{formatMoney(totales.subtotal)}</span>
                            </div>
                            <div className="flex justify-between gap-4">
                                <span className="text-muted-foreground">
                                    {t('caja:ventas.create.res_igv', { pct: clinica.igv_porcentaje })}
                                </span>
                                <span className="tabular-nums font-medium">{formatMoney(totales.igv)}</span>
                            </div>
                            <div className="flex justify-between gap-4 border-t border-primary/15 pt-3">
                                <span className="text-base font-semibold">{t('caja:ventas.create.res_total')}</span>
                                <span className="text-lg font-bold tabular-nums text-primary">
                                    {formatMoney(totales.total)}
                                </span>
                            </div>
                            {esEfectivo && form.data.monto_recibido ? (
                                <div className="flex justify-between gap-4 text-muted-foreground">
                                    <span>{t('caja:ventas.create.res_vuelto')}</span>
                                    <span className="tabular-nums">
                                        {formatMoney(
                                            Math.max(
                                                0,
                                                Number(String(form.data.monto_recibido).replace(',', '.')) -
                                                    totales.total,
                                            ),
                                        )}
                                    </span>
                                </div>
                            ) : null}
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="notas">{t('caja:ventas.create.notas')}</Label>
                            <Textarea
                                id="notas"
                                rows={2}
                                className="min-h-[72px] resize-y"
                                value={form.data.notas}
                                onChange={(e) => form.setData('notas', e.target.value)}
                                disabled={!puede_vender}
                            />
                        </div>

                        {form.errors.caja_sesion_id ? (
                            <p className="text-xs text-destructive">{form.errors.caja_sesion_id}</p>
                        ) : null}

                        {erroresFormulario.length > 0 ? (
                            <Alert variant="destructive">
                                <AlertTitle>{t('caja:ventas.create.error_guardar_title')}</AlertTitle>
                                <AlertDescription>
                                    <ul className="list-inside list-disc space-y-1 text-sm">
                                        {erroresFormulario.map((msg) => (
                                            <li key={msg}>{msg}</li>
                                        ))}
                                    </ul>
                                </AlertDescription>
                            </Alert>
                        ) : null}

                        <Button
                            type="button"
                            className="w-full"
                            size="lg"
                            disabled={!puedeConfirmar || form.processing}
                            onClick={submit}
                        >
                            {form.processing ? (
                                <>
                                    <Loader2 className="mr-2 size-4 animate-spin" aria-hidden />
                                    {t('caja:ventas.create.guardando')}
                                </>
                            ) : (
                                t('caja:ventas.create.confirmar')
                            )}
                        </Button>
                    </PosPanel>
                </div>
            </div>

            <PropietarioFormModal
                open={nuevoClienteOpen}
                onOpenChange={setNuevoClienteOpen}
                propietario={null}
                departamentos={departamentos}
                jsonStoreUrl="/caja/ventas/propietarios-rapido"
                onCreated={(p) => {
                    setPropietariosLocales((prev) => {
                        if (prev.some((x) => x.id === p.id)) {
                            return prev;
                        }

                        return [p, ...prev];
                    });
                    onPropietarioChange(p.id);
                }}
            />
        </>
    );
}

Create.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Caja' },
            { title: 'Ventas', href: caja.ventas.index.url() },
            { title: 'Nueva', href: caja.ventas.create.url() },
        ]}
    >
        {page}
    </AppLayout>
);
