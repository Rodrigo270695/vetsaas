import { Head, router } from '@inertiajs/react';
import {
    Activity,
    Bot,
    CalendarDays,
    CheckCircle2,
    Download,
    Filter,
    MessageCircle,
    PauseCircle,
    PlayCircle,
    RefreshCw,
    SendHorizonal,
    Snowflake,
    Sparkles,
    Trash2,
    Upload,
    User,
    XCircle,
    Loader2,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import {
    DataPagination,
    DataTable,
    DataToolbar,
    EmptyState,
    FilterChips,
    PageHeader,
    StatBadge,
} from '@/components/data-page';
import type { DataTableColumn, FilterChip } from '@/components/data-page';
import { FormField, FormModal, FormSection } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import { toastManager } from '@/lib/toast';
import AppLayout from '@/layouts/app-layout';
import salesbotConversations from '@/routes/plataforma/salesbot-conversations';
import type { Paginated } from '@/types';

type Conversation = {
    id: string;
    phone: string;
    prospect_name: string | null;
    turn_count: number;
    bot_active: boolean;
    bot_paused_manually: boolean;
    converted: boolean;
    activation_trigger: string | null;
    reactivation_count: number;
    last_reactivation_at: string | null;
    lost_at: string | null;
    last_message_at: string | null;
    last_message_body: string | null;
    last_message_role: string | null;
    created_at: string;
};

type EstadoFilter = 'todos' | 'activo' | 'pausado' | 'frio' | 'convertido' | 'perdido';

type ConvFilters = {
    search: string;
    estado: EstadoFilter;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
};

type ConvStats = {
    total: number;
    activos: number;
    pausados: number;
    convertidos: number;
    frios: number;
    perdidos: number;
    hoy: number;
    coincidencias: number;
};

type Props = {
    conversations: Paginated<Conversation>;
    filters: ConvFilters;
    stats: ConvStats;
};

const DEFAULT_PER_PAGE = 15;
const DEFAULT_ESTADO: EstadoFilter = 'todos';

const formatWhen = (iso: string | null): string => {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' });
};

/** Etiqueta legible del origen del lead. */
const formatTrigger = (trigger: string | null): string | null => {
    if (!trigger) return null;
    if (trigger.startsWith('facebook:')) return 'Facebook Ads';
    if (trigger.startsWith('manual:engage')) return 'Activación manual';
    if (trigger.startsWith('manual:')) return 'Importado';
    if (trigger.startsWith('auto-pausa:')) return 'Pausado auto';
    if (trigger.startsWith('reactivado:')) return 'Reactivado';
    return trigger;
};

/** Muestra el teléfono como número legible. */
const formatPhone = (raw: string): string => {
    if (raw.startsWith('lid:')) {
        return 'WhatsApp (ID privado)';
    }
    const digits = raw.replace('@c.us', '').replace(/\D/g, '');
    if (digits.startsWith('51') && digits.length === 11) {
        return `+51 ${digits.slice(2, 3)} ${digits.slice(3, 6)} ${digits.slice(6, 9)} ${digits.slice(9)}`;
    }
    if (digits.length >= 13) {
        return 'WhatsApp (ID privado)';
    }
    return `+${digits}`;
};

/** Tiempo relativo tipo "hace 3 min", "hace 2h", "hace 1d". */
const timeAgo = (iso: string | null): string => {
    if (!iso) return '—';
    const diffMs = Date.now() - new Date(iso).getTime();
    const secs = Math.floor(diffMs / 1000);
    if (secs < 60) return 'hace un momento';
    const mins = Math.floor(secs / 60);
    if (mins < 60) return `hace ${mins} min`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `hace ${hrs}h`;
    const days = Math.floor(hrs / 24);
    return `hace ${days}d`;
};

/** Hace un POST/DELETE simple usando fetch con el token CSRF. */
function csrfFetch(url: string, method: 'POST' | 'DELETE'): Promise<Response> {
    const xsrf = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1] ?? '';
    return fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': decodeURIComponent(xsrf),
        },
    });
}

function csrfPostJson(url: string, body: Record<string, unknown>): Promise<Response> {
    const xsrf = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1] ?? '';
    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': decodeURIComponent(xsrf),
        },
        body: JSON.stringify(body),
    });
}

// ─── Barra compacta de acciones (IA + CSV) ─────────────────────────────────
function SalesBotQuickActions({
    canUpdate,
    onSuccess,
}: {
    canUpdate: boolean;
    onSuccess: () => void;
}) {
    const [engageOpen, setEngageOpen] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const [phone, setPhone] = useState('');
    const [name, setName] = useState('');
    const [message, setMessage] = useState('Hola, quisiera información sobre VetSaaS y los costos.');
    const [engageSending, setEngageSending] = useState(false);
    const [file, setFile] = useState<File | null>(null);
    const [days, setDays] = useState(5);
    const [uploading, setUploading] = useState(false);
    const [importResult, setImportResult] = useState<{
        imported: number;
        skipped: number;
        duplicates: { phone: string; name: string | null; reason: string }[];
        errors: string[];
    } | null>(null);

    if (!canUpdate) return null;

    const handleEngage = (e?: FormEvent) => {
        e?.preventDefault();
        if (!phone.trim()) return;
        setEngageSending(true);
        csrfPostJson('/plataforma/salesbot-conversations/engage-phone', {
            phone: phone.trim(),
            name: name.trim() || undefined,
            message: message.trim() || undefined,
        })
            .then(async (res) => {
                const data = await res.json();
                if (!res.ok) throw new Error(data?.error ?? 'Error');
                toastManager.success({
                    title: 'IA activada y mensaje enviado',
                    description: data.reply?.slice(0, 100) ?? undefined,
                });
                setPhone('');
                setName('');
                setEngageOpen(false);
                onSuccess();
            })
            .catch((e: Error) => toastManager.error({ title: e.message || 'Error al activar la IA' }))
            .finally(() => setEngageSending(false));
    };

    const handleImport = (e?: FormEvent) => {
        e?.preventDefault();
        if (!file) return;
        const xsrf = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1] ?? '';
        const form = new FormData();
        form.append('file', file);
        form.append('days', String(days));
        setUploading(true);
        setImportResult(null);
        fetch('/plataforma/salesbot-conversations/import-csv', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(xsrf),
            },
            body: form,
        })
            .then(async (r) => {
                const data = await r.json();
                if (!r.ok) {
                    const validationErrors = data?.errors;
                    const errMsg = Array.isArray(validationErrors)
                        ? validationErrors.join(' | ')
                        : typeof validationErrors === 'object' && validationErrors !== null
                          ? Object.values(validationErrors as Record<string, string[]>).flat().join(' | ')
                          : (data?.error as string) ?? (data?.message as string) ?? 'Error al importar el archivo.';
                    setImportResult({
                        imported: 0,
                        skipped: 0,
                        duplicates: [],
                        errors: [errMsg],
                    });
                    return;
                }
                setImportResult({
                    imported: Number(data.imported ?? 0),
                    skipped: Number(data.skipped ?? 0),
                    duplicates: Array.isArray(data.duplicates) ? data.duplicates : [],
                    errors: Array.isArray(data.errors) ? data.errors : [],
                });
                if (Number(data.imported ?? 0) > 0) {
                    setFile(null);
                    onSuccess();
                }
            })
            .catch(() =>
                setImportResult({
                    imported: 0,
                    skipped: 0,
                    duplicates: [],
                    errors: ['Error al subir el archivo.'],
                }),
            )
            .finally(() => setUploading(false));
    };

    return (
        <>
            <div className="flex flex-wrap items-center gap-1.5">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="h-8 cursor-pointer gap-1.5 text-xs"
                    onClick={() => setEngageOpen(true)}
                >
                    <Sparkles className="size-3.5 text-primary" />
                    <span className="hidden sm:inline">Responder con IA</span>
                    <span className="sm:hidden">IA</span>
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="h-8 cursor-pointer gap-1.5 text-xs"
                    onClick={() => {
                        setImportResult(null);
                        setImportOpen(true);
                    }}
                >
                    <Upload className="size-3.5" />
                    <span className="hidden sm:inline">Importar CSV</span>
                    <span className="sm:hidden">CSV</span>
                </Button>
                <a
                    href="/plataforma/salesbot-conversations/csv-template"
                    download
                    className="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    title="Descargar plantilla CSV"
                >
                    <Download className="size-3.5" />
                </a>
            </div>

            <FormModal
                open={engageOpen}
                onOpenChange={setEngageOpen}
                title="Activar bot y enviar respuesta"
                description="Para leads de Facebook que no entraron solos o cuando el bot no respondió."
                size="sm"
                onSubmit={handleEngage}
                footer={
                    <div className="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button type="button" variant="outline" onClick={() => setEngageOpen(false)}>
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            disabled={engageSending || !phone.trim()}
                            className="gap-2"
                        >
                            {engageSending && <Loader2 className="size-4 animate-spin" />}
                            {engageSending ? 'Enviando…' : 'Activar y enviar'}
                        </Button>
                    </div>
                }
            >
                <FormSection>
                    <FormField id="engage-phone" label="Teléfono WhatsApp" required>
                        <Input
                            id="engage-phone"
                            placeholder="961777549"
                            value={phone}
                            onChange={(e) => setPhone(e.target.value)}
                        />
                    </FormField>
                    <FormField id="engage-name" label="Nombre (opcional)">
                        <Input
                            id="engage-name"
                            placeholder="Beatriz Moscol"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                        />
                    </FormField>
                    <FormField id="engage-message" label="Mensaje del lead">
                        <Textarea
                            id="engage-message"
                            rows={3}
                            value={message}
                            onChange={(e) => setMessage(e.target.value)}
                        />
                    </FormField>
                </FormSection>
            </FormModal>

            <FormModal
                open={importOpen}
                onOpenChange={setImportOpen}
                title="Importar leads desde CSV"
                description="Duplicados se ignoran. El scheduler los reactivará según los días de inactividad."
                size="sm"
                onSubmit={handleImport}
                footer={
                    <div className="flex w-full flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <a
                            href="/plataforma/salesbot-conversations/csv-template"
                            download
                            className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                        >
                            <Download className="size-3.5" />
                            Descargar plantilla
                        </a>
                        <div className="flex flex-col-reverse gap-2 sm:flex-row">
                            <Button type="button" variant="outline" onClick={() => setImportOpen(false)}>
                                Cerrar
                            </Button>
                            <Button type="submit" disabled={!file || uploading} className="gap-2">
                                {uploading && <Loader2 className="size-4 animate-spin" />}
                                {uploading ? 'Subiendo…' : 'Importar'}
                            </Button>
                        </div>
                    </div>
                }
            >
                <FormSection>
                    <FormField id="import-csv-file" label="Archivo CSV" required>
                        <input
                            id="import-csv-file"
                            type="file"
                            accept=".csv,text/csv"
                            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                            className="text-xs file:mr-2 file:cursor-pointer file:rounded file:border-0 file:bg-primary file:px-2 file:py-1 file:text-xs file:text-primary-foreground"
                        />
                    </FormField>
                    <FormField id="import-days" label="Días de inactividad simulada">
                        <Input
                            id="import-days"
                            type="number"
                            min={1}
                            max={30}
                            value={days}
                            onChange={(e) => setDays(Number(e.target.value))}
                            className="w-24"
                        />
                    </FormField>
                    {importResult && (
                        <div className="space-y-2">
                            <p
                                className={`rounded-md px-2 py-1.5 text-xs ${
                                    importResult.errors.length > 0 && importResult.imported === 0
                                        ? 'bg-destructive/10 text-destructive'
                                        : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400'
                                }`}
                            >
                                {importResult.errors.length > 0 && importResult.imported === 0
                                    ? importResult.errors.join(' | ')
                                    : `✅ ${importResult.imported} importados, ${importResult.skipped} duplicados omitidos.`}
                            </p>
                            {importResult.errors.length > 0 && importResult.imported > 0 && (
                                <p className="rounded-md bg-amber-50 px-2 py-1.5 text-xs text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                                    Advertencias: {importResult.errors.join(' | ')}
                                </p>
                            )}
                            {importResult.duplicates.length > 0 && (
                                <div className="rounded-md border bg-muted/30 px-2 py-1.5 text-xs">
                                    <p className="mb-1 font-medium text-foreground">Duplicados (no importados):</p>
                                    <ul className="max-h-28 space-y-0.5 overflow-y-auto text-muted-foreground">
                                        {importResult.duplicates.map((dup) => (
                                            <li key={`${dup.phone}-${dup.reason}`}>
                                                {dup.phone}
                                                {dup.name ? ` — ${dup.name}` : ''}
                                                {dup.reason === 'ya_registrado'
                                                    ? ' (ya en el panel)'
                                                    : ' (repetido en el CSV)'}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </div>
                    )}
                </FormSection>
            </FormModal>
        </>
    );
}

// ─────────────────────────────────────────────────────────────────────────────

export default function SalesBotConversationsIndex({ conversations, filters, stats }: Props) {
    const { t } = useTranslation(['salesbot-conversations', 'common']);
    const { can } = usePermission();
    const canUpdate = can('salesbot-knowledge.update');
    const canDelete = can('salesbot-knowledge.delete');

    // Estado local para reflejar cambios sin recargar la página entera.
    const [localBotActive, setLocalBotActive] = useState<Record<string, boolean>>({});
    const [localManualPause, setLocalManualPause] = useState<Record<string, boolean>>({});
    const [localConverted, setLocalConverted] = useState<Record<string, boolean>>({});
    const [processingId, setProcessingId] = useState<string | null>(null);
    const [engageTarget, setEngageTarget] = useState<Conversation | null>(null);
    const [engageMessage, setEngageMessage] = useState('');
    const [engageSending, setEngageSending] = useState(false);

    // ── Auto-refresh cada 15 s ──────────────────────────────────────────────
    const REFRESH_INTERVAL_MS = 15_000;
    const [secondsSince, setSecondsSince] = useState(0);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
    const tickRef    = useRef<ReturnType<typeof setInterval> | null>(null);

    const doRefresh = useCallback(() => {
        setIsRefreshing(true);
        router.reload({
            only: ['conversations', 'stats'],
            onFinish: () => {
                setIsRefreshing(false);
                setSecondsSince(0);
            },
        });
    }, []);

    useEffect(() => {
        intervalRef.current = setInterval(doRefresh, REFRESH_INTERVAL_MS);
        tickRef.current = setInterval(() => setSecondsSince((s) => s + 1), 1_000);
        return () => {
            if (intervalRef.current) clearInterval(intervalRef.current);
            if (tickRef.current) clearInterval(tickRef.current);
        };
    }, [doRefresh]);
    // ───────────────────────────────────────────────────────────────────────

    const getBotActive = (conv: Conversation): boolean =>
        conv.id in localBotActive ? localBotActive[conv.id] : conv.bot_active;

    const getManualPause = (conv: Conversation): boolean =>
        conv.id in localManualPause ? localManualPause[conv.id] : conv.bot_paused_manually;

    const getConverted = (conv: Conversation): boolean =>
        conv.id in localConverted ? localConverted[conv.id] : conv.converted;

    const handleToggle = useCallback((conv: Conversation) => {
        const currentlyActive = getBotActive(conv);
        const url = currentlyActive
            ? salesbotConversations.pause(conv.id).url
            : salesbotConversations.resume(conv.id).url;

        setProcessingId(conv.id);
        csrfFetch(url, 'POST')
            .then(async (res) => {
                if (!res.ok) throw new Error();
                const data = await res.json();
                setLocalBotActive((prev) => ({ ...prev, [conv.id]: Boolean(data.bot_active) }));
                setLocalManualPause((prev) => ({
                    ...prev,
                    [conv.id]: Boolean(data.bot_paused_manually),
                }));
                toastManager.success({
                    title: currentlyActive
                        ? `Bot pausado manualmente para ${conv.prospect_name ?? conv.phone}`
                        : `Bot reactivado para ${conv.prospect_name ?? conv.phone}`,
                });
            })
            .catch(() => {
                toastManager.error({ title: 'Error al cambiar el estado del bot' });
            })
            .finally(() => setProcessingId(null));
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [localBotActive]);

    const handleConvert = useCallback((conv: Conversation) => {
        if (!confirm(`¿Marcar a ${conv.prospect_name ?? conv.phone} como convertido? Ya no recibirá mensajes de reactivación.`)) return;
        setProcessingId(conv.id);
        csrfFetch(salesbotConversations.convert(conv.id).url, 'POST')
            .then((res) => {
                if (!res.ok) throw new Error();
                setLocalConverted((prev) => ({ ...prev, [conv.id]: true }));
                setLocalBotActive((prev) => ({ ...prev, [conv.id]: false }));
                toastManager.success({ title: `✅ Lead convertido: ${conv.prospect_name ?? conv.phone}` });
            })
            .catch(() => toastManager.error({ title: 'Error al marcar como convertido' }))
            .finally(() => setProcessingId(null));
    }, []);

    const handleReactivate = useCallback((conv: Conversation) => {
        if (getManualPause(conv)) {
            toastManager.error({
                title: 'Lead pausado manualmente. Pulsa Reanudar antes de enviar reactivación.',
            });
            return;
        }
        if (!confirm(`¿Enviar mensaje de reactivación a ${conv.prospect_name ?? conv.phone} ahora mismo?`)) return;
        setProcessingId(conv.id);
        csrfFetch(salesbotConversations.reactivate(conv.id).url, 'POST')
            .then(async (res) => {
                const data = await res.json();
                if (!res.ok) throw new Error(data?.error ?? 'Error');
                toastManager.success({
                    title: `Mensaje de reactivación enviado (intento #${data.reactivation_count})`,
                });
                doRefresh();
            })
            .catch((e: Error) => toastManager.error({ title: e.message || 'Error al reactivar' }))
            .finally(() => setProcessingId(null));
    }, [doRefresh]);

    const handleDelete = useCallback((conv: Conversation) => {
        if (!confirm(`¿Eliminar la conversación de ${conv.prospect_name ?? conv.phone}? El bot lo tratará como lead nuevo.`)) return;
        csrfFetch(salesbotConversations.destroy(conv.id).url, 'DELETE')
            .then((res) => {
                if (!res.ok) throw new Error();
                toastManager.success({ title: 'Conversación eliminada' });
                window.location.reload();
            })
            .catch(() => toastManager.error({ title: 'Error al eliminar' }));
    }, []);

    const openEngageDialog = useCallback((conv: Conversation) => {
        setEngageTarget(conv);
        setEngageMessage(
            conv.last_message_role === 'user' && conv.last_message_body
                ? conv.last_message_body
                : 'Hola, quisiera información sobre VetSaaS y los costos.',
        );
    }, []);

    const handleEngageSubmit = useCallback((e?: FormEvent) => {
        e?.preventDefault();
        if (!engageTarget) return;
        setEngageSending(true);
        csrfPostJson(`/plataforma/salesbot-conversations/${engageTarget.id}/engage`, {
            message: engageMessage.trim(),
        })
            .then(async (res) => {
                const data = await res.json();
                if (!res.ok) throw new Error(data?.error ?? 'Error');
                setLocalBotActive((prev) => ({ ...prev, [engageTarget.id]: true }));
                toastManager.success({
                    title: `IA respondió a ${engageTarget.prospect_name ?? engageTarget.phone}`,
                    description: data.reply?.slice(0, 120) ?? undefined,
                });
                setEngageTarget(null);
                doRefresh();
            })
            .catch((e: Error) => toastManager.error({ title: e.message || 'Error al activar la IA' }))
            .finally(() => setEngageSending(false));
    }, [engageTarget, engageMessage, doRefresh]);

    const estadoOptions: readonly FilterChip<EstadoFilter>[] = useMemo(
        () => [
            { value: 'todos',      label: 'Todos' },
            { value: 'activo',     label: 'Bot activo' },
            { value: 'pausado',    label: 'Pausado' },
            { value: 'frio',       label: '❄️ Fríos +3d' },
            { value: 'convertido', label: '✅ Convertidos' },
            { value: 'perdido',    label: '⛔ Perdidos' },
        ],
        [],
    );

    const { search, setSearch, isLoading, sort, setSort, setPerPage, applyFilter } =
        useDataTablePage<{ estado: EstadoFilter }>({
            routeUrl: salesbotConversations.index().url,
            initialFilters: filters,
            only: ['conversations', 'filters', 'stats'],
            errorMessage: 'Error al cargar las conversaciones',
            storageKey: 'vetsaas.plataforma.salesbot-conversations.prefs',
            defaults: { per_page: DEFAULT_PER_PAGE, sort: null, direction: null },
        });

    const activeFiltersCount = useMemo(() => {
        let n = 0;
        if (filters.search) n++;
        if (filters.estado !== DEFAULT_ESTADO) n++;
        if (filters.per_page !== DEFAULT_PER_PAGE) n++;
        return n;
    }, [filters.search, filters.estado, filters.per_page]);

    const columns = useMemo<DataTableColumn<Conversation>[]>(() => [
        {
            key: 'prospect_name',
            header: 'Lead',
            sortable: true,
            cell: (conv) => (
                <div className="flex items-center gap-2">
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <User className="size-4" strokeWidth={2.25} />
                    </span>
                    <div className="flex min-w-0 flex-col leading-tight">
                        <span className="truncate text-sm font-semibold text-foreground">
                            {conv.prospect_name ?? 'Sin nombre'}
                        </span>
                        <span className="truncate font-mono text-xs text-muted-foreground">
                            {formatPhone(conv.phone)}
                        </span>
                        {formatTrigger(conv.activation_trigger) && (
                            <span className="mt-0.5 text-[10px] font-medium text-primary/80">
                                {formatTrigger(conv.activation_trigger)}
                            </span>
                        )}
                    </div>
                </div>
            ),
        },
        {
            key: 'last_message_at',
            header: 'Última actividad',
            sortable: true,
            cell: (conv) => (
                <div className="flex min-w-[120px] flex-col leading-tight">
                    <span className="text-xs font-medium text-foreground">
                        {timeAgo(conv.last_message_at)}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {formatWhen(conv.last_message_at)}
                    </span>
                </div>
            ),
        },
        {
            key: 'bot_active',
            header: 'Estado',
            cell: (conv) => {
                if (getConverted(conv)) {
                    return <StatBadge label="Convertido" value="" variant="success" />;
                }
                if (conv.lost_at) {
                    return <StatBadge label="Perdido" value="" variant="error" />;
                }
                const active = getBotActive(conv);
                const manualPause = getManualPause(conv);
                return active ? (
                    <StatBadge label="Bot activo" value="" variant="success" />
                ) : manualPause ? (
                    <StatBadge label="Pausado manual" value="" variant="error" />
                ) : (
                    <StatBadge label="Pausado auto" value="" variant="warning" />
                );
            },
        },
        {
            key: 'reactivation_count',
            header: 'React.',
            cell: (conv) => (
                <div className="flex flex-col items-center leading-tight">
                    <span className="text-xs font-medium text-foreground">{conv.reactivation_count}/2</span>
                    {conv.last_reactivation_at && (
                        <span className="text-[10px] text-muted-foreground">
                            {timeAgo(conv.last_reactivation_at)}
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'last_message_body',
            header: 'Último mensaje',
            cell: (conv) => (
                <div className="flex max-w-xs flex-col leading-tight">
                    <span className="line-clamp-2 text-xs text-foreground/80">
                        {conv.last_message_body ?? '—'}
                    </span>
                </div>
            ),
        },
        {
            key: 'turn_count',
            header: 'Turnos',
            cell: (conv) => (
                <span className="text-xs text-muted-foreground">{conv.turn_count}</span>
            ),
        },
        {
            key: 'acciones',
            header: <span className="md:sr-only">Acciones</span>,
            align: 'right',
            className: 'w-40',
            cell: (conv) => {
                const active   = getBotActive(conv);
                const converted = getConverted(conv);
                const loading  = processingId === conv.id;
                const canReactivate = !converted && (conv.reactivation_count < 2);
                const needsEngage =
                    !converted &&
                    !conv.lost_at &&
                    (conv.turn_count <= 1 ||
                        (conv.last_message_role === 'user' && getBotActive(conv)));
                return (
                    <div className="flex items-center justify-end gap-1">
                        {canUpdate && !converted && !conv.lost_at && (
                            <Button
                                type="button"
                                size="icon"
                                variant={needsEngage ? 'default' : 'ghost'}
                                disabled={loading}
                                onClick={() => openEngageDialog(conv)}
                                title="Responder con IA ahora"
                                className={`size-8 cursor-pointer ${needsEngage ? '' : 'text-primary hover:text-primary'}`}
                            >
                                <Sparkles className="size-4" strokeWidth={2.5} />
                            </Button>
                        )}
                        {canUpdate && !converted && (
                            <Button
                                type="button"
                                size="sm"
                                variant={active ? 'outline' : 'default'}
                                disabled={loading}
                                onClick={() => handleToggle(conv)}
                                className="cursor-pointer gap-1.5 text-xs"
                            >
                                {active ? (
                                    <>
                                        <PauseCircle className="size-3.5" strokeWidth={2.5} />
                                        <span className="hidden sm:inline">Pausar</span>
                                    </>
                                ) : (
                                    <>
                                        <PlayCircle className="size-3.5" strokeWidth={2.5} />
                                        <span className="hidden sm:inline">Reanudar</span>
                                    </>
                                )}
                            </Button>
                        )}
                        {canUpdate && canReactivate && (
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                disabled={loading}
                                onClick={() => handleReactivate(conv)}
                                title="Enviar mensaje de reactivación ahora"
                                className="size-8 cursor-pointer text-blue-500 hover:text-blue-600"
                            >
                                <SendHorizonal className="size-4" strokeWidth={2.5} />
                            </Button>
                        )}
                        {canUpdate && !converted && (
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                disabled={loading}
                                onClick={() => handleConvert(conv)}
                                title="Marcar como convertido (cerrado)"
                                className="size-8 cursor-pointer text-emerald-500 hover:text-emerald-600"
                            >
                                <CheckCircle2 className="size-4" strokeWidth={2.5} />
                            </Button>
                        )}
                        {canDelete && (
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                onClick={() => handleDelete(conv)}
                                className="size-8 cursor-pointer text-destructive hover:text-destructive"
                            >
                                <Trash2 className="size-4" strokeWidth={2.5} />
                            </Button>
                        )}
                    </div>
                );
            },
        },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    ], [canUpdate, canDelete, processingId, localBotActive, localConverted, handleToggle, handleReactivate, handleConvert, handleDelete, openEngageDialog]);

    return (
        <>
            <Head title="Conversaciones del bot" />

            <div className="flex flex-1 flex-col gap-3 p-4 sm:p-6">
                <PageHeader
                    title="Conversaciones del bot"
                    description={
                        <span className="flex items-center gap-3">
                            <span className="hidden sm:inline">
                                Pausa por lead · IA con ✨ en cada fila · actualización cada 15 s
                            </span>
                            <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <span
                                    className={`inline-block size-2 rounded-full ${isRefreshing ? 'animate-ping bg-amber-400' : 'bg-emerald-400'}`}
                                />
                                {isRefreshing
                                    ? 'Actualizando…'
                                    : `Actualizado hace ${secondsSince}s`}
                                <button
                                    type="button"
                                    onClick={doRefresh}
                                    disabled={isRefreshing}
                                    className="ml-1 cursor-pointer rounded p-0.5 hover:text-foreground disabled:opacity-50"
                                    title="Actualizar ahora"
                                >
                                    <RefreshCw className={`size-3 ${isRefreshing ? 'animate-spin' : ''}`} />
                                </button>
                            </span>
                        </span>
                    }
                    stats={[
                        { label: 'Total', value: stats.total, variant: 'info', icon: MessageCircle },
                        { label: 'Bot activo', value: stats.activos, variant: 'success', icon: Bot },
                        { label: 'Pausados', value: stats.pausados, variant: 'warning', icon: PauseCircle },
                        { label: 'Fríos', value: stats.frios, variant: 'warning', icon: Snowflake },
                        { label: 'Convertidos', value: stats.convertidos, variant: 'success', icon: CheckCircle2 },
                        { label: 'Perdidos', value: stats.perdidos, variant: 'error', icon: XCircle },
                        { label: 'Hoy', value: stats.hoy, variant: 'primary', icon: CalendarDays },
                        { label: 'Filtros', value: activeFiltersCount, variant: 'warning', icon: Filter },
                        { label: 'Coincidencias', value: stats.coincidencias, variant: 'primary', icon: Activity },
                    ]}
                />

                <DataTable
                    columns={columns}
                    data={conversations.data}
                    rowKey={(c) => c.id}
                    sort={sort}
                    onSortChange={setSort}
                    isLoading={isLoading}
                    ariaLiveMessage={`${stats.coincidencias} conversaciones`}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder="Buscar por nombre o teléfono..."
                            filtersClassName="sm:flex-1 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-2"
                        >
                            <FilterChips
                                ariaLabel="Filtrar por estado del bot"
                                value={filters.estado}
                                onChange={(estado) => applyFilter({ estado })}
                                options={estadoOptions}
                            />
                            <SalesBotQuickActions canUpdate={canUpdate} onSuccess={doRefresh} />
                        </DataToolbar>
                    }
                    footer={
                        <DataPagination
                            meta={conversations}
                            onPerPageChange={setPerPage}
                            preservedQuery={{
                                search: filters.search || undefined,
                                per_page: filters.per_page,
                                estado: filters.estado !== DEFAULT_ESTADO ? filters.estado : undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={MessageCircle}
                            title="Sin conversaciones todavía"
                            description="Los leads de Facebook Ads aparecerán aquí al enviar el saludo. Si no entró la IA, usa «Responder con IA» arriba."
                        />
                    }
                />

                <FormModal
                    open={engageTarget !== null}
                    onOpenChange={(open) => !open && setEngageTarget(null)}
                    title="Responder con IA"
                    description={
                        engageTarget
                            ? `${engageTarget.prospect_name ?? 'Sin nombre'} · ${formatPhone(engageTarget.phone)}`
                            : undefined
                    }
                    size="sm"
                    onSubmit={handleEngageSubmit}
                    footer={
                        <div className="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button type="button" variant="outline" onClick={() => setEngageTarget(null)}>
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={engageSending} className="gap-2">
                                {engageSending && <Loader2 className="size-4 animate-spin" />}
                                {engageSending ? 'Enviando…' : 'Activar y enviar'}
                            </Button>
                        </div>
                    }
                >
                    <FormSection>
                        <FormField id="row-engage-message" label="Mensaje del lead (contexto para la IA)">
                            <Textarea
                                id="row-engage-message"
                                rows={4}
                                value={engageMessage}
                                onChange={(e) => setEngageMessage(e.target.value)}
                            />
                        </FormField>
                    </FormSection>
                </FormModal>
            </div>
        </>
    );
}

SalesBotConversationsIndex.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma' },
            { title: 'Conversaciones bot', href: '/plataforma/salesbot-conversations' },
        ]}
    >
        {page}
    </AppLayout>
);
