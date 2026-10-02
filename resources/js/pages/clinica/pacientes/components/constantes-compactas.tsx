import { ChevronDown, ChevronUp, Droplets, HeartPulse, Scale, Thermometer, Wind } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

export type ConstanteKey =
    | 'peso_kg'
    | 'temperatura_c'
    | 'fc_lpm'
    | 'fr_rpm'
    | 'tlc'
    | 'pa'
    | 'hidratacion';

export type ConstantesValores = Record<ConstanteKey, string>;

type Modo = 'decimal' | 'entero';

const CAMPOS: readonly {
    key: ConstanteKey;
    modo: Modo;
    step: number | null;
    icon?: LucideIcon;
    corto?: string;
}[] = [
    { key: 'temperatura_c', modo: 'decimal', step: 0.1, icon: Thermometer },
    { key: 'fc_lpm', modo: 'entero', step: 1, icon: HeartPulse },
    { key: 'fr_rpm', modo: 'entero', step: 1, icon: Wind },
    { key: 'peso_kg', modo: 'decimal', step: null, icon: Scale },
    { key: 'hidratacion', modo: 'entero', step: null, icon: Droplets },
    { key: 'tlc', modo: 'decimal', step: null, corto: 'TLC' },
    { key: 'pa', modo: 'entero', step: null, corto: 'PA' },
];

export function soloNumero(value: string | null | undefined, modo: Modo): string {
    if (value == null) {
        return '';
    }

    const raw = String(value);
    if (modo === 'entero') {
        return raw.replace(/\D/g, '').slice(0, 4);
    }

    const cleaned = raw.replace(',', '.').replace(/[^\d.]/g, '');
    const parts = cleaned.split('.');
    const entero = (parts[0] ?? '').slice(0, 4);
    if (parts.length < 2) {
        return entero;
    }

    return `${entero}.${parts.slice(1).join('').replace(/\./g, '').slice(0, 2)}`;
}

function ajustar(actual: string, delta: number, modo: Modo): string {
    const base = Number.parseFloat(actual.replace(',', '.'));
    const siguiente = (Number.isFinite(base) ? base : 0) + delta;
    if (siguiente < 0) {
        return '';
    }

    const texto = modo === 'entero' ? String(Math.round(siguiente)) : siguiente.toFixed(1).replace(/\.0$/, '');

    return soloNumero(texto, modo);
}

type Props = {
    title: string;
    labels: Record<ConstanteKey, string>;
    values: ConstantesValores;
    disabled?: boolean;
    onChange: (key: ConstanteKey, value: string) => void;
};

export function ConstantesCompactas({ title, labels, values, disabled = false, onChange }: Props) {
    return (
        <section className="rounded-xl border bg-card px-3 py-2.5">
            <h2 className="mb-2 text-sm font-semibold">{title}</h2>
            <TooltipProvider>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 xl:grid-cols-7">
                    {CAMPOS.map((campo) => {
                        const Icon = campo.icon;

                        return (
                            <Tooltip key={campo.key}>
                                <TooltipTrigger asChild>
                                    <div
                                        className={cn(
                                            'flex h-9 min-w-0 items-center overflow-hidden rounded-md border border-amber-200/80 bg-amber-50/90 dark:border-amber-900/50 dark:bg-amber-950/30',
                                            disabled && 'opacity-70',
                                        )}
                                    >
                                        <span className="flex w-8 shrink-0 items-center justify-center text-[11px] font-semibold text-muted-foreground">
                                            {Icon ? <Icon className="size-3.5" /> : campo.corto}
                                        </span>
                                        <input
                                            aria-label={labels[campo.key]}
                                            inputMode={campo.modo === 'entero' ? 'numeric' : 'decimal'}
                                            disabled={disabled}
                                            value={values[campo.key]}
                                            className="h-full min-w-0 flex-1 bg-transparent pr-1 text-sm outline-none placeholder:text-muted-foreground/70"
                                            onChange={(event) =>
                                                onChange(campo.key, soloNumero(event.target.value, campo.modo))
                                            }
                                        />
                                        {campo.step != null && !disabled ? (
                                            <span className="flex h-full shrink-0 flex-col border-l border-amber-200/80 dark:border-amber-900/50">
                                                <button
                                                    type="button"
                                                    tabIndex={-1}
                                                    className="flex flex-1 cursor-pointer items-center px-1 text-muted-foreground hover:text-foreground"
                                                    onClick={() =>
                                                        onChange(
                                                            campo.key,
                                                            ajustar(values[campo.key], campo.step ?? 1, campo.modo),
                                                        )
                                                    }
                                                >
                                                    <ChevronUp className="size-3" />
                                                </button>
                                                <button
                                                    type="button"
                                                    tabIndex={-1}
                                                    className="flex flex-1 cursor-pointer items-center px-1 text-muted-foreground hover:text-foreground"
                                                    onClick={() =>
                                                        onChange(
                                                            campo.key,
                                                            ajustar(values[campo.key], -(campo.step ?? 1), campo.modo),
                                                        )
                                                    }
                                                >
                                                    <ChevronDown className="size-3" />
                                                </button>
                                            </span>
                                        ) : null}
                                    </div>
                                </TooltipTrigger>
                                <TooltipContent>{labels[campo.key]}</TooltipContent>
                            </Tooltip>
                        );
                    })}
                </div>
            </TooltipProvider>
        </section>
    );
}
