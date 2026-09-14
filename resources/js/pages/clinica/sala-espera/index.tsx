import { Head, Link, router } from '@inertiajs/react';
import { Bath, Check, Megaphone, Search, Stethoscope } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/data-page';
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

function PacienteAvatar({
    fotoUrl,
    nombre,
    size = 'md',
}: {
    fotoUrl: string | null;
    nombre: string;
    size?: 'sm' | 'md';
}) {
    const cls = size === 'sm' ? 'size-10' : 'size-14';

    if (fotoUrl) {
        return (
            <img
                src={fotoUrl}
                alt={nombre}
                className={cn(
                    cls,
                    'shrink-0 rounded-xl object-cover ring-1 ring-border/70',
                )}
            />
        );
    }

    return (
        <span
            className={cn(
                cls,
                'flex shrink-0 items-center justify-center rounded-xl bg-muted text-sm font-semibold text-muted-foreground',
            )}
        >
            {nombre.slice(0, 1).toUpperCase()}
        </span>
    );
}

function llamarTurno(item: SalaItem, colaLabel: string): void {
    const turno =
        item.numero != null ? `Turno ${item.numero}. ` : '';
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

    useEffect(() => {
        setConsulta(board.consulta);
        setGrooming(board.grooming);
    }, [board]);

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

    const stats = useMemo(
        () => [
            {
                label: t('sala_espera.title_consulta'),
                value: String(
                    consulta.espera.length +
                        consulta.proximas.length +
                        consulta.en_curso.length,
                ),
            },
            {
                label: t('sala_espera.title_grooming'),
                value: String(
                    grooming.espera.length +
                        grooming.proximas.length +
                        grooming.en_curso.length,
                ),
            },
        ],
        [consulta, grooming, t],
    );

    return (
        <>
            <Head title={t('sala_espera.title')} />

            <div className="flex min-w-0 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('sala_espera.title')}
                    description={t('sala_espera.subtitle')}
                    stats={stats}
                />

                {board.can_enviar ? (
                    <section className="rounded-2xl border border-border/70 bg-card/60 p-4 shadow-sm">
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder={t('sala_espera.search_placeholder')}
                                className="h-11 pl-9"
                                autoComplete="off"
                            />
                        </div>
                        <p className="mt-2 text-xs text-muted-foreground">
                            {t('sala_espera.search_hint')}
                        </p>
                        {q.trim().length > 0 && q.trim().length < 2 ? (
                            <p className="mt-3 text-sm text-muted-foreground">
                                {t('sala_espera.search_min')}
                            </p>
                        ) : null}
                        {q.trim().length >= 2 ? (
                            <div className="mt-3 divide-y divide-border/60 overflow-hidden rounded-xl border border-border/60">
                                {searching && hits.length === 0 ? (
                                    <p className="px-3 py-4 text-sm text-muted-foreground">
                                        {t('actions.loading')}
                                    </p>
                                ) : hits.length === 0 ? (
                                    <p className="px-3 py-4 text-sm text-muted-foreground">
                                        {t('sala_espera.search_empty')}
                                    </p>
                                ) : (
                                    hits.map((hit) => (
                                        <div
                                            key={hit.id}
                                            className="flex flex-wrap items-center gap-3 px-3 py-2.5"
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
                                                    {t('sala_espera.owner')}:{' '}
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

                <div className="grid min-w-0 gap-4 xl:grid-cols-2">
                    {board.can_consulta ? (
                        <ColaPanel
                            title={t('sala_espera.title_consulta')}
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
                            title={t('sala_espera.title_grooming')}
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
    icon: Icon,
    accent,
    queue,
    canMarcar,
    llamados,
    onLlamar,
    onMarcar,
}: {
    title: string;
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
    const total =
        queue.espera.length + queue.proximas.length + queue.en_curso.length;

    return (
        <section
            className={cn(
                'flex min-h-112 flex-col overflow-hidden rounded-2xl border bg-card/70 shadow-sm',
                accent === 'violet'
                    ? 'border-violet-500/25'
                    : 'border-sky-500/25',
            )}
        >
            <header
                className={cn(
                    'flex items-center gap-2 border-b px-4 py-3',
                    accent === 'violet'
                        ? 'border-violet-500/20 bg-violet-500/5'
                        : 'border-sky-500/20 bg-sky-500/5',
                )}
            >
                <Icon
                    className={cn(
                        'size-5',
                        accent === 'violet' ? 'text-violet-600' : 'text-sky-600',
                    )}
                />
                <h2 className="text-base font-semibold">{title}</h2>
                <span className="ml-auto rounded-full bg-background px-2 py-0.5 text-xs font-medium tabular-nums">
                    {total}
                </span>
            </header>
            <div className="flex-1 space-y-4 overflow-y-auto p-3">
                {total === 0 ? (
                    <p className="flex min-h-40 items-center justify-center text-sm text-muted-foreground">
                        {t('sala_espera.empty')}
                    </p>
                ) : (
                    groups.map((group) =>
                        group.items.length === 0 ? null : (
                            <div key={group.key}>
                                <p className="mb-2 px-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                    {group.title}
                                    <span className="ml-1 tabular-nums">
                                        ({group.items.length})
                                    </span>
                                </p>
                                <div className="space-y-2">
                                    {group.items.map((item) => (
                                        <TurnoCard
                                            key={`${item.tipo}-${item.id}`}
                                            item={item}
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
    called,
    canMarcar,
    onLlamar,
    onMarcar,
}: {
    item: SalaItem;
    called: boolean;
    canMarcar: boolean;
    onLlamar: () => void;
    onMarcar: () => void;
}) {
    const { t } = useTranslation('common');

    return (
        <article
            className={cn(
                'flex gap-3 rounded-xl border border-border/70 bg-background/80 p-3',
                called && 'ring-2 ring-amber-400/80',
            )}
        >
            <div className="flex w-16 shrink-0 flex-col items-center justify-center rounded-lg bg-muted/80 px-1 py-2">
                <span className="text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
                    {t('sala_espera.turno')}
                </span>
                <span className="text-3xl font-bold tabular-nums leading-none">
                    {item.numero ?? '—'}
                </span>
            </div>
            <PacienteAvatar fotoUrl={item.foto_url} nombre={item.paciente} />
            <div className="min-w-0 flex-1">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <p className="truncate text-base font-semibold">
                            {item.paciente}
                        </p>
                        <p className="truncate text-sm text-muted-foreground">
                            {t('sala_espera.owner')}: {item.propietario}
                        </p>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            {item.especie ? `${item.especie} · ` : ''}
                            {item.hora}
                            {' · '}
                            {t('sala_espera.minutos', {
                                count: item.minutos_espera,
                            })}
                        </p>
                    </div>
                </div>
                <div className="mt-2 flex flex-wrap gap-1.5">
                    <Button
                        type="button"
                        size="sm"
                        variant={called ? 'secondary' : 'default'}
                        className="h-8 cursor-pointer gap-1"
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
                            className="h-8 cursor-pointer gap-1 text-emerald-700"
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
                        className="h-8 cursor-pointer"
                        asChild
                    >
                        <Link href={item.hc_href}>{t('sala_espera.hc')}</Link>
                    </Button>
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
