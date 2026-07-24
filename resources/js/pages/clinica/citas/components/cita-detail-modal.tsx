import { router, usePage } from '@inertiajs/react';
import { Clock, Loader2, MapPin, Pencil, Stethoscope, Trash2, User, XCircle } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import { formatAtendidoInAppTimezone } from '../../historias-clinicas/format-atendido';
import type { CitaRow } from '../types';
import { displayPacienteCita, displayPropietarioCita } from './citas-calendar';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    cita: CitaRow | null;
    onEdit: (cita: CitaRow) => void;
    onDelete: (cita: CitaRow) => void;
    onCancel: (cita: CitaRow) => void;
    canUpdate: boolean;
    canDelete: boolean;
    canCancel: boolean;
};

type ConsultaHcOption = {
    id: string;
    atendido_at: string | null;
    atendido_label: string;
    motivo: string | null;
    abierta: boolean;
};

type HcChoice = 'nueva' | string;

function estadoBadgeClass(estado: string): string {
    switch (estado) {
        case 'en_atencion':
            return 'border-sky-300 bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-100';
        case 'completada':
            return 'border-emerald-300 bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100';
        case 'cancelada':
        case 'no_asistio':
            return 'border-rose-300 bg-rose-100 text-rose-900 dark:bg-rose-950 dark:text-rose-100';
        case 'programada':
        case 'confirmada':
        default:
            return 'border-amber-300 bg-amber-100 text-amber-950 dark:bg-amber-950 dark:text-amber-100';
    }
}

function DetailRow({
    icon: Icon,
    label,
    value,
    className,
}: {
    icon: typeof User;
    label: string;
    value: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('flex gap-3', className)}>
            <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <Icon className="size-4" strokeWidth={2.25} />
            </span>
            <div className="min-w-0 flex-1">
                <p className="text-[0.65rem] font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
                <div className="mt-0.5 text-sm text-foreground">{value}</div>
            </div>
        </div>
    );
}

export function CitaDetailModal({
    open,
    onOpenChange,
    cita,
    onEdit,
    onDelete,
    onCancel,
    canUpdate,
    canDelete,
    canCancel,
}: Props) {
    const { t } = useTranslation(['citas', 'common']);
    const { locale: appLocale, timezone: appTz } = usePage().props;
    const { can } = usePermission();
    const [confirmAperturarOpen, setConfirmAperturarOpen] = useState(false);
    const [consultasHc, setConsultasHc] = useState<ConsultaHcOption[]>([]);
    const [loadingHc, setLoadingHc] = useState(false);
    const [loadHcError, setLoadHcError] = useState(false);
    const [puedeReabrir, setPuedeReabrir] = useState(false);
    const [puedeCrear, setPuedeCrear] = useState(true);
    const [hcChoice, setHcChoice] = useState<HcChoice>('nueva');

    const canCancelCita =
        cita !== null &&
        canCancel &&
        !['cancelada', 'completada'].includes(cita.estado);

    const canAperturar =
        cita !== null &&
        can('citas.aperturar') &&
        can('historias-clinicas.create') &&
        Boolean(cita.paciente_id) &&
        ['programada', 'confirmada'].includes(cita.estado);

    const canAbrirHcEnAtencion =
        cita !== null &&
        can('citas.aperturar') &&
        (can('historias-clinicas.create') || can('historias-clinicas.update')) &&
        Boolean(cita.paciente_id) &&
        cita.estado === 'en_atencion';

    useEffect(() => {
        if (!confirmAperturarOpen || !cita?.id) {
            return;
        }

        let cancelled = false;
        setLoadingHc(true);
        setLoadHcError(false);

        fetch(`/clinica/citas/${cita.id}/consultas-hc`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(async (res) => {
                if (!res.ok) {
                    throw new Error('load_failed');
                }

                return res.json() as Promise<{
                    consultas: ConsultaHcOption[];
                    puede_reabrir: boolean;
                    puede_crear: boolean;
                }>;
            })
            .then((data) => {
                if (cancelled) {
                    return;
                }

                const list = Array.isArray(data.consultas) ? data.consultas : [];
                setConsultasHc(list);
                setPuedeReabrir(Boolean(data.puede_reabrir));
                setPuedeCrear(Boolean(data.puede_crear));

                const abierta = list.find((c) => c.abierta);
                if (abierta && data.puede_reabrir) {
                    setHcChoice(abierta.id);
                } else if (data.puede_crear) {
                    setHcChoice('nueva');
                } else if (list[0] && data.puede_reabrir) {
                    setHcChoice(list[0].id);
                } else {
                    setHcChoice('nueva');
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setLoadHcError(true);
                    setConsultasHc([]);
                    setHcChoice('nueva');
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoadingHc(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [confirmAperturarOpen, cita?.id]);

    if (!cita) {
        return null;
    }

    const aperturarYAbrirHc = () => {
        const payload =
            hcChoice === 'nueva'
                ? { accion: 'nueva' }
                : { accion: 'reabrir', consulta_id: hcChoice };

        setConfirmAperturarOpen(false);
        onOpenChange(false);
        router.post(`/clinica/citas/${cita.id}/aperturar`, payload);
    };

    const confirmDisabled =
        loadingHc ||
        loadHcError ||
        (hcChoice === 'nueva' ? !puedeCrear : !puedeReabrir);

    return (
        <>
            <Dialog
                open={open}
                onOpenChange={(next) => {
                    if (!next) {
                        setConfirmAperturarOpen(false);
                    }
                    onOpenChange(next);
                }}
            >
                <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-md">
                    <div className="border-b border-border/60 bg-gradient-to-br from-primary/10 via-primary/5 to-transparent px-6 pb-4 pt-6">
                        <DialogHeader className="space-y-3 text-left">
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <DialogTitle className="text-xl font-semibold tracking-tight">
                                    {displayPacienteCita(cita.paciente)}
                                </DialogTitle>
                                <Badge
                                    variant="outline"
                                    className={cn('shrink-0 font-normal', estadoBadgeClass(cita.estado))}
                                >
                                    {t(`citas:estado.${cita.estado}`, { defaultValue: cita.estado })}
                                </Badge>
                            </div>
                            <DialogDescription className="text-sm text-muted-foreground">
                                {displayPropietarioCita(cita.paciente?.propietario)}
                            </DialogDescription>
                        </DialogHeader>
                    </div>

                    <div className="space-y-4 px-6 py-5">
                        <DetailRow
                            icon={Clock}
                            label={t('citas:columns.inicio_at')}
                            value={
                                <span className="font-medium">
                                    {formatAtendidoInAppTimezone(cita.inicio_at, appLocale, appTz)}
                                    <span className="ml-2 text-muted-foreground">
                                        · {cita.duracion_minutos} min
                                    </span>
                                </span>
                            }
                        />

                        {cita.veterinario ? (
                            <DetailRow
                                icon={Stethoscope}
                                label={t('citas:columns.veterinario')}
                                value={cita.veterinario.name}
                            />
                        ) : null}

                        {cita.sede ? (
                            <DetailRow
                                icon={MapPin}
                                label={t('citas:columns.sede')}
                                value={
                                    <span>
                                        {cita.sede.nombre}
                                        <span className="ml-1.5 font-mono text-xs text-muted-foreground">
                                            {cita.sede.codigo}
                                        </span>
                                    </span>
                                }
                            />
                        ) : null}

                        {cita.motivo?.trim() ? (
                            <DetailRow
                                icon={User}
                                label={t('citas:columns.motivo')}
                                value={cita.motivo}
                            />
                        ) : null}

                        {cita.notas?.trim() ? (
                            <div className="rounded-lg border border-border/60 bg-muted/30 px-3 py-2.5">
                                <p className="text-[0.65rem] font-medium uppercase tracking-wide text-muted-foreground">
                                    {t('citas:form.notas')}
                                </p>
                                <p className="mt-1 whitespace-pre-wrap text-sm text-foreground">{cita.notas}</p>
                            </div>
                        ) : null}
                    </div>

                    <DialogFooter className="flex-col gap-2 border-t border-border/60 bg-muted/20 px-6 py-4 sm:flex-row sm:justify-between">
                        <div className="flex flex-wrap gap-2">
                            {canAperturar ? (
                                <Button
                                    type="button"
                                    className="cursor-pointer gap-2"
                                    onClick={() => setConfirmAperturarOpen(true)}
                                >
                                    <Stethoscope className="size-4" />
                                    {t('citas:detail.open_consulta')}
                                </Button>
                            ) : null}
                            {canAbrirHcEnAtencion ? (
                                <Button
                                    type="button"
                                    className="cursor-pointer gap-2"
                                    onClick={() => setConfirmAperturarOpen(true)}
                                >
                                    <Stethoscope className="size-4" />
                                    {t('citas:detail.open_hc')}
                                </Button>
                            ) : null}
                            {canUpdate ? (
                                <Button
                                    type="button"
                                    variant={canAperturar || canAbrirHcEnAtencion ? 'outline' : 'default'}
                                    className="cursor-pointer gap-2"
                                    onClick={() => {
                                        onOpenChange(false);
                                        onEdit(cita);
                                    }}
                                >
                                    <Pencil className="size-4" />
                                    {t('citas:detail.edit')}
                                </Button>
                            ) : null}
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {canCancelCita ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="cursor-pointer gap-2"
                                    onClick={() => {
                                        onOpenChange(false);
                                        onCancel(cita);
                                    }}
                                >
                                    <XCircle className="size-4" />
                                    {t('citas:actions.cancel_appointment')}
                                </Button>
                            ) : null}
                            {canDelete ? (
                                <Button
                                    type="button"
                                    variant="destructive"
                                    className="cursor-pointer gap-2"
                                    onClick={() => {
                                        onOpenChange(false);
                                        onDelete(cita);
                                    }}
                                >
                                    <Trash2 className="size-4" />
                                    {t('common:actions.delete')}
                                </Button>
                            ) : null}
                        </div>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={confirmAperturarOpen} onOpenChange={setConfirmAperturarOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{t('citas:aperturar_hc.title')}</DialogTitle>
                        <DialogDescription>
                            {t('citas:aperturar_hc.description', {
                                paciente: displayPacienteCita(cita.paciente),
                            })}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="max-h-72 space-y-2 overflow-y-auto py-1">
                        {loadingHc ? (
                            <div className="flex items-center justify-center gap-2 py-8 text-sm text-muted-foreground">
                                <Loader2 className="size-4 animate-spin" />
                                {t('citas:aperturar_hc.loading')}
                            </div>
                        ) : loadHcError ? (
                            <p className="rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive">
                                {t('citas:aperturar_hc.load_error')}
                            </p>
                        ) : (
                            <>
                                {puedeCrear ? (
                                    <label
                                        className={cn(
                                            'flex cursor-pointer items-start gap-3 rounded-lg border px-3 py-2.5 text-sm transition-colors',
                                            hcChoice === 'nueva'
                                                ? 'border-primary bg-primary/5'
                                                : 'border-border/60 hover:bg-muted/40',
                                        )}
                                    >
                                        <input
                                            type="radio"
                                            name="hc-choice"
                                            className="mt-1"
                                            checked={hcChoice === 'nueva'}
                                            onChange={() => setHcChoice('nueva')}
                                        />
                                        <span>
                                            <span className="block font-medium">
                                                {t('citas:aperturar_hc.option_nueva')}
                                            </span>
                                            <span className="mt-0.5 block text-xs text-muted-foreground">
                                                {t('citas:aperturar_hc.option_nueva_hint')}
                                            </span>
                                        </span>
                                    </label>
                                ) : null}

                                {puedeReabrir
                                    ? consultasHc.map((c) => (
                                          <label
                                              key={c.id}
                                              className={cn(
                                                  'flex cursor-pointer items-start gap-3 rounded-lg border px-3 py-2.5 text-sm transition-colors',
                                                  hcChoice === c.id
                                                      ? 'border-primary bg-primary/5'
                                                      : 'border-border/60 hover:bg-muted/40',
                                              )}
                                          >
                                              <input
                                                  type="radio"
                                                  name="hc-choice"
                                                  className="mt-1"
                                                  checked={hcChoice === c.id}
                                                  onChange={() => setHcChoice(c.id)}
                                              />
                                              <span className="min-w-0 flex-1">
                                                  <span className="flex flex-wrap items-center gap-2 font-medium">
                                                      {c.abierta
                                                          ? t('citas:aperturar_hc.option_abierta')
                                                          : t('citas:aperturar_hc.option_reactivar')}
                                                      <Badge
                                                          variant="outline"
                                                          className={cn(
                                                              'h-5 px-1.5 text-[0.65rem] font-normal',
                                                              c.abierta
                                                                  ? 'border-sky-300 bg-sky-50 text-sky-800'
                                                                  : 'border-slate-300 bg-slate-50 text-slate-700',
                                                          )}
                                                      >
                                                          {c.abierta
                                                              ? t('citas:aperturar_hc.badge_abierta')
                                                              : t('citas:aperturar_hc.badge_cerrada')}
                                                      </Badge>
                                                  </span>
                                                  <span className="mt-0.5 block text-xs text-muted-foreground">
                                                      {c.atendido_label}
                                                      {c.motivo ? ` · ${c.motivo}` : ''}
                                                  </span>
                                              </span>
                                          </label>
                                      ))
                                    : null}

                                {!puedeCrear && consultasHc.length === 0 ? (
                                    <p className="px-1 py-4 text-center text-sm text-muted-foreground">
                                        {t('citas:aperturar_hc.empty')}
                                    </p>
                                ) : null}
                            </>
                        )}
                    </div>

                    <DialogFooter className="gap-2 sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setConfirmAperturarOpen(false)}
                        >
                            {t('common:actions.cancel')}
                        </Button>
                        <Button
                            type="button"
                            className="gap-2"
                            disabled={confirmDisabled}
                            onClick={aperturarYAbrirHc}
                        >
                            <Stethoscope className="size-4" />
                            {hcChoice === 'nueva'
                                ? t('citas:aperturar_hc.confirm_nueva')
                                : t('citas:aperturar_hc.confirm_reactivar')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
