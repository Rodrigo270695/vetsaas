import { useCallback, useRef } from 'react';
import type { MouseEvent, PointerEvent } from 'react';

type Options = {
    delayMs?: number;
    moveThresholdPx?: number;
};

/**
 * Long-press para móvil (sin hover). Cancela si el dedo se mueve mucho.
 */
export function useLongPress(
    onLongPress: () => void,
    { delayMs = 480, moveThresholdPx = 12 }: Options = {},
) {
    const timerRef = useRef<number | undefined>(undefined);
    const startRef = useRef<{ x: number; y: number } | null>(null);
    const firedRef = useRef(false);

    const clear = useCallback(() => {
        if (timerRef.current !== undefined) {
            window.clearTimeout(timerRef.current);
            timerRef.current = undefined;
        }
        startRef.current = null;
    }, []);

    const onPointerDown = useCallback(
        (e: PointerEvent) => {
            if (e.pointerType === 'mouse' && e.button !== 0) return;
            firedRef.current = false;
            startRef.current = { x: e.clientX, y: e.clientY };
            clear();
            timerRef.current = window.setTimeout(() => {
                firedRef.current = true;
                // Feedback háptico suave si el dispositivo lo soporta.
                try {
                    navigator.vibrate?.(12);
                } catch {
                    // ignore
                }
                onLongPress();
                timerRef.current = undefined;
            }, delayMs);
        },
        [clear, delayMs, onLongPress],
    );

    const onPointerMove = useCallback(
        (e: PointerEvent) => {
            const start = startRef.current;
            if (!start || timerRef.current === undefined) return;
            const dx = Math.abs(e.clientX - start.x);
            const dy = Math.abs(e.clientY - start.y);
            if (dx > moveThresholdPx || dy > moveThresholdPx) {
                clear();
            }
        },
        [clear, moveThresholdPx],
    );

    const onPointerUp = useCallback(() => {
        clear();
    }, [clear]);

    const onPointerCancel = useCallback(() => {
        clear();
    }, [clear]);

    /** Evita que el click posterior al long-press dispare otras acciones. */
    const onClickCapture = useCallback((e: MouseEvent) => {
        if (firedRef.current) {
            e.preventDefault();
            e.stopPropagation();
            firedRef.current = false;
        }
    }, []);

    const onContextMenu = useCallback((e: MouseEvent) => {
        e.preventDefault();
    }, []);

    return {
        onPointerDown,
        onPointerMove,
        onPointerUp,
        onPointerCancel,
        onClickCapture,
        onContextMenu,
    };
}
