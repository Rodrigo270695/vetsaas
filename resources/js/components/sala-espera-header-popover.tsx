import { Link, usePage } from '@inertiajs/react';
import { CalendarClock, Scissors } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { usePermission } from '@/hooks/use-permission';
import { useTenantModuleEnabled } from '@/hooks/use-tenant-modules';
import { cn } from '@/lib/utils';

type SalaItem = {
    id: string;
    tipo: 'cita' | 'grooming';
    paciente: string;
    hora: string;
    estado: string;
    href: string;
};

type SalaPayload = {
    fecha: string;
    count: number;
    espera: SalaItem[];
    en_curso: SalaItem[];
};

const EMPTY: SalaPayload = {
    fecha: '',
    count: 0,
    espera: [],
    en_curso: [],
};

export function SalaEsperaHeaderPopover() {
    const { t } = useTranslation('common');
    const { tenant } = usePage().props;
    const { can } = usePermission();
    const citasOn = useTenantModuleEnabled('citas');
    const groomingOn = useTenantModuleEnabled('grooming');
    const canCitas = can('citas.view') && citasOn;
    const canGrooming = can('grooming.view') && groomingOn;
    const visible = tenant != null && (canCitas || canGrooming);

    const [open, setOpen] = useState(false);
    const [data, setData] = useState<SalaPayload>(EMPTY);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(false);

    const load = useCallback(async () => {
        if (!visible) {
            return;
        }

        setLoading(true);
        setError(false);
        try {
            const res = await fetch('/clinica/sala-espera', {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!res.ok) {
                setError(true);
                return;
            }
            const json = (await res.json()) as SalaPayload;
            setData({
                fecha: json.fecha ?? '',
                count: json.count ?? 0,
                espera: Array.isArray(json.espera) ? json.espera : [],
                en_curso: Array.isArray(json.en_curso) ? json.en_curso : [],
            });
        } catch {
            setError(true);
        } finally {
            setLoading(false);
        }
    }, [visible]);

    useEffect(() => {
        if (!visible) {
            return;
        }

        void load();
        const id = window.setInterval(() => {
            void load();
        }, 60_000);

        return () => window.clearInterval(id);
    }, [visible, load]);

    if (!visible) {
        return null;
    }

    const badge = data.count > 99 ? '99+' : String(data.count);

    return (
        <Popover
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (next) {
                    void load();
                }
            }}
        >
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="relative size-9 cursor-pointer text-muted-foreground hover:text-foreground"
                    aria-label={t('sala_espera.title')}
                >
                    <CalendarClock className="size-4" strokeWidth={2.1} />
                    {data.count > 0 ? (
                        <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-500 px-1 text-[10px] font-semibold text-white">
                            {badge}
                        </span>
                    ) : null}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-[22rem] p-0" sideOffset={8}>
                <div className="border-b border-border/60 px-3 py-2.5">
                    <p className="text-sm font-semibold text-foreground">
                        {t('sala_espera.title')}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {t('sala_espera.subtitle')}
                    </p>
                </div>

                <div className="max-h-80 overflow-y-auto p-2">
                    {error ? (
                        <p className="px-2 py-6 text-center text-sm text-destructive">
                            {t('sala_espera.error')}
                        </p>
                    ) : data.espera.length === 0 && data.en_curso.length === 0 ? (
                        <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                            {loading
                                ? t('actions.loading')
                                : t('sala_espera.empty')}
                        </p>
                    ) : (
                        <>
                            <SalaEsperaGroup
                                items={data.espera.filter((i) => i.tipo === 'cita')}
                                title={t('sala_espera.cita')}
                                onNavigate={() => setOpen(false)}
                            />
                            <SalaEsperaGroup
                                items={data.espera.filter((i) => i.tipo === 'grooming')}
                                title={t('sala_espera.grooming')}
                                onNavigate={() => setOpen(false)}
                            />
                            {data.en_curso.length > 0 ? (
                                <>
                                    <p className="mt-2 px-2 pb-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                        {t('sala_espera.en_curso')}
                                    </p>
                                    {data.en_curso.map((item) => (
                                        <SalaEsperaRow
                                            key={`curso-${item.tipo}-${item.id}`}
                                            item={item}
                                            tipoLabel={t(
                                                `sala_espera.${item.tipo}`,
                                            )}
                                            muted
                                            onNavigate={() => setOpen(false)}
                                        />
                                    ))}
                                </>
                            ) : null}
                        </>
                    )}
                </div>

                <div className="flex gap-1 border-t border-border/60 p-2">
                    {canCitas ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8 flex-1 cursor-pointer text-xs"
                            asChild
                        >
                            <Link
                                href="/clinica/citas"
                                onClick={() => setOpen(false)}
                            >
                                {t('sala_espera.ver_citas')}
                            </Link>
                        </Button>
                    ) : null}
                    {canGrooming ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8 flex-1 cursor-pointer text-xs"
                            asChild
                        >
                            <Link
                                href="/servicios/grooming"
                                onClick={() => setOpen(false)}
                            >
                                {t('sala_espera.ver_grooming')}
                            </Link>
                        </Button>
                    ) : null}
                </div>
            </PopoverContent>
        </Popover>
    );
}

function SalaEsperaGroup({
    items,
    title,
    onNavigate,
}: {
    items: SalaItem[];
    title: string;
    onNavigate: () => void;
}) {
    if (items.length === 0) {
        return null;
    }

    return (
        <div className="mb-1">
            <p className="px-2 pb-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                {title}
            </p>
            {items.map((item) => (
                <SalaEsperaRow
                    key={`${item.tipo}-${item.id}`}
                    item={item}
                    tipoLabel={title}
                    onNavigate={onNavigate}
                />
            ))}
        </div>
    );
}

function SalaEsperaRow({
    item,
    tipoLabel,
    muted = false,
    onNavigate,
}: {
    item: SalaItem;
    tipoLabel: string;
    muted?: boolean;
    onNavigate: () => void;
}) {
    const isGrooming = item.tipo === 'grooming';

    return (
        <Link
            href={item.href}
            onClick={onNavigate}
            className={cn(
                'flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-muted/70',
                muted && 'opacity-80',
            )}
        >
            {isGrooming ? (
                <Scissors className="size-3.5 shrink-0 text-violet-600 dark:text-violet-300" />
            ) : (
                <CalendarClock className="size-3.5 shrink-0 text-sky-600 dark:text-sky-300" />
            )}
            <span className="min-w-0 flex-1 truncate font-medium text-foreground">
                {item.paciente}
            </span>
            <span className="shrink-0 font-mono text-xs tabular-nums text-muted-foreground">
                {item.hora}
            </span>
            <span
                className={cn(
                    'shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold',
                    isGrooming
                        ? 'bg-violet-500/15 text-violet-800 dark:text-violet-200'
                        : 'bg-sky-500/15 text-sky-800 dark:text-sky-200',
                )}
            >
                {tipoLabel}
            </span>
        </Link>
    );
}
