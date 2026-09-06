import { Delete } from 'lucide-react';
import { useCallback, useEffect, useRef } from 'react';

type Props = {
    length?: number;
    value: string;
    onChange: (value: string) => void;
    onComplete?: (value: string) => void;
    disabled?: boolean;
    autoFocus?: boolean;
    ariaLabel: string;
    variant?: 'boxes' | 'lock';
    keypad?: boolean;
    invalid?: boolean;
};

export function PortalPinInput({
    length = 4,
    value,
    onChange,
    onComplete,
    disabled = false,
    autoFocus = true,
    ariaLabel,
    variant = 'boxes',
    keypad = false,
    invalid = false,
}: Props) {
    const refs = useRef<Array<HTMLInputElement | null>>([]);

    useEffect(() => {
        if (autoFocus && variant === 'boxes') {
            refs.current[0]?.focus();
        }
    }, [autoFocus, variant]);

    const apply = useCallback(
        (next: string) => {
            const pin = next.replace(/\D/g, '').slice(0, length);
            onChange(pin);
            if (pin.length === length) {
                onComplete?.(pin);
            }
        },
        [length, onChange, onComplete],
    );

    const pushDigit = (digit: string) => {
        if (disabled || value.length >= length) {
            return;
        }
        apply(value + digit);
    };

    const popDigit = () => {
        if (disabled || value.length === 0) {
            return;
        }
        apply(value.slice(0, -1));
    };

    const valueRef = useRef(value);
    valueRef.current = value;

    useEffect(() => {
        if (!keypad) {
            return;
        }
        const onKey = (e: KeyboardEvent) => {
            if (disabled) {
                return;
            }
            const target = e.target as HTMLElement | null;
            if (target && ['INPUT', 'TEXTAREA'].includes(target.tagName)) {
                return;
            }
            if (/^\d$/.test(e.key)) {
                e.preventDefault();
                apply(valueRef.current + e.key);
            }
            if (e.key === 'Backspace') {
                e.preventDefault();
                apply(valueRef.current.slice(0, -1));
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [apply, disabled, keypad]);

    if (variant === 'lock') {
        return (
            <div className="space-y-6" role="group" aria-label={ariaLabel}>
                <div className={`flex justify-center gap-3.5 ${invalid ? 'portal-pin-shake' : ''}`}>
                    {Array.from({ length }, (_, i) => (
                        <span
                            key={i}
                            className={`size-4 rounded-full transition ${
                                invalid
                                    ? 'scale-110 bg-red-500 shadow-[0_0_0_4px_rgba(239,68,68,0.22)]'
                                    : value[i]
                                      ? 'scale-110 bg-brand-600 shadow-[0_0_0_4px_color-mix(in_oklch,var(--brand-600)_22%,transparent)]'
                                      : 'bg-slate-200 dark:bg-slate-700'
                            }`}
                        />
                    ))}
                </div>
                {keypad ? (
                    <div className="mx-auto grid max-w-[17rem] grid-cols-3 gap-2.5">
                        {['1', '2', '3', '4', '5', '6', '7', '8', '9', '', '0', 'del'].map((key) => {
                            if (key === '') {
                                return <span key="empty" />;
                            }
                            if (key === 'del') {
                                return (
                                    <button
                                        key="del"
                                        type="button"
                                        disabled={disabled}
                                        aria-label="Borrar"
                                        onClick={popDigit}
                                        className="inline-flex h-12 cursor-pointer items-center justify-center rounded-full text-slate-600 transition active:scale-95 hover:bg-slate-100 disabled:opacity-40 dark:text-slate-300 dark:hover:bg-white/10"
                                    >
                                        <Delete className="size-6" />
                                    </button>
                                );
                            }
                            return (
                                <button
                                    key={key}
                                    type="button"
                                    disabled={disabled}
                                    onClick={() => pushDigit(key)}
                                    className="inline-flex h-12 cursor-pointer items-center justify-center rounded-full bg-slate-100 text-xl font-semibold text-slate-900 transition active:scale-95 hover:bg-brand-600 hover:text-white disabled:opacity-40 dark:bg-white/10 dark:text-white dark:hover:bg-brand-500"
                                >
                                    {key}
                                </button>
                            );
                        })}
                    </div>
                ) : null}
            </div>
        );
    }

    const setDigit = useCallback(
        (index: number, digit: string) => {
            const chars = Array.from({ length }, (_, i) => value[i] ?? '');
            chars[index] = digit;
            onChange(chars.join('').replace(/\D/g, '').slice(0, length));
        },
        [length, onChange, value],
    );

    return (
        <div className="flex justify-center gap-3" role="group" aria-label={ariaLabel}>
            {Array.from({ length }, (_, i) => (
                <input
                    key={i}
                    ref={(el) => {
                        refs.current[i] = el;
                    }}
                    inputMode="numeric"
                    autoComplete={i === 0 ? 'one-time-code' : 'off'}
                    maxLength={1}
                    disabled={disabled}
                    value={value[i] ?? ''}
                    onChange={(e) => {
                        const d = e.target.value.replace(/\D/g, '').slice(-1);
                        if (!d) {
                            setDigit(i, '');
                            return;
                        }
                        const chars = Array.from({ length }, (_, j) => value[j] ?? '');
                        chars[i] = d;
                        const next = chars.join('').replace(/\D/g, '').slice(0, length);
                        onChange(next);
                        if (next.length === length) {
                            onComplete?.(next);
                        } else {
                            refs.current[i + 1]?.focus();
                        }
                    }}
                    onKeyDown={(e) => {
                        if (e.key === 'Backspace' && !value[i] && i > 0) {
                            refs.current[i - 1]?.focus();
                        }
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            const next = (value + (e.currentTarget.value.replace(/\D/g, '') || ''))
                                .replace(/\D/g, '')
                                .slice(0, length);
                            if (next.length === length) {
                                onComplete?.(next);
                            }
                        }
                    }}
                    onPaste={(e) => {
                        e.preventDefault();
                        const pasted = e.clipboardData
                            .getData('text')
                            .replace(/\D/g, '')
                            .slice(0, length);
                        onChange(pasted);
                    }}
                    className="size-14 cursor-text rounded-2xl border-2 border-brand-200/80 bg-white text-center text-2xl font-semibold tracking-widest text-brand-950 shadow-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-400/30 disabled:opacity-50 dark:border-brand-700 dark:bg-brand-950/40 dark:text-brand-50"
                />
            ))}
        </div>
    );
}
