import { Eye, EyeOff, Lock, type LucideIcon } from 'lucide-react';
import type { ComponentProps, Ref } from 'react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type PasswordInputProps = Omit<ComponentProps<'input'>, 'type'> & {
    ref?: Ref<HTMLInputElement>;
    /** Icono decorativo al inicio (default: candado). Pasa `null` para ocultarlo. */
    icon?: LucideIcon | null;
};

export default function PasswordInput({
    className,
    ref,
    icon: Icon = Lock,
    ...props
}: PasswordInputProps) {
    const [showPassword, setShowPassword] = useState(false);
    const hasLeftIcon = Icon !== null;

    return (
        <div className="group relative">
            {hasLeftIcon && Icon && (
                <Icon
                    aria-hidden="true"
                    strokeWidth={2.5}
                    className="pointer-events-none absolute top-1/2 left-3 z-10 size-5 -translate-y-1/2 text-brand-700 transition-all duration-200 group-hover:text-brand-800 group-focus-within:scale-110 group-focus-within:text-brand-900 dark:text-brand-300 dark:group-hover:text-brand-200 dark:group-focus-within:text-brand-100"
                />
            )}
            <Input
                type={showPassword ? 'text' : 'password'}
                className={cn(hasLeftIcon ? 'pr-12 pl-11' : 'pr-12', className)}
                ref={ref}
                {...props}
            />
            <button
                type="button"
                onClick={() => setShowPassword((prev) => !prev)}
                className="absolute inset-y-0 right-0 z-10 flex min-w-11 cursor-pointer items-center justify-center rounded-r-md text-brand-700 transition-colors hover:text-brand-900 focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none dark:text-brand-300 dark:hover:text-brand-100"
                aria-label={
                    showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'
                }
                tabIndex={-1}
            >
                {showPassword ? (
                    <EyeOff className="size-5" strokeWidth={2.5} />
                ) : (
                    <Eye className="size-5" strokeWidth={2.5} />
                )}
            </button>
        </div>
    );
}
