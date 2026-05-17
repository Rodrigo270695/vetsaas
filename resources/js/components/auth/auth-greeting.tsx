import { useMemo } from 'react';

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

/**
 * Status pill + saludo dinámico + headline editorial con gradiente.
 * El `title` se renderiza como segunda línea en color de marca.
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

    return (
        <header className="mb-8 space-y-3 text-center sm:mb-10">
            <span className="inline-flex items-center gap-1.5 rounded-full border border-border/60 bg-card/60 px-2.5 py-1 text-[0.7rem] font-medium tracking-wider text-muted-foreground uppercase backdrop-blur">
                <span className="relative flex size-1.5">
                    <span className="absolute inline-flex size-1.5 animate-ping rounded-full bg-success/60" />
                    <span className="relative inline-flex size-1.5 rounded-full bg-success" />
                </span>
                Sistema operativo
            </span>
            <h1
                key={title}
                className="animate-in fade-in slide-in-from-bottom-1 text-balance text-3xl font-semibold tracking-tight text-foreground duration-500 sm:text-4xl"
            >
                {greeting},
                <span className="block bg-linear-to-br from-brand-700 to-brand-500 bg-clip-text text-transparent dark:from-brand-300 dark:to-brand-200">
                    {title ?? 'bienvenido de vuelta.'}
                </span>
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
