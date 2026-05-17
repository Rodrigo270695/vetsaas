import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, ClipboardList, FileText, Loader2, Package, Pill, Sparkles } from 'lucide-react';
import { useMemo, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState, StatBadge } from '@/components/data-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardTitle } from '@/components/ui/card';
import { FormField } from '@/components/forms';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import clinica from '@/routes/clinica';
import { PlanMedicacionEditor } from './components/plan-medicacion-editor';
import { formatAtendidoInAppTimezone } from './format-atendido';
import type { ConsultaHistoriaPlanPageRow } from './types';

type Props = {
    consulta: ConsultaHistoriaPlanPageRow;
};

type SeguimientoForm = {
    registrado_at: string;
    nota: string;
};

function toDatetimeLocalValue(d: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function estadoBadgeVariant(
    estado: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (estado === 'activo') {
        return 'default';
    }
    if (estado === 'completado') {
        return 'secondary';
    }
    if (estado === 'suspendido') {
        return 'outline';
    }
    return 'secondary';
}

/** Rango de filtro `atendido_*` del mes calendario de la fecha de atención (para el listado). */
function monthRangeFromAtendidoIso(iso: string): { desde: string; hasta: string } {
    const d = new Date(iso);
    const y = d.getFullYear();
    const m = d.getMonth();
    const pad = (n: number) => String(n).padStart(2, '0');
    const desde = `${y}-${pad(m + 1)}-01`;
    const last = new Date(y, m + 1, 0);
    const hasta = `${y}-${pad(m + 1)}-${pad(last.getDate())}`;

    return { desde, hasta };
}

export default function PlanTratamiento({ consulta }: Props) {
    const { t } = useTranslation(['historias-clinicas', 'common']);
    const { locale: appLocale, timezone: appTz } = usePage().props;
    const { can } = usePermission();
    const canManage = can('historias-clinicas-planes.manage');
    const canEditConsulta = can('historias-clinicas.update');
    const consultaCerrada = Boolean(consulta.cerrada_at);
    const canManagePlan = canManage && !consultaCerrada;

    const plan = consulta.plan_tratamiento;
    const pacienteNombre = consulta.historia_clinica.paciente.nombre;

    const title = useMemo(
        () => t('plan.page_title', { paciente: pacienteNombre }),
        [t, pacienteNombre],
    );

    const emptySeg: SeguimientoForm = useMemo(
        () => ({
            registrado_at: toDatetimeLocalValue(new Date()),
            nota: '',
        }),
        [],
    );

    const { data, setData, post, processing, errors, clearErrors } = useForm<SeguimientoForm>(emptySeg);

    const onSeguimientoSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (consultaCerrada) {
            return;
        }
        if (!plan) {
            return;
        }
        post(
            clinica.historiasClinicas.consultas.planTratamiento.seguimientos.store.url(consulta.id),
            {
                preserveScroll: true,
                onSuccess: () => {
                    clearErrors();
                    setData({
                        registrado_at: toDatetimeLocalValue(new Date()),
                        nota: '',
                    });
                },
            },
        );
    };

    const historiasEditarUrl = useMemo(() => {
        const { desde, hasta } = monthRangeFromAtendidoIso(consulta.atendido_at);
        return clinica.historiasClinicas.url({
            query: {
                editar_consulta: consulta.id,
                atendido_desde: desde,
                atendido_hasta: hasta,
            },
        });
    }, [consulta.atendido_at, consulta.id]);

    const fechaFmt = (iso: string | null) => {
        if (!iso) {
            return '—';
        }
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) {
            return '—';
        }
        return d.toLocaleDateString(appLocale, { timeZone: appTz });
    };

    return (
        <>
            <Head title={title} />
            <div className="flex flex-1 flex-col gap-4 p-4 sm:gap-5 sm:p-6">
                {consultaCerrada && canManage ? (
                    <div className="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm text-amber-950 dark:text-amber-100">
                        {t('plan.consulta_cerrada_aviso')}
                    </div>
                ) : null}
                <div className="rounded-lg border border-border/80 bg-card shadow-sm ring-1 ring-primary/5">
                    <div className="flex flex-col gap-3 p-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-4 sm:py-3">
                        <div className="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-start sm:gap-3">
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-8 w-fit shrink-0 gap-1.5 px-2 text-muted-foreground hover:bg-primary/10 hover:text-foreground"
                                asChild
                            >
                                <Link href={clinica.historiasClinicas.url()}>
                                    <ArrowLeft className="size-4 shrink-0" strokeWidth={2.25} />
                                    {t('plan.back_list')}
                                </Link>
                            </Button>
                            <div className="min-w-0 flex-1 space-y-1">
                                <h1 className="text-lg font-semibold tracking-tight text-foreground sm:text-xl">
                                    {title}
                                </h1>
                                <p className="line-clamp-2 text-xs leading-snug text-muted-foreground sm:text-sm">
                                    {t('plan.page_description')}
                                </p>
                            </div>
                        </div>
                        <StatBadge
                            label={t('columns.atendido_at')}
                            value={formatAtendidoInAppTimezone(
                                consulta.atendido_at,
                                appLocale,
                                appTz,
                            )}
                            variant="primary"
                            icon={CalendarDays}
                            className="max-w-full shrink-0 self-start sm:self-center"
                        />
                    </div>
                </div>

                    {!plan ? (
                        <div className="flex flex-col gap-4">
                            {canManagePlan ? (
                                <Card className="gap-0 overflow-hidden border-border/70 py-0 shadow-md ring-1 ring-primary/10 dark:ring-primary/20">
                                    <div className="flex items-center gap-2 border-b border-primary/10 bg-gradient-to-r from-primary/[0.08] to-muted/40 px-4 py-2.5 sm:px-5 dark:from-primary/15">
                                        <span className="flex size-8 items-center justify-center rounded-lg bg-primary/15 text-primary shadow-sm ring-1 ring-primary/20">
                                            <Sparkles className="size-4" strokeWidth={2} aria-hidden />
                                        </span>
                                        <div>
                                            <CardTitle className="text-sm font-semibold">
                                                {t('plan.editor.define_title')}
                                            </CardTitle>
                                            <p className="text-xs text-muted-foreground">
                                                {t('plan.empty_no_plan_description')}
                                            </p>
                                        </div>
                                    </div>
                                    <CardContent className="px-4 py-4 sm:px-5">
                                        <PlanMedicacionEditor
                                            consultaId={consulta.id}
                                            initialPlan={null}
                                        />
                                    </CardContent>
                                </Card>
                            ) : (
                                <EmptyState
                                    icon={Pill}
                                    title={t('plan.empty_no_plan_title')}
                                    description={t('plan.empty_readonly_hint')}
                                />
                            )}
                            {canEditConsulta ? (
                                <p className="text-center text-sm text-muted-foreground sm:text-left">
                                    <Link
                                        href={historiasEditarUrl}
                                        className="font-medium text-primary underline decoration-primary/35 underline-offset-4 transition-colors hover:text-primary/90 hover:decoration-primary/55"
                                    >
                                        {t('plan.soap_edit_link')}
                                    </Link>
                                </p>
                            ) : null}
                        </div>
                    ) : (
                        <div className="flex flex-col gap-4 sm:gap-5">
                            <Card className="gap-0 overflow-hidden border-border/70 py-0 shadow-md ring-1 ring-primary/10 dark:ring-primary/20">
                                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-primary/10 bg-gradient-to-r from-primary/[0.08] to-muted/40 px-3 py-2 sm:px-4 dark:from-primary/15">
                                    <div className="flex items-center gap-2">
                                        <span className="flex size-7 items-center justify-center rounded-md bg-primary/15 text-primary shadow-sm ring-1 ring-primary/20">
                                            <CalendarDays className="size-3.5" strokeWidth={2.25} aria-hidden />
                                        </span>
                                        <CardTitle className="text-sm font-semibold tracking-tight">
                                            {t('plan.card_resumen_title')}
                                        </CardTitle>
                                    </div>
                                    <Badge
                                        variant={estadoBadgeVariant(plan.estado)}
                                        className="rounded-full px-2.5 py-0.5 text-xs font-semibold shadow-sm"
                                    >
                                        {t(`plan.estado.${plan.estado}`)}
                                    </Badge>
                                </div>
                                <CardContent className="space-y-3 bg-card px-3 py-3 sm:px-4">
                                    <div className="flex flex-wrap items-center gap-2 sm:gap-3">
                                        <div className="inline-flex items-center gap-2 rounded-lg border border-primary/15 bg-primary/[0.04] px-2.5 py-1.5 text-sm shadow-sm dark:bg-primary/10">
                                            <span className="text-[0.65rem] font-semibold uppercase tracking-wide text-muted-foreground">
                                                {t('plan.fecha_inicio')}
                                            </span>
                                            <span className="font-semibold tabular-nums text-foreground">
                                                {fechaFmt(plan.fecha_inicio)}
                                            </span>
                                        </div>
                                        <span
                                            className="hidden text-muted-foreground/50 sm:inline"
                                            aria-hidden
                                        >
                                            →
                                        </span>
                                        <div className="inline-flex items-center gap-2 rounded-lg border border-primary/15 bg-primary/[0.04] px-2.5 py-1.5 text-sm shadow-sm dark:bg-primary/10">
                                            <span className="text-[0.65rem] font-semibold uppercase tracking-wide text-muted-foreground">
                                                {t('plan.fecha_fin')}
                                            </span>
                                            <span className="font-semibold tabular-nums text-foreground">
                                                {fechaFmt(plan.fecha_fin)}
                                            </span>
                                        </div>
                                    </div>
                                    {plan.indicaciones ? (
                                        <div className="rounded-lg border border-dashed border-primary/25 bg-primary/[0.04] px-3 py-2.5 dark:bg-primary/10">
                                            <p className="text-[0.65rem] font-semibold uppercase tracking-wide text-primary/90">
                                                {t('plan.indicaciones')}
                                            </p>
                                            <p className="mt-1 max-h-28 overflow-y-auto whitespace-pre-wrap text-sm leading-snug text-foreground">
                                                {plan.indicaciones}
                                            </p>
                                        </div>
                                    ) : null}
                                </CardContent>
                            </Card>

                            {canManagePlan ? (
                                <Card className="gap-0 overflow-hidden border-border/70 py-0 shadow-md ring-1 ring-primary/10 dark:ring-primary/20">
                                    <div className="flex flex-wrap items-start gap-3 border-b border-primary/10 bg-gradient-to-r from-primary/[0.08] to-muted/40 px-4 py-3 sm:px-5 dark:from-primary/15">
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/15 text-primary shadow-sm ring-1 ring-primary/20">
                                            <Sparkles className="size-4" strokeWidth={2} aria-hidden />
                                        </span>
                                        <div className="min-w-0 flex-1 space-y-0.5">
                                            <CardTitle className="text-sm font-semibold sm:text-base">
                                                {t('plan.editor.update_title')}
                                            </CardTitle>
                                            <p className="text-xs leading-relaxed text-muted-foreground sm:text-sm">
                                                {t('plan.editor.update_description')}
                                            </p>
                                        </div>
                                    </div>
                                    <CardContent className="px-4 py-4 sm:px-5">
                                        <PlanMedicacionEditor
                                            consultaId={consulta.id}
                                            initialPlan={plan}
                                        />
                                    </CardContent>
                                </Card>
                            ) : null}

                            {/* Solo lectura: quien no puede editar ve la tabla compacta. Quien gestiona el plan ya tiene las líneas en el editor. */}
                            {!canManagePlan ? (
                                <Card className="gap-0 overflow-hidden border-border/70 py-0 shadow-md ring-1 ring-primary/10 dark:ring-primary/20">
                                    <div className="flex items-center gap-2 border-b border-primary/10 bg-gradient-to-r from-primary/[0.08] to-muted/40 px-4 py-2.5 sm:px-5 dark:from-primary/15">
                                        <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/15 text-primary shadow-sm ring-1 ring-primary/20">
                                            <Pill className="size-4" strokeWidth={2} aria-hidden />
                                        </span>
                                        <CardTitle className="text-sm font-semibold">
                                            {t('plan.card_lineas_title')}
                                        </CardTitle>
                                    </div>
                                    <CardContent className="px-0 pb-0 pt-0">
                                        {plan.lineas.length === 0 ? (
                                            <div className="flex flex-col gap-2 px-4 py-6 sm:px-5">
                                                <p className="text-sm text-muted-foreground">{t('plan.lineas_empty')}</p>
                                            </div>
                                        ) : (
                                            <div className="overflow-x-auto">
                                                <table className="w-full min-w-xl border-collapse text-sm">
                                                    <thead>
                                                        <tr className="border-b border-primary/10 bg-primary/[0.08] text-left text-xs font-semibold uppercase tracking-wide text-primary/90 dark:bg-primary/15">
                                                            <th className="px-4 py-2.5 pr-3 sm:px-5">
                                                                {t('plan.linea.medicamento')}
                                                            </th>
                                                            <th className="py-2.5 pr-3">{t('plan.linea.dosis')}</th>
                                                            <th className="py-2.5 pr-3">{t('plan.linea.unidad')}</th>
                                                            <th className="py-2.5 pr-3">{t('plan.linea.via')}</th>
                                                            <th className="py-2.5 pr-3">{t('plan.linea.frecuencia')}</th>
                                                            <th className="py-2.5 pr-3">{t('plan.linea.lote')}</th>
                                                            <th className="py-2.5 pr-4 sm:pr-5">
                                                                {t('plan.linea.cantidad_inventario')}
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {plan.lineas.map((ln, rowIdx) => (
                                                            <tr
                                                                key={ln.id}
                                                                className={cn(
                                                                    'border-b border-border/50 transition-colors last:border-0 hover:bg-primary/[0.05] dark:hover:bg-primary/10',
                                                                    rowIdx % 2 === 1 && 'bg-muted/15',
                                                                )}
                                                            >
                                                                <td className="px-4 py-2.5 pr-3 font-medium text-foreground sm:px-5">
                                                                    <span className="inline-flex items-center gap-1.5">
                                                                        {ln.producto_id ? (
                                                                            <Package
                                                                                className="size-3.5 shrink-0 text-primary"
                                                                                strokeWidth={2}
                                                                                aria-hidden
                                                                            />
                                                                        ) : null}
                                                                        <span>{ln.medicamento}</span>
                                                                    </span>
                                                                </td>
                                                                <td className="py-2.5 pr-3 text-muted-foreground">
                                                                    {ln.dosis ?? '—'}
                                                                </td>
                                                                <td className="py-2.5 pr-3 text-muted-foreground">
                                                                    {ln.unidad ?? '—'}
                                                                </td>
                                                                <td className="py-2.5 pr-3 text-muted-foreground">
                                                                    {ln.via ?? '—'}
                                                                </td>
                                                                <td className="py-2.5 pr-3 text-muted-foreground">
                                                                    {ln.frecuencia ?? '—'}
                                                                </td>
                                                                <td className="py-2.5 pr-3 text-muted-foreground">
                                                                    {ln.lote ?? '—'}
                                                                </td>
                                                                <td className="py-2.5 pr-4 text-muted-foreground sm:pr-5">
                                                                    {ln.cantidad != null &&
                                                                    String(ln.cantidad).trim() !== ''
                                                                        ? String(ln.cantidad)
                                                                        : '—'}
                                                                </td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            ) : null}

                            {canManagePlan ? (
                                <Card className="gap-0 overflow-hidden border-border/70 py-0 shadow-md ring-1 ring-primary/10 dark:ring-primary/20">
                                    <div className="flex items-center gap-2 border-b border-primary/10 bg-gradient-to-r from-primary/[0.08] to-muted/40 px-4 py-2.5 sm:px-5 dark:from-primary/15">
                                        <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/15 text-primary shadow-sm ring-1 ring-primary/20">
                                            <ClipboardList className="size-4" strokeWidth={2} aria-hidden />
                                        </span>
                                        <CardTitle className="text-sm font-semibold">
                                            {t('plan.card_nuevo_seguimiento')}
                                        </CardTitle>
                                    </div>
                                    <CardContent className="px-4 py-4 sm:px-5">
                                        <form
                                            onSubmit={onSeguimientoSubmit}
                                            className="flex flex-col gap-4"
                                        >
                                            <div className="grid gap-4 sm:grid-cols-2 sm:items-start">
                                                <FormField
                                                    id="seg-fecha"
                                                    label={t('plan.seguimiento.registrado_at')}
                                                    required
                                                    error={errors.registrado_at}
                                                    className="min-w-0"
                                                >
                                                    <Input
                                                        id="seg-fecha"
                                                        type="datetime-local"
                                                        value={data.registrado_at}
                                                        onChange={(e) =>
                                                            setData('registrado_at', e.target.value)
                                                        }
                                                        className="h-9 w-full min-w-0 text-sm"
                                                    />
                                                </FormField>
                                                <div className="hidden sm:block" aria-hidden />
                                                <FormField
                                                    id="seg-nota"
                                                    label={t('plan.seguimiento.nota')}
                                                    required
                                                    error={errors.nota}
                                                    className="min-w-0 sm:col-span-2"
                                                >
                                                    <Textarea
                                                        id="seg-nota"
                                                        value={data.nota}
                                                        onChange={(e) => setData('nota', e.target.value)}
                                                        rows={3}
                                                        className="min-h-[5.5rem] w-full resize-y text-sm leading-relaxed"
                                                        placeholder={t('plan.seguimiento.nota_placeholder')}
                                                    />
                                                </FormField>
                                            </div>
                                            <div className="flex justify-end border-t border-border/50 pt-3">
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    disabled={
                                                        processing ||
                                                        data.nota.trim().length === 0 ||
                                                        data.registrado_at.trim().length === 0
                                                    }
                                                    className="cursor-pointer gap-2 shadow-sm"
                                                >
                                                    {processing && (
                                                        <Loader2 className="size-4 animate-spin" aria-hidden />
                                                    )}
                                                    {t('plan.seguimiento.submit')}
                                                </Button>
                                            </div>
                                        </form>
                                    </CardContent>
                                </Card>
                            ) : null}

                            <Card className="gap-0 overflow-hidden border-border/70 py-0 shadow-md ring-1 ring-primary/10 dark:ring-primary/20">
                                <div className="flex items-center gap-2 border-b border-primary/10 bg-gradient-to-r from-primary/[0.08] to-muted/40 px-4 py-2.5 sm:px-5 dark:from-primary/15">
                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/15 text-primary shadow-sm ring-1 ring-primary/20">
                                        <FileText className="size-4" strokeWidth={2} aria-hidden />
                                    </span>
                                    <CardTitle className="text-sm font-semibold">
                                        {t('plan.card_seguimientos_title')}
                                    </CardTitle>
                                </div>
                                <CardContent className="px-4 py-4 sm:px-5">
                                    {plan.seguimientos.length === 0 ? (
                                        <p className="rounded-lg border border-dashed border-border/80 bg-muted/20 px-3 py-6 text-center text-sm text-muted-foreground">
                                            {t('plan.seguimientos_empty')}
                                        </p>
                                    ) : (
                                        <div className="relative">
                                            <div
                                                className="absolute bottom-3 left-3 top-2 w-px bg-gradient-to-b from-primary/45 via-primary/20 to-transparent"
                                                aria-hidden
                                            />
                                            <ul className="relative m-0 list-none space-y-3 p-0">
                                                {plan.seguimientos.map((s) => (
                                                    <li key={s.id} className="relative pl-8">
                                                        <span className="absolute left-3 top-2 z-1 size-2.5 -translate-x-1/2 rounded-full border-2 border-background bg-primary shadow-sm ring-2 ring-primary/25" />
                                                        <div className="rounded-xl border border-primary/15 bg-gradient-to-br from-card to-primary/[0.04] px-3 py-2.5 shadow-sm transition-shadow hover:border-primary/25 hover:shadow-md dark:to-primary/10">
                                                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                                                <span className="text-xs font-semibold uppercase tracking-wide text-primary">
                                                                    {formatAtendidoInAppTimezone(
                                                                        s.registrado_at,
                                                                        appLocale,
                                                                        appTz,
                                                                    )}
                                                                </span>
                                                                {s.creado_por ? (
                                                                    <span className="text-[0.7rem] text-muted-foreground">
                                                                        {s.creado_por.name}
                                                                    </span>
                                                                ) : null}
                                                            </div>
                                                            <p className="mt-1.5 whitespace-pre-wrap text-sm leading-relaxed text-foreground">
                                                                {s.nota}
                                                            </p>
                                                        </div>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    )}
            </div>
        </>
    );
}

PlanTratamiento.layout = {
    breadcrumbs: [
        { title: 'Clínica', href: dashboard().url },
        { title: 'Historias clínicas', href: clinica.historiasClinicas.url() },
        { title: 'Plan de tratamiento', href: '#' },
    ],
};
