import { Bird, Cat, Dog, Fish, PawPrint, Rabbit } from 'lucide-react';
import { cn } from '@/lib/utils';

const PALETTES = [
    'from-brand-800 via-brand-600 to-brand-400',
    'from-brand-900 via-brand-600 to-brand-300',
    'from-brand-700 via-brand-500 to-brand-300',
    'from-brand-800 via-brand-500 to-brand-200',
    'from-brand-950 via-brand-700 to-brand-400',
    'from-brand-700 via-brand-600 to-brand-400',
];

function hashName(name: string): number {
    let h = 0;
    for (let i = 0; i < name.length; i += 1) {
        h = (h * 33 + name.charCodeAt(i)) >>> 0;
    }
    return h;
}

function SpeciesIcon({ especie, className }: { especie: string | null; className?: string }) {
    const key = (especie ?? '').toLowerCase();
    if (/gato|felin|cat/.test(key)) {
        return <Cat className={className} />;
    }
    if (/ave|bird|loro|canario/.test(key)) {
        return <Bird className={className} />;
    }
    if (/conejo|rabbit/.test(key)) {
        return <Rabbit className={className} />;
    }
    if (/pez|fish/.test(key)) {
        return <Fish className={className} />;
    }
    if (/perro|canin|dog/.test(key)) {
        return <Dog className={className} />;
    }
    return <PawPrint className={className} />;
}

export function PortalPetCover({
    nombre,
    fotoUrl,
    especie,
    className,
}: {
    nombre: string;
    fotoUrl: string | null;
    especie?: string | null;
    className?: string;
}) {
    const palette = PALETTES[hashName(nombre) % PALETTES.length];

    if (fotoUrl) {
        return (
            <img src={fotoUrl} alt="" className={cn('block size-full object-cover', className)} />
        );
    }

    return (
        <div className={cn('relative size-full overflow-hidden bg-linear-to-br', palette, className)}>
            <svg
                className="absolute inset-0 size-full opacity-25"
                viewBox="0 0 200 200"
                aria-hidden
            >
                <circle cx="36" cy="48" r="14" fill="white" />
                <circle cx="62" cy="38" r="11" fill="white" />
                <circle cx="18" cy="72" r="10" fill="white" />
                <ellipse cx="48" cy="78" rx="18" ry="14" fill="white" />
                <circle cx="148" cy="22" r="16" fill="white" />
                <circle cx="176" cy="40" r="12" fill="white" />
                <circle cx="132" cy="48" r="10" fill="white" />
                <ellipse cx="158" cy="58" rx="20" ry="15" fill="white" />
                <circle cx="168" cy="148" r="18" fill="white" />
                <circle cx="140" cy="168" r="12" fill="white" />
                <circle cx="188" cy="172" r="11" fill="white" />
                <ellipse cx="164" cy="178" rx="22" ry="16" fill="white" />
            </svg>
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(255,255,255,0.28),transparent_55%)]" />
            <div className="absolute inset-0 flex items-center justify-center">
                <SpeciesIcon
                    especie={especie ?? null}
                    className="size-24 text-white/85 drop-shadow-lg sm:size-28"
                />
            </div>
        </div>
    );
}
