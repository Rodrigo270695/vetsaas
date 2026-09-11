import { Head, Link, router } from '@inertiajs/react';
import L from 'leaflet';
import {
    Ban,
    Check,
    Flag,
    History,
    List,
    Loader2,
    LocateFixed,
    MapPin,
    MoreHorizontal,
    Navigation,
    Play,
    Plus,
    Radar,
    Route as RouteIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { MapContainer, Marker, Polyline, Popup, TileLayer, useMap } from 'react-leaflet';
import 'leaflet/dist/leaflet.css';
import { PageHeader } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';

type Stop = {
    id: string;
    orden: number;
    nombre: string;
    telefono: string | null;
    direccion: string | null;
    departamento: string | null;
    distrito: string | null;
    lat: number;
    lng: number;
    km_desde_anterior: number;
    maps_url: string;
    nav_url?: string | null;
    indicacion?: string;
    visitado?: boolean;
};

type HistorialItem = {
    id: string;
    departamento: string;
    estado: string;
    paradas: number;
    visitadas: number;
    km: number | null;
    minutos: number | null;
    fecha: string | null;
};

type RutaActiva = {
    id: string;
    estado: string;
    departamento: string;
    solo_lectura: boolean;
};

type PasoLeg = {
    hasta: string;
    textos: string[];
};

type Props = {
    departamento: string;
    departamentos: string[];
    origin: { lat: number; lng: number; label: string };
    max: number;
    ruta: Stop[];
    calle_polyline: [number, number][];
    calles_ok: boolean;
    pasos: PasoLeg[];
    maps_url: string | null;
    maps_nav_url: string | null;
    places_configurado: boolean;
    ruta_activa: RutaActiva | null;
    historial: HistorialItem[];
    stats: {
        con_xy: number;
        sin_xy: number;
        en_ruta: number;
        visitadas: number;
        km_aprox: number;
        minutos: number;
    };
};

type PanelTab = 'mapa' | 'paradas' | 'historial';

function Fit({
    origin,
    ruta,
    callePolyline,
}: {
    origin: Props['origin'];
    ruta: Stop[];
    callePolyline: [number, number][];
}) {
    const map = useMap();

    useEffect(() => {
        const pts: [number, number][] =
            callePolyline.length > 2
                ? callePolyline
                : [
                      [origin.lat, origin.lng],
                      ...ruta.map((s) => [s.lat, s.lng] as [number, number]),
                  ];
        if (pts.length === 1) {
            map.setView(pts[0], 13);
            return;
        }
        map.fitBounds(L.latLngBounds(pts).pad(0.18));
    }, [map, origin.lat, origin.lng, ruta, callePolyline]);

    return null;
}

function pinIcon(n: number, origin = false, done = false): L.DivIcon {
    const bg = origin ? '#059669' : done ? '#0f766e' : '#0f172a';
    const label = origin ? 'Tú' : String(n);
    return L.divIcon({
        className: 'vetsaas-volante-pin',
        iconSize: [28, 28],
        iconAnchor: [14, 28],
        html: `<div style="width:28px;height:28px;border-radius:9999px;background:${bg};color:#fff;font:700 11px/28px sans-serif;text-align:center;box-shadow:0 2px 8px rgba(15,23,42,.28);border:2px solid #fff">${label}</div>`,
    });
}

function MapaVolantes({
    departamento,
    departamentos,
    origin,
    max,
    ruta,
    calle_polyline,
    calles_ok,
    pasos,
    maps_url,
    maps_nav_url,
    places_configurado,
    ruta_activa,
    historial = [],
    stats,
}: Props) {
    const { can } = usePermission();
    const canCreate = can('plataforma-prospectos.create');
    const canUpdate = can('plataforma-prospectos.update');
    const [busy, setBusy] = useState<'import' | 'geo' | 'gps' | 'nav' | 'gen' | string | null>(null);
    const [tab, setTab] = useState<PanelTab>('mapa');

    const line = useMemo<[number, number][]>(() => {
        if (calle_polyline.length > 1) {
            return calle_polyline;
        }
        return [[origin.lat, origin.lng], ...ruta.map((s) => [s.lat, s.lng] as [number, number])];
    }, [calle_polyline, origin.lat, origin.lng, ruta]);

    const abierta = ruta_activa?.estado === 'abierta';
    const soloLectura = ruta_activa?.solo_lectura === true;

    const estadoLabel = ruta_activa
        ? ruta_activa.estado === 'abierta'
            ? 'Ruta abierta'
            : `Ruta ${ruta_activa.estado}`
        : 'Vista previa';

    const reload = (extra: Record<string, string | number> = {}) => {
        router.get(
            '/plataforma/prospectos-veterinarias/mapa',
            {
                departamento,
                max,
                origin_lat: origin.lat,
                origin_lng: origin.lng,
                ...(ruta_activa && extra.ruta === undefined ? { ruta: ruta_activa.id } : {}),
                ...extra,
            },
            { preserveScroll: true },
        );
    };

    const generarHoy = () => {
        const post = (lat: number, lng: number) => {
            setBusy('gen');
            router.post(
                '/plataforma/prospectos-veterinarias/mapa/ruta',
                {
                    departamento,
                    origin_lat: lat,
                    origin_lng: lng,
                    max,
                },
                { onFinish: () => setBusy(null) },
            );
        };
        if (!navigator.geolocation) {
            post(origin.lat, origin.lng);
            return;
        }
        setBusy('gen');
        navigator.geolocation.getCurrentPosition(
            (pos) => post(pos.coords.latitude, pos.coords.longitude),
            () => post(origin.lat, origin.lng),
            { enableHighAccuracy: true, timeout: 12_000 },
        );
    };

    const iniciarRuta = () => {
        const abrir = (url: string | null | undefined) => {
            if (url) {
                window.open(url, '_blank', 'noopener,noreferrer');
            }
        };

        if (!navigator.geolocation) {
            abrir(maps_nav_url ?? maps_url);
            return;
        }

        setBusy('nav');
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                router.get(
                    '/plataforma/prospectos-veterinarias/mapa',
                    {
                        departamento,
                        max,
                        origin_lat: pos.coords.latitude,
                        origin_lng: pos.coords.longitude,
                    },
                    {
                        preserveScroll: true,
                        onSuccess: (page) => {
                            const url = (page.props as { maps_nav_url?: string | null }).maps_nav_url;
                            abrir(url ?? maps_nav_url ?? maps_url);
                        },
                        onFinish: () => setBusy(null),
                    },
                );
            },
            () => {
                setBusy(null);
                abrir(maps_nav_url ?? maps_url);
            },
            { enableHighAccuracy: true, timeout: 12_000 },
        );
    };

    const usarGps = () => {
        if (!navigator.geolocation) {
            return;
        }
        setBusy('gps');
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                setBusy(null);
                reload({
                    origin_lat: pos.coords.latitude,
                    origin_lng: pos.coords.longitude,
                });
            },
            () => setBusy(null),
            { enableHighAccuracy: true, timeout: 12_000 },
        );
    };

    const traerXy = () => {
        setBusy('import');
        router.post(
            '/plataforma/prospectos-veterinarias/mapa/import',
            { departamento },
            { preserveScroll: true, onFinish: () => setBusy(null) },
        );
    };

    const completarXy = () => {
        setBusy('geo');
        router.post(
            '/plataforma/prospectos-veterinarias/mapa/geocode',
            { departamento },
            { preserveScroll: true, onFinish: () => setBusy(null) },
        );
    };

    const mapPane = (
        <div className="relative z-0 h-[min(52vh,420px)] w-full sm:h-[min(58vh,480px)] lg:h-full lg:min-h-140">
            {typeof window !== 'undefined' ? (
                <MapContainer
                    center={[origin.lat, origin.lng]}
                    zoom={12}
                    scrollWheelZoom
                    className="h-full w-full"
                >
                    <TileLayer
                        attribution="&copy; OpenStreetMap"
                        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                    />
                    <Fit origin={origin} ruta={ruta} callePolyline={calle_polyline} />
                    {line.length > 1 ? (
                        <Polyline
                            positions={line}
                            pathOptions={{
                                color: calles_ok ? '#0f766e' : '#334155',
                                weight: calles_ok ? 5 : 3,
                                opacity: 0.88,
                            }}
                        />
                    ) : null}
                    <Marker position={[origin.lat, origin.lng]} icon={pinIcon(0, true)}>
                        <Popup>Punto de partida</Popup>
                    </Marker>
                    {ruta.map((s) => (
                        <Marker
                            key={s.id}
                            position={[s.lat, s.lng]}
                            icon={pinIcon(s.orden, false, s.visitado === true)}
                        >
                            <Popup>
                                <p className="font-semibold">
                                    {s.orden}. {s.nombre}
                                </p>
                                <p className="text-xs">{s.direccion || s.distrito}</p>
                            </Popup>
                        </Marker>
                    ))}
                </MapContainer>
            ) : null}
        </div>
    );

    const paradasPane = (
        <div className="flex min-h-0 flex-1 flex-col">
            {ruta.length === 0 ? (
                <p className="px-4 py-12 text-center text-sm text-muted-foreground">
                    No hay paradas. Traé coordenadas o generá una ruta donde haya clínicas pendientes.
                </p>
            ) : (
                <ol className="divide-y divide-border/50">
                    {ruta.map((s, idx) => (
                        <li key={s.id} className={cn('px-3 py-3 sm:px-4', s.visitado && 'bg-emerald-50/50 dark:bg-emerald-950/20')}>
                            <div className="flex items-start gap-3">
                                <span
                                    className={cn(
                                        'mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full text-[11px] font-bold text-white',
                                        s.visitado ? 'bg-teal-700' : 'bg-slate-800',
                                    )}
                                >
                                    {s.visitado ? <Check className="size-3.5" /> : s.orden}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold leading-tight">{s.nombre}</p>
                                    <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                        {[s.distrito, s.direccion].filter(Boolean).join(' · ') || s.departamento}
                                    </p>
                                    {pasos[idx]?.textos?.[0] ? (
                                        <p className="mt-1 line-clamp-2 text-[11px] text-muted-foreground">
                                            {pasos[idx].textos[0]}
                                        </p>
                                    ) : s.indicacion ? (
                                        <p className="mt-1 line-clamp-2 text-[11px] text-muted-foreground">{s.indicacion}</p>
                                    ) : null}
                                    <p className="mt-1 text-[11px] tabular-nums text-muted-foreground">
                                        +{s.km_desde_anterior} km
                                        {s.telefono ? ` · ${s.telefono}` : ''}
                                    </p>
                                    <div className="mt-2 flex flex-wrap gap-1.5">
                                        {(s.nav_url || s.maps_url) && (
                                            <Button size="sm" variant="outline" className="h-8 px-2.5 text-xs" asChild>
                                                <a href={s.nav_url || s.maps_url} target="_blank" rel="noreferrer">
                                                    Ir
                                                </a>
                                            </Button>
                                        )}
                                        {canUpdate && !soloLectura ? (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant={s.visitado ? 'ghost' : 'default'}
                                                className="h-8 px-2.5 text-xs"
                                                disabled={busy === s.id}
                                                onClick={() => {
                                                    setBusy(s.id);
                                                    router.post(
                                                        `/plataforma/prospectos-veterinarias/${s.id}/volante`,
                                                        {},
                                                        { preserveScroll: true, onFinish: () => setBusy(null) },
                                                    );
                                                }}
                                            >
                                                {busy === s.id ? (
                                                    <Loader2 className="size-3.5 animate-spin" />
                                                ) : (
                                                    <Check className="size-3.5" />
                                                )}
                                                {s.visitado ? 'Quitar' : 'Volante'}
                                            </Button>
                                        ) : s.visitado ? (
                                            <span className="inline-flex h-8 items-center text-xs font-medium text-teal-700">
                                                Entregado
                                            </span>
                                        ) : null}
                                    </div>
                                </div>
                            </div>
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );

    const historialPane = (
        <div>
            {historial.length === 0 ? (
                <p className="px-4 py-12 text-center text-sm text-muted-foreground">Todavía no hay rutas guardadas.</p>
            ) : (
                <ul className="divide-y divide-border/50">
                    {historial.map((h) => (
                        <li key={h.id}>
                            <button
                                type="button"
                                className={cn(
                                    'w-full cursor-pointer px-3 py-3 text-left hover:bg-muted/50 sm:px-4',
                                    ruta_activa?.id === h.id && 'bg-muted/70',
                                )}
                                onClick={() => {
                                    reload({ ruta: h.id });
                                    setTab('paradas');
                                }}
                            >
                                <p className="text-sm font-medium">
                                    {h.fecha} · {h.departamento}
                                </p>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    {h.visitadas}/{h.paradas} volantes
                                    {h.km != null ? ` · ${h.km} km` : ''}
                                    {' · '}
                                    {h.estado}
                                </p>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );

    const renderMenuDatos = (compact: boolean) => (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className={cn('shrink-0 px-2.5', compact ? 'h-11' : 'h-9')}
                >
                    <MoreHorizontal className="size-4" />
                    <span className="hidden sm:inline">Más</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuItem className="cursor-pointer" disabled={busy === 'gps'} onClick={usarGps}>
                    {busy === 'gps' ? <Loader2 className="size-4 animate-spin" /> : <LocateFixed className="size-4" />}
                    Estoy aquí (GPS)
                </DropdownMenuItem>
                {canCreate ? (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem className="cursor-pointer" disabled={busy === 'import'} onClick={traerXy}>
                            {busy === 'import' ? <Loader2 className="size-4 animate-spin" /> : <Radar className="size-4" />}
                            Traer XY {departamento === 'todos' ? 'norte' : departamento}
                        </DropdownMenuItem>
                        <DropdownMenuItem className="cursor-pointer" disabled={busy === 'geo'} onClick={completarXy}>
                            Completar XY de la lista
                        </DropdownMenuItem>
                    </>
                ) : null}
                {soloLectura ? (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem className="cursor-pointer" onClick={() => reload({ ruta: '' })}>
                            Volver a la ruta de hoy
                        </DropdownMenuItem>
                    </>
                ) : null}
                {canUpdate && abierta && ruta_activa ? (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            className="cursor-pointer text-destructive"
                            onClick={() =>
                                router.post(`/plataforma/prospectos-veterinarias/mapa/ruta/${ruta_activa.id}/cancelar`)
                            }
                        >
                            <Ban className="size-4" />
                            Cancelar ruta
                        </DropdownMenuItem>
                    </>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );

    const renderAccionesPrincipales = (compact: boolean) => (
        <>
            {canUpdate && !abierta && !soloLectura ? (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className={cn('min-w-0', compact ? 'h-11 flex-1' : 'h-9 sm:flex-none')}
                    disabled={busy === 'gen' || ruta.length === 0}
                    onClick={generarHoy}
                >
                    {busy === 'gen' ? <Loader2 className="size-4 animate-spin" /> : <Plus className="size-4" />}
                    {compact ? 'Generar' : 'Generar hoy'}
                </Button>
            ) : null}
            {canUpdate && abierta && ruta_activa ? (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className={cn('min-w-0', compact ? 'h-11 flex-1' : 'h-9 sm:flex-none')}
                    onClick={() =>
                        router.post(`/plataforma/prospectos-veterinarias/mapa/ruta/${ruta_activa.id}/completar`)
                    }
                >
                    <Flag className="size-4" />
                    Completar
                </Button>
            ) : null}
            {soloLectura ? (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className={cn(compact ? 'h-11' : 'h-9')}
                    onClick={() => reload({ ruta: '' })}
                >
                    Hoy
                </Button>
            ) : null}
            <Button
                type="button"
                size="sm"
                className={cn(
                    'min-w-0 bg-emerald-600 text-white hover:bg-emerald-700',
                    compact ? 'h-11 flex-[1.35]' : 'h-9 sm:flex-none',
                )}
                disabled={busy === 'nav' || ruta.length === 0}
                onClick={iniciarRuta}
            >
                {busy === 'nav' ? <Loader2 className="size-4 animate-spin" /> : <Play className="size-4 fill-current" />}
                Iniciar
            </Button>
        </>
    );

    return (
        <>
            <Head title="Ruta de volantes" />
            <div className="flex flex-1 flex-col gap-4 p-4 pb-24 sm:p-6 lg:pb-6">
                <PageHeader
                    title="Ruta de volantes"
                    description="Recorrido por calles con OpenStreetMap. Google Maps no se usa (Workspace lo bloquea)."
                    stats={[
                        { label: 'Con XY', value: stats.con_xy, variant: 'success', icon: MapPin },
                        { label: 'Sin XY', value: stats.sin_xy, variant: 'warning', icon: Radar },
                        { label: 'Paradas', value: stats.en_ruta, variant: 'primary', icon: RouteIcon },
                        { label: 'Volantes', value: stats.visitadas ?? 0, variant: 'success', icon: Check },
                        {
                            label: stats.minutos > 0 ? `~${stats.minutos} min` : 'Km',
                            value: stats.km_aprox,
                            variant: 'info',
                            icon: Navigation,
                        },
                    ]}
                    action={
                        <Button variant="outline" size="sm" className="hidden h-9 sm:inline-flex" asChild>
                            <Link href="/plataforma/prospectos-veterinarias">
                                <List className="size-4" />
                                Lista
                            </Link>
                        </Button>
                    }
                />

                <div className="rounded-xl border border-border/70 bg-card p-3 shadow-sm sm:p-4">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div className="flex min-w-0 flex-wrap items-center gap-2">
                            <Select
                                value={departamento}
                                onValueChange={(v) => reload({ departamento: v, ruta: '' })}
                            >
                                <SelectTrigger className="h-9 w-full min-w-0 cursor-pointer sm:w-52">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {departamentos.map((d) => (
                                        <SelectItem key={d} value={d}>
                                            {d === 'todos' ? 'Todo el norte' : d}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <span
                                className={cn(
                                    'inline-flex h-9 items-center rounded-md px-2.5 text-xs font-medium',
                                    abierta
                                        ? 'bg-emerald-500/10 text-emerald-800 dark:text-emerald-300'
                                        : soloLectura
                                          ? 'bg-muted text-muted-foreground'
                                          : 'bg-sky-500/10 text-sky-800 dark:text-sky-300',
                                )}
                            >
                                {estadoLabel}
                            </span>
                            <p className="hidden text-xs text-muted-foreground xl:block">
                                {calles_ok
                                    ? 'Trazado por calles (OSRM).'
                                    : 'Sin trazo de calles ahora; igual podés iniciar en OSM.'}
                                {places_configurado ? ' Places activo.' : ''}
                            </p>
                        </div>
                        <div className="hidden items-center gap-2 lg:flex">
                            {renderMenuDatos(false)}
                            {renderAccionesPrincipales(false)}
                        </div>
                    </div>
                </div>

                <div className="lg:hidden">
                    <Tabs value={tab} onValueChange={(v) => setTab(v as PanelTab)}>
                        <TabsList className="grid h-10 w-full grid-cols-3">
                            <TabsTrigger value="mapa" className="cursor-pointer text-xs">
                                Mapa
                            </TabsTrigger>
                            <TabsTrigger value="paradas" className="cursor-pointer text-xs">
                                Paradas ({ruta.length})
                            </TabsTrigger>
                            <TabsTrigger value="historial" className="cursor-pointer text-xs">
                                Historial
                            </TabsTrigger>
                        </TabsList>
                        <TabsContent value="mapa" className="mt-3 overflow-hidden rounded-xl border border-border/70">
                            {mapPane}
                        </TabsContent>
                        <TabsContent
                            value="paradas"
                            className="mt-3 max-h-[min(62vh,520px)] overflow-y-auto rounded-xl border border-border/70 bg-card"
                        >
                            {paradasPane}
                        </TabsContent>
                        <TabsContent
                            value="historial"
                            className="mt-3 max-h-[min(62vh,520px)] overflow-y-auto rounded-xl border border-border/70 bg-card"
                        >
                            {historialPane}
                        </TabsContent>
                    </Tabs>
                </div>

                <div className="hidden min-h-0 flex-1 gap-4 lg:grid lg:grid-cols-[minmax(0,1fr)_24rem]">
                    <div className="overflow-hidden rounded-xl border border-border/70 bg-card shadow-sm">
                        {mapPane}
                    </div>
                    <div className="flex min-h-0 flex-col overflow-hidden rounded-xl border border-border/70 bg-card shadow-sm">
                        <Tabs defaultValue="paradas" className="flex min-h-0 flex-1 flex-col gap-0">
                            <div className="border-b border-border/60 px-3 py-2">
                                <TabsList className="h-9 w-full">
                                    <TabsTrigger value="paradas" className="flex-1 cursor-pointer text-xs">
                                        <RouteIcon className="mr-1 size-3.5" />
                                        Paradas
                                    </TabsTrigger>
                                    <TabsTrigger value="historial" className="flex-1 cursor-pointer text-xs">
                                        <History className="mr-1 size-3.5" />
                                        Historial
                                    </TabsTrigger>
                                </TabsList>
                            </div>
                            <TabsContent value="paradas" className="mt-0 min-h-0 flex-1 overflow-y-auto">
                                {paradasPane}
                            </TabsContent>
                            <TabsContent value="historial" className="mt-0 min-h-0 flex-1 overflow-y-auto">
                                {historialPane}
                            </TabsContent>
                        </Tabs>
                    </div>
                </div>
            </div>

            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-border/70 bg-background/95 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur lg:hidden">
                <div className="mx-auto flex max-w-lg items-center gap-2">
                    <Button variant="outline" size="sm" className="h-11 shrink-0 px-3" asChild>
                        <Link href="/plataforma/prospectos-veterinarias">
                            <List className="size-4" />
                        </Link>
                    </Button>
                    {renderMenuDatos(true)}
                    {renderAccionesPrincipales(true)}
                </div>
            </div>
        </>
    );
}

MapaVolantes.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma', href: '/plataforma/tenants' },
            { title: 'Prospectos', href: '/plataforma/prospectos-veterinarias' },
            { title: 'Ruta volantes', href: '/plataforma/prospectos-veterinarias/mapa' },
        ]}
    >
        {page}
    </AppLayout>
);

export default MapaVolantes;
