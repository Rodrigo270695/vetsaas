import type { LucideIcon } from 'lucide-react';
import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type FieldWithIconProps = ComponentProps<typeof Input> & {
    icon: LucideIcon;
};

/**
 * Input estándar con un icono decorativo al inicio.
 * Mantiene el mismo Input subyacente (focus rings, aria-invalid, etc.).
 */
export function FieldWithIcon({
    icon: Icon,
    className,
    ...props
}: FieldWithIconProps) {
    return (
        <div className="group relative">
            <Icon
                aria-hidden="true"
                strokeWidth={2.5}
                className="pointer-events-none absolute top-1/2 left-3 z-10 size-5 -translate-y-1/2 text-brand-700 transition-all duration-200 group-hover:text-brand-800 group-focus-within:scale-110 group-focus-within:text-brand-900 dark:text-brand-300 dark:group-hover:text-brand-200 dark:group-focus-within:text-brand-100"
            />
            <Input className={cn('pl-11', className)} {...props} />
        </div>
    );
}
