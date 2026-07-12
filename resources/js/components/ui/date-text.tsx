import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type DateTextProps = {
    children: ReactNode;
    className?: string;
};

/** Texto de fecha con color de marca (listados / columnas de tabla). */
export function DateText({ children, className }: DateTextProps) {
    return <span className={cn('text-date', className)}>{children}</span>;
}
