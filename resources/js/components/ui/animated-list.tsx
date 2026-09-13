import {
    Children,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';
import { cn } from '@/lib/utils';

type AnimatedListProps = {
    children: ReactNode;
    className?: string;
    delay?: number;
};

/**
 * Lista que va apareciendo ítem a ítem (Magic UI Animated List, sin Motion).
 * Los más nuevos quedan arriba; al terminar el ciclo vuelve a empezar.
 */
export function AnimatedList({ children, className, delay = 1600 }: AnimatedListProps) {
    const items = useMemo(() => Children.toArray(children), [children]);
    const [tick, setTick] = useState(0);

    useEffect(() => {
        if (items.length === 0) {
            return;
        }
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setTick(Math.max(items.length - 1, 0));
            return;
        }
        const id = window.setTimeout(() => {
            setTick((current) => current + 1);
        }, delay);

        return () => window.clearTimeout(id);
    }, [delay, items.length, tick]);

    const visible = useMemo(() => {
        if (items.length === 0) {
            return [];
        }
        const windowSize = Math.min(4, tick + 1, items.length);
        const rows: { node: ReactNode; itemIndex: number }[] = [];
        for (let i = 0; i < windowSize; i++) {
            const itemIndex = (tick - i + items.length * 50) % items.length;
            const node = items[itemIndex];
            if (node !== undefined) {
                rows.push({ node, itemIndex });
            }
        }
        return rows;
    }, [items, tick]);

    return (
        <div className={cn('flex w-full flex-col gap-2', className)}>
            {visible.map((row, i) => (
                <div
                    key={row.itemIndex}
                    className={cn(
                        i === 0 && 'animate-in fade-in slide-in-from-top-2 zoom-in-95 duration-300',
                    )}
                >
                    {row.node}
                </div>
            ))}
        </div>
    );
}
