import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Bath,
    Check,
    Clock3,
    FolderOpen,
    Megaphone,
    Search,
    Stethoscope,
    Timer,
    Trash2,
    UserRound,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { SalaEsperaEnviarButton } from '@/components/sala-espera-enviar-button';
import { SALA_ESPERA_CHANGED_EVENT } from '@/components/sala-espera-header-popover';
import { SALA_ESPERA_LLAMAR_EVENT } from '@/hooks/use-sala-espera-realtime';
import { ConsultaHistorialFloatingPanel } from '@/pages/clinica/historias-clinicas/components/consulta-historial-floating-panel';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type SalaItem = {
    id: string;
    tipo: 'consulta' | 'grooming';
    paciente: string;
    paciente_id: string | null;
    propietario: string;
    especie: string | null;
    foto_url: string | null;
    numero: number | null;
    hora: string;
    estado: string;
    motivo: string | null;
    minutos_espera: number;
    enviado_at?: string | null;
    href: string;
    hc_href: string;
};

type SalaQueue = {
    tipo: string;
    fecha: string;
    count: number;
    espera: SalaItem[];
    proximas: SalaItem[];
    en_curso: SalaItem[];
    can_marcar: boolean;
    visible: boolean;
};

type Board = {
    consulta: SalaQueue;
    grooming: SalaQueue;
    can_enviar: boolean;
    can_marcar: boolean;
    can_consulta: boolean;
    can_grooming: boolean;
};

type SearchHit = {
    id: string;
    nombre: string;
    especie: string | null;
    foto_url: string | null;
    propietario: string;
    propietario_id: string | null;
    href: string;
};

type Props = {
    board: Board;
};

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

function padTurno(n: number | null): string {
    if (n == null) {
        return '—';
    }

    return String(n).padStart(2, '0');
}

function pad2(n: number): string {
    return String(n).padStart(2, '0');
}

function formatWait(enviadoAt: string | null | undefined, now: Date, fallbackMin: number): string {
    if (!enviadoAt) {
        return `${fallbackMin}:00`;
    }
    const start = Date.parse(enviadoAt);
    if (Number.isNaN(start)) {
        return `${fallbackMin}:00`;
    }
    const totalSec = Math.max(0, Math.floor((now.getTime() - start) / 1000));
    const hours = Math.floor(totalSec / 3600);
    const minutes = Math.floor((totalSec % 3600) / 60);
    const seconds = totalSec % 60;
    if (hours > 0) {
        return `${hours}:${pad2(minutes)}:${pad2(seconds)}`;
    }

    return `${minutes}:${pad2(seconds)}`;
}

function formatIngreso(enviadoAt: string | null | undefined, fallbackHora: string, locale: string): string {
    if (enviadoAt) {
        const ms = Date.parse(enviadoAt);
        if (!Number.isNaN(ms)) {
            return new Date(ms).toLocaleTimeString(locale, {
                hour: '2-digit',
                minute: '2-digit',
            });
        }
    }

    return fallbackHora;
}

function waitSeconds(enviadoAt: string | null | undefined, now: Date, fallbackMin: number): number {
    if (!enviadoAt) {
        return fallbackMin * 60;
    }
    const start = Date.parse(enviadoAt);
    if (Number.isNaN(start)) {
        return fallbackMin * 60;
    }

    return Math.max(0, Math.floor((now.getTime() - start) / 1000));
}

function queueTotal(queue: SalaQueue): number {
    return queue.espera.length + queue.proximas.length + queue.en_curso.length;
}

function PacienteAvatar({
    fotoUrl,
    nombre,
    size = 'md',
}: {
    fotoUrl: string | null;
    nombre: string;
    size?: 'sm' | 'md' | 'lg';
}) {
    const cls =
        size === 'lg' ? 'size-16' : size === 'sm' ? 'size-10' : 'size-12';

    if (fotoUrl) {
        return (
            <img
                src={fotoUrl}
                alt=""
                className={cn(
                    cls,
                    'shrink-0 rounded-2xl object-cover ring-2 ring-white shadow-sm dark:ring-background',
                )}
            />
        );
    }

    return (
        <span
            className={cn(
                cls,
                'flex shrink-0 items-center justify-center rounded-2xl bg-muted text-base font-semibold text-muted-foreground ring-2 ring-white dark:ring-background',
            )}
        >
            {nombre.slice(0, 1).toUpperCase()}
        </span>
    );
}

function llamarTurno(item: SalaItem, colaLabel: string): void {
    const turno = item.numero != null ? `Turno ${item.numero}. ` : '';
    const text = `${turno}${item.paciente}. ${item.propietario}. ${colaLabel}.`;
    if (typeof window === 'undefined' || !('speechSynthesis' in window)) {
        return;
    }
    const utter = new SpeechSynthesisUtterance(text);
    utter.lang = 'es-PE';
    utter.rate = 0.95;
    window.speechSynthesis.cancel();
    window.speechSynthesis.speak(utter);
}

export default function SalaEsperaIndex({ board }: Props) {
    const { t, i18n } = useTranslation('common');
    const { auth, broadcast } = usePage().props;
    const myId = auth.user?.id ? String(auth.user.id) : '';
    const realtimeOn = Boolean(broadcast?.enabled && broadcast.key);
    const [consulta, setConsulta] = useState(board.consulta);
    const [grooming, setGrooming] = useState(board.grooming);
    const [q, setQ] = useState('');
    const [hits, setHits] = useState<SearchHit[]>([]);
    const [searching, setSearching] = useState(false);
    const [llamados, setLlamados] = useState<Record<string, boolean>>({});
    const [now, setNow] = useState(() => new Date());
    const [quitar, setQuitar] = useState<SalaItem | null>(null);
    const [hcPaciente, setHcPaciente] = useState<{
        id: string;
        nombre: string;
    } | null>(null);

    useEffect(() => {
        setConsulta(board.consulta);
        setGrooming(board.grooming);
    }, [board]);

    useEffect(() => {
        const id = window.setInterval(() => {
            setNow(new Date());
        }, 1_000);

        return () => window.clearInterval(id);
    }, []);

    const clockParts = useMemo(() => {
        const locale = i18n.language?.startsWith('en') ? 'en-US' : 'es-PE';
        const parts = new Intl.DateTimeFormat(locale, {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true,
        }).formatToParts(now);
        const grab = (type: Intl.DateTimeFormatPartTypes) =>
            parts.find((part) => part.type === type)?.value ?? '';

        return {
            hour: grab('hour'),
            minute: grab('minute'),
            second: grab('second'),
            dayPeriod: grab('dayPeriod'),
        };
    }, [i18n.language, now]);

    const reloadBoard = useCallback(() => {
        router.reload({
            only: ['board'],
            preserveScroll: true,
            preserveState: true,
        });
    }, []);

    useEffect(() => {
        const id = window.setInterval(reloadBoard, realtimeOn ? 45_000 : 8_000);
        const onChanged = () => reloadBoard();
        window.addEventListener(SALA_ESPERA_CHANGED_EVENT, onChanged);

        return () => {
            window.clearInterval(id);
            window.removeEventListener(SALA_ESPERA_CHANGED_EVENT, onChanged);
        };
    }, [realtimeOn, reloadBoard]);

    useEffect(() => {
        const term = q.trim();
        if (term.length < 2) {
            setHits([]);
            setSearching(false);
            return;
        }

        setSearching(true);
        const handle = window.setTimeout(() => {
            void (async () => {
                try {
                    const res = await fetch(
                        `/clinica/sala-espera/buscar?q=${encodeURIComponent(term)}`,
                        {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                        },
                    );
                    const json = (await res.json()) as { data?: SearchHit[] };
                    setHits(Array.isArray(json.data) ? json.data : []);
                } catch {
                    setHits([]);
                } finally {
                    setSearching(false);
                }
            })();
        }, 280);

        return () => window.clearTimeout(handle);
    }, [q]);

    const dropItem = useCallback((item: SalaItem) => {
        const drop = (queue: SalaQueue): SalaQueue => {
            const without = (rows: SalaItem[]) =>
                rows.filter((row) => !(row.id === item.id && row.tipo === item.tipo));

            return {
                ...queue,
                espera: without(queue.espera),
                proximas: without(queue.proximas),
                en_curso: without(queue.en_curso),
            };
        };
        if (item.tipo === 'grooming') {
            setGrooming(drop);
        } else {
            setConsulta(drop);
        }
    }, []);

    const mark = useCallback(async (item: SalaItem) => {
        dropItem(item);

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
        } else {
            reloadBoard();
        }
    }, [dropItem, reloadBoard]);

    const confirmQuitar = useCallback(async () => {
        if (!quitar) {
            return;
        }
        const item = quitar;
        setQuitar(null);
        dropItem(item);
        const res = await fetch(
            `/clinica/sala-espera/${item.tipo}/${item.id}/retirar`,
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
        } else {
            reloadBoard();
        }
    }, [dropItem, quitar, reloadBoard]);

    const callTurno = useCallback(
        async (item: SalaItem, colaLabel: string) => {
            llamarTurno(item, colaLabel);
            setLlamados((prev) => ({
                ...prev,
                [`${item.tipo}-${item.id}`]: true,
            }));
            await fetch(`/clinica/sala-espera/${item.tipo}/${item.id}/llamar`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
        },
        [],
    );

    useEffect(() => {
        const onLlamar = (event: Event) => {
            const detail = (event as CustomEvent).detail as {
                actor_id?: string | null;
                item?: Partial<SalaItem> & { id?: string; tipo?: string };
            };
            if (detail.actor_id && myId && String(detail.actor_id) === myId) {
                return;
            }
            const item = detail.item;
            if (!item?.id || !item.tipo) {
                return;
            }
            setLlamados((prev) => ({
                ...prev,
                [`${item.tipo}-${item.id}`]: true,
            }));
            llamarTurno(
                {
                    id: item.id,
                    tipo: item.tipo as 'consulta' | 'grooming',
                    paciente: item.paciente ?? '',
                    paciente_id: item.paciente_id ?? null,
                    propietario: item.propietario ?? '',
                    especie: item.especie ?? null,
                    foto_url: item.foto_url ?? null,
                    numero: item.numero ?? null,
                    hora: item.hora ?? '',
                    estado: item.estado ?? '',
                    motivo: item.motivo ?? null,
                    minutos_espera: item.minutos_espera ?? 0,
                    href: item.href ?? '',
                    hc_href: item.hc_href ?? '',
                },
                item.tipo === 'grooming'
                    ? t('sala_espera.grooming')
                    : t('sala_espera.cita'),
            );
        };
        window.addEventListener(SALA_ESPERA_LLAMAR_EVENT, onLlamar);

        return () => window.removeEventListener(SALA_ESPERA_LLAMAR_EVENT, onLlamar);
    }, [myId, t]);

    const waiting = useMemo(
        () => queueTotal(consulta) + queueTotal(grooming),
        [consulta, grooming],
    );

    return (
        <>
            <Head title={t('sala_espera.title')} />

            <div className="flex min-h-0 min-w-0 flex-1 flex-col gap-4 p-4 md:p-6">
                <header className="relative overflow-hidden rounded-2xl border border-sky-500/20 bg-gradient-to-r from-sky-50 via-card to-violet-50 px-4 py-5 shadow-sm md:flex-row md:items-center md:justify-between md:px-6 dark:from-sky-950/40 dark:via-card dark:to-violet-950/30">
                    <div className="pointer-events-none absolute -top-16 -left-10 size-40 rounded-full bg-sky-400/15 blur-3xl" />
                    <div className="pointer-events-none absolute -right-10 -bottom-20 size-44 rounded-full bg-violet-400/15 blur-3xl" />
                    <div className="relative flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-3">
                            <span className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-500 to-violet-600 text-white shadow-lg shadow-sky-500/25">
                                <Timer className="size-6" strokeWidth={2.25} />
                            </span>
                            <div className="min-w-0">
                                <p className="text-[11px] font-semibold tracking-[0.22em] text-sky-700 uppercase dark:text-sky-300">
                                    {t('sala_espera.kicker')}
                                </p>
                                <div className="mt-0.5 flex flex-wrap items-center gap-2">
                                    <h1 className="bg-gradient-to-r from-sky-800 via-slate-900 to-violet-700 bg-clip-text text-2xl font-bold tracking-tight text-transparent md:text-3xl dark:from-sky-200 dark:via-white dark:to-violet-200">
                                        {t('sala_espera.title')}
                                    </h1>
                                    <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-700 ring-1 ring-emerald-500/20 dark:text-emerald-300">
                                        <span className="relative flex size-2">
                                            <span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                                            <span className="relative inline-flex size-2 rounded-full bg-emerald-500" />
                                        </span>
                                        {t('sala_espera.live')}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {t('sala_espera.count_waiting', { count: waiting })}
                            {' · '}
                            {t('sala_espera.turnos_dia')}
                        </p>
                    </div>
                    <div className="flex items-center gap-3 rounded-2xl bg-muted/40 px-4 py-2 ring-1 ring-border/60">
                        <Clock3 className="size-5 text-sky-600" />
                        <p className="font-mono text-3xl font-semibold tabular-nums tracking-tight text-foreground md:text-4xl">
                            <span>{clockParts.hour}</span>
                            <span className={cn('mx-0.5 text-sky-500', now.getSeconds() % 2 === 0 ? 'opacity-100' : 'opacity-25')}>:</span>
                            <span>{clockParts.minute}</span>
                            <span className={cn('mx-0.5 text-sky-500', now.getSeconds() % 2 === 0 ? 'opacity-100' : 'opacity-25')}>:</span>
                            <span className="text-sky-600">{clockParts.second}</span>
                            {clockParts.dayPeriod ? (
                                <span className="ml-2 text-sm font-medium tracking-normal text-muted-foreground">
                                    {clockParts.dayPeriod}
                                </span>
                            ) : null}
                        </p>
                    </div>
                    </div>

                    {board.can_enviar ? (
                        <div className="relative mt-5 border-t border-sky-500/15 pt-4 dark:border-white/10">
                            <div className="relative">
                                <Search
                                    className="pointer-events-none absolute top-1/2 left-3.5 z-10 size-5 -translate-y-1/2 text-sky-600/80 dark:text-sky-400"
                                    strokeWidth={2.25}
                                    aria-hidden
                                />
                                <Input
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    placeholder={t('sala_espera.search_placeholder')}
                                    className="h-11 rounded-xl border-border/50 bg-white/70 pr-3 pl-11 text-base shadow-none backdrop-blur-sm dark:bg-background/50"
                                    autoComplete="off"
                                />
                            </div>
                            {q.trim().length === 0 ? (
                                <p className="mt-2 px-0.5 text-xs text-muted-foreground">
                                    {t('sala_espera.search_hint')}
                                </p>
                            ) : null}
                            {q.trim().length > 0 && q.trim().length < 2 ? (
                                <p className="mt-2 text-sm text-muted-foreground">
                                    {t('sala_espera.search_min')}
                                </p>
                            ) : null}
                            {q.trim().length >= 2 ? (
                                <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                    {searching && hits.length === 0 ? (
                                        <p className="px-1 py-3 text-sm text-muted-foreground sm:col-span-2">
                                            {t('actions.loading')}
                                        </p>
                                    ) : hits.length === 0 ? (
                                        <p className="px-1 py-3 text-sm text-muted-foreground sm:col-span-2">
                                            {t('sala_espera.search_empty')}
                                        </p>
                                    ) : (
                                        hits.map((hit) => (
                                            <div
                                                key={hit.id}
                                                className="flex items-center gap-3 rounded-xl border border-border/50 bg-white/60 px-3 py-2.5 dark:bg-background/40"
                                            >
                                                <PacienteAvatar
                                                    fotoUrl={hit.foto_url}
                                                    nombre={hit.nombre}
                                                    size="sm"
                                                />
                                                <div className="min-w-0 flex-1">
                                                    <Link
                                                        href={hit.href}
                                                        className="font-medium text-foreground hover:underline"
                                                    >
                                                        {hit.nombre}
                                                    </Link>
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {hit.especie
                                                            ? `${hit.especie} · `
                                                            : ''}
                                                        {hit.propietario}
                                                    </p>
                                                </div>
                                                <SalaEsperaEnviarButton
                                                    pacienteId={hit.id}
                                                    canConsulta={board.can_consulta}
                                                    canGrooming={board.can_grooming}
                                                />
                                            </div>
                                        ))
                                    )}
                                </div>
                            ) : null}
                        </div>
                    ) : null}
                </header>

                <div className="grid min-h-0 flex-1 gap-4 xl:grid-cols-2">
                    {board.can_consulta ? (
                        <ColaPanel
                            title={t('sala_espera.cita')}
                            emptyLabel={t('sala_espera.empty_consulta')}
                            icon={Stethoscope}
                            accent="sky"
                            queue={consulta}
                            canMarcar={board.can_marcar}
                            llamados={llamados}
                            now={now}
                            locale={i18n.language?.startsWith('en') ? 'en-US' : 'es-PE'}
                            onLlamar={(item) => {
                                void callTurno(item, t('sala_espera.cita'));
                            }}
                            onMarcar={mark}
                            onQuitar={setQuitar}
                            onHc={(item) => {
                                if (item.paciente_id) {
                                    setHcPaciente({
                                        id: item.paciente_id,
                                        nombre: item.paciente,
                                    });
                                }
                            }}
                        />
                    ) : null}
                    {board.can_grooming ? (
                        <ColaPanel
                            title={t('sala_espera.grooming')}
                            emptyLabel={t('sala_espera.empty_grooming')}
                            icon={Bath}
                            accent="violet"
                            queue={grooming}
                            canMarcar={board.can_marcar}
                            llamados={llamados}
                            now={now}
                            locale={i18n.language?.startsWith('en') ? 'en-US' : 'es-PE'}
                            onLlamar={(item) => {
                                void callTurno(item, t('sala_espera.grooming'));
                            }}
                            onMarcar={mark}
                            onQuitar={setQuitar}
                            onHc={(item) => {
                                if (item.paciente_id) {
                                    setHcPaciente({
                                        id: item.paciente_id,
                                        nombre: item.paciente,
                                    });
                                }
                            }}
                        />
                    ) : null}
                </div>

                <Dialog open={quitar !== null} onOpenChange={(open) => !open && setQuitar(null)}>
                    <DialogContent className="max-w-md">
                        <DialogHeader>
                            <DialogTitle>{t('sala_espera.quitar_title')}</DialogTitle>
                        </DialogHeader>
                        <p className="text-sm text-muted-foreground">
                            {t('sala_espera.quitar_body', {
                                name: quitar?.paciente ?? '',
                            })}
                        </p>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setQuitar(null)}>
                                {t('actions.cancel')}
                            </Button>
                            <Button type="button" variant="destructive" onClick={() => void confirmQuitar()}>
                                {t('sala_espera.quitar_ok')}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
                <ConsultaHistorialFloatingPanel
                    open={hcPaciente !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setHcPaciente(null);
                        }
                    }}
                    pacienteId={hcPaciente?.id ?? null}
                    pacienteNombre={hcPaciente?.nombre ?? null}
                />
            </div>
        </>
    );
}

function ColaPanel({
    title,
    emptyLabel,
    icon: Icon,
    accent,
    queue,
    canMarcar,
    llamados,
    now,
    locale,
    onLlamar,
    onMarcar,
    onQuitar,
    onHc,
}: {
    title: string;
    emptyLabel: string;
    icon: typeof Stethoscope;
    accent: 'sky' | 'violet';
    queue: SalaQueue;
    canMarcar: boolean;
    llamados: Record<string, boolean>;
    now: Date;
    locale: string;
    onLlamar: (item: SalaItem) => void;
    onMarcar: (item: SalaItem) => void;
    onQuitar: (item: SalaItem) => void;
    onHc: (item: SalaItem) => void;
}) {
    const { t } = useTranslation('common');
    const groups: { key: string; title: string; items: SalaItem[] }[] = [
        { key: 'espera', title: t('sala_espera.ahora'), items: queue.espera },
        {
            key: 'proximas',
            title: t('sala_espera.proximas'),
            items: queue.proximas,
        },
        {
            key: 'en_curso',
            title: t('sala_espera.en_curso'),
            items: queue.en_curso,
        },
    ];
    const total = queueTotal(queue);
    const isViolet = accent === 'violet';

    return (
        <section
            className={cn(
                'flex min-h-96 flex-col overflow-hidden rounded-2xl border bg-card shadow-sm',
                isViolet ? 'border-violet-500/20' : 'border-sky-500/20',
            )}
        >
            <header
                className={cn(
                    'flex items-center gap-3 border-b px-4 py-3.5',
                    isViolet
                        ? 'border-violet-500/15 bg-violet-500/5'
                        : 'border-sky-500/15 bg-sky-500/5',
                )}
            >
                <span
                    className={cn(
                        'flex size-10 items-center justify-center rounded-xl',
                        isViolet
                            ? 'bg-violet-600 text-white'
                            : 'bg-sky-600 text-white',
                    )}
                >
                    <Icon className="size-5" />
                </span>
                <div className="min-w-0 flex-1">
                    <h2 className="text-base font-semibold tracking-tight">
                        {title}
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        {t('sala_espera.count_waiting', { count: total })}
                    </p>
                </div>
                <span
                    className={cn(
                        'rounded-full px-2.5 py-1 text-sm font-semibold tabular-nums',
                        isViolet
                            ? 'bg-violet-600/10 text-violet-800 dark:text-violet-200'
                            : 'bg-sky-600/10 text-sky-800 dark:text-sky-200',
                    )}
                >
                    {total}
                </span>
            </header>
            <div className="flex-1 space-y-5 overflow-y-auto p-3 md:p-4">
                {total === 0 ? (
                    <div className="flex min-h-64 flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border/80 bg-muted/20 px-6 text-center">
                        <span
                            className={cn(
                                'flex size-14 items-center justify-center rounded-2xl',
                                isViolet
                                    ? 'bg-violet-500/10 text-violet-500'
                                    : 'bg-sky-500/10 text-sky-500',
                            )}
                        >
                            <Icon className="size-7" />
                        </span>
                        <p className="text-sm font-medium text-muted-foreground">
                            {emptyLabel}
                        </p>
                    </div>
                ) : (
                    groups.map((group) =>
                        group.items.length === 0 ? null : (
                            <div key={group.key} className="space-y-2.5">
                                <p className="flex items-center gap-2 px-0.5 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                    <span
                                        className={cn(
                                            'size-1.5 rounded-full',
                                            group.key === 'en_curso'
                                                ? 'bg-amber-500'
                                                : group.key === 'proximas'
                                                  ? 'bg-muted-foreground/40'
                                                  : isViolet
                                                    ? 'bg-violet-500'
                                                    : 'bg-sky-500',
                                        )}
                                    />
                                    {group.title}
                                    <span className="tabular-nums">
                                        {group.items.length}
                                    </span>
                                </p>
                                <div className="space-y-2.5">
                                    {group.items.map((item) => (
                                        <TurnoCard
                                            key={`${item.tipo}-${item.id}`}
                                            item={item}
                                            accent={accent}
                                            called={
                                                llamados[
                                                    `${item.tipo}-${item.id}`
                                                ] === true
                                            }
                                            canMarcar={canMarcar}
                                            now={now}
                                            locale={locale}
                                            onLlamar={() => onLlamar(item)}
                                            onMarcar={() => onMarcar(item)}
                                            onQuitar={() => onQuitar(item)}
                                            onHc={() => onHc(item)}
                                        />
                                    ))}
                                </div>
                            </div>
                        ),
                    )
                )}
            </div>
        </section>
    );
}

function TurnoCard({
    item,
    accent,
    called,
    canMarcar,
    now,
    locale,
    onLlamar,
    onMarcar,
    onQuitar,
    onHc,
}: {
    item: SalaItem;
    accent: 'sky' | 'violet';
    called: boolean;
    canMarcar: boolean;
    now: Date;
    locale: string;
    onLlamar: () => void;
    onMarcar: () => void;
    onQuitar: () => void;
    onHc: () => void;
}) {
    const { t } = useTranslation('common');
    const waited = waitSeconds(item.enviado_at, now, item.minutos_espera);
    const longWait = waited >= 20 * 60;
    const isViolet = accent === 'violet';

    return (
        <article
            className={cn(
                'relative overflow-hidden rounded-2xl border bg-background p-3 shadow-sm transition-all duration-300 md:p-3.5',
                called
                    ? 'border-amber-400/70 ring-2 ring-amber-300/60 shadow-amber-500/10'
                    : 'border-border/70 hover:-translate-y-0.5 hover:border-border hover:shadow-md',
            )}
        >
            <span
                className={cn(
                    'absolute inset-y-0 left-0 w-1.5',
                    called
                        ? 'animate-pulse bg-amber-400'
                        : isViolet
                          ? 'bg-violet-500'
                          : 'bg-sky-500',
                )}
            />
            <div className="flex gap-3 pl-2">
                <div className="flex w-17 shrink-0 flex-col items-center justify-center">
                    <span className="text-[10px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                        {t('sala_espera.turno')}
                    </span>
                    <span
                        className={cn(
                            'text-4xl font-bold tabular-nums leading-none tracking-tight',
                            isViolet ? 'text-violet-700 dark:text-violet-300' : 'text-sky-700 dark:text-sky-300',
                        )}
                    >
                        {padTurno(item.numero)}
                    </span>
                </div>
                <PacienteAvatar
                    fotoUrl={item.foto_url}
                    nombre={item.paciente}
                    size="lg"
                />
                <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0">
                            <p className="truncate text-lg font-semibold leading-tight">
                                {item.paciente}
                            </p>
                            <p className="mt-0.5 flex min-w-0 items-center gap-1 truncate text-sm text-muted-foreground">
                                <UserRound className="size-3.5 shrink-0" />
                                <span className="truncate">{item.propietario}</span>
                            </p>
                        </div>
                        <span
                            className={cn(
                                'shrink-0 rounded-full px-2 py-0.5 font-mono text-xs font-semibold tabular-nums',
                                longWait
                                    ? 'bg-amber-500/15 text-amber-800 dark:text-amber-200'
                                    : 'bg-muted text-muted-foreground',
                            )}
                        >
                            {formatWait(item.enviado_at, now, item.minutos_espera)}
                        </span>
                    </div>
                    <p className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-muted-foreground">
                        {item.especie ? <span>{item.especie}</span> : null}
                        {item.especie ? <span className="text-border">·</span> : null}
                        <span className="inline-flex items-center gap-1 font-medium text-foreground/80">
                            <Clock3 className="size-3 shrink-0 text-sky-600" />
                            {t('sala_espera.ingreso', {
                                time: formatIngreso(item.enviado_at, item.hora, locale),
                            })}
                        </span>
                    </p>
                    <div className="mt-3 flex flex-wrap gap-1.5">
                        <Button
                            type="button"
                            size="sm"
                            className={cn(
                                'h-8 cursor-pointer gap-1.5 px-3',
                                called
                                    ? 'bg-amber-500 text-white hover:bg-amber-500/90'
                                    : '',
                            )}
                            variant={called ? 'default' : 'default'}
                            onClick={onLlamar}
                        >
                            <Megaphone className="size-3.5" />
                            {called
                                ? t('sala_espera.llamado')
                                : t('sala_espera.llamar')}
                        </Button>
                        {canMarcar ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="h-8 cursor-pointer gap-1.5 border-emerald-500/30 px-3 text-emerald-700 hover:bg-emerald-500/10 dark:text-emerald-300"
                                onClick={onMarcar}
                            >
                                <Check className="size-3.5" />
                                {t('sala_espera.marcar')}
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="h-8 cursor-pointer gap-1.5 px-2.5 text-muted-foreground"
                            disabled={!item.paciente_id}
                            onClick={onHc}
                        >
                            <FolderOpen className="size-3.5" />
                            {t('sala_espera.hc')}
                        </Button>
                        {canMarcar ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                className="h-8 cursor-pointer gap-1.5 px-2.5 text-red-600 hover:bg-red-500/10 hover:text-red-700"
                                onClick={onQuitar}
                            >
                                <Trash2 className="size-3.5" />
                                {t('sala_espera.quitar')}
                            </Button>
                        ) : null}
                    </div>
                </div>
            </div>
        </article>
    );
}

SalaEsperaIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Sala de espera', href: '/clinica/sala-espera' },
    ],
};
