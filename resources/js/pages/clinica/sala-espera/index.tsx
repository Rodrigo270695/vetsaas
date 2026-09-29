import { Head, Link, router, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { enUS, es as esLocale } from 'date-fns/locale';
import {
    Ban,
    CalendarClock,
    CalendarIcon,
    CircleCheck,
    Clock3,
    FolderOpen,
    Hourglass,
    Megaphone,
    Search,
    Stethoscope,
    Timer,
    Trash2,
    UserRound,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PacienteHcLink } from '@/components/clinica/paciente-hc-link';
import { SalaEsperaEnviarButton } from '@/components/sala-espera-enviar-button';
import { SALA_ESPERA_CHANGED_EVENT } from '@/components/sala-espera-header-popover';
import { SALA_ESPERA_LLAMAR_EVENT } from '@/hooks/use-sala-espera-realtime';
import { ConsultaHistorialFloatingPanel } from '@/pages/clinica/historias-clinicas/components/consulta-historial-floating-panel';
import { ReportCheckboxSelect, type CheckboxSelectOption } from '@/pages/reportes/components/report-checkbox-select';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    TIPO_ATENCION_SALA_CLASS,
    TIPO_ATENCION_SALA_DOT,
    tipoAtencionSalaDe,
} from '@/lib/sala-espera-atencion';
import { toastManager } from '@/lib/toast';
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
    sala_estado?: string;
    motivo: string | null;
    tipo_atencion?: string | null;
    minutos_espera: number;
    enviado_at?: string | null;
    espera_hasta?: string | null;
    href: string;
    hc_href: string;
    tratante_id?: string | null;
    tratante_nombre?: string | null;
};

type SalaQueue = {
    tipo: string;
    fecha: string;
    count: number;
    espera: SalaItem[];
    proximas: SalaItem[];
    en_curso: SalaItem[];
    historial?: SalaItem[];
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

type UsuarioSala = {
    id: string;
    name: string;
};

type Props = {
    board: Board;
    usuarios?: readonly UsuarioSala[];
    alcance?: 'mios' | 'todos';
    filters?: {
        fecha: string;
    };
    filtro_ui?: {
        default_fecha: string;
    };
};

const ESTADOS_SALA = ['citado', 'en_espera', 'en_atencion', 'atendido', 'cancelado'] as const;

type EstadoSala = (typeof ESTADOS_SALA)[number];

const ESTADO_SALA_CLASS: Record<EstadoSala, string> = {
    citado: 'border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-700 dark:bg-sky-950/50 dark:text-sky-200',
    en_espera: 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950/50 dark:text-amber-100',
    en_atencion: 'border-violet-300 bg-violet-50 text-violet-800 dark:border-violet-700 dark:bg-violet-950/50 dark:text-violet-100',
    atendido: 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-100',
    cancelado: 'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-700 dark:bg-rose-950/40 dark:text-rose-100',
};

const ESTADO_SALA_DOT: Record<EstadoSala, string> = {
    citado: 'bg-sky-500',
    en_espera: 'bg-amber-500',
    en_atencion: 'bg-violet-500',
    atendido: 'bg-emerald-500',
    cancelado: 'bg-rose-500',
};

const ESTADO_SALA_TEXT: Record<EstadoSala, string> = {
    citado: 'text-sky-700 dark:text-sky-300',
    en_espera: 'text-amber-700 dark:text-amber-300',
    en_atencion: 'text-violet-700 dark:text-violet-300',
    atendido: 'text-emerald-700 dark:text-emerald-300',
    cancelado: 'text-rose-700 dark:text-rose-300',
};

const pausaVista = new Map<string, number>();

function estadoSalaDe(item: SalaItem): EstadoSala {
    const value = item.sala_estado ?? '';

    return (ESTADOS_SALA as readonly string[]).includes(value) ? (value as EstadoSala) : 'citado';
}

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

function relojPausado(estado: EstadoSala): boolean {
    return estado === 'en_atencion' || estado === 'atendido' || estado === 'cancelado';
}

function finDeDiaMs(iso: string): number | null {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
        return null;
    }
    const [y, m, d] = iso.split('-').map(Number);
    const dt = new Date(y, m - 1, d, 23, 59, 59, 999);

    return Number.isNaN(dt.getTime()) ? null : dt.getTime();
}

function esperaFinMs(item: SalaItem, now: Date, fecha: string, hoy: string): number {
    const nowMs = now.getTime();
    const key = `${item.tipo}-${item.id}`;
    let end = nowMs;

    if (item.espera_hasta) {
        const ms = Date.parse(item.espera_hasta);
        if (!Number.isNaN(ms)) {
            end = ms;
            pausaVista.delete(key);
        }
    } else if (relojPausado(estadoSalaDe(item))) {
        if (!pausaVista.has(key)) {
            pausaVista.set(key, nowMs);
        }
        end = pausaVista.get(key) ?? nowMs;
    } else {
        pausaVista.delete(key);
    }

    if (fecha && hoy && fecha < hoy) {
        const tope = finDeDiaMs(fecha);
        if (tope != null) {
            end = Math.min(end, tope);
        }
    }

    return end;
}

function formatWait(item: SalaItem, now: Date, fecha: string, hoy: string): string {
    const enviadoAt = item.enviado_at;
    if (!enviadoAt) {
        return `${item.minutos_espera}:00`;
    }
    const start = Date.parse(enviadoAt);
    if (Number.isNaN(start)) {
        return `${item.minutos_espera}:00`;
    }
    const totalSec = Math.max(0, Math.floor((esperaFinMs(item, now, fecha, hoy) - start) / 1000));
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

function waitSeconds(item: SalaItem, now: Date, fecha: string, hoy: string): number {
    if (!item.enviado_at) {
        return item.minutos_espera * 60;
    }
    const start = Date.parse(item.enviado_at);
    if (Number.isNaN(start)) {
        return item.minutos_espera * 60;
    }

    return Math.max(0, Math.floor((esperaFinMs(item, now, fecha, hoy) - start) / 1000));
}

function parseIsoDay(iso: string): Date | undefined {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
        return undefined;
    }
    const [y, m, d] = iso.split('-').map(Number);
    const dt = new Date(y, m - 1, d);

    return Number.isNaN(dt.getTime()) ? undefined : dt;
}

function moverItemEstado(queue: SalaQueue, item: SalaItem, estado: EstadoSala): SalaQueue {
    const esEste = (row: SalaItem) => row.tipo === item.tipo && row.id === item.id;
    const pausa = relojPausado(estado);
    const next: SalaItem = {
        ...item,
        sala_estado: estado,
        espera_hasta: pausa ? (item.espera_hasta ?? new Date().toISOString()) : null,
    };
    const espera = queue.espera.filter((row) => !esEste(row));
    const proximas = queue.proximas.filter((row) => !esEste(row));
    const enCurso = queue.en_curso.filter((row) => !esEste(row));
    const historial = (queue.historial ?? []).filter((row) => !esEste(row));

    if (estado === 'atendido' || estado === 'cancelado') {
        historial.push(next);
    } else if (estado === 'en_atencion') {
        enCurso.push(next);
    } else if (queue.proximas.some(esEste)) {
        proximas.push(next);
    } else {
        espera.push(next);
    }

    return { ...queue, espera, proximas, en_curso: enCurso, historial };
}

function unirColas(izquierda: SalaQueue, derecha: SalaQueue): SalaQueue {
    const porTurno = (a: SalaItem, b: SalaItem) => {
        const delta = (a.numero ?? 9999) - (b.numero ?? 9999);
        if (delta !== 0) {
            return delta;
        }

        return (a.enviado_at ?? '').localeCompare(b.enviado_at ?? '');
    };
    const juntar = (a: SalaItem[], b: SalaItem[]) => [...a, ...b].sort(porTurno);

    return {
        ...izquierda,
        espera: juntar(izquierda.espera, derecha.espera),
        proximas: juntar(izquierda.proximas, derecha.proximas),
        en_curso: juntar(izquierda.en_curso, derecha.en_curso),
        historial: juntar(izquierda.historial ?? [], derecha.historial ?? []),
        can_marcar: izquierda.can_marcar || derecha.can_marcar,
        visible: izquierda.visible || derecha.visible,
    };
}

function filtrarCola(queue: SalaQueue, estados: readonly string[]): SalaQueue {
    const ok = (item: SalaItem) => estados.includes(estadoSalaDe(item));

    return {
        ...queue,
        espera: queue.espera.filter(ok),
        proximas: queue.proximas.filter(ok),
        en_curso: queue.en_curso.filter(ok),
        historial: (queue.historial ?? []).filter(ok),
    };
}

function SalaFechaFilter({
    fecha,
    onChange,
}: {
    fecha: string;
    onChange: (fecha: string) => void;
}) {
    const { t, i18n } = useTranslation('common');
    const [open, setOpen] = useState(false);
    const locale = i18n.language?.startsWith('en') ? enUS : esLocale;
    const selected = parseIsoDay(fecha);
    const label = selected ? format(selected, 'dd/MM/yyyy', { locale }) : fecha;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    className="h-9 cursor-pointer gap-2 bg-white/80 px-3 font-normal dark:bg-background/60"
                    aria-label={t('sala_espera.fecha')}
                >
                    <CalendarIcon className="size-4 text-sky-600" />
                    {label}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-auto p-0">
                <Calendar
                    mode="single"
                    selected={selected}
                    defaultMonth={selected}
                    onSelect={(day) => {
                        if (!day) {
                            return;
                        }
                        onChange(format(day, 'yyyy-MM-dd'));
                        setOpen(false);
                    }}
                />
            </PopoverContent>
        </Popover>
    );
}

function queueTotal(queue: SalaQueue): number {
    return queue.espera.length + queue.proximas.length + queue.en_curso.length;
}

function queueVisible(queue: SalaQueue): number {
    return queueTotal(queue) + (queue.historial?.length ?? 0);
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

export default function SalaEsperaIndex({
    board,
    usuarios = [],
    alcance = 'mios',
    filters,
    filtro_ui,
}: Props) {
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
    const [estadosFiltro, setEstadosFiltro] = useState<string[]>([...ESTADOS_SALA]);
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

    const cambiarEstado = useCallback(async (item: SalaItem, estado: EstadoSala) => {
        const aplicar = (queue: SalaQueue) => moverItemEstado(queue, item, estado);
        if (item.tipo === 'grooming') {
            setGrooming(aplicar);
        } else {
            setConsulta(aplicar);
        }

        const res = await fetch(
            `/clinica/sala-espera/${item.tipo}/${item.id}/estado`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ estado }),
            },
        );
        if (res.ok) {
            window.dispatchEvent(new Event(SALA_ESPERA_CHANGED_EVENT));

            return;
        }

        let message = t('sala_espera.estado_error');
        try {
            const json = (await res.json()) as { message?: string };
            if (json.message) {
                message = json.message;
            }
        } catch {
            // La respuesta no trae JSON.
        }
        toastManager.error({ title: message });
        reloadBoard();
    }, [reloadBoard, t]);

    const assign = useCallback(async (item: SalaItem, tratanteId: string | null) => {
        const res = await fetch(`/clinica/sala-espera/${item.tipo}/${item.id}/tratante`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ tratante_id: tratanteId }),
        });
        if (res.ok) {
            window.dispatchEvent(new Event(SALA_ESPERA_CHANGED_EVENT));
        }
    }, []);

    const hoy = filtro_ui?.default_fecha ?? '';
    const fecha = filters?.fecha ?? hoy;

    const querySala = (patch: Record<string, string | undefined>) => {
        const params: Record<string, string> = {};
        if (alcance === 'todos') {
            params.alcance = 'todos';
        }
        if (fecha) {
            params.fecha = fecha;
        }
        for (const [key, value] of Object.entries(patch)) {
            if (value === undefined || value === '') {
                delete params[key];
            } else {
                params[key] = value;
            }
        }

        router.get('/clinica/sala-espera', params, {
            preserveScroll: true,
            preserveState: true,
            only: ['board', 'alcance', 'usuarios', 'filters', 'filtro_ui'],
        });
    };

    const setAlcance = (next: 'mios' | 'todos') => {
        querySala({ alcance: next === 'todos' ? 'todos' : undefined });
    };

    const confirmQuitar = useCallback(async () => {
        if (!quitar) {
            return;
        }
        const item = quitar;
        setQuitar(null);
        const estado = estadoSalaDe(item);
        if (estado === 'atendido' || estado === 'cancelado') {
            return;
        }
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
    }, [quitar, reloadBoard]);

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
            const tratanteId = item.tratante_id ? String(item.tratante_id) : '';
            setLlamados((prev) => ({
                ...prev,
                [`${item.tipo}-${item.id}`]: true,
            }));
            if (tratanteId !== '' && myId && tratanteId !== myId) {
                return;
            }
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
                    tratante_id: item.tratante_id ?? null,
                    tratante_nombre: item.tratante_nombre ?? null,
                },
                item.tipo === 'grooming'
                    ? t('sala_espera.grooming')
                    : t('sala_espera.cita'),
            );
        };
        window.addEventListener(SALA_ESPERA_LLAMAR_EVENT, onLlamar);

        return () => window.removeEventListener(SALA_ESPERA_LLAMAR_EVENT, onLlamar);
    }, [myId, t]);

    const estadoOptions = useMemo<CheckboxSelectOption[]>(
        () => [
            { value: 'citado', label: t('sala_espera.estados.citado'), icon: CalendarClock, tone: 'info' },
            { value: 'en_espera', label: t('sala_espera.estados.en_espera'), icon: Hourglass, tone: 'warning' },
            { value: 'en_atencion', label: t('sala_espera.estados.en_atencion'), icon: Stethoscope, tone: 'default' },
            { value: 'atendido', label: t('sala_espera.estados.atendido'), icon: CircleCheck, tone: 'success' },
            { value: 'cancelado', label: t('sala_espera.estados.cancelado'), icon: Ban, tone: 'danger' },
        ],
        [t],
    );

    const consultaVista = useMemo(
        () => filtrarCola(consulta, estadosFiltro),
        [consulta, estadosFiltro],
    );
    const groomingVista = useMemo(
        () => filtrarCola(grooming, estadosFiltro),
        [grooming, estadosFiltro],
    );

    const salaVista = useMemo(
        () => unirColas(consultaVista, groomingVista),
        [consultaVista, groomingVista],
    );

    const waiting = useMemo(
        () => queueTotal(salaVista),
        [salaVista],
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
                        <div className="mt-3 flex flex-wrap items-center gap-2">
                        <div className="inline-flex rounded-lg bg-white/70 p-0.5 ring-1 ring-border/60 dark:bg-background/40">
                            <button
                                type="button"
                                className={cn(
                                    'cursor-pointer rounded-md px-3 py-1 text-xs font-medium',
                                    alcance !== 'todos' ? 'bg-sky-600 text-white' : 'text-muted-foreground',
                                )}
                                onClick={() => setAlcance('mios')}
                            >
                                {t('sala_espera.alcance_mios')}
                            </button>
                            <button
                                type="button"
                                className={cn(
                                    'cursor-pointer rounded-md px-3 py-1 text-xs font-medium',
                                    alcance === 'todos' ? 'bg-sky-600 text-white' : 'text-muted-foreground',
                                )}
                                onClick={() => setAlcance('todos')}
                            >
                                {t('sala_espera.alcance_todos')}
                            </button>
                        </div>
                        <SalaFechaFilter
                            fecha={fecha}
                            onChange={(next) => querySala({ fecha: next })}
                        />
                        <ReportCheckboxSelect
                            label={t('sala_espera.filtro_estado')}
                            allLabel={t('sala_espera.filtro_todos')}
                            options={estadoOptions}
                            selected={estadosFiltro}
                            onChange={setEstadosFiltro}
                            className="h-9 min-w-40 bg-white/80 dark:bg-background/60"
                        />
                        </div>
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
                                                    usuarios={usuarios}
                                                />
                                            </div>
                                        ))
                                    )}
                                </div>
                            ) : null}
                        </div>
                    ) : null}

                    {board.can_consulta || board.can_grooming ? (
                    <ColaPanel
                        emptyLabel={t('sala_espera.empty')}
                        icon={Timer}
                        accent="sky"
                        queue={salaVista}
                        fecha={fecha}
                        hoy={hoy}
                        canMarcar={board.can_marcar}
                        llamados={llamados}
                        now={now}
                        locale={i18n.language?.startsWith('en') ? 'en-US' : 'es-PE'}
                        onLlamar={(item) => {
                            void callTurno(
                                item,
                                item.tipo === 'grooming'
                                    ? t('sala_espera.grooming')
                                    : t('sala_espera.cita'),
                            );
                        }}
                        onEstado={(item, estado) => void cambiarEstado(item, estado)}
                        onQuitar={setQuitar}
                        onHc={(item) => {
                            if (item.paciente_id) {
                                setHcPaciente({
                                    id: item.paciente_id,
                                    nombre: item.paciente,
                                });
                            }
                        }}
                        usuarios={usuarios}
                        onAsignar={(item, tratanteId) => void assign(item, tratanteId)}
                    />
                    ) : null}
                </header>

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
    emptyLabel,
    icon: Icon,
    accent,
    queue,
    fecha,
    hoy,
    canMarcar,
    llamados,
    now,
    locale,
    onLlamar,
    onEstado,
    onQuitar,
    onHc,
    usuarios,
    onAsignar,
}: {
    emptyLabel: string;
    icon: typeof Stethoscope;
    accent: 'sky' | 'violet';
    queue: SalaQueue;
    fecha: string;
    hoy: string;
    canMarcar: boolean;
    llamados: Record<string, boolean>;
    now: Date;
    locale: string;
    onLlamar: (item: SalaItem) => void;
    onEstado: (item: SalaItem, estado: EstadoSala) => void;
    onQuitar: (item: SalaItem) => void;
    onHc: (item: SalaItem) => void;
    usuarios: readonly UsuarioSala[];
    onAsignar: (item: SalaItem, tratanteId: string | null) => void;
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
        {
            key: 'historial',
            title: t('sala_espera.historial'),
            items: queue.historial ?? [],
        },
    ];
    const total = queueVisible(queue);
    const isViolet = accent === 'violet';

    return (
        <section className="relative mt-5 border-t border-sky-500/15 pt-4 dark:border-white/10">
            <div className="space-y-5">
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
                                            group.key === 'historial'
                                                ? 'bg-muted-foreground/50'
                                                : group.key === 'en_curso'
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
                                            fecha={fecha}
                                            hoy={hoy}
                                            called={
                                                llamados[
                                                    `${item.tipo}-${item.id}`
                                                ] === true
                                            }
                                            canMarcar={canMarcar}
                                            now={now}
                                            locale={locale}
                                            onLlamar={() => onLlamar(item)}
                                            onEstado={(estado) => onEstado(item, estado)}
                                            onQuitar={() => onQuitar(item)}
                                            onHc={() => onHc(item)}
                                            usuarios={usuarios}
                                            onAsignar={(tratanteId) => onAsignar(item, tratanteId)}
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
    fecha,
    hoy,
    called,
    canMarcar,
    now,
    locale,
    onLlamar,
    onEstado,
    onQuitar,
    onHc,
    usuarios,
    onAsignar,
}: {
    item: SalaItem;
    fecha: string;
    hoy: string;
    called: boolean;
    canMarcar: boolean;
    now: Date;
    locale: string;
    onLlamar: () => void;
    onEstado: (estado: EstadoSala) => void;
    onQuitar: () => void;
    onHc: () => void;
    usuarios: readonly UsuarioSala[];
    onAsignar: (tratanteId: string | null) => void;
}) {
    const { t } = useTranslation('common');
    const estado = estadoSalaDe(item);
    const tipoAtencion = tipoAtencionSalaDe(item.tipo_atencion);
    const paused = relojPausado(estado);
    const waited = waitSeconds(item, now, fecha, hoy);
    const longWait = !paused && waited >= 20 * 60;
    const enHistorial = estado === 'atendido' || estado === 'cancelado';

    return (
        <article
            className={cn(
                'relative overflow-hidden rounded-xl border bg-background py-2 pr-2.5 pl-3.5 shadow-sm transition-all duration-300',
                enHistorial && 'opacity-80',
                called
                    ? 'border-amber-400/70 ring-2 ring-amber-300/60 shadow-amber-500/10'
                    : 'border-border/70 hover:border-border hover:shadow-md',
            )}
        >
            <span
                className={cn(
                    'absolute inset-y-0 left-0 w-1.5',
                    ESTADO_SALA_DOT[estado],
                    called && !paused && 'animate-pulse',
                )}
            />
            <div className="flex items-center gap-2.5">
                <div className="flex w-12 shrink-0 flex-col items-center justify-center">
                    <span className="text-[9px] font-semibold tracking-[0.12em] text-muted-foreground uppercase">
                        {t('sala_espera.turno')}
                    </span>
                    <span className={cn('text-2xl font-bold tabular-nums leading-none tracking-tight', ESTADO_SALA_TEXT[estado])}>
                        {padTurno(item.numero)}
                    </span>
                </div>
                <PacienteAvatar
                    fotoUrl={item.foto_url}
                    nombre={item.paciente}
                    size="sm"
                />
                <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0">
                            <PacienteHcLink
                                pacienteId={item.paciente_id}
                                className="block truncate text-sm font-semibold leading-tight"
                            >
                                {item.paciente}
                            </PacienteHcLink>
                            <p className="mt-1 flex flex-wrap items-center gap-1">
                                <span
                                    className={cn(
                                        'inline-flex h-5 items-center rounded-full border px-1.5 text-[10px] font-medium',
                                        item.tipo === 'grooming'
                                            ? 'border-violet-300 bg-violet-50 text-violet-800 dark:border-violet-700 dark:bg-violet-950/40 dark:text-violet-100'
                                            : 'border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-700 dark:bg-sky-950/40 dark:text-sky-100',
                                    )}
                                >
                                    {item.tipo === 'grooming' ? t('sala_espera.grooming') : t('sala_espera.cita')}
                                </span>
                                {tipoAtencion ? (
                                    <span
                                        className={cn(
                                            'inline-flex h-5 items-center gap-1 rounded-full border px-1.5 text-[10px] font-medium',
                                            TIPO_ATENCION_SALA_CLASS[tipoAtencion],
                                        )}
                                    >
                                        <span className={cn('size-1.5 rounded-full', TIPO_ATENCION_SALA_DOT[tipoAtencion])} />
                                        {t(`sala_espera.tipos_atencion.${tipoAtencion}`)}
                                    </span>
                                ) : null}
                            </p>
                            {item.motivo ? (
                                <p className="mt-0.5 truncate text-xs text-foreground/80">{item.motivo}</p>
                            ) : null}
                            <p className="flex min-w-0 items-center gap-1 truncate text-xs text-muted-foreground">
                                <UserRound className="size-3 shrink-0" />
                                <span className="truncate">{item.propietario}</span>
                                {item.especie ? <span className="text-border">·</span> : null}
                                {item.especie ? <span className="truncate">{item.especie}</span> : null}
                                <span className="text-border">·</span>
                                <Clock3 className="size-3 shrink-0" />
                                <span className="shrink-0">
                                    {formatIngreso(item.enviado_at, item.hora, locale)}
                                </span>
                            </p>
                        </div>
                        <span
                            className={cn(
                                'shrink-0 rounded-full px-1.5 py-0.5 font-mono text-[11px] font-semibold tabular-nums',
                                paused
                                    ? 'bg-muted text-muted-foreground'
                                    : longWait
                                      ? 'bg-amber-500/15 text-amber-800 dark:text-amber-200'
                                      : 'bg-muted text-muted-foreground',
                            )}
                        >
                            {formatWait(item, now, fecha, hoy)}
                        </span>
                    </div>
                    <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                        <Select
                            value={item.tratante_id ? item.tratante_id : '__none__'}
                            onValueChange={(value) => onAsignar(value === '__none__' ? null : value)}
                        >
                            <SelectTrigger className="h-7 w-36 text-xs">
                                <SelectValue placeholder={t('sala_espera.tratante_placeholder')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none__">{t('sala_espera.tratante_placeholder')}</SelectItem>
                                {item.tratante_id && !usuarios.some((usuario) => usuario.id === item.tratante_id) ? (
                                    <SelectItem value={item.tratante_id}>
                                        {item.tratante_nombre || item.tratante_id}
                                    </SelectItem>
                                ) : null}
                                {usuarios.map((usuario) => (
                                    <SelectItem key={usuario.id} value={usuario.id}>
                                        {usuario.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {canMarcar ? (
                            <Select
                                value={estado}
                                onValueChange={(value) => onEstado(value as EstadoSala)}
                            >
                                <SelectTrigger
                                    className={cn(
                                        'h-7 w-34 text-xs font-medium',
                                        ESTADO_SALA_CLASS[estado],
                                    )}
                                    aria-label={t('sala_espera.estado')}
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {ESTADOS_SALA.map((opcion) => (
                                        <SelectItem key={opcion} value={opcion}>
                                            <span className="inline-flex items-center gap-2">
                                                <span className={cn('size-2 rounded-full', ESTADO_SALA_DOT[opcion])} />
                                                {t(`sala_espera.estados.${opcion}`)}
                                            </span>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        ) : (
                            <span
                                className={cn(
                                    'inline-flex h-7 items-center rounded-md border px-2 text-xs font-medium',
                                    ESTADO_SALA_CLASS[estado],
                                )}
                            >
                                {t(`sala_espera.estados.${estado}`)}
                            </span>
                        )}
                        <Button
                            type="button"
                            size="sm"
                            className={cn(
                                'h-7 cursor-pointer gap-1 px-2.5',
                                called ? 'bg-amber-500 text-white hover:bg-amber-500/90' : '',
                            )}
                            onClick={onLlamar}
                        >
                            <Megaphone className="size-3.5" />
                            {called ? t('sala_espera.llamado') : t('sala_espera.llamar')}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="h-7 cursor-pointer gap-1 px-2 text-muted-foreground"
                            disabled={!item.paciente_id}
                            onClick={onHc}
                        >
                            <FolderOpen className="size-3.5" />
                            {t('sala_espera.hc')}
                        </Button>
                        {canMarcar && !enHistorial ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                className="h-7 cursor-pointer gap-1 px-2 text-red-600 hover:bg-red-500/10 hover:text-red-700"
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
