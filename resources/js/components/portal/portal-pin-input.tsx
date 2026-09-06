import { useCallback, useEffect, useRef } from 'react';

type Props = {
    length?: number;
    value: string;
    onChange: (value: string) => void;
    onComplete?: (value: string) => void;
    disabled?: boolean;
    autoFocus?: boolean;
    ariaLabel: string;
};

export function PortalPinInput({
    length = 4,
    value,
    onChange,
    onComplete,
    disabled = false,
    autoFocus = true,
    ariaLabel,
}: Props) {
    const refs = useRef<Array<HTMLInputElement | null>>([]);

    useEffect(() => {
        if (autoFocus) {
            refs.current[0]?.focus();
        }
    }, [autoFocus]);

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
                            const next = (value + (e.currentTarget.value.replace(/\D/g, '') || '')).replace(
                                /\D/g,
                                '',
                            ).slice(0, length);
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
                    className="size-14 rounded-2xl border-2 border-teal-200/80 bg-white text-center text-2xl font-semibold tracking-widest text-teal-950 shadow-sm outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-400/30 disabled:opacity-50 dark:border-teal-700 dark:bg-teal-950/40 dark:text-teal-50"
                />
            ))}
        </div>
    );
}
