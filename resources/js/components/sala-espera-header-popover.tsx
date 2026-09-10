import { Link, usePage } from '@inertiajs/react';
import { Bath, CalendarDays, Check, Stethoscope } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
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
    tipo: 'consulta' | 'grooming';
    paciente: string;
    hora: string;
    estado: string;
    href: string;
};

type SalaPayload = {
    tipo: string;
    fecha: string;
    count: number;
    espera: SalaItem[];
    proximas: SalaItem[];
    en_curso: SalaItem[];
    can_marcar: boolean;
};

const EMPTY: SalaPayload = {
    tipo: '',
    fecha: '',
    count: 0,
    espera: [],
    proximas: [],
    en_curso: [],
    can_marcar: false,
};

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

function notifyOs(title: string, body: string): void {
    if (typeof Notification === 'undefined' || Notification.permission !== 'granted') {
        return;
    }

    try {
        new Notification(title, { body, silent: false });
    } catch {
        // El SO bloquea notificaciones en este contexto.
    }
}

export const SALA_ESPERA_CHANGED_EVENT = 'vetsaas:sala-espera-changed';

export function SalaEsperaHeaderIcons() {
    const { t } = useTranslation('common');
    const { tenant } = usePage().props;
    const { can } = usePermission();
    const citasOn = useTenantModuleEnabled('citas');
    const groomingOn = useTenantModuleEnabled('grooming');
    const canConsulta = can('sala-espera.consulta') && citasOn;
    const canGrooming = can('sala-espera.grooming') && groomingOn;
    const canCitas = can('citas.view') && citasOn;
    const canSala = canConsulta || canGrooming;
    const [visibles, setVisibles] = useState({ consulta: false, grooming: false });

    const loadResumen = useCallback(async () => {
        if (!canSala) {
            return;
        }
        try {
            const res = await fetch('/clinica/sala-espera/resumen', {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!res.ok) {
                return;
            }
            const json = (await res.json()) as {
                consulta?: boolean;
                grooming?: boolean;
            };
            setVisibles({
                consulta: json.consulta === true,
                grooming: json.grooming === true,
            });
        } catch {
            // El poll reintenta.
        }
    }, [canSala]);

    useEffect(() => {
        void loadResumen();
        if (!canSala) {
            return;
        }
        const id = window.setInterval(() => {
            void loadResumen();
        }, 20_000);
        const onChanged = () => {
            void loadResumen();
        };
        window.addEventListener(SALA_ESPERA_CHANGED_EVENT, onChanged);

        return () => {
            window.clearInterval(id);
            window.removeEventListener(SALA_ESPERA_CHANGED_EVENT, onChanged);
        };
    }, [canSala, loadResumen]);

    if (tenant == null) {
        return null;
    }

    return (
        <>
            {canCitas ? (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="relative size-9 cursor-pointer text-emerald-700 hover:bg-emerald-50 hover:text-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-950/40"
                    asChild
                >
                    <Link href="/clinica/citas" aria-label={t('sala_espera.ir_citas')} title={t('sala_espera.ir_citas')}>
                        <CalendarDays className="size-4" strokeWidth={2.25} />
                    </Link>
                </Button>
            ) : null}
            {canConsulta && visibles.consulta ? (
                <SalaEsperaTipoPopover tipo="consulta" />
            ) : null}
            {canGrooming && visibles.grooming ? (
                <SalaEsperaTipoPopover tipo="grooming" />
            ) : null}
        </>
    );
}

function SalaEsperaTipoPopover({ tipo }: { tipo: 'consulta' | 'grooming' }) {
    const { t } = useTranslation('common');
    const isGrooming = tipo === 'grooming';
    const [open, setOpen] = useState(false);
    const [data, setData] = useState<SalaPayload>(EMPTY);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(false);
    const prevCount = useRef<number | null>(null);

    const load = useCallback(async () => {
        setLoading(true);
        setError(false);
        try {
            const res = await fetch(`/clinica/sala-espera?tipo=${tipo}`, {
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
            const next: SalaPayload = {
                tipo: json.tipo ?? tipo,
                fecha: json.fecha ?? '',
                count: json.count ?? 0,
                espera: Array.isArray(json.espera) ? json.espera : [],
                proximas: Array.isArray(json.proximas) ? json.proximas : [],
                en_curso: Array.isArray(json.en_curso) ? json.en_curso : [],
                can_marcar: json.can_marcar === true,
            };

            if (prevCount.current !== null && next.count > prevCount.current) {
                notifyOs(
                    isGrooming
                        ? t('sala_espera.title_grooming')
                        : t('sala_espera.title_consulta'),
                    t('sala_espera.push_body', { count: next.count }),
                );
            }
            prevCount.current = next.count;
            setData(next);
        } catch {
            setError(true);
        } finally {
            setLoading(false);
        }
    }, [isGrooming, t, tipo]);

    useEffect(() => {
        void load();
        const id = window.setInterval(() => {
            void load();
        }, 20_000);

        return () => window.clearInterval(id);
    }, [load]);

    useEffect(() => {
        if (open && typeof Notification !== 'undefined' && Notification.permission === 'default') {
            void Notification.requestPermission();
        }
    }, [open]);

    const mark = useCallback(
        async (item: SalaItem) => {
            const res = await fetch(
                `/clinica/sala-espera/${item.tipo}/${item.id}/atendido`,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                },
            );
            if (res.ok) {
                window.dispatchEvent(new Event(SALA_ESPERA_CHANGED_EVENT));
                void load();
            }
        },
        [load],
    );

    const badge = data.count > 99 ? '99+' : String(data.count);
    const listLong =
        data.espera.length + data.proximas.length + data.en_curso.length > 6;

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
                    className={cn(
                        'relative size-9 cursor-pointer',
                        isGrooming
                            ? 'text-violet-600 hover:bg-violet-50 hover:text-violet-700 dark:text-violet-300 dark:hover:bg-violet-950/40'
                            : 'text-sky-600 hover:bg-sky-50 hover:text-sky-700 dark:text-sky-300 dark:hover:bg-sky-950/40',
                    )}
                    aria-label={
                        isGrooming
                            ? t('sala_espera.title_grooming')
                            : t('sala_espera.title_consulta')
                    }
                >
                    {isGrooming ? (
                        <Bath className="size-4" strokeWidth={2.25} />
                    ) : (
                        <Stethoscope className="size-4" strokeWidth={2.25} />
                    )}
                    {data.count > 0 ? (
                        <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-500 px-1 text-[10px] font-semibold text-white">
                            {badge}
                        </span>
                    ) : null}
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="end"
                className="flex w-[22rem] max-h-[min(28rem,70vh)] flex-col overflow-hidden p-0"
                sideOffset={8}
            >
                <div className="shrink-0 border-b border-border/60 px-3 py-2.5">
                    <p className="text-sm font-semibold text-foreground">
                        {isGrooming
                            ? t('sala_espera.title_grooming')
                            : t('sala_espera.title_consulta')}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {t('sala_espera.count_now', { count: data.count })}
                    </p>
                </div>

                <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain p-2">
                    {error ? (
                        <p className="px-2 py-6 text-center text-sm text-destructive">
                            {t('sala_espera.error')}
                        </p>
                    ) : data.espera.length === 0 &&
                      data.proximas.length === 0 &&
                      data.en_curso.length === 0 ? (
                        <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                            {loading
                                ? t('actions.loading')
                                : t('sala_espera.empty')}
                        </p>
                    ) : (
                        <>
                            <SalaGroup
                                title={t('sala_espera.ahora')}
                                items={data.espera}
                                canMarcar={data.can_marcar}
                                onMarcar={mark}
                                marcarLabel={t('sala_espera.marcar')}
                                onNavigate={() => setOpen(false)}
                            />
                            <SalaGroup
                                title={t('sala_espera.proximas')}
                                items={data.proximas}
                                canMarcar={false}
                                onMarcar={mark}
                                marcarLabel={t('sala_espera.marcar')}
                                onNavigate={() => setOpen(false)}
                            />
                            <SalaGroup
                                title={t('sala_espera.en_curso')}
                                items={data.en_curso}
                                canMarcar={data.can_marcar}
                                onMarcar={mark}
                                marcarLabel={t('sala_espera.marcar')}
                                onNavigate={() => setOpen(false)}
                            />
                        </>
                    )}
                </div>

                <div className="shrink-0 border-t border-border/60">
                    {listLong ? (
                        <p className="px-3 pt-2 text-[11px] text-muted-foreground">
                            {t('sala_espera.scroll_hint')}
                        </p>
                    ) : null}
                    <div className="p-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8 w-full cursor-pointer text-xs"
                            asChild
                        >
                            <Link
                                href={
                                    isGrooming
                                        ? '/servicios/grooming'
                                        : '/clinica/citas'
                                }
                                onClick={() => setOpen(false)}
                            >
                                {isGrooming
                                    ? t('sala_espera.ver_grooming')
                                    : t('sala_espera.ver_citas')}
                            </Link>
                        </Button>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}

function SalaGroup({
    title,
    items,
    canMarcar,
    onMarcar,
    marcarLabel,
    onNavigate,
}: {
    title: string;
    items: SalaItem[];
    canMarcar: boolean;
    onMarcar: (item: SalaItem) => void;
    marcarLabel: string;
    onNavigate: () => void;
}) {
    if (items.length === 0) {
        return null;
    }

    return (
        <div className="mb-1">
            <p className="sticky top-0 z-10 bg-popover px-2 py-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                {title}
                <span className="ml-1 tabular-nums">({items.length})</span>
            </p>
            {items.map((item) => (
                <div
                    key={`${item.tipo}-${item.id}`}
                    className="flex items-center gap-1 rounded-md pr-1 hover:bg-muted/70"
                >
                    <Link
                        href={item.href}
                        onClick={onNavigate}
                        className="flex min-w-0 flex-1 items-center gap-2 px-2 py-1.5 text-sm"
                    >
                        <span className="min-w-0 flex-1 truncate font-medium text-foreground">
                            {item.paciente}
                        </span>
                        <span className="shrink-0 font-mono text-xs tabular-nums text-muted-foreground">
                            {item.hora}
                        </span>
                    </Link>
                    {canMarcar ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-7 shrink-0 cursor-pointer text-emerald-600 hover:text-emerald-700"
                            title={marcarLabel}
                            aria-label={marcarLabel}
                            onClick={() => onMarcar(item)}
                        >
                            <Check className="size-3.5" strokeWidth={2.5} />
                        </Button>
                    ) : null}
                </div>
            ))}
        </div>
    );
}
