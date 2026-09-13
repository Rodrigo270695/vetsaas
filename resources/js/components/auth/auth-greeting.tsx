import { useMemo } from 'react';
import { TextFlippingBoard } from '@/components/ui/text-flipping-board';

type AuthGreetingProps = {
    title?: string;
    description?: string;
    /** Forzar saludo (útil para tests). Si no, se calcula por hora local. */
    overrideGreeting?: string;
};

function pickGreeting(hour: number): string {
    if (hour < 6) return 'Buenas noches';
    if (hour < 12) return 'Buenos días';
    if (hour < 19) return 'Buenas tardes';
    return 'Buenas noches';
}

function boardColumns(titleLength: number): number {
    return Math.min(18, Math.max(12, titleLength));
}

/**
 * Status pill + saludo dinámico en tablero split-flap + descripción.
 * El `title` (nombre del tenant) es la segunda línea del tablero.
 */
export default function AuthGreeting({
    title,
    description,
    overrideGreeting,
}: AuthGreetingProps) {
    const greeting = useMemo(
        () => overrideGreeting ?? pickGreeting(new Date().getHours()),
        [overrideGreeting],
    );
    const headline = (title ?? 'bienvenido de vuelta.').trim();
    const boardText = `${greeting},\n${headline}`;
    const columns = boardColumns(headline.length);

    return (
        <header className="mb-8 space-y-3 text-center sm:mb-10">
            <span className="inline-flex items-center gap-1.5 rounded-full border border-border/60 bg-card/60 px-2.5 py-1 text-[0.7rem] font-medium tracking-wider text-muted-foreground uppercase backdrop-blur">
                <span className="relative flex size-1.5">
                    <span className="absolute inline-flex size-1.5 animate-ping rounded-full bg-success/60" />
                    <span className="relative inline-flex size-1.5 rounded-full bg-success" />
                </span>
                Sistema operativo
            </span>
            <h1 className="flex justify-center">
                <TextFlippingBoard
                    key={boardText}
                    text={boardText}
                    columns={columns}
                    duration={1.35}
                />
            </h1>
            {description && (
                <p
                    key={description}
                    className="animate-in fade-in text-pretty text-sm text-muted-foreground duration-500"
                >
                    {description}
                </p>
            )}
        </header>
    );
}
