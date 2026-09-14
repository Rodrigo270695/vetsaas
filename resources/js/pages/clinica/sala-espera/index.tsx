import { Head, Link, router } from '@inertiajs/react';
import {
    Bath,
    Check,
    Clock3,
    FolderOpen,
    Megaphone,
    Search,
    Stethoscope,
    UserRound,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { SalaEsperaEnviarButton } from '@/components/sala-espera-enviar-button';
import { SALA_ESPERA_CHANGED_EVENT } from '@/components/sala-espera-header-popover';
import { Button } from '@/components/ui/button';
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
    const { t } = useTranslation('common');
    const [consulta, setConsulta] = useState(board.consulta);
    const [grooming, setGrooming] = useState(board.grooming);
    const [q, setQ] = useState('');
    const [hits, setHits] = useState<SearchHit[]>([]);
    const [searching, setSearching] = useState(false);
    const [llamados, setLlamados] = useState<Record<string, boolean>>({});
    const [clock, setClock] = useState(() =>
        new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
    );

    useEffect(() => {
        setConsulta(board.consulta);
        setGrooming(board.grooming);
    }, [board]);

    useEffect(() => {
        const id = window.setInterval(() => {
            setClock(
                new Date().toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit',
                }),
            );
        }, 15_000);

        return () => window.clearInterval(id);
    }, []);

    const reloadBoard = useCallback(() => {
        router.reload({
            only: ['board'],
            preserveScroll: true,
            preserveState: true,
        });
    }, []);

    useEffect(() => {
        const id = window.setInterval(reloadBoard, 15_000);
        const onChanged = () => reloadBoard();
        window.addEventListener(SALA_ESPERA_CHANGED_EVENT, onChanged);

        return () => {
            window.clearInterval(id);
            window.removeEventListener(SALA_ESPERA_CHANGED_EVENT, onChanged);
        };
    }, [reloadBoard]);

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

    const mark = useCallback(async (item: SalaItem) => {
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
        }
    }, []);

    const waiting = useMemo(
        () => queueTotal(consulta) + queueTotal(grooming),
        [consulta, grooming],
    );

    return (
        <>
            <Head title={t('sala_espera.title')} />

            <div className="flex min-h-0 min-w-0 flex-1 flex-col gap-4 p-4 md:p-6">
                <header className="flex flex-col gap-4 rounded-2xl border border-border/70 bg-card px-4 py-4 shadow-sm md:flex-row md:items-center md:justify-between md:px-5">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-xl font-semibold tracking-tight md:text-2xl">
                                {t('sala_espera.title')}
                            </h1>
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-700 ring-1 ring-emerald-500/20 dark:text-emerald-300">
                                <span className="size-1.5 rounded-full bg-emerald-500" />
                                {t('sala_espera.live')}
                            </span>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('sala_espera.count_waiting', { count: waiting })}
                        </p>
                    </div>
                    <div className="flex items-center gap-3 text-muted-foreground">
                        <Clock3 className="size-4" />
                        <span className="font-mono text-2xl font-semibold tabular-nums tracking-tight text-foreground">
                            {clock}
                        </span>
                    </div>
                </header>

                {board.can_enviar ? (
                    <section className="rounded-2xl border border-border/70 bg-card p-3 shadow-sm md:p-4">
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder={t('sala_espera.search_placeholder')}
                                className="h-12 rounded-xl border-border/80 bg-muted/30 pl-10 text-base shadow-none"
                                autoComplete="off"
                            />
                        </div>
                        {q.trim().length === 0 ? (
                            <p className="mt-2 px-0.5 text-xs text-muted-foreground">
                                {t('sala_espera.search_hint')}
                            </p>
                        ) : null}
                        {q.trim().length > 0 && q.trim().length < 2 ? (
                            <p className="mt-3 text-sm text-muted-foreground">
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
                                            className="flex items-center gap-3 rounded-xl border border-border/60 bg-muted/20 px-3 py-2.5"
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
                    </section>
                ) : null}

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
                            onLlamar={(item) => {
                                llamarTurno(item, t('sala_espera.cita'));
                                setLlamados((prev) => ({
                                    ...prev,
                                    [`${item.tipo}-${item.id}`]: true,
                                }));
                            }}
                            onMarcar={mark}
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
                            onLlamar={(item) => {
                                llamarTurno(item, t('sala_espera.grooming'));
                                setLlamados((prev) => ({
                                    ...prev,
                                    [`${item.tipo}-${item.id}`]: true,
                                }));
                            }}
                            onMarcar={mark}
                        />
                    ) : null}
                </div>
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
    onLlamar,
    onMarcar,
}: {
    title: string;
    emptyLabel: string;
    icon: typeof Stethoscope;
    accent: 'sky' | 'violet';
    queue: SalaQueue;
    canMarcar: boolean;
    llamados: Record<string, boolean>;
    onLlamar: (item: SalaItem) => void;
    onMarcar: (item: SalaItem) => void;
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
                                            onLlamar={() => onLlamar(item)}
                                            onMarcar={() => onMarcar(item)}
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
    onLlamar,
    onMarcar,
}: {
    item: SalaItem;
    accent: 'sky' | 'violet';
    called: boolean;
    canMarcar: boolean;
    onLlamar: () => void;
    onMarcar: () => void;
}) {
    const { t } = useTranslation('common');
    const longWait = item.minutos_espera >= 20;
    const isViolet = accent === 'violet';

    return (
        <article
            className={cn(
                'relative overflow-hidden rounded-2xl border bg-background p-3 shadow-sm transition-shadow md:p-3.5',
                called
                    ? 'border-amber-400/70 ring-2 ring-amber-300/50'
                    : 'border-border/70 hover:border-border',
            )}
        >
            <span
                className={cn(
                    'absolute inset-y-0 left-0 w-1',
                    called
                        ? 'bg-amber-400'
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
                                'shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums',
                                longWait
                                    ? 'bg-amber-500/15 text-amber-800 dark:text-amber-200'
                                    : 'bg-muted text-muted-foreground',
                            )}
                        >
                            {t('sala_espera.minutos', {
                                count: item.minutos_espera,
                            })}
                        </span>
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {[item.especie, item.hora].filter(Boolean).join(' · ')}
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
                            asChild
                        >
                            <Link href={item.hc_href}>
                                <FolderOpen className="size-3.5" />
                                {t('sala_espera.hc')}
                            </Link>
                        </Button>
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
