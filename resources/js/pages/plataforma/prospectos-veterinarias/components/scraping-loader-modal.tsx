import { Radar } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { CSSProperties } from 'react';
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';

const MESSAGES = [
    'Conectando con los directorios de veterinarias de Perú…',
    'Escaneando clínicas y hospitales por departamento…',
    'Extrayendo teléfonos, correos y direcciones…',
    'Cargando energía de prospección… casi listo',
    'Filtrando duplicados y guardando resultados…',
];

/** Ángulos de partida de cada partícula que converge al núcleo. */
const PARTICLE_ANGLES = [0, 45, 90, 135, 180, 225, 270, 315];

/**
 * Bola de energía "estilo Genkidama": partículas orbitando que convergen
 * a un núcleo pulsante, con un anillo de radar de fondo y texto de
 * estado que va cambiando. Se monta desde cero cada vez que el modal se
 * abre, así el ciclo de mensajes siempre arranca en el primero.
 */
function EnergyBallLoader() {
    const [messageIndex, setMessageIndex] = useState(0);

    useEffect(() => {
        const id = setInterval(() => {
            setMessageIndex((prev) => (prev + 1) % MESSAGES.length);
        }, 1900);

        return () => clearInterval(id);
    }, []);

    return (
        <>
            {/* ── Bola de energía ───────────────────────────────── */}
            <div className="relative flex size-40 items-center justify-center">
                {/* Radar de fondo: anillo giratorio tipo escáner */}
                <div
                    className="ev-radar-spin absolute inset-0 rounded-full opacity-70"
                    style={{
                        animation: 'spin 3.2s linear infinite',
                        background:
                            'conic-gradient(from 0deg, transparent 0%, transparent 70%, rgba(52,211,153,0.55) 92%, transparent 100%)',
                    }}
                />
                <div className="absolute inset-3 rounded-full border border-emerald-400/15" />
                <div className="absolute inset-8 rounded-full border border-emerald-400/10" />

                {/* Anillos expansivos (sonar) */}
                <div className="ev-ring absolute size-16 rounded-full border-2 border-emerald-400/60" />
                <div
                    className="ev-ring absolute size-16 rounded-full border-2 border-emerald-400/60"
                    style={{ animationDelay: '0.8s' }}
                />
                <div
                    className="ev-ring absolute size-16 rounded-full border-2 border-emerald-400/60"
                    style={{ animationDelay: '1.6s' }}
                />

                {/* Partículas convergiendo al núcleo */}
                {PARTICLE_ANGLES.map((angle, i) => (
                    <div
                        key={angle}
                        className="absolute inset-0"
                        style={{ transform: `rotate(${angle}deg)` }}
                    >
                        <div
                            className="ev-particle absolute top-1/2 left-1/2 size-1.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-emerald-300 shadow-[0_0_6px_2px_rgba(52,211,153,0.8)]"
                            style={
                                {
                                    animationDelay: `${i * 0.18}s`,
                                    '--ev-orbit-radius': '68px',
                                    '--ev-orbit-spin': '320deg',
                                } as CSSProperties
                            }
                        />
                    </div>
                ))}

                {/* Aura / glow */}
                <div className="ev-glow absolute size-20 rounded-full bg-emerald-400/70 blur-xl" />

                {/* Núcleo */}
                <div className="ev-core relative flex size-14 items-center justify-center rounded-full bg-linear-to-br from-emerald-200 via-emerald-400 to-emerald-600 shadow-[0_0_25px_8px_rgba(16,185,129,0.55)]">
                    <Radar
                        className="size-6 text-emerald-950 drop-shadow-sm"
                        strokeWidth={2.5}
                    />
                </div>
            </div>

            {/* ── Texto de estado (cicla) ───────────────────────── */}
            <div className="mt-6 flex min-h-10 items-center justify-center px-2">
                <p
                    key={messageIndex}
                    className="animate-in fade-in slide-in-from-bottom-1 text-sm font-medium text-emerald-50 duration-500"
                >
                    {MESSAGES[messageIndex]}
                </p>
            </div>

            {/* ── Barra de progreso indeterminada ───────────────── */}
            <div className="mt-4 h-1.5 w-full overflow-hidden rounded-full bg-white/10">
                <div className="ev-shimmer h-full w-1/3 rounded-full bg-linear-to-r from-transparent via-emerald-400 to-transparent" />
            </div>

            <p className="mt-4 text-xs text-slate-400">
                Esto tarda solo unos segundos. No cierres esta ventana.
            </p>
        </>
    );
}

type ScrapingLoaderModalProps = {
    open: boolean;
};

/**
 * Modal de carga mostrado mientras corre el scraping manual
 * ("Traer nuevos"). No se puede cerrar mientras `open` es true — el
 * propio request lo cierra al terminar.
 */
export function ScrapingLoaderModal({ open }: ScrapingLoaderModalProps) {
    return (
        <Dialog open={open} onOpenChange={() => {}}>
            <DialogContent
                hideCloseButton
                className="flex flex-col items-center gap-0 overflow-hidden border-emerald-500/20 bg-linear-to-b from-slate-950 via-slate-900 to-slate-950 p-8 text-center shadow-2xl shadow-emerald-500/20 sm:max-w-sm"
                onPointerDownOutside={(e) => e.preventDefault()}
                onInteractOutside={(e) => e.preventDefault()}
                onEscapeKeyDown={(e) => e.preventDefault()}
            >
                <DialogTitle className="sr-only">
                    Buscando veterinarias en Perú
                </DialogTitle>

                {open && <EnergyBallLoader />}
            </DialogContent>
        </Dialog>
    );
}
