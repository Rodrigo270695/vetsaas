import { useEffect, useRef, useState, type ReactNode } from 'react';
import { cn } from '@/lib/utils';

export const DASHBOARD_CHART_HEIGHT = 280;

type ChartDimensions = {
    width: number;
    height: number;
};

type Props = {
    height?: number;
    className?: string;
    children: (dimensions: ChartDimensions) => ReactNode;
};

/**
 * Recharts `ResponsiveContainer` falla (width/height -1) dentro de grid/flex
 * de Inertia antes de que el layout tenga tamaño. Medimos el contenedor y
 * pasamos dimensiones explícitas al chart.
 */
export function DashboardChartShell({
    height = DASHBOARD_CHART_HEIGHT,
    className,
    children,
}: Props) {
    const ref = useRef<HTMLDivElement>(null);
    const [dimensions, setDimensions] = useState<ChartDimensions | null>(null);

    useEffect(() => {
        const node = ref.current;
        if (!node) {
            return;
        }

        const measure = (): void => {
            const width = Math.floor(node.clientWidth);
            const measuredHeight = Math.floor(node.clientHeight);

            if (width > 0 && measuredHeight > 0) {
                setDimensions({ width, height: measuredHeight });
            }
        };

        measure();

        const observer = new ResizeObserver(() => {
            measure();
        });
        observer.observe(node);

        return () => observer.disconnect();
    }, [height]);

    return (
        <div
            ref={ref}
            className={cn('w-full min-w-0', className)}
            style={{ height }}
            aria-hidden={dimensions === null}
        >
            {dimensions !== null ? children(dimensions) : null}
        </div>
    );
}
