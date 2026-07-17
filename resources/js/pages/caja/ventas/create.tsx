import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Banknote,
    Building2,
    CreditCard,
    Loader2,
    Minus,
    PackagePlus,
    PackageSearch,
    Plus,
    Search,
    ShoppingBag,
    ShoppingCart,
    Smartphone,
    Stethoscope,
    Trash2,
    UserPlus,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import AppLayout from '@/layouts/app-layout';
import { PropietarioFormModal } from '@/pages/clinica/propietarios/components/propietario-form-modal';
import { ProductoRapidoDialog, ServicioRapidoDialog } from './components/registro-rapido-dialogs';
import { toastManager } from '@/lib/toast';
import { loadCajaBootstrap, searchCachedProductos } from '@/lib/offline/cache';
import { isIndexedDbSupported } from '@/lib/offline/idb';
import { enqueueOutbox } from '@/lib/offline/outbox';
import { cn } from '@/lib/utils';
import { useOfflineSync } from '@/hooks/use-offline-sync';
import caja from '@/routes/caja';
import { PosPanel } from './components/pos-panel';
import type { ProductoBusqueda, ServicioTarifaBusqueda, VentasCreateProps } from './types';
import { calcTotalesVenta, lineTotalFromSubtotal, lineTotalLinea } from './venta-pricing';

type CartLine = {
    key: string;
    producto_id: string | null;
    consulta_cargo_linea_id?: string | null;
    tipo_linea?: string;
    nombre: string;
    unidad: string;
    precio_venta: string | null;
    descuento_pct: number;
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

async function jsonPost(url: string, body: unknown): Promise<unknown> {
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': readXsrfToken(),
        },
        body: JSON.stringify(body),
    });

    if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
    }

    return res.json();
}

type PromotionPreviewLine = {
    producto_id: string | null;
    subtotal: number;
    promotion_id: string | null;
    descuento_pct?: number;
};

type PromotionPreview = {
    discount_amount: string;
    promotion_name: string | null;
    subtotal: string;
    igv_monto: string;
    total: string;
    lineas?: PromotionPreviewLine[];
};

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
    puede_crear_producto: puedeCrearProducto,
    puede_crear_servicio: puedeCrearServicio,
    unidad_opciones: unidadOpciones,
}: VentasCreateProps) {
    const { t, i18n } = useTranslation(['caja', 'common', 'offline']);
    const { isOnline, refreshPending } = useOfflineSync();
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
    const [propietariosLocales, setPropietariosLocales] = useState(propietariosOpciones);
    const [nuevoClienteOpen, setNuevoClienteOpen] = useState(false);
    const [catalogTab, setCatalogTab] = useState<'productos' | 'servicios'>('productos');
    const [productoRapidoOpen, setProductoRapidoOpen] = useState(false);
    const [servicioRapidoOpen, setServicioRapidoOpen] = useState(false);

    const form = useForm({
        caja_sesion_id: mi_sesion?.id ?? '',
        propietario_id: '',
        paciente_id: null as string | null,
        tipo_comprobante_sunat: 0 as 0 | 1 | 2,
        consulta_id: null as string | null,
        consulta_cargo_id: null as string | null,
        grooming_turno_id: null as string | null,
        hotel_estancia_id: null as string | null,
        promotion_code: '',
        lineas: [] as { producto_id: string; cantidad: number }[],
        metodo_pago: 'efectivo',
        monto_recibido: '',
        notas: '',
    });

    const [promoPreview, setPromoPreview] = useState<PromotionPreview | null>(null);

    useEffect(() => {
        setPropietariosLocales(propietariosOpciones);
    }, [propietariosOpciones]);

    const puedeEmitirBoleta  = clinica.emite_comprobantes_sunat && clinica.plan_permite_boletas;
    const puedeEmitirFactura = clinica.emite_comprobantes_sunat && clinica.plan_permite_facturas;
    const puedeElegirComprobanteSunat = puedeEmitirBoleta || puedeEmitirFactura;

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
                descuento_pct: 0,
                cantidad: Number(ln.cantidad) || 1,
                stock_disponible: tieneProducto ? stock : 999999,
                omitir_stock: !tieneProducto,
            };
        });

        setCart(lineasCart);
        form.setData((prev) => ({
            ...prev,
            propietario_id: desdeCargo.propietario_id,
            paciente_id: desdeCargo.paciente_id ?? null,
            consulta_id: desdeCargo.consulta_id,
            consulta_cargo_id: desdeCargo.consulta_cargo_id,
            grooming_turno_id: desdeCargo.grooming_turno_id ?? null,
            hotel_estancia_id: desdeCargo.hotel_estancia_id ?? null,
        }));
    }, [desdeCargo, form, t]);

    const igvPct = Number(clinica.igv_porcentaje);
    const precioIncluyeIgv = clinica.precio_incluye_igv;

    const totalesBase = useMemo(
        () => calcTotalesVenta(cart, igvPct, precioIncluyeIgv),
        [cart, igvPct, precioIncluyeIgv],
    );

    const totales = useMemo(() => {
        if (!promoPreview) {
            return totalesBase;
        }

        return {
            subtotal: Number(promoPreview.subtotal),
            igv: Number(promoPreview.igv_monto),
            total: Number(promoPreview.total),
            discount: Number(promoPreview.discount_amount),
        };
    }, [promoPreview, totalesBase]);

    useEffect(() => {
        if (!form.data.propietario_id || cart.length === 0 || !navigator.onLine) {
            setPromoPreview(null);

            return;
        }

        const tmr = window.setTimeout(() => {
            jsonPost('/caja/ventas/preview-promotions', {
                propietario_id: form.data.propietario_id,
                paciente_id: form.data.paciente_id,
                grooming_turno_id: form.data.grooming_turno_id,
                hotel_estancia_id: form.data.hotel_estancia_id,
                promotion_code: form.data.promotion_code.trim() || null,
                lineas: cart.map((l) => ({
                    producto_id: l.producto_id,
                    concepto: l.producto_id ? null : l.nombre,
                    precio_lista:
                        l.precio_venta === '' || l.precio_venta === null ? '0' : String(l.precio_venta),
                    tipo_linea: l.tipo_linea ?? (l.producto_id ? 'producto' : 'servicio'),
                    cantidad: l.cantidad,
                    descuento_pct: l.descuento_pct,
                })),
            })
                .then((raw) => {
                    const data = (raw as { data?: PromotionPreview }).data ?? null;
                    setPromoPreview(data);
                })
                .catch(() => setPromoPreview(null));
        }, 350);

        return () => window.clearTimeout(tmr);
    }, [
        cart,
        form.data.propietario_id,
        form.data.paciente_id,
        form.data.grooming_turno_id,
        form.data.hotel_estancia_id,
        form.data.promotion_code,
    ]);

    useEffect(() => {
        const tmr = window.setTimeout(() => {
            const q = qProducto.trim();

            if (q.length < 2) {
                setHits([]);

                return;
            }

            if (!navigator.onLine && isIndexedDbSupported()) {
                setBuscando(true);
                void loadCajaBootstrap()
                    .then((cache) => {
                        if (!cache) {
                            setHits([]);

                            return;
                        }

                        setHits(
                            searchCachedProductos(cache, q).map((p) => ({
                                id: p.id,
                                nombre: p.nombre,
                                sku: p.sku,
                                precio_venta: p.precio_venta,
                                unidad: p.unidad,
                                stock_sede: p.stock_sede,
                            })),
                        );
                    })
                    .finally(() => setBuscando(false));

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
                        descuento_pct: 0,
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
                    descuento_pct: 0,
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

    const setDescuentoLinea = useCallback((lineKey: string, value: string) => {
        const normalized = value.trim().replace(',', '.');
        const parsed = normalized === '' || normalized === '.' ? 0 : Number(normalized);
        if (Number.isNaN(parsed) || parsed < 0) {
            return;
        }

        const descuento = Math.round(Math.min(100, parsed) * 100) / 100;
        setCart((prev) =>
            prev.map((line) =>
                line.key === lineKey ? { ...line, descuento_pct: descuento } : line,
            ),
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

    const buildVentaPayload = useCallback(() => {
        const d = form.data;

        return {
            caja_sesion_id: mi_sesion!.id,
            propietario_id: d.propietario_id,
            paciente_id: d.paciente_id || null,
            consulta_id: d.consulta_id || null,
            consulta_cargo_id: d.consulta_cargo_id || null,
            grooming_turno_id: d.grooming_turno_id || null,
            hotel_estancia_id: d.hotel_estancia_id || null,
            promotion_code: d.promotion_code.trim() || null,
            metodo_pago: d.metodo_pago,
            monto_recibido: d.metodo_pago === 'efectivo' ? d.monto_recibido : null,
            notas: d.notas || null,
            tipo_comprobante_sunat:
                d.tipo_comprobante_sunat === 0 ? null : d.tipo_comprobante_sunat,
            lineas: cart.map((l) => ({
                producto_id: l.producto_id,
                concepto: l.producto_id ? null : l.nombre,
                precio_lista:
                    l.precio_venta === '' || l.precio_venta === null
                        ? '0'
                        : String(l.precio_venta),
                tipo_linea: l.tipo_linea ?? (l.producto_id ? 'producto' : 'servicio'),
                consulta_cargo_linea_id: l.consulta_cargo_linea_id ?? null,
                cantidad: l.cantidad,
                descuento_pct: l.descuento_pct,
            })),
        };
    }, [cart, form.data, mi_sesion]);

    const submit = useCallback(() => {
        if (!puede_vender || !mi_sesion || cart.length === 0) {
            return;
        }

        if (!isOnline && isIndexedDbSupported()) {
            void (async () => {
                try {
                    const item = await enqueueOutbox(
                        'caja.venta.create',
                        buildVentaPayload(),
                    );
                    await refreshPending();
                    setCart([]);
                    setHits([]);
                    setQProducto('');
                    form.setData((prev) => ({
                        ...prev,
                        paciente_id: null,
                        promotion_code: '',
                        monto_recibido: '',
                        notas: '',
                    }));
                    toastManager.success({
                        title: t('offline:venta.queued_title'),
                        description: t('offline:venta.queued_body', {
                            label: item.local_label ?? item.uuid.slice(0, 8),
                        }),
                    });
                } catch {
                    toastManager.error({
                        title: t('caja:ventas.create.error_guardar_title'),
                        description: t('caja:ventas.create.error_guardar_generico'),
                    });
                }
            })();

            return;
        }

        form.transform((d) => ({
            ...d,
            caja_sesion_id: mi_sesion.id,
            paciente_id: d.paciente_id || null,
            grooming_turno_id: d.grooming_turno_id || null,
            hotel_estancia_id: d.hotel_estancia_id || null,
            promotion_code: d.promotion_code.trim() || null,
            lineas: cart.map((l) => ({
                producto_id: l.producto_id,
                concepto: l.producto_id ? null : l.nombre,
                precio_lista:
                    l.precio_venta === '' || l.precio_venta === null ? '0' : String(l.precio_venta),
                tipo_linea: l.tipo_linea ?? (l.producto_id ? 'producto' : 'servicio'),
                consulta_cargo_linea_id: l.consulta_cargo_linea_id ?? null,
                cantidad: l.cantidad,
                descuento_pct: l.descuento_pct,
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
    }, [
        buildVentaPayload,
        cart,
        form,
        isOnline,
        mi_sesion,
        puede_vender,
        refreshPending,
        t,
    ]);

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

    const esEfectivo = form.data.metodo_pago === 'efectivo';

    const erroresFormulario = useMemo(
        () =>
            Object.entries(form.errors).flatMap(([key, message]) =>
                typeof message === 'string' && message.length > 0 ? [`${key}: ${message}`] : [],
            ),
        [form.errors],
    );

    const paymentMethods: { value: string; label: string; icon: LucideIcon }[] = useMemo(
        () => [
            { value: 'efectivo', label: t('caja:ventas.create.mp_efectivo'), icon: Banknote },
            { value: 'yape', label: t('caja:ventas.create.mp_yape'), icon: Smartphone },
            { value: 'plin', label: t('caja:ventas.create.mp_plin'), icon: Smartphone },
            { value: 'tarjeta', label: t('caja:ventas.create.mp_tarjeta'), icon: CreditCard },
            { value: 'transferencia', label: t('caja:ventas.create.mp_transferencia'), icon: Building2 },
        ],
        [t],
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

            <div className="flex flex-1 flex-col">
                {/* ── Barra superior ── */}
                <div className="flex shrink-0 items-center gap-2 border-b border-border/40 bg-background/95 px-3 py-2 backdrop-blur-sm sm:px-4">
                    <Button variant="ghost" size="icon" className="size-7 shrink-0" asChild>
                        <Link href={caja.ventas.index.url()}>
                            <ArrowLeft className="size-3.5" aria-hidden />
                        </Link>
                    </Button>
                    <div className="min-w-0 flex-1">
                        <h1 className="truncate text-sm font-semibold leading-tight sm:text-base">
                            {desdeCargo
                                ? t('caja:ventas.create.desde_cargo_titulo')
                                : t('caja:ventas.create.title')}
                        </h1>
                        {puede_vender && mi_sesion ? (
                            <p className="truncate text-[11px] text-muted-foreground">
                                {mi_sesion.sede_nombre ?? '—'} · {mi_sesion.moneda ?? clinica.moneda}
                            </p>
                        ) : null}
                    </div>
                    {puede_vender && mi_sesion ? (
                        <span
                            className="flex max-w-[45%] shrink-0 items-center gap-1 rounded-full border border-emerald-500/25 bg-emerald-500/8 px-2 py-0.5 text-[10px] font-medium text-emerald-700 dark:text-emerald-400 sm:max-w-none"
                            title={t('caja:ventas.create.sesion_activa_body', {
                                sede: mi_sesion.sede_nombre ?? '—',
                                moneda: mi_sesion.moneda ?? clinica.moneda,
                            })}
                        >
                            <span className="size-1.5 shrink-0 rounded-full bg-emerald-500" />
                            <span className="truncate">
                                {t('caja:ventas.create.sesion_activa_badge', {
                                    sede: mi_sesion.sede_nombre ?? '—',
                                })}
                            </span>
                        </span>
                    ) : (
                        <Badge variant="destructive" className="shrink-0 text-[10px]">
                            {t('caja:ventas.create.sin_sesion_title')}
                        </Badge>
                    )}
                </div>

                {/* ── Alertas condicionales ── */}
                {(!puede_vender || desdeCargo) ? (
                    <div className="flex flex-col gap-2 px-3 pt-2 sm:px-4">
                        {!puede_vender ? (
                            <Alert variant="destructive">
                                <AlertTitle>{t('caja:ventas.create.sin_sesion_title')}</AlertTitle>
                                <AlertDescription className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <span>{t('caja:ventas.create.sin_sesion_body')}</span>
                                    <Button asChild variant="secondary" size="sm">
                                        <Link href={caja.sesiones.index.url()}>
                                            {t('caja:ventas.create.ir_sesiones')}
                                        </Link>
                                    </Button>
                                </AlertDescription>
                            </Alert>
                        ) : null}
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
                    </div>
                ) : null}

                {/* ── Layout POS ── */}
                <div className="flex flex-1 flex-col gap-3 p-3 sm:p-4 lg:grid lg:grid-cols-[minmax(0,1fr)_min(360px,36%)] lg:items-start lg:gap-4">

                    {/* Columna izquierda: cliente + catálogo + carrito */}
                    <div className="flex min-w-0 flex-col gap-3">

                        {/* Cliente y comprobante en una sola franja */}
                        <section className="rounded-lg border border-border/60 bg-card px-3 py-2.5 shadow-xs ring-1 ring-border/10">
                            <div className="flex flex-col gap-2.5 lg:flex-row lg:items-end lg:gap-3">
                                <div className="min-w-0 flex-1 space-y-1">
                                    <div className="flex h-6 items-center justify-between gap-2">
                                        <Label htmlFor="propietario" className="text-xs text-muted-foreground">
                                            {t('caja:ventas.create.propietario')}
                                        </Label>
                                        {!desdeCargo ? (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="h-6 gap-1 px-2 text-[11px] text-primary"
                                                disabled={!puede_vender}
                                                onClick={() => setNuevoClienteOpen(true)}
                                            >
                                                <UserPlus className="size-3" aria-hidden />
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
                                        <p className="text-[11px] text-muted-foreground">
                                            {t('caja:ventas.desde_cargo.cliente_bloqueado')}
                                        </p>
                                    ) : null}
                                    {form.errors.propietario_id ? (
                                        <p className="text-[11px] text-destructive">{form.errors.propietario_id}</p>
                                    ) : null}
                                </div>

                                <div className="shrink-0 space-y-1">
                                    <Label className="flex h-6 items-center text-xs text-muted-foreground">
                                        {t('caja:ventas.create.tipo_comprobante')}
                                    </Label>
                                    <ToggleGroup
                                        type="single"
                                        variant="outline"
                                        size="sm"
                                        className="flex w-full flex-wrap justify-start gap-0.5"
                                        value={String(form.data.tipo_comprobante_sunat)}
                                        onValueChange={(v) => {
                                            if (v === '0' || v === '1' || v === '2') {
                                                form.setData('tipo_comprobante_sunat', Number(v) as 0 | 1 | 2);
                                            }
                                        }}
                                        disabled={!puede_vender}
                                    >
                                        <ToggleGroupItem value="0" className="h-7 min-w-0 flex-1 cursor-pointer px-2 text-xs">
                                            {t('caja:ventas.create.comprobante_ticket')}
                                        </ToggleGroupItem>
                                        {puedeEmitirBoleta ? (
                                            <ToggleGroupItem value="2" className="h-7 min-w-0 flex-1 cursor-pointer px-2 text-xs">
                                                {t('caja:ventas.create.comprobante_boleta')}
                                            </ToggleGroupItem>
                                        ) : null}
                                        {puedeEmitirFactura ? (
                                            <ToggleGroupItem value="1" className="h-7 min-w-0 flex-1 cursor-pointer px-2 text-xs">
                                                {t('caja:ventas.create.comprobante_factura')}
                                            </ToggleGroupItem>
                                        ) : null}
                                    </ToggleGroup>
                                    {form.errors.tipo_comprobante_sunat ? (
                                        <p className="text-[11px] text-destructive">{form.errors.tipo_comprobante_sunat}</p>
                                    ) : null}
                                </div>
                            </div>
                        </section>

                        {/* Catálogo unificado: productos | servicios */}
                        <PosPanel
                            compact
                            title={t('caja:ventas.create.card_productos')}
                            icon={PackageSearch}
                            className="min-h-0"
                        >
                            <Tabs
                                value={catalogTab}
                                onValueChange={(v) => {
                                    if (v === 'productos' || v === 'servicios') {
                                        setCatalogTab(v);
                                    }
                                }}
                                className="flex min-h-0 flex-1 flex-col gap-2.5"
                            >
                                <TabsList className="grid h-8 w-full grid-cols-2">
                                    <TabsTrigger value="productos" className="cursor-pointer gap-1.5 text-xs">
                                        <PackageSearch className="size-3" aria-hidden />
                                        {t('caja:ventas.create.card_productos')}
                                    </TabsTrigger>
                                    <TabsTrigger value="servicios" className="cursor-pointer gap-1.5 text-xs">
                                        <Stethoscope className="size-3" aria-hidden />
                                        {t('caja:ventas.create.card_servicios')}
                                    </TabsTrigger>
                                </TabsList>

                                <TabsContent value="productos" className="mt-0 flex flex-col gap-2">
                                    <div className="relative">
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden
                                        />
                                        <Input
                                            className="h-8 w-full pl-8 text-sm"
                                            placeholder={t('caja:ventas.create.buscar_producto_ph')}
                                            value={qProducto}
                                            onChange={(e) => setQProducto(e.target.value)}
                                            disabled={!puede_vender}
                                        />
                                        {buscando ? (
                                            <Loader2
                                                className="absolute top-1/2 right-2.5 size-3.5 -translate-y-1/2 animate-spin text-muted-foreground"
                                                aria-hidden
                                            />
                                        ) : null}
                                    </div>
                                    {mi_sesion?.sede_nombre ? (
                                        <p className="text-[10px] leading-snug text-muted-foreground">
                                            {t('caja:ventas.create.stock_sede_hint', { sede: mi_sesion.sede_nombre })}
                                        </p>
                                    ) : null}
                                    {hits.length > 0 ? (
                                        <ul className="max-h-44 overflow-auto rounded-md border border-border/50 bg-muted/10 text-sm">
                                            {hits.map((p) => {
                                                const stock = parseStock(p.stock_sede);
                                                const sinStock = stock <= 0;

                                                return (
                                                    <li
                                                        key={p.id}
                                                        className={cn(
                                                            'border-b border-border/30 last:border-0',
                                                            sinStock && 'bg-destructive/5',
                                                        )}
                                                    >
                                                        <button
                                                            type="button"
                                                            disabled={sinStock || !puede_vender}
                                                            className={cn(
                                                                'flex w-full items-center justify-between gap-2 px-2.5 py-2 text-left transition-colors focus-visible:outline-none',
                                                                sinStock
                                                                    ? 'cursor-not-allowed text-destructive'
                                                                    : 'cursor-pointer hover:bg-muted/40 focus-visible:bg-muted/40',
                                                            )}
                                                            onClick={() => addProduct(p)}
                                                        >
                                                            <span className="min-w-0 truncate text-xs font-medium">{p.nombre}</span>
                                                            <span
                                                                className={cn(
                                                                    'flex shrink-0 flex-col items-end gap-0 text-[10px] tabular-nums',
                                                                    sinStock ? 'text-destructive' : 'text-muted-foreground',
                                                                )}
                                                            >
                                                                <span>
                                                                    {p.precio_venta ? formatMoney(Number(p.precio_venta)) : '—'} /{' '}
                                                                    {p.unidad}
                                                                </span>
                                                                <span>
                                                                    {sinStock
                                                                        ? t('caja:ventas.create.stock_cero')
                                                                        : t('caja:ventas.create.stock_disponible', { stock })}
                                                                </span>
                                                            </span>
                                                        </button>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    ) : qProducto.trim().length >= 2 && !buscando ? (
                                        <p className="py-1 text-center text-[11px] text-muted-foreground">
                                            {t('caja:ventas.create.sin_resultados')}
                                        </p>
                                    ) : null}
                                    {puedeCrearProducto ? (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-8 w-full justify-center gap-1.5 border-dashed"
                                            disabled={!puede_vender}
                                            onClick={() => setProductoRapidoOpen(true)}
                                        >
                                            <PackagePlus className="size-3.5" aria-hidden />
                                            {t('caja:ventas.create.rapido_producto_cta')}
                                        </Button>
                                    ) : null}
                                </TabsContent>

                                <TabsContent value="servicios" className="mt-0 flex flex-col gap-2">
                                    <div className="relative">
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden
                                        />
                                        <Input
                                            className="h-8 w-full pl-8 text-sm"
                                            placeholder={t('caja:ventas.create.buscar_servicio_ph')}
                                            value={qServicio}
                                            onChange={(e) => setQServicio(e.target.value)}
                                            disabled={!puede_vender}
                                        />
                                        {buscandoServicio ? (
                                            <Loader2
                                                className="absolute top-1/2 right-2.5 size-3.5 -translate-y-1/2 animate-spin text-muted-foreground"
                                                aria-hidden
                                            />
                                        ) : null}
                                    </div>
                                    {hitsServicio.length > 0 ? (
                                        <ul className="max-h-32 overflow-auto rounded-md border border-border/50 bg-muted/10 text-sm">
                                            {hitsServicio.map((s) => (
                                                <li
                                                    key={`${s.nombre}:${s.precio_lista}`}
                                                    className="border-b border-border/30 last:border-0"
                                                >
                                                    <button
                                                        type="button"
                                                        disabled={!puede_vender}
                                                        className="flex w-full cursor-pointer items-center justify-between gap-2 px-2.5 py-2 text-left text-xs transition-colors hover:bg-muted/40 focus-visible:bg-muted/40 focus-visible:outline-none"
                                                        onClick={() => addServicioFromTarifa(s)}
                                                    >
                                                        <span className="min-w-0 truncate font-medium">{s.nombre}</span>
                                                        <span className="shrink-0 tabular-nums text-[10px] text-muted-foreground">
                                                            {formatMoney(Number(s.precio_lista))}
                                                        </span>
                                                    </button>
                                                </li>
                                            ))}
                                        </ul>
                                    ) : qServicio.trim().length > 0 && !buscandoServicio ? (
                                        <p className="py-1 text-center text-[11px] text-muted-foreground">
                                            {t('caja:ventas.create.sin_tarifas_servicio')}
                                        </p>
                                    ) : null}

                                    <div className="grid grid-cols-[minmax(0,1fr)_5.5rem_auto] items-end gap-2 border-t border-border/40 pt-2">
                                        <div className="min-w-0 space-y-1">
                                            <Label htmlFor="servicio-concepto" className="text-[11px] text-muted-foreground">
                                                {t('caja:ventas.create.servicio_concepto')}
                                            </Label>
                                            <Input
                                                id="servicio-concepto"
                                                className="h-8 text-sm"
                                                placeholder={t('caja:ventas.create.servicio_concepto_ph')}
                                                value={servicioConcepto}
                                                onChange={(e) => setServicioConcepto(e.target.value)}
                                                disabled={!puede_vender}
                                            />
                                        </div>
                                        <div className="min-w-0 space-y-1">
                                            <Label htmlFor="servicio-precio" className="text-[11px] text-muted-foreground">
                                                {t('caja:ventas.create.servicio_precio')}
                                            </Label>
                                            <Input
                                                id="servicio-precio"
                                                className="h-8 text-sm tabular-nums"
                                                type="number"
                                                inputMode="decimal"
                                                min={0}
                                                step={0.01}
                                                placeholder="0.00"
                                                value={servicioPrecio}
                                                onChange={(e) => setServicioPrecio(e.target.value)}
                                                disabled={!puede_vender}
                                            />
                                        </div>
                                        <Button
                                            type="button"
                                            size="sm"
                                            className="h-8 gap-1 px-2.5"
                                            disabled={!puede_vender}
                                            onClick={() => addServicioLine(servicioConcepto, servicioPrecio)}
                                        >
                                            <Plus className="size-3.5" aria-hidden />
                                            <span className="hidden sm:inline">{t('caja:ventas.create.agregar_servicio')}</span>
                                        </Button>
                                    </div>
                                    {puedeCrearServicio ? (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-8 w-full justify-center gap-1.5 border-dashed"
                                            disabled={!puede_vender}
                                            onClick={() => setServicioRapidoOpen(true)}
                                        >
                                            <Stethoscope className="size-3.5" aria-hidden />
                                            {t('caja:ventas.create.rapido_servicio_cta')}
                                        </Button>
                                    ) : null}
                                </TabsContent>
                            </Tabs>
                        </PosPanel>
                        {/* Carrito de ítems — debajo del catálogo */}
                        <PosPanel
                            compact
                            title={t('caja:ventas.create.card_carrito')}
                            icon={ShoppingBag}
                            badge={
                                cart.length > 0 ? (
                                    <Badge variant="secondary" className="h-5 px-1.5 text-[10px] tabular-nums">
                                        {cart.length}
                                    </Badge>
                                ) : null
                            }
                        >
                            {cart.length === 0 ? (
                                <div className="flex flex-col items-center justify-center gap-2 rounded-md border border-dashed border-border/60 bg-muted/5 px-4 py-6 text-center">
                                    <ShoppingBag className="size-7 text-muted-foreground/40" strokeWidth={1.5} aria-hidden />
                                    <p className="text-xs font-medium text-foreground">{t('caja:ventas.create.carrito_vacio')}</p>
                                    <p className="text-[11px] text-muted-foreground">{t('caja:ventas.create.carrito_vacio_hint')}</p>
                                </div>
                            ) : (
                                <ul className="divide-y divide-border/40 rounded-md border border-border/50">
                                    {cart.map((line, cartIndex) => {
                                        const lista = Number(line.precio_venta ?? 0);
                                        const lineTotalOriginal = lineTotalLinea(
                                            lista,
                                            line.cantidad,
                                            igvPct,
                                            precioIncluyeIgv,
                                        );
                                        const lineTotal = lineTotalLinea(
                                            lista,
                                            line.cantidad,
                                            igvPct,
                                            precioIncluyeIgv,
                                            line.descuento_pct,
                                        );
                                        const previewLine = promoPreview?.lineas?.[cartIndex];
                                        const hasLinePromo = Boolean(previewLine?.promotion_id);
                                        const displayTotal =
                                            previewLine
                                                ? lineTotalFromSubtotal(
                                                      Number(previewLine.subtotal),
                                                      igvPct,
                                                      precioIncluyeIgv,
                                                  )
                                                : lineTotal;
                                        const excedeStock =
                                            !line.omitir_stock &&
                                            line.cantidad > line.stock_disponible + 0.0001;

                                        return (
                                            <li
                                                key={line.key}
                                                className={cn(
                                                    'px-2 py-2.5',
                                                    excedeStock && 'bg-destructive/5',
                                                )}
                                            >
                                                <div className="flex items-start justify-between gap-2">
                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex flex-wrap items-center gap-1.5">
                                                            <p className="text-xs font-medium leading-snug">{line.nombre}</p>
                                                            {line.descuento_pct > 0 ? (
                                                                <Badge
                                                                    variant="secondary"
                                                                    className="h-4 bg-amber-100 px-1 text-[9px] font-medium text-amber-800 dark:bg-amber-950/50 dark:text-amber-300"
                                                                >
                                                                    −{line.descuento_pct}%
                                                                </Badge>
                                                            ) : null}
                                                            {hasLinePromo ? (
                                                                <Badge
                                                                    variant="secondary"
                                                                    className="h-4 px-1 text-[9px] font-medium uppercase tracking-wide"
                                                                >
                                                                    {t('caja:ventas.create.promotion_line_badge')}
                                                                </Badge>
                                                            ) : null}
                                                        </div>
                                                        {line.producto_id === null ? (
                                                            <Input
                                                                type="number"
                                                                inputMode="decimal"
                                                                min={0}
                                                                step={0.01}
                                                                className="mt-1 h-7 w-20 text-right text-[10px] tabular-nums"
                                                                value={lista}
                                                                onChange={(e) =>
                                                                    setPrecioListaLinea(line.key, e.target.value)
                                                                }
                                                                disabled={!puede_vender}
                                                                aria-label={t('caja:ventas.create.col_precio_unit')}
                                                            />
                                                        ) : (
                                                            <p
                                                                className={cn(
                                                                    'mt-0.5 text-[10px] tabular-nums',
                                                                    excedeStock ? 'text-destructive' : 'text-muted-foreground',
                                                                )}
                                                            >
                                                                {formatMoney(lista)} / {line.unidad}
                                                            </p>
                                                        )}
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-7 shrink-0 text-muted-foreground hover:text-destructive"
                                                        onClick={() => removeLine(line.key)}
                                                        disabled={!puede_vender}
                                                    >
                                                        <Trash2 className="size-3.5" aria-hidden />
                                                    </Button>
                                                </div>
                                                <div className="mt-2 flex items-center justify-between gap-2">
                                                    <div className="flex items-center gap-2">
                                                        <div className="flex items-center gap-0.5">
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="icon"
                                                                className="size-7 shrink-0"
                                                                onClick={() => setCantidad(line.key, line.cantidad - 1)}
                                                                disabled={!puede_vender}
                                                            >
                                                                <Minus className="size-3" aria-hidden />
                                                            </Button>
                                                            <Input
                                                                className="h-7 w-12 border-0 bg-transparent px-0 text-center text-xs tabular-nums shadow-none focus-visible:ring-0"
                                                                value={String(line.cantidad)}
                                                                onChange={(e) => {
                                                                    const v = Number(e.target.value.replace(',', '.'));
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
                                                                className="size-7 shrink-0"
                                                                onClick={() => setCantidad(line.key, line.cantidad + 1)}
                                                                disabled={
                                                                    !puede_vender ||
                                                                    (!line.omitir_stock &&
                                                                        line.cantidad >= line.stock_disponible - 0.0001)
                                                                }
                                                            >
                                                                <Plus className="size-3" aria-hidden />
                                                            </Button>
                                                        </div>
                                                        <div className="flex items-center gap-1">
                                                            <span className="text-[10px] text-muted-foreground">
                                                                {t('caja:ventas.create.descuento_linea_corto')}
                                                            </span>
                                                            <div className="relative">
                                                                <Input
                                                                    type="number"
                                                                    inputMode="decimal"
                                                                    min={0}
                                                                    max={100}
                                                                    step={0.01}
                                                                    className="h-7 w-19 pr-6 text-right text-xs tabular-nums"
                                                                    value={line.descuento_pct || ''}
                                                                    placeholder="0"
                                                                    onChange={(e) =>
                                                                        setDescuentoLinea(line.key, e.target.value)
                                                                    }
                                                                    disabled={!puede_vender}
                                                                    aria-label={t('caja:ventas.create.descuento_linea')}
                                                                    title={t('caja:ventas.create.descuento_linea')}
                                                                />
                                                                <span className="pointer-events-none absolute top-1/2 right-2 -translate-y-1/2 text-[10px] text-muted-foreground">
                                                                    %
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div className="flex flex-col items-end gap-0.5">
                                                        {displayTotal < lineTotalOriginal - 0.0001 ? (
                                                            <span className="text-[10px] text-muted-foreground line-through tabular-nums">
                                                                {formatMoney(lineTotalOriginal)}
                                                            </span>
                                                        ) : null}
                                                        <span
                                                            className={cn(
                                                                'text-sm font-semibold tabular-nums',
                                                                displayTotal < lineTotalOriginal - 0.0001 &&
                                                                    'text-emerald-700 dark:text-emerald-400',
                                                            )}
                                                        >
                                                            {formatMoney(displayTotal)}
                                                        </span>
                                                    </div>
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                            {form.errors.lineas ? (
                                <p className="text-[11px] text-destructive">{form.errors.lineas}</p>
                            ) : null}
                        </PosPanel>
                    </div>

                    {/* Columna derecha: solo COBRO — sticky, siempre visible */}
                    <div className="lg:sticky lg:top-3">

                        <PosPanel compact title={t('caja:ventas.create.card_pago')} icon={CreditCard}>
                            <div className="space-y-2.5">
                                <div className="space-y-1">
                                    <Label className="text-[11px] text-muted-foreground">{t('caja:ventas.create.metodo_pago')}</Label>
                                    <div className="grid grid-cols-5 gap-1">
                                        {paymentMethods.map(({ value, label, icon: PMIcon }) => (
                                            <button
                                                key={value}
                                                type="button"
                                                disabled={!puede_vender}
                                                onClick={() => form.setData('metodo_pago', value)}
                                                className={cn(
                                                    'flex flex-col items-center gap-0.5 rounded-md border px-0.5 py-1.5 text-center transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                                    puede_vender ? 'cursor-pointer' : 'cursor-not-allowed opacity-50',
                                                    form.data.metodo_pago === value
                                                        ? 'border-primary bg-primary/10 text-primary shadow-sm'
                                                        : 'border-border/50 bg-muted/20 text-muted-foreground hover:border-primary/30 hover:text-foreground',
                                                )}
                                            >
                                                <PMIcon className="size-3.5" aria-hidden />
                                                <span className="w-full truncate text-[9px] font-medium leading-tight">{label}</span>
                                            </button>
                                        ))}
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-2">
                                    <div className="min-w-0 space-y-1">
                                        <Label htmlFor="promotion_code" className="text-[11px] text-muted-foreground">
                                            {t('caja:ventas.create.promotion_code')}
                                        </Label>
                                        <Input
                                            id="promotion_code"
                                            className="h-8 font-mono text-xs uppercase"
                                            placeholder={t('caja:ventas.create.promotion_code_ph')}
                                            value={form.data.promotion_code}
                                            onChange={(e) => form.setData('promotion_code', e.target.value.toUpperCase())}
                                            disabled={!puede_vender}
                                        />
                                    </div>
                                    {!esEfectivo ? (
                                        <div className="min-w-0 space-y-1">
                                            <Label htmlFor="notas" className="text-[11px] text-muted-foreground">
                                                {t('caja:ventas.create.notas')}
                                            </Label>
                                            <Input
                                                id="notas"
                                                className="h-8 text-sm"
                                                value={form.data.notas}
                                                onChange={(e) => form.setData('notas', e.target.value)}
                                                disabled={!puede_vender}
                                                placeholder="…"
                                            />
                                        </div>
                                    ) : null}
                                </div>

                                {/* ── Monto recibido (efectivo) ── */}
                                {esEfectivo ? (() => {
                                    const montoActual = Number(String(form.data.monto_recibido).replace(',', '.')) || 0;
                                    const faltaMonto = cart.length > 0 && montoActual < totales.total - 0.0001;
                                    const billetes = [10, 20, 50, 100, 200];

                                    return (
                                        <div
                                            className={cn(
                                                'rounded-lg border p-2.5 transition-all duration-300',
                                                faltaMonto
                                                    ? 'border-emerald-400 bg-emerald-50 shadow-sm shadow-emerald-200/60 dark:border-emerald-500/60 dark:bg-emerald-950/30 dark:shadow-emerald-900/30'
                                                    : 'border-border/50 bg-muted/10',
                                            )}
                                        >
                                            <div className="mb-1.5 flex items-center justify-between gap-2">
                                                <Label
                                                    htmlFor="monto_recibido"
                                                    className={cn(
                                                        'text-[11px] font-semibold transition-colors',
                                                        faltaMonto ? 'text-emerald-700 dark:text-emerald-400' : 'text-muted-foreground',
                                                    )}
                                                >
                                                    {faltaMonto ? '⚠ Ingresa el monto recibido' : t('caja:ventas.create.monto_recibido')}
                                                </Label>
                                                {cart.length > 0 ? (
                                                    <button
                                                        type="button"
                                                        disabled={!puede_vender}
                                                        onClick={() => form.setData('monto_recibido', String(totales.total))}
                                                        className="cursor-pointer rounded-md bg-primary px-2 py-0.5 text-[10px] font-bold text-primary-foreground transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                                                    >
                                                        Exacto
                                                    </button>
                                                ) : null}
                                            </div>

                                            <Input
                                                id="monto_recibido"
                                                className={cn(
                                                    'h-9 text-sm font-semibold tabular-nums transition-all',
                                                    faltaMonto
                                                        ? 'border-emerald-400 bg-white ring-2 ring-emerald-300/60 focus-visible:ring-emerald-400 dark:bg-emerald-950/40 dark:ring-emerald-500/40'
                                                        : '',
                                                )}
                                                inputMode="decimal"
                                                placeholder={formatMoney(totales.total)}
                                                value={form.data.monto_recibido}
                                                onChange={(e) => form.setData('monto_recibido', e.target.value)}
                                                disabled={!puede_vender}
                                            />

                                            {/* Billetes rápidos */}
                                            {cart.length > 0 ? (
                                                <div className="mt-2 flex flex-wrap gap-1">
                                                    {billetes.map((b) => {
                                                        const suficiente = b >= totales.total - 0.0001;
                                                        return (
                                                            <button
                                                                key={b}
                                                                type="button"
                                                                disabled={!puede_vender}
                                                                onClick={() => form.setData('monto_recibido', String(b))}
                                                                className={cn(
                                                                    'flex-1 cursor-pointer rounded-md border px-1.5 py-1 text-center text-[10px] font-semibold tabular-nums transition-all disabled:cursor-not-allowed disabled:opacity-50',
                                                                    suficiente
                                                                        ? 'border-emerald-400/60 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-400'
                                                                        : 'border-border/50 bg-background text-muted-foreground hover:bg-muted/40',
                                                                )}
                                                            >
                                                                S/{b}
                                                            </button>
                                                        );
                                                    })}
                                                </div>
                                            ) : null}

                                            {form.errors.monto_recibido ? (
                                                <p className="mt-1 text-[11px] text-destructive">{form.errors.monto_recibido}</p>
                                            ) : null}
                                        </div>
                                    );
                                })() : null}

                                <div
                                    className={cn(
                                        'space-y-1 rounded-lg border border-primary/15 bg-linear-to-br from-primary/5 to-transparent p-2.5',
                                        cart.length === 0 && 'opacity-60',
                                    )}
                                >
                                    <div className="flex justify-between gap-2 text-[11px]">
                                        <span className="text-muted-foreground">{t('caja:ventas.create.res_subtotal')}</span>
                                        <span className="tabular-nums">{formatMoney(totales.subtotal)}</span>
                                    </div>
                                    <div className="flex justify-between gap-2 text-[11px]">
                                        <span className="text-muted-foreground">
                                            {t('caja:ventas.create.res_igv', { pct: clinica.igv_porcentaje })}
                                        </span>
                                        <span className="tabular-nums">{formatMoney(totales.igv)}</span>
                                    </div>
                                    {'discount' in totales && totales.discount > 0 ? (
                                        <div className="flex justify-between gap-2 text-[11px] text-emerald-700 dark:text-emerald-400">
                                            <span>{t('caja:ventas.create.res_descuento')}</span>
                                            <span className="tabular-nums">− {formatMoney(totales.discount)}</span>
                                        </div>
                                    ) : null}
                                    {promoPreview?.promotion_name ? (
                                        <p className="truncate text-[10px] text-emerald-700 dark:text-emerald-400">
                                            {t('caja:ventas.create.promotion_applied', {
                                                name: promoPreview.promotion_name,
                                                amount: formatMoney(Number(promoPreview.discount_amount)),
                                            })}
                                        </p>
                                    ) : null}
                                    <div className="flex items-baseline justify-between gap-2 border-t border-primary/15 pt-1.5">
                                        <span className="text-xs font-semibold">{t('caja:ventas.create.res_total')}</span>
                                        <span className="text-lg font-bold tabular-nums text-primary">
                                            {formatMoney(totales.total)}
                                        </span>
                                    </div>
                                    {esEfectivo && form.data.monto_recibido ? (
                                        <div className="flex justify-between gap-2 rounded-md bg-emerald-500/10 px-2 py-1 text-[11px] text-emerald-700 dark:text-emerald-400">
                                            <span>{t('caja:ventas.create.res_vuelto')}</span>
                                            <span className="font-semibold tabular-nums">
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

                                {!esEfectivo ? (
                                    <Textarea
                                        id="notas-full"
                                        rows={2}
                                        className="min-h-14 resize-none text-sm"
                                        value={form.data.notas}
                                        onChange={(e) => form.setData('notas', e.target.value)}
                                        disabled={!puede_vender}
                                        placeholder={t('caja:ventas.create.notas')}
                                    />
                                ) : null}

                                {form.errors.caja_sesion_id ? (
                                    <p className="text-[11px] text-destructive">{form.errors.caja_sesion_id}</p>
                                ) : null}

                                {erroresFormulario.length > 0 ? (
                                    <Alert variant="destructive" className="py-2">
                                        <AlertTitle className="text-xs">{t('caja:ventas.create.error_guardar_title')}</AlertTitle>
                                        <AlertDescription>
                                            <ul className="list-inside list-disc space-y-0.5 text-[11px]">
                                                {erroresFormulario.map((msg) => (
                                                    <li key={msg}>{msg}</li>
                                                ))}
                                            </ul>
                                        </AlertDescription>
                                    </Alert>
                                ) : null}

                                <Button
                                    type="button"
                                    className="h-10 w-full gap-2 text-sm font-semibold shadow-md shadow-primary/15"
                                    disabled={!puedeConfirmar || form.processing}
                                    onClick={submit}
                                >
                                    {form.processing ? (
                                        <>
                                            <Loader2 className="size-4 animate-spin" aria-hidden />
                                            {t('caja:ventas.create.guardando')}
                                        </>
                                    ) : (
                                        <>
                                            <ShoppingCart className="size-4" aria-hidden />
                                            {t('caja:ventas.create.confirmar')} · {formatMoney(totales.total)}
                                        </>
                                    )}
                                </Button>
                            </div>
                        </PosPanel>
                    </div>
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

            <ProductoRapidoDialog
                open={productoRapidoOpen}
                onOpenChange={setProductoRapidoOpen}
                initialNombre={qProducto}
                unidadOptions={unidadOpciones}
                sedeNombre={mi_sesion?.sede_nombre ?? null}
                onCreated={(p) => addProduct(p)}
            />

            <ServicioRapidoDialog
                open={servicioRapidoOpen}
                onOpenChange={setServicioRapidoOpen}
                initialNombre={servicioConcepto || qServicio}
                initialPrecio={servicioPrecio}
                onCreated={(s) => addServicioFromTarifa(s)}
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
