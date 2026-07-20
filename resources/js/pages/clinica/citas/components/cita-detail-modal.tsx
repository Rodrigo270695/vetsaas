import { router, usePage } from '@inertiajs/react';
import { Clock, MapPin, Pencil, Stethoscope, Trash2, User, XCircle } from 'lucide-react';
import type { ReactNode } from 'react';
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

    if (!cita) {
        return null;
    }

    const canCancelCita =
        canCancel && !['cancelada', 'completada'].includes(cita.estado);

    const canAperturar =
        can('citas.aperturar') &&
        can('historias-clinicas.create') &&
        Boolean(cita.paciente_id) &&
        ['programada', 'confirmada'].includes(cita.estado);

    const aperturarConsulta = () => {
        onOpenChange(false);
        router.post(`/clinica/citas/${cita.id}/aperturar`);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
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
                                onClick={aperturarConsulta}
                            >
                                <Stethoscope className="size-4" />
                                {t('citas:detail.open_consulta')}
                            </Button>
                        ) : null}
                        {canUpdate ? (
                            <Button
                                type="button"
                                variant={canAperturar ? 'outline' : 'default'}
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
    );
}
