import type { InputHTMLAttributes } from 'react';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

type BaseProps = {
    id: string;
    value: string;
    onChange: (value: string) => void;
    maxLength?: number | null;
    disabled?: boolean;
    placeholder?: string;
    className?: string;
};

type InputProps = BaseProps & {
    as?: 'input';
    type?: InputHTMLAttributes<HTMLInputElement>['type'];
    inputMode?: InputHTMLAttributes<HTMLInputElement>['inputMode'];
};

type TextareaProps = BaseProps & {
    as: 'textarea';
    rows?: number;
};

type Props = InputProps | TextareaProps;

/**
 * Input/textarea con contador de caracteres dentro del campo.
 */
export function ApiPeruCountedField(props: Props) {
    const max = props.maxLength ?? null;
    const showCounter = typeof max === 'number' && max > 0;
    const count = props.value.length;

    if (props.as === 'textarea') {
        return (
            <div className="relative">
                <Textarea
                    id={props.id}
                    value={props.value}
                    onChange={(e) => props.onChange(e.target.value)}
                    placeholder={props.placeholder}
                    maxLength={max ?? undefined}
                    disabled={props.disabled}
                    rows={props.rows ?? 4}
                    className={cn('pb-7 font-mono text-xs sm:text-sm', props.className)}
                />
                {showCounter ? (
                    <span className="pointer-events-none absolute right-2.5 bottom-2 text-[11px] tabular-nums text-muted-foreground">
                        {count}/{max}
                    </span>
                ) : null}
            </div>
        );
    }

    return (
        <div className="relative">
            <Input
                id={props.id}
                type={props.type ?? 'text'}
                value={props.value}
                onChange={(e) => props.onChange(e.target.value)}
                placeholder={props.placeholder}
                maxLength={max ?? undefined}
                disabled={props.disabled}
                inputMode={props.inputMode}
                className={cn('font-mono', showCounter && 'pr-14', props.className)}
            />
            {showCounter ? (
                <span className="pointer-events-none absolute top-1/2 right-2.5 -translate-y-1/2 text-[11px] tabular-nums text-muted-foreground">
                    {count}/{max}
                </span>
            ) : null}
        </div>
    );
}
