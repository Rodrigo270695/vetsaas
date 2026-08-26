import { Loader2, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export function soloDigitosDocumento(value: string, max?: number): string {
    const digits = value.replace(/\D/g, '');

    return max !== undefined ? digits.slice(0, max) : digits;
}

export type DocumentNumberLookupFieldProps = {
    id: string;
    value: string;
    onChange: (next: string) => void;
    /** Longitud esperada para DNI/RUC; si undefined, no muestra contador ni botón. */
    maxLength?: number;
    consulting?: boolean;
    disabled?: boolean;
    invalid?: boolean;
    onConsult?: () => void;
    consultAriaLabel: string;
    className?: string;
};

/**
 * Número de documento con contador `n/max` y botón verde de consulta (RENIEC/SUNAT),
 * mismo patrón visual que propietarios / proveedores.
 */
export function DocumentNumberLookupField({
    id,
    value,
    onChange,
    maxLength,
    consulting = false,
    disabled = false,
    invalid = false,
    onConsult,
    consultAriaLabel,
    className,
}: DocumentNumberLookupFieldProps) {
    const isConsultable = maxLength !== undefined;
    const len = soloDigitosDocumento(value).length;
    const completo = isConsultable && len === maxLength;

    return (
        <div
            className={cn(
                'flex gap-2',
                isConsultable ? 'items-stretch' : 'flex-col',
                className,
            )}
        >
            <div className="relative min-w-0 flex-1">
                <Input
                    id={id}
                    className={cn(isConsultable && 'pr-14 tabular-nums tracking-wide')}
                    inputMode={isConsultable ? 'numeric' : undefined}
                    autoComplete="off"
                    maxLength={maxLength}
                    value={value}
                    disabled={disabled}
                    aria-invalid={invalid}
                    onChange={(e) =>
                        onChange(
                            isConsultable
                                ? soloDigitosDocumento(e.target.value, maxLength)
                                : e.target.value,
                        )
                    }
                />
                {isConsultable ? (
                    <span
                        className={cn(
                            'pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs font-medium tabular-nums',
                            completo
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-muted-foreground',
                        )}
                        aria-hidden
                    >
                        {len}/{maxLength}
                    </span>
                ) : null}
            </div>
            {isConsultable && onConsult ? (
                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    disabled={disabled || consulting || !completo}
                    onClick={() => onConsult()}
                    className={cn(
                        'size-9 shrink-0 cursor-pointer rounded-lg border-0 shadow-sm transition-all',
                        'bg-gradient-to-br from-teal-500 to-emerald-600 text-white',
                        'hover:from-teal-600 hover:to-emerald-700 hover:shadow-md',
                        'focus-visible:ring-2 focus-visible:ring-emerald-500/40',
                        'disabled:cursor-not-allowed disabled:from-muted disabled:to-muted disabled:text-muted-foreground disabled:opacity-60 disabled:shadow-none',
                    )}
                    aria-label={consultAriaLabel}
                    title={consultAriaLabel}
                >
                    {consulting ? (
                        <Loader2 className="size-4 animate-spin" aria-hidden />
                    ) : (
                        <Search className="size-4" aria-hidden />
                    )}
                </Button>
            ) : null}
        </div>
    );
}
