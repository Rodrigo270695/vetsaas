import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

type FlippingTextProps = {
    text: string;
    className?: string;
    glyphClassName?: string;
    /** Retraso antes de empezar a formar este bloque. */
    delayMs?: number;
    duration?: number;
};

const FLIP_CHARS =
    'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789áéíóúñÁÉÍÓÚÑ';

function sequenceFor(target: string, steps: number): string[] {
    const seq: string[] = [];
    for (let i = 0; i < steps; i++) {
        seq.push(FLIP_CHARS[Math.floor(Math.random() * FLIP_CHARS.length)] ?? 'A');
    }
    seq.push(target);
    return seq;
}

function FlipGlyph({
    finalChar,
    delayMs,
    tickMs,
    steps,
    reduceMotion,
    className,
}: {
    finalChar: string;
    delayMs: number;
    tickMs: number;
    steps: number;
    reduceMotion: boolean;
    className?: string;
}) {
    const isSpace = finalChar === ' ';
    const [char, setChar] = useState(reduceMotion || isSpace ? finalChar : '');

    useEffect(() => {
        if (reduceMotion || isSpace) {
            setChar(finalChar);
            return;
        }

        const seq = sequenceFor(finalChar, steps);
        const timers = seq.map((next, index) =>
            window.setTimeout(() => setChar(next), delayMs + index * tickMs),
        );

        return () => timers.forEach((id) => window.clearTimeout(id));
    }, [delayMs, finalChar, isSpace, reduceMotion, steps, tickMs]);

    if (isSpace) {
        return <span className={className}>{' '}</span>;
    }

    return (
        <span className={cn('inline-block', className)}>
            {char || '\u00a0'}
        </span>
    );
}

/**
 * El texto conserva la tipografía del padre; las letras ciclan hasta formar la frase.
 */
export function FlippingText({
    text,
    className,
    glyphClassName,
    delayMs = 0,
    duration = 1.15,
}: FlippingTextProps) {
    const [reduceMotion, setReduceMotion] = useState(false);
    const letters = Array.from(text);
    const steps = 6;
    const tickMs = Math.max(36, (duration * 0.35 * 1000) / steps);
    const staggerMs = (duration * 0.65 * 1000) / Math.max(letters.length, 1);

    useEffect(() => {
        setReduceMotion(window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }, []);

    return (
        <span className={className}>
            <span className="sr-only">{text}</span>
            <span aria-hidden>
            {letters.map((letter, index) => (
                <FlipGlyph
                    key={`${index}-${letter}`}
                    finalChar={letter}
                    delayMs={delayMs + index * staggerMs}
                    tickMs={tickMs}
                    steps={steps}
                    reduceMotion={reduceMotion}
                    className={glyphClassName}
                />
            ))}
            </span>
        </span>
    );
}
