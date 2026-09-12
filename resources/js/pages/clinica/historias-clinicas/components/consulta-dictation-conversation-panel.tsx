import { GripHorizontal, MessagesSquare, X } from 'lucide-react';
import { useEffect, useRef, useState, type PointerEvent as ReactPointerEvent } from 'react';
import { createPortal } from 'react-dom';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { ConsultaDictationConversationTurn, ConsultaDictationResult } from './consulta-dictation-bar';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    result: ConsultaDictationResult | null;
};

type WindowGeom = { x: number; y: number; w: number; h: number };
type TabId = 'resumen' | 'dialogo' | 'transcripcion';

const WINDOW_STORAGE_KEY = 'vetsaas.consulta-dictation-panel.window';
const MIN_W = 340;
const MIN_H = 420;
const DEFAULT_W = 420;
const DEFAULT_H = 640;

function defaultWindowGeom(): WindowGeom {
    if (typeof window === 'undefined') {
        return { x: 16, y: 72, w: DEFAULT_W, h: DEFAULT_H };
    }
    const margin = 16;
    const w = Math.min(DEFAULT_W, window.innerWidth - margin * 2);
    const h = Math.min(DEFAULT_H, window.innerHeight - margin * 2);
    return {
        x: margin,
        y: Math.max(margin, 72),
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
    const x = Math.min(Math.max(margin, geom.x), Math.max(margin, window.innerWidth - w - margin));
    const y = Math.min(Math.max(margin, geom.y), Math.max(margin, window.innerHeight - h - margin));
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

function roleBubbleClass(role: ConsultaDictationConversationTurn['role']): string {
    if (role === 'veterinario') {
        return 'ml-6 bg-primary text-primary-foreground';
    }
    if (role === 'propietario') {
        return 'mr-6 bg-muted text-foreground';
    }
    return 'mx-4 bg-amber-50 text-foreground dark:bg-amber-950/40';
}

export function ConsultaDictationConversationPanel({ open, onOpenChange, result }: Props) {
    const { t } = useTranslation(['historias-clinicas', 'common']);
    const [geom, setGeom] = useState<WindowGeom>(() => loadWindowGeom());
    const [tab, setTab] = useState<TabId>('resumen');
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
        setTab('resumen');
    }, [open]);

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

    if (!open || !result || typeof document === 'undefined') {
        return null;
    }

    const roleLabel = (role: ConsultaDictationConversationTurn['role']) => t(`dictation.role_${role}`);

    return createPortal(
        <div
            role="dialog"
            aria-modal="false"
            aria-label={t('dictation.conversation_title')}
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
                className="flex cursor-grab items-start gap-2 border-b border-border/60 bg-sky-500/8 px-3 py-2.5 active:cursor-grabbing"
                onPointerDown={onDragPointerDown}
                onPointerMove={onDragPointerMove}
                onPointerUp={onDragPointerUp}
                onPointerCancel={onDragPointerUp}
            >
                <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-sky-600 text-white">
                    <MessagesSquare className="size-4" />
                </span>
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                        <p className="truncate text-sm font-semibold text-foreground">
                            {t('dictation.conversation_title')}
                        </p>
                        <GripHorizontal className="size-3.5 shrink-0 text-muted-foreground/70" />
                    </div>
                    <p className="text-[0.65rem] text-muted-foreground/80">
                        {t('dictation.conversation_drag_hint')}
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

            <div className="flex gap-1 border-b border-border/50 px-2 py-1.5">
                {(['resumen', 'dialogo', 'transcripcion'] as const).map((id) => (
                    <button
                        key={id}
                        type="button"
                        className={cn(
                            'cursor-pointer rounded-md px-2.5 py-1 text-xs font-medium',
                            tab === id
                                ? 'bg-sky-600 text-white'
                                : 'text-muted-foreground hover:bg-muted',
                        )}
                        onClick={() => setTab(id)}
                    >
                        {t(`dictation.tab_${id}`)}
                    </button>
                ))}
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-3 [pointer-events:auto] touch-pan-y">
                {tab === 'resumen' ? (
                    <div className="space-y-3">
                        <p className="text-xs text-muted-foreground">{t('dictation.resumen_hint')}</p>
                        {result.highlights.length > 0 ? (
                            <ul className="list-disc space-y-1 pl-4 text-sm leading-relaxed">
                                {result.highlights.map((line, i) => (
                                    <li key={`${i}-${line.slice(0, 24)}`}>{line}</li>
                                ))}
                            </ul>
                        ) : null}
                        {result.fields.subjetivo ? (
                            <div className="rounded-lg border border-border/60 bg-muted/30 px-3 py-2.5 text-sm leading-relaxed whitespace-pre-wrap">
                                {result.fields.subjetivo}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">{t('dictation.resumen_empty')}</p>
                        )}
                    </div>
                ) : null}

                {tab === 'dialogo' ? (
                    <div className="flex flex-col gap-2">
                        {result.conversation.length === 0 ? (
                            <p className="text-sm text-muted-foreground">{t('dictation.dialogo_empty')}</p>
                        ) : (
                            result.conversation.map((turn, i) => (
                                <div
                                    key={`${i}-${turn.role}`}
                                    className={cn(
                                        'rounded-2xl px-3 py-2 text-sm leading-relaxed',
                                        roleBubbleClass(turn.role),
                                    )}
                                >
                                    <p className="mb-0.5 text-[0.65rem] font-semibold uppercase tracking-wide opacity-80">
                                        {roleLabel(turn.role)}
                                    </p>
                                    <p className="whitespace-pre-wrap">{turn.text}</p>
                                </div>
                            ))
                        )}
                    </div>
                ) : null}

                {tab === 'transcripcion' ? (
                    <p className="text-sm leading-relaxed whitespace-pre-wrap text-foreground/90">
                        {result.transcript || t('dictation.transcripcion_empty')}
                    </p>
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
