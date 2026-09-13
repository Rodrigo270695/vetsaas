import { useMemo } from 'react';
import { TypingAnimation } from '@/components/ui/typing-animation';

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
 * El `title` se escribe como si fuera teclado (Magic UI Typing Animation).
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
    const headline = title ?? 'bienvenido de vuelta.';
    const greetingLine = `${greeting},`;
    const typeSpeed = 58;
    const headlineDelay = 280 + greetingLine.length * typeSpeed;

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
                key={`${greeting}-${headline}`}
                className="text-balance text-3xl font-semibold tracking-tight text-foreground sm:text-4xl"
            >
                <TypingAnimation typeSpeed={typeSpeed} delay={180}>
                    {greetingLine}
                </TypingAnimation>
                <span className="block bg-linear-to-br from-brand-700 to-brand-500 bg-clip-text text-transparent dark:from-brand-300 dark:to-brand-200">
                    <TypingAnimation
                        typeSpeed={typeSpeed}
                        delay={headlineDelay}
                        className="bg-linear-to-br from-brand-700 to-brand-500 bg-clip-text text-transparent dark:from-brand-300 dark:to-brand-200"
                    >
                        {headline}
                    </TypingAnimation>
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
