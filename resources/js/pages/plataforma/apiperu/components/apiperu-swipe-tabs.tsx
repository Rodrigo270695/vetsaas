import { ChevronLeft, ChevronRight } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useRef,
    useState,
    type ReactNode,
} from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Props = {
    children: ReactNode;
    className?: string;
    /** Se fuerza a re-medir fades al cambiar de pestaña / payload. */
    remountKey?: string;
};

/**
 * Tabs con swipe táctil + flechas y fades laterales para que se note
 * que hay más pestañas (SSCO, Trabajadores, etc. tras Deuda coactiva).
 */
export function ApiPeruSwipeTabs({ children, className, remountKey }: Props) {
    const scrollerRef = useRef<HTMLDivElement>(null);
    const [canLeft, setCanLeft] = useState(false);
    const [canRight, setCanRight] = useState(false);

    const updateEdges = useCallback(() => {
        const el = scrollerRef.current;
        if (!el) {
            return;
        }

        const max = el.scrollWidth - el.clientWidth;
        setCanLeft(el.scrollLeft > 4);
        setCanRight(max - el.scrollLeft > 4);
    }, []);

    useEffect(() => {
        updateEdges();
        const el = scrollerRef.current;
        if (!el) {
            return;
        }

        const onScroll = () => updateEdges();
        el.addEventListener('scroll', onScroll, { passive: true });

        const ro = typeof ResizeObserver !== 'undefined' ? new ResizeObserver(updateEdges) : null;
        ro?.observe(el);

        // Re-medir tras layout de tabs
        const t = window.setTimeout(updateEdges, 50);

        return () => {
            el.removeEventListener('scroll', onScroll);
            ro?.disconnect();
            window.clearTimeout(t);
        };
    }, [updateEdges, remountKey, children]);

    const scrollByDir = (dir: -1 | 1) => {
        const el = scrollerRef.current;
        if (!el) {
            return;
        }

        el.scrollBy({ left: dir * Math.max(160, el.clientWidth * 0.55), behavior: 'smooth' });
    };

    return (
        <div className={cn('relative', className)}>
            {canLeft ? (
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label="Ver pestañas anteriores"
                    className="absolute top-1/2 left-0 z-10 size-8 -translate-y-1/2 rounded-full bg-background/95 shadow-sm"
                    onClick={() => scrollByDir(-1)}
                >
                    <ChevronLeft className="size-4" aria-hidden />
                </Button>
            ) : null}

            {canRight ? (
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label="Ver más pestañas"
                    className="absolute top-1/2 right-0 z-10 size-8 -translate-y-1/2 rounded-full bg-background/95 shadow-sm"
                    onClick={() => scrollByDir(1)}
                >
                    <ChevronRight className="size-4" aria-hidden />
                </Button>
            ) : null}

            <div
                aria-hidden
                className={cn(
                    'pointer-events-none absolute inset-y-0 left-0 z-[5] w-8 bg-linear-to-r from-card to-transparent transition-opacity',
                    canLeft ? 'opacity-100' : 'opacity-0',
                )}
            />
            <div
                aria-hidden
                className={cn(
                    'pointer-events-none absolute inset-y-0 right-0 z-[5] w-8 bg-linear-to-l from-card to-transparent transition-opacity',
                    canRight ? 'opacity-100' : 'opacity-0',
                )}
            />

            <div
                ref={scrollerRef}
                className={cn(
                    'overflow-x-auto overscroll-x-contain px-1 pb-1',
                    'touch-pan-x [-webkit-overflow-scrolling:touch]',
                    '[scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden',
                )}
            >
                {children}
            </div>

            {canRight ? (
                <p className="mt-1 text-center text-[11px] text-muted-foreground sm:hidden">
                    Desliza o usa › para ver más pestañas
                </p>
            ) : null}
        </div>
    );
}
