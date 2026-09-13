import { Cake, CalendarDays, Clock, MessageCircle, Syringe } from 'lucide-react';
import type { ComponentType } from 'react';
import { AnimatedList } from '@/components/ui/animated-list';
import { PointerGlare, pointerGlareLeave, pointerGlareMove } from '@/components/ui/pointer-glare';
import { cn } from '@/lib/utils';

type ReminderNotice = {
    title: string;
    subtitle: string;
    time: string;
    color: string;
    icon: ComponentType<{ className?: string }>;
};

const NOTICES: ReminderNotice[] = [
    {
        title: 'Recordatorio de cita',
        subtitle: 'WhatsApp enviado',
        time: 'hace 2 min',
        color: '#3B82F6',
        icon: CalendarDays,
    },
    {
        title: 'Vacuna próxima',
        subtitle: 'Aviso al propietario',
        time: 'hace 5 min',
        color: '#EC4899',
        icon: Syringe,
    },
    {
        title: 'Recordatorio 2 h',
        subtitle: 'Consulta de esta tarde',
        time: 'hace 10 min',
        color: '#F59E0B',
        icon: Clock,
    },
    {
        title: 'Cumpleaños',
        subtitle: 'Saludo automático',
        time: 'hace 15 min',
        color: '#10B981',
        icon: Cake,
    },
    {
        title: 'Confirmación',
        subtitle: 'Mensaje entregado',
        time: 'hace 2 min',
        color: '#6366F1',
        icon: MessageCircle,
    },
];

function ReminderCard({ title, subtitle, time, color, icon: Icon }: ReminderNotice) {
    return (
        <div className="flex items-center gap-3 rounded-2xl border border-black/[0.04] bg-white px-3 py-2.5 shadow-[0_8px_24px_-12px_rgba(0,0,0,0.18)] dark:border-white/10 dark:bg-zinc-900/80">
            <span
                className="flex size-9 shrink-0 items-center justify-center rounded-full text-white"
                style={{ backgroundColor: color }}
            >
                <Icon className="size-4" />
            </span>
            <div className="min-w-0 flex-1 text-left">
                <p className="truncate text-[0.8rem] font-semibold text-zinc-800 dark:text-zinc-100">
                    {title}
                    <span className="font-normal text-zinc-400"> · {time}</span>
                </p>
                <p className="truncate text-[0.7rem] text-zinc-500">{subtitle}</p>
            </div>
        </div>
    );
}

/**
 * Lista animada de envíos de recordatorio (Magic UI) para el login.
 */
export function AuthRemindersAnimatedList({ className }: { className?: string }) {
    return (
        <div
            aria-hidden
            className={cn(
                'pointer-events-auto absolute w-[17.5rem] overflow-hidden rounded-2xl border border-border/60 bg-card/85 p-3 text-left shadow-[0_20px_60px_-30px_rgba(0,40,30,0.35)] backdrop-blur-xl dark:bg-card/60',
                className,
            )}
            onPointerMove={pointerGlareMove}
            onPointerLeave={pointerGlareLeave}
        >
            <PointerGlare />
            <div className="relative h-[17.5rem] overflow-hidden">
                <AnimatedList delay={1700} className="px-0.5">
                    {NOTICES.map((notice) => (
                        <ReminderCard key={notice.title} {...notice} />
                    ))}
                </AnimatedList>
                <div className="pointer-events-none absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-card via-card/80 to-transparent dark:from-card" />
            </div>
            <p className="mt-1 px-1 text-sm font-semibold text-foreground">Recordatorios</p>
            <p className="px-1 text-xs leading-relaxed text-muted-foreground">
                Citas, vacunas y saludos se envían solos por WhatsApp.
            </p>
        </div>
    );
}
