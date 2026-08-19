import { usePage } from '@inertiajs/react';
import { ClipboardList, GripHorizontal, History, Loader2, X } from 'lucide-react';
import { useEffect, useRef, useState, type PointerEvent as ReactPointerEvent } from 'react';
import { createPortal } from 'react-dom';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { PacienteTimelineRow } from '@/pages/clinica/pacientes/components/paciente-timeline-row';
import type { TimelineItem } from '@/pages/clinica/pacientes/show';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    pacienteId: string | null;
    pacienteNombre?: string | null;
};

type TimelineResponse = {
    paciente: { id: string; nombre: string; propietario: string | null };
    timeline: TimelineItem[];
    permisos: { consultas_ver: boolean; vacunas_ver: boolean };
};

type WindowGeom = { x: number; y: number; w: number; h: number };

const WINDOW_STORAGE_KEY = 'vetsaas.consulta-hc-panel.window';
const MIN_W = 340;
const MIN_H = 420;
const DEFAULT_W = 420;
const DEFAULT_H = 640;

function defaultWindowGeom(): WindowGeom {
    if (typeof window === 'undefined') {
        return { x: 24, y: 24, w: DEFAULT_W, h: DEFAULT_H };
    }
    const margin = 16;
    const w = Math.min(DEFAULT_W, window.innerWidth - margin * 2);
    const h = Math.min(DEFAULT_H, window.innerHeight - margin * 2);
    return {
        x: Math.max(margin, window.innerWidth - w - margin),
        y: Math.max(margin, window.innerHeight - h - margin),
        w,
        h,
    };
}

function clampWindowGeom(geom: WindowGeom): WindowGeom {
    if (typeof window === 'undefined') {
        return geom;
    }
    const margin = 8;
    const maxW = Math.max(MIN_W, window.innerWidth - margin * 2);
    const maxH = Math.max(MIN_H, window.innerHeight - margin * 2);
    const w = Math.min(Math.max(MIN_W, geom.w), maxW);
    const h = Math.min(Math.max(MIN_H, geom.h), maxH);
    const x = Math.min(
        Math.max(margin, geom.x),
        Math.max(margin, window.innerWidth - w - margin),
    );
    const y = Math.min(
        Math.max(margin, geom.y),
        Math.max(margin, window.innerHeight - h - margin),
    );
    return { x, y, w, h };
}

function loadWindowGeom(): WindowGeom {
    try {
        const raw = localStorage.getItem(WINDOW_STORAGE_KEY);
        if (!raw) {
            return defaultWindowGeom();
        }
        const parsed = JSON.parse(raw) as Partial<WindowGeom>;
        if (
            typeof parsed.x !== 'number' ||
            typeof parsed.y !== 'number' ||
            typeof parsed.w !== 'number' ||
            typeof parsed.h !== 'number'
        ) {
            return defaultWindowGeom();
        }
        return clampWindowGeom({
            x: parsed.x,
            y: parsed.y,
            w: parsed.w,
            h: parsed.h,
        });
    } catch {
        return defaultWindowGeom();
    }
}

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ??
        ''
    );
}

export function ConsultaHistorialFloatingPanel({
    open,
    onOpenChange,
    pacienteId,
    pacienteNombre = null,
}: Props) {
    const { t } = useTranslation(['historias-clinicas', 'common']);
    const { locale: appLocale, timezone: appTz } = usePage().props;
    const [geom, setGeom] = useState<WindowGeom>(() => loadWindowGeom());
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [payload, setPayload] = useState<TimelineResponse | null>(null);
    const dragRef = useRef<{
        pointerId: number;
        startX: number;
        startY: number;
        origX: number;
        origY: number;
    } | null>(null);
    const resizeRef = useRef<{
        pointerId: number;
        startX: number;
        startY: number;
        origW: number;
        origH: number;
    } | null>(null);

    useEffect(() => {
        if (!open) {
            return;
        }
        setGeom(loadWindowGeom());
    }, [open]);

    useEffect(() => {
        if (!open || !pacienteId) {
            return;
        }

        let cancelled = false;
        setLoading(true);
        setError(null);
        setPayload(null);

        void fetch(`/clinica/pacientes/${pacienteId}/timeline`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
        })
            .then(async (res) => {
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}`);
                }
                return (await res.json()) as TimelineResponse;
            })
            .then((data) => {
                if (!cancelled) {
                    setPayload(data);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setError(t('form.ver_hc_error'));
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [open, pacienteId, t]);

    useEffect(() => {
        if (!open) {
            return;
        }
        try {
            localStorage.setItem(WINDOW_STORAGE_KEY, JSON.stringify(geom));
        } catch {
            // ignore
        }
    }, [geom, open]);

    const onDragPointerDown = (e: ReactPointerEvent<HTMLDivElement>) => {
        if (e.button !== 0) {
            return;
        }
        e.currentTarget.setPointerCapture(e.pointerId);
        dragRef.current = {
            pointerId: e.pointerId,
            startX: e.clientX,
            startY: e.clientY,
            origX: geom.x,
            origY: geom.y,
        };
    };

    const onDragPointerMove = (e: ReactPointerEvent<HTMLDivElement>) => {
        const drag = dragRef.current;
        if (!drag || drag.pointerId !== e.pointerId) {
            return;
        }
        setGeom((prev) =>
            clampWindowGeom({
                ...prev,
                x: drag.origX + (e.clientX - drag.startX),
                y: drag.origY + (e.clientY - drag.startY),
            }),
        );
    };

    const onDragPointerUp = (e: ReactPointerEvent<HTMLDivElement>) => {
        if (dragRef.current?.pointerId === e.pointerId) {
            dragRef.current = null;
        }
    };

    const onResizePointerDown = (e: ReactPointerEvent<HTMLDivElement>) => {
        if (e.button !== 0) {
            return;
        }
        e.stopPropagation();
        e.currentTarget.setPointerCapture(e.pointerId);
        resizeRef.current = {
            pointerId: e.pointerId,
            startX: e.clientX,
            startY: e.clientY,
            origW: geom.w,
            origH: geom.h,
        };
    };

    const onResizePointerMove = (e: ReactPointerEvent<HTMLDivElement>) => {
        const resize = resizeRef.current;
        if (!resize || resize.pointerId !== e.pointerId) {
            return;
        }
        setGeom((prev) =>
            clampWindowGeom({
                ...prev,
                w: resize.origW + (e.clientX - resize.startX),
                h: resize.origH + (e.clientY - resize.startY),
            }),
        );
    };

    const onResizePointerUp = (e: ReactPointerEvent<HTMLDivElement>) => {
        if (resizeRef.current?.pointerId === e.pointerId) {
            resizeRef.current = null;
        }
    };

    if (!open || typeof document === 'undefined') {
        return null;
    }

    const titleName = payload?.paciente.nombre ?? pacienteNombre ?? t('form.paciente');
    const timeline = payload?.timeline ?? [];

    return createPortal(
        <div
            role="dialog"
            aria-modal="false"
            aria-label={t('form.ver_hc_title')}
            className="pointer-events-auto fixed z-[200] flex flex-col overflow-hidden rounded-2xl border border-border/70 bg-card shadow-2xl ring-1 ring-black/5"
            style={{
                left: geom.x,
                top: geom.y,
                width: geom.w,
                height: geom.h,
            }}
            onPointerDown={(e) => e.stopPropagation()}
            onClick={(e) => e.stopPropagation()}
        >
            <div
                className="flex cursor-grab items-start gap-2 border-b border-border/60 bg-primary/5 px-3 py-2.5 active:cursor-grabbing"
                onPointerDown={onDragPointerDown}
                onPointerMove={onDragPointerMove}
                onPointerUp={onDragPointerUp}
                onPointerCancel={onDragPointerUp}
            >
                <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                    <History className="size-4" />
                </span>
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                        <p className="truncate text-sm font-semibold text-foreground">
                            {t('form.ver_hc_title')}
                        </p>
                        <GripHorizontal className="size-3.5 shrink-0 text-muted-foreground/70" />
                    </div>
                    <p className="truncate text-xs text-muted-foreground">
                        {titleName}
                        {payload?.paciente.propietario
                            ? ` · ${payload.paciente.propietario}`
                            : ''}
                    </p>
                    <p className="text-[0.65rem] text-muted-foreground/80">
                        {t('form.ver_hc_drag_hint')}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8 shrink-0 cursor-pointer"
                    onPointerDown={(e) => e.stopPropagation()}
                    onClick={(e) => {
                        e.stopPropagation();
                        onOpenChange(false);
                    }}
                    aria-label={t('common:actions.close', { defaultValue: 'Cerrar' })}
                >
                    <X className="size-4" />
                </Button>
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-3 [pointer-events:auto] touch-pan-y">
                {loading ? (
                    <div className="flex h-full min-h-40 flex-col items-center justify-center gap-2 text-sm text-muted-foreground">
                        <Loader2 className="size-5 animate-spin" />
                        {t('form.ver_hc_loading')}
                    </div>
                ) : null}

                {!loading && error ? (
                    <div className="rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-4 text-sm text-destructive">
                        {error}
                    </div>
                ) : null}

                {!loading && !error && timeline.length === 0 ? (
                    <div className="flex h-full min-h-40 flex-col items-center justify-center gap-2 text-center text-sm text-muted-foreground">
                        <ClipboardList className="size-8 opacity-40" />
                        <p>{t('form.ver_hc_empty')}</p>
                    </div>
                ) : null}

                {!loading && !error && timeline.length > 0 ? (
                    <div className="flex flex-col gap-1">
                        {timeline.map((item, index) => (
                            <PacienteTimelineRow
                                key={`${item.kind}-${item.id}`}
                                item={item}
                                appLocale={String(appLocale ?? 'es')}
                                appTz={typeof appTz === 'string' ? appTz : undefined}
                                permisos={{
                                    consultas_ver: payload?.permisos.consultas_ver ?? false,
                                    vacunas_ver: payload?.permisos.vacunas_ver ?? false,
                                }}
                                isLast={index === timeline.length - 1}
                                variant="admin"
                            />
                        ))}
                    </div>
                ) : null}
            </div>

            <div
                className={cn(
                    'absolute bottom-0 right-0 size-4 cursor-se-resize',
                    'after:absolute after:bottom-1 after:right-1 after:size-2 after:rounded-sm after:bg-muted-foreground/40',
                )}
                onPointerDown={onResizePointerDown}
                onPointerMove={onResizePointerMove}
                onPointerUp={onResizePointerUp}
                onPointerCancel={onResizePointerUp}
            />
        </div>,
        document.body,
    );
}
