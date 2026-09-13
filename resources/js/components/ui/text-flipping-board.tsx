import { useEffect, useMemo, useState } from 'react';
import { cn } from '@/lib/utils';

type TextFlippingBoardProps = {
    text: string;
    /** Columnas del tablero. Si el texto es más largo, parte por palabras. */
    columns?: number;
    /** Duración total aproximada en segundos. */
    duration?: number;
    className?: string;
};

const FLIP_CHARS =
    'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789áéíóúñÁÉÍÓÚÑ';

function wrapRows(text: string, columns: number): string[] {
    const rows: string[] = [];
    for (const rawLine of text.split('\n')) {
        const line = rawLine.trimEnd();
        if (line === '') {
            rows.push(' '.repeat(columns));
            continue;
        }
        const words = line.split(/\s+/);
        let current = '';
        for (const word of words) {
            if (word.length > columns) {
                if (current !== '') {
                    rows.push(current.padEnd(columns));
                    current = '';
                }
                for (let i = 0; i < word.length; i += columns) {
                    const chunk = word.slice(i, i + columns);
                    if (chunk.length === columns) {
                        rows.push(chunk);
                    } else {
                        current = chunk;
                    }
                }
                continue;
            }
            const next = current === '' ? word : `${current} ${word}`;
            if (next.length <= columns) {
                current = next;
            } else {
                rows.push(current.padEnd(columns));
                current = word;
            }
        }
        rows.push(current.padEnd(columns));
    }
    return rows;
}

function flipSequence(target: string, steps: number): string[] {
    if (target === ' ') {
        return [' '];
    }
    const seq: string[] = [];
    for (let i = 0; i < steps; i++) {
        seq.push(FLIP_CHARS[Math.floor(Math.random() * FLIP_CHARS.length)] ?? 'A');
    }
    seq.push(target);
    return seq;
}

function FlapFaces({ char }: { char: string }) {
    const glyph = char === ' ' ? '' : char;

    return (
        <>
            <span className="absolute inset-x-0 top-0 h-1/2 overflow-hidden rounded-t-[3px]">
                <span className="flex h-[200%] items-center justify-center">{glyph}</span>
            </span>
            <span className="absolute inset-x-0 bottom-0 h-1/2 overflow-hidden rounded-b-[3px]">
                <span className="-translate-y-1/2 flex h-[200%] items-center justify-center">{glyph}</span>
            </span>
        </>
    );
}

function FlapTile({
    finalChar,
    delayMs,
    tickMs,
    steps,
    reduceMotion,
}: {
    finalChar: string;
    delayMs: number;
    tickMs: number;
    steps: number;
    reduceMotion: boolean;
}) {
    const [char, setChar] = useState(reduceMotion ? finalChar : ' ');
    const [spin, setSpin] = useState(0);

    useEffect(() => {
        if (reduceMotion) {
            setChar(finalChar);
            return;
        }

        const seq = flipSequence(finalChar, finalChar === ' ' ? 0 : steps);
        const timers = seq.map((next, index) =>
            window.setTimeout(() => {
                setChar(next);
                setSpin((n) => n + 1);
            }, delayMs + index * tickMs),
        );

        return () => timers.forEach((id) => window.clearTimeout(id));
    }, [delayMs, finalChar, reduceMotion, steps, tickMs]);

    return (
        <span
            aria-hidden
            className="relative block h-[1.45rem] w-[1.05rem] overflow-hidden rounded-[3px] bg-zinc-800 text-[0.68rem] leading-none font-semibold text-zinc-100 shadow-[inset_0_1px_0_0_rgb(255_255_255/0.08)] sm:h-[1.6rem] sm:w-[1.15rem] sm:text-[0.72rem] dark:bg-zinc-900"
            style={{ perspective: '480px' }}
        >
            <span
                key={spin}
                className="absolute inset-0 animate-[split-flap-tick_85ms_ease-in-out] transform-3d"
            >
                <FlapFaces char={char} />
            </span>
            <span className="pointer-events-none absolute inset-x-0 top-1/2 z-10 h-px -translate-y-px bg-black/55" />
        </span>
    );
}

/**
 * Tablero split-flap (estilo Vestaboard / Aceternity Text Flipping Board).
 * Sin Motion: CSS 3D + timeouts. Respeta prefers-reduced-motion.
 */
export function TextFlippingBoard({
    text,
    columns = 16,
    duration = 1.25,
    className,
}: TextFlippingBoardProps) {
    const rows = useMemo(() => wrapRows(text, columns), [columns, text]);
    const [reduceMotion, setReduceMotion] = useState(false);

    useEffect(() => {
        setReduceMotion(window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }, []);

    const steps = 7;
    const totalCells = Math.max(rows.length * columns, 1);
    const staggerMs = reduceMotion ? 0 : (duration * 0.55 * 1000) / totalCells;
    const tickMs = reduceMotion ? 0 : Math.max(42, (duration * 0.4 * 1000) / steps);

    return (
        <div
            role="img"
            aria-label={text.replace(/\n/g, ' ')}
            className={cn(
                'inline-flex flex-col gap-[3px] rounded-xl border border-zinc-800/80 bg-zinc-950 p-2 shadow-[0_12px_40px_-18px_rgb(0_0_0/0.55)]',
                className,
            )}
        >
            {rows.map((row, rowIndex) => (
                <div key={`${rowIndex}-${row}`} className="flex justify-center gap-[3px]">
                    {Array.from(row).map((cell, colIndex) => (
                        <FlapTile
                            key={`${rowIndex}-${colIndex}`}
                            finalChar={cell}
                            delayMs={staggerMs * (rowIndex * columns + colIndex)}
                            tickMs={tickMs}
                            steps={steps}
                            reduceMotion={reduceMotion}
                        />
                    ))}
                </div>
            ))}
        </div>
    );
}
