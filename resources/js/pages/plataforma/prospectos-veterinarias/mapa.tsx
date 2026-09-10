import { Head, Link, router } from '@inertiajs/react';
import L from 'leaflet';
import {
    Check,
    Loader2,
    MapPin,
    Navigation,
    Play,
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
    stats: {
        con_xy: number;
        sin_xy: number;
        en_ruta: number;
        km_aprox: number;
        minutos: number;
    };
};

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

function pinIcon(n: number, origin = false): L.DivIcon {
    const bg = origin ? '#059669' : '#0369a1';
    const label = origin ? 'Tú' : String(n);
    return L.divIcon({
        className: 'vetsaas-volante-pin',
        iconSize: [28, 28],
        iconAnchor: [14, 28],
        html: `<div style="width:28px;height:28px;border-radius:9999px;background:${bg};color:#fff;font:700 11px/28px sans-serif;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,.3)">${label}</div>`,
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
    stats,
}: Props) {
    const { can } = usePermission();
    const canCreate = can('plataforma-prospectos.create');
    const canUpdate = can('plataforma-prospectos.update');
    const [busy, setBusy] = useState<'import' | 'geo' | 'gps' | 'nav' | string | null>(null);

    const line = useMemo<[number, number][]>(() => {
        if (calle_polyline.length > 1) {
            return calle_polyline;
        }
        return [[origin.lat, origin.lng], ...ruta.map((s) => [s.lat, s.lng] as [number, number])];
    }, [calle_polyline, origin.lat, origin.lng, ruta]);

    const reload = (extra: Record<string, string | number> = {}) => {
        router.get(
            '/plataforma/prospectos-veterinarias/mapa',
            {
                departamento,
                max,
                origin_lat: origin.lat,
                origin_lng: origin.lng,
                ...extra,
            },
            { preserveScroll: true },
        );
    };

    const iniciarRuta = () => {
        const abrir = (url: string | null | undefined) => {
            if (url) {
                window.location.href = url;
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

    return (
        <>
            <Head title="Ruta de volantes" />
            <div className="flex flex-1 flex-col gap-3 p-4 sm:p-6">
                <PageHeader
                    title="Ruta de volantes — costa norte"
                    description="La línea sigue las calles. Iniciar ruta toma tu GPS y abre Google Maps en modo navegación (giros, recálculo, voz)."
                    stats={[
                        { label: 'Con XY', value: stats.con_xy, variant: 'success', icon: MapPin },
                        { label: 'Sin XY', value: stats.sin_xy, variant: 'warning', icon: Radar },
                        { label: 'Paradas hoy', value: stats.en_ruta, variant: 'primary', icon: RouteIcon },
                        {
                            label: stats.minutos > 0 ? `~${stats.minutos} min` : 'Km ruta',
                            value: stats.km_aprox,
                            variant: 'info',
                            icon: Navigation,
                        },
                    ]}
                    action={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/plataforma/prospectos-veterinarias">Lista</Link>
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                className="cursor-pointer gap-1.5 bg-emerald-600 text-white hover:bg-emerald-700"
                                disabled={busy === 'nav' || ruta.length === 0}
                                onClick={iniciarRuta}
                            >
                                {busy === 'nav' ? (
                                    <Loader2 className="size-3.5 animate-spin" />
                                ) : (
                                    <Play className="size-3.5 fill-current" />
                                )}
                                Iniciar ruta
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="cursor-pointer gap-1.5"
                                disabled={busy === 'gps'}
                                onClick={usarGps}
                            >
                                {busy === 'gps' ? <Loader2 className="size-3.5 animate-spin" /> : <Navigation className="size-3.5" />}
                                Estoy aquí
                            </Button>
                            {canCreate ? (
                                <>
                                    <Button
                                        type="button"
                                        size="sm"
                                        className="cursor-pointer gap-1.5"
                                        disabled={busy === 'import'}
                                        onClick={() => {
                                            setBusy('import');
                                            router.post(
                                                '/plataforma/prospectos-veterinarias/mapa/import',
                                                {},
                                                { preserveScroll: true, onFinish: () => setBusy(null) },
                                            );
                                        }}
                                    >
                                        {busy === 'import' ? <Loader2 className="size-3.5 animate-spin" /> : <Radar className="size-3.5" />}
                                        Traer XY norte
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="cursor-pointer"
                                        disabled={busy === 'geo'}
                                        onClick={() => {
                                            setBusy('geo');
                                            router.post(
                                                '/plataforma/prospectos-veterinarias/mapa/geocode',
                                                {},
                                                { preserveScroll: true, onFinish: () => setBusy(null) },
                                            );
                                        }}
                                    >
                                        Completar XY de la lista
                                    </Button>
                                </>
                            ) : null}
                            {maps_url ? (
                                <Button size="sm" className="cursor-pointer gap-1.5" asChild>
                                    <a href={maps_url} target="_blank" rel="noreferrer">
                                        Abrir ruta en Google Maps
                                    </a>
                                </Button>
                            ) : null}
                        </div>
                    }
                />

                <p className="text-xs text-muted-foreground">
                    {calles_ok
                        ? 'Trazado por calles (OSRM). Iniciar ruta abre Google Maps con voz y recálculo.'
                        : 'No se pudo trazar por calles ahora; se muestra línea directa. Igual podés iniciar navegación en Google Maps.'}
                    {places_configurado ? ' Places API activa.' : ''}
                </p>

                <div className="flex flex-wrap items-center gap-2">
                    <Select
                        value={departamento}
                        onValueChange={(v) => reload({ departamento: v })}
                    >
                        <SelectTrigger className="h-9 w-52 cursor-pointer">
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
                </div>

                <div className="grid gap-3 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <div className="overflow-hidden rounded-xl border border-border/70 bg-card">
                        <div className="relative z-0 h-[min(70vh,560px)] w-full">
                            {typeof window !== 'undefined' ? (
                                <MapContainer
                                    center={[origin.lat, origin.lng]}
                                    zoom={12}
                                    scrollWheelZoom
                                    className="h-full w-full"
                                >
                                    <TileLayer
                                        attribution='&copy; OpenStreetMap'
                                        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                                    />
                                    <Fit origin={origin} ruta={ruta} callePolyline={calle_polyline} />
                                    {line.length > 1 ? (
                                        <Polyline
                                            positions={line}
                                            pathOptions={{
                                                color: calles_ok ? '#0f766e' : '#0369a1',
                                                weight: calles_ok ? 5 : 3,
                                                opacity: 0.9,
                                            }}
                                        />
                                    ) : null}
                                    <Marker position={[origin.lat, origin.lng]} icon={pinIcon(0, true)}>
                                        <Popup>Punto de partida</Popup>
                                    </Marker>
                                    {ruta.map((s) => (
                                        <Marker key={s.id} position={[s.lat, s.lng]} icon={pinIcon(s.orden)}>
                                            <Popup>
                                                <p className="font-semibold">{s.orden}. {s.nombre}</p>
                                                <p className="text-xs">{s.direccion || s.distrito}</p>
                                            </Popup>
                                        </Marker>
                                    ))}
                                </MapContainer>
                            ) : null}
                        </div>
                    </div>

                    <div className="max-h-[min(70vh,560px)] overflow-y-auto rounded-xl border border-border/70 bg-card">
                        {ruta.length === 0 ? (
                            <p className="px-4 py-10 text-center text-sm text-muted-foreground">
                                Aún no hay pines. Pulsa «Traer XY norte» (tarda ~1 min) o «Completar XY» para geocodificar los de Lambayeque que ya están en la lista.
                            </p>
                        ) : (
                            <ol className="divide-y divide-border/60">
                                {ruta.map((s, idx) => (
                                    <li key={s.id} className="px-3 py-2.5">
                                        <div className="flex items-start gap-2">
                                            <span className="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-sky-700 text-[11px] font-bold text-white">
                                                {s.orden}
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium">{s.nombre}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {[s.distrito, s.direccion].filter(Boolean).join(' · ') || s.departamento}
                                                </p>
                                                {pasos[idx]?.textos?.length ? (
                                                    <ul className="mt-1 space-y-0.5 text-[11px] text-teal-800 dark:text-teal-200">
                                                        {pasos[idx].textos.slice(0, 4).map((t) => (
                                                            <li key={t}>→ {t}</li>
                                                        ))}
                                                    </ul>
                                                ) : s.indicacion ? (
                                                    <p className="mt-0.5 text-[11px] text-teal-800">→ {s.indicacion}</p>
                                                ) : null}
                                                <p className="text-[11px] text-muted-foreground">
                                                    +{s.km_desde_anterior} km
                                                    {s.telefono ? ` · ${s.telefono}` : ''}
                                                </p>
                                                <div className="mt-1 flex flex-wrap gap-2">
                                                    {s.nav_url ? (
                                                        <a
                                                            className="text-[11px] font-medium text-emerald-700 underline"
                                                            href={s.nav_url}
                                                        >
                                                            Ir a esta parada
                                                        </a>
                                                    ) : (
                                                        <a
                                                            className="text-[11px] text-sky-700 underline"
                                                            href={s.maps_url}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
                                                            Maps
                                                        </a>
                                                    )}
                                                    {canUpdate ? (
                                                        <button
                                                            type="button"
                                                            className={cn(
                                                                'inline-flex cursor-pointer items-center gap-0.5 text-[11px] text-emerald-700',
                                                                busy === s.id && 'opacity-50',
                                                            )}
                                                            onClick={() => {
                                                                setBusy(s.id);
                                                                router.post(
                                                                    `/plataforma/prospectos-veterinarias/${s.id}/volante`,
                                                                    {},
                                                                    { preserveScroll: true, onFinish: () => setBusy(null) },
                                                                );
                                                            }}
                                                        >
                                                            <Check className="size-3" />
                                                            Volante dejado
                                                        </button>
                                                    ) : null}
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </div>
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
