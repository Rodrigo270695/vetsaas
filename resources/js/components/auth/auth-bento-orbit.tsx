import {
    Receipt,
    ShieldCheck,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import { AuthPetFolderCard } from '@/components/auth/auth-pet-folder-card';
import { AuthRemindersAnimatedList } from '@/components/auth/auth-reminders-animated-list';
import { BorderBeam } from '@/components/ui/border-beam';
import { PointerGlare, pointerGlareLeave, pointerGlareMove } from '@/components/ui/pointer-glare';
import { Ripple } from '@/components/ui/ripple';
import { cn } from '@/lib/utils';

type BentoCardProps = {
    icon: ComponentType<{ className?: string }>;
    eyebrow: string;
    title: string;
    accent?: ReactNode;
    className?: string;
    effect?: 'sunat' | 'security';
};

function BentoCard({
    icon: Icon,
    eyebrow,
    title,
    accent,
    className,
    effect,
}: BentoCardProps) {
    return (
        <div
            aria-hidden="true"
            className={cn(
                'animate-in fade-in slide-in-from-bottom-3 pointer-events-auto absolute w-60 overflow-hidden rounded-2xl border border-border/60 bg-card/80 p-4 text-left shadow-[0_20px_60px_-30px_rgba(0,40,30,0.35)] backdrop-blur-xl duration-700 ease-out dark:bg-card/60 dark:shadow-[0_20px_60px_-30px_rgba(0,0,0,0.7)]',
                className,
            )}
            onPointerMove={pointerGlareMove}
            onPointerLeave={pointerGlareLeave}
        >
            <PointerGlare />
            {effect === 'sunat' ? (
                <BorderBeam duration={7} className="auth-border-beam-sunat" />
            ) : null}
            {effect === 'security' ? (
                <>
                    <Ripple />
                    <BorderBeam duration={10} reverse />
                </>
            ) : null}
            <div className="relative z-[1]">
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
            <AuthPetFolderCard className="top-[12%] left-[5%] -rotate-2 delay-100" />

            <AuthRemindersAnimatedList className="top-[8%] right-[4%] rotate-1 delay-200" />

            <BentoCard
                icon={Receipt}
                eyebrow="Facturación"
                title="SUNAT integrada"
                effect="sunat"
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
                effect="security"
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
