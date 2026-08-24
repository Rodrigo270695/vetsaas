import * as React from 'react';

import { cn } from '@/lib/utils';

type TextareaProps = React.ComponentProps<'textarea'> & {
    /**
     * Crece en altura según el contenido (sin scroll interno).
     * @default true
     */
    autoGrow?: boolean;
};

function assignRef<T>(ref: React.Ref<T> | undefined, value: T | null) {
    if (typeof ref === 'function') {
        ref(value);
    } else if (ref && typeof ref === 'object') {
        (ref as React.MutableRefObject<T | null>).current = value;
    }
}

/**
 * Variante multilinea del `Input`: mismo look (border, hover, focus).
 * Por defecto crece verticalmente al escribir (`autoGrow`).
 */
function Textarea({
    className,
    autoGrow = true,
    onChange,
    onInput,
    style,
    ref,
    ...props
}: TextareaProps) {
    const localRef = React.useRef<HTMLTextAreaElement | null>(null);

    const adjustHeight = React.useCallback(() => {
        const el = localRef.current;
        if (!el || !autoGrow) {
            return;
        }

        el.style.height = 'auto';
        el.style.height = `${el.scrollHeight}px`;
    }, [autoGrow]);

    React.useLayoutEffect(() => {
        adjustHeight();
    }, [adjustHeight, props.value, props.defaultValue]);

    return (
        <textarea
            data-slot="textarea"
            {...props}
            ref={(node) => {
                localRef.current = node;
                assignRef(ref, node);
            }}
            style={style}
            className={cn(
                'border-input file:text-foreground placeholder:text-muted-foreground/70 selection:bg-primary selection:text-primary-foreground flex min-h-20 w-full min-w-0 rounded-md border bg-card/70 px-3 py-2 text-sm shadow-xs backdrop-blur-sm transition-[border-color,box-shadow,background-color] outline-none hover:border-input/80 hover:bg-card/85 focus-visible:bg-card/85 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
                'focus-visible:border-ring focus-visible:ring-ring/25 focus-visible:ring-2',
                'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                className,
                autoGrow && 'resize-none overflow-hidden',
            )}
            onChange={(e) => {
                onChange?.(e);
                if (autoGrow) {
                    const el = e.currentTarget;
                    el.style.height = 'auto';
                    el.style.height = `${el.scrollHeight}px`;
                }
            }}
            onInput={(e) => {
                onInput?.(e);
                if (autoGrow) {
                    const el = e.currentTarget;
                    el.style.height = 'auto';
                    el.style.height = `${el.scrollHeight}px`;
                }
            }}
        />
    );
}

export { Textarea };
