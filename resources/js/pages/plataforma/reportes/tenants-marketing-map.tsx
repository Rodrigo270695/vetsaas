import L from 'leaflet';
import { useEffect, useMemo } from 'react';
import { MapContainer, Marker, Popup, TileLayer, useMap } from 'react-leaflet';
import 'leaflet/dist/leaflet.css';
import { VETSAAS_DEFAULT_LOGO } from '@/lib/brand';
import { cn } from '@/lib/utils';

export type TenantMapMarker = {
    tenant_id: string;
    slug: string;
    label: string;
    segment: 'paid' | 'free';
    lat: number;
    lng: number;
    source: 'gps' | 'departamento' | null;
    departamento: string | null;
    logo_url: string;
    has_custom_logo: boolean;
};

type Props = {
    markers: TenantMapMarker[];
    className?: string;
    /** Altura del mapa (Tailwind). Default embebido. */
    mapClassName?: string;
    title?: string;
    description?: string;
    emptyLabel?: string;
    /** Oculta el header interno (útil si el padre ya muestra título). */
    hideChrome?: boolean;
};

function FitBounds({ markers }: { markers: TenantMapMarker[] }) {
    const map = useMap();

    useEffect(() => {
        if (markers.length === 0) {
            map.setView([-9.19, -75.0152], 5);
            return;
        }
        if (markers.length === 1) {
            map.setView([markers[0].lat, markers[0].lng], 11);
            return;
        }
        const bounds = L.latLngBounds(
            markers.map((m) => [m.lat, m.lng] as [number, number]),
        );
        map.fitBounds(bounds.pad(0.2));
    }, [map, markers]);

    return null;
}

function logoIcon(marker: TenantMapMarker): L.DivIcon {
    const ring = marker.segment === 'paid' ? '#10b981' : '#0ea5e9';
    const badgeBg = marker.segment === 'paid' ? '#059669' : '#0284c7';
    const src = marker.logo_url || VETSAAS_DEFAULT_LOGO;
    const badge = marker.segment === 'paid' ? 'PAGO' : 'FREE';

    return L.divIcon({
        className: 'vetsaas-tenant-marker',
        iconSize: [44, 52],
        iconAnchor: [22, 52],
        popupAnchor: [0, -44],
        html: `<div style="display:flex;flex-direction:column;align-items:center;filter:drop-shadow(0 2px 4px rgba(0,0,0,.25))">
            <div style="width:40px;height:40px;border-radius:9999px;overflow:hidden;background:#fff;box-shadow:0 0 0 3px ${ring}">
                <img src="${src.replace(/"/g, '&quot;')}" alt="" style="width:100%;height:100%;object-fit:cover" onerror="this.onerror=null;this.src='${VETSAAS_DEFAULT_LOGO}'" />
            </div>
            <span style="margin-top:2px;font-size:9px;font-weight:700;color:#fff;padding:1px 5px;border-radius:9999px;background:${badgeBg}">${badge}</span>
        </div>`,
    });
}

export function TenantsMarketingMap({
    markers,
    className,
    mapClassName,
    title = 'Mapa de clínicas',
    description = 'OpenStreetMap (Leaflet). Verde = pago, azul = free.',
    emptyLabel = 'Sin clínicas para mostrar con los filtros actuales.',
    hideChrome = false,
}: Props) {
    const safeMarkers = useMemo(
        () =>
            markers.filter(
                (m) =>
                    Number.isFinite(m.lat) &&
                    Number.isFinite(m.lng) &&
                    Math.abs(m.lat) <= 90 &&
                    Math.abs(m.lng) <= 180,
            ),
        [markers],
    );

    return (
        <div
            className={cn(
                'overflow-hidden rounded-xl border border-border/70 bg-card shadow-sm',
                className,
            )}
        >
            {!hideChrome ? (
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border/60 px-4 py-3">
                    <div>
                        <h3 className="text-sm font-semibold">{title}</h3>
                        <p className="text-xs text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    <div className="flex gap-2 text-[10px] font-semibold uppercase tracking-wide">
                        <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                            Pago
                        </span>
                        <span className="rounded-full bg-sky-100 px-2 py-0.5 text-sky-800 dark:bg-sky-950 dark:text-sky-200">
                            Free
                        </span>
                    </div>
                </div>
            ) : null}
            <div className={cn('relative z-0 h-[420px] w-full', mapClassName)}>
                {safeMarkers.length === 0 ? (
                    <div className="flex h-full items-center justify-center bg-muted/30 px-6 text-center text-sm text-muted-foreground">
                        {emptyLabel}
                    </div>
                ) : typeof window !== 'undefined' ? (
                    <MapContainer
                        key={`map-${safeMarkers.length}-${safeMarkers[0]?.tenant_id ?? 'x'}`}
                        center={[-9.19, -75.0152]}
                        zoom={5}
                        scrollWheelZoom
                        className="h-full w-full"
                    >
                        <TileLayer
                            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                        />
                        <FitBounds markers={safeMarkers} />
                        {safeMarkers.map((m) => (
                            <Marker
                                key={m.tenant_id}
                                position={[m.lat, m.lng]}
                                icon={logoIcon(m)}
                            >
                                <Popup>
                                    <div className="min-w-[160px] text-sm">
                                        <p className="font-semibold">{m.label}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {m.slug}
                                        </p>
                                        <p className="mt-1 text-xs">
                                            {m.segment === 'paid'
                                                ? 'De pago'
                                                : 'Free'}
                                            {m.departamento
                                                ? ` · ${m.departamento}`
                                                : ''}
                                        </p>
                                        <p className="text-[10px] text-muted-foreground">
                                            {m.source === 'gps'
                                                ? 'Ubicación GPS (consentimiento)'
                                                : 'Aprox. por departamento (sede)'}
                                        </p>
                                    </div>
                                </Popup>
                            </Marker>
                        ))}
                    </MapContainer>
                ) : null}
            </div>
        </div>
    );
}
