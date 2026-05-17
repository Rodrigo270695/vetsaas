import {
    MessageCircle,
    Receipt,
    ShieldCheck,
    Stethoscope,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import { cn } from '@/lib/utils';

type BentoCardProps = {
    icon: ComponentType<{ className?: string }>;
    eyebrow: string;
    title: string;
    accent?: ReactNode;
    className?: string;
};

function BentoCard({
    icon: Icon,
    eyebrow,
    title,
    accent,
    className,
}: BentoCardProps) {
    return (
        <div
            aria-hidden="true"
            className={cn(
                'animate-in fade-in slide-in-from-bottom-3 absolute w-60 rounded-2xl border border-border/60 bg-card/80 p-4 text-left shadow-[0_20px_60px_-30px_rgba(0,40,30,0.35)] backdrop-blur-xl duration-700 ease-out dark:bg-card/60 dark:shadow-[0_20px_60px_-30px_rgba(0,0,0,0.7)]',
                className,
            )}
        >
            <div className="flex items-center gap-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                <span className="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary ring-1 ring-primary/15">
                    <Icon className="size-3.5" />
                </span>
                {eyebrow}
            </div>
            <p className="mt-3 text-sm font-semibold text-foreground">{title}</p>
            {accent && (
                <div className="mt-1 text-xs text-muted-foreground">
                    {accent}
                </div>
            )}
        </div>
    );
}

/**
 * Cuatro tarjetas decorativas posicionadas alrededor del formulario.
 * Comunican el valor del producto (no datos reales del usuario aún).
 * Solo se muestran en xl+ (≥1280px).
 */
export default function AuthBentoOrbit() {
    return (
        <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 z-0 hidden xl:block"
        >
            <BentoCard
                icon={Stethoscope}
                eyebrow="Historia clínica"
                title="SOAP completo"
                accent={
                    <div className="mt-2 flex flex-wrap gap-1.5">
                        {['Vacunas', 'Recetas', 'Lab', 'Cirugía'].map((chip) => (
                            <span
                                key={chip}
                                className="rounded-md bg-primary/8 px-1.5 py-0.5 text-[0.7rem] font-medium text-primary ring-1 ring-primary/15"
                            >
                                {chip}
                            </span>
                        ))}
                    </div>
                }
                className="top-[18%] left-[6%] -rotate-3 delay-100"
            />

            <BentoCard
                icon={MessageCircle}
                eyebrow="Recordatorios"
                title="WhatsApp automático"
                accent={
                    <span className="inline-flex items-center gap-1.5 rounded-md bg-success/12 px-1.5 py-0.5 text-[0.7rem] font-medium text-success">
                        Hasta −30% no-shows
                    </span>
                }
                className="top-[14%] right-[6%] rotate-3 delay-200"
            />

            <BentoCard
                icon={Receipt}
                eyebrow="Facturación"
                title="SUNAT integrada"
                accent={
                    <span className="inline-flex items-center gap-1.5 text-[0.7rem] text-muted-foreground">
                        <span className="size-1.5 rounded-full bg-success" />
                        Boletas y facturas en segundos
                    </span>
                }
                className="bottom-[14%] left-[7%] rotate-2 delay-300"
            />

            <BentoCard
                icon={ShieldCheck}
                eyebrow="Seguridad"
                title="Datos cifrados"
                accent={
                    <span className="text-[0.7rem] text-muted-foreground">
                        AES-256 · Backups diarios · Ley 29733
                    </span>
                }
                className="right-[8%] bottom-[18%] -rotate-2 delay-[450ms]"
            />
        </div>
    );
}
