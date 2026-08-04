import { Link } from '@inertiajs/react';
import {
    Ban,
    CheckCircle2,
    Eye,
    MoreHorizontal,
    Pencil,
    Play,
    Receipt,
    Trash2,
    UserX,
    Wallet,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import type { GroomingTurnoRow } from '../types';
import type { GroomingEstadoTarget } from './grooming-estado-modal';

export type GroomingRowActionsProps = {
    turno: GroomingTurnoRow;
    onEdit: (t: GroomingTurnoRow) => void;
    onDelete: (t: GroomingTurnoRow) => void;
    onEstado: (t: GroomingTurnoRow, target: GroomingEstadoTarget) => void;
    onDetalle: (t: GroomingTurnoRow) => void;
    canUpdate: boolean;
    canDelete: boolean;
    canCobrar: boolean;
};

export function GroomingRowActions({
    turno,
    onEdit,
    onDelete,
    onEstado,
    onDetalle,
    canUpdate,
    canDelete,
    canCobrar,
}: GroomingRowActionsProps) {
    const { t } = useTranslation(['grooming', 'common']);

    const puedeVerCargos =
        (canUpdate || canCobrar) &&
        (turno.estado === 'en_proceso' || turno.estado === 'completada');
    const urlCargos = puedeVerCargos ? `/servicios/grooming/${turno.id}/cargos` : null;

    const urlCobrarFromServer =
        typeof turno.url_cobrar === 'string' && turno.url_cobrar !== '' ? turno.url_cobrar : null;
    const urlCobrarFallback =
        canCobrar &&
        (turno.estado === 'en_proceso' || turno.estado === 'completada') &&
        turno.cargo?.estado === 'confirmado' &&
        turno.cargo.venta_id == null
            ? `/caja/ventas/desde-grooming/${turno.id}`
            : null;
    const urlCobrar = urlCobrarFromServer ?? urlCobrarFallback;

    const puedeIniciar =
        canUpdate && (turno.estado === 'programada' || turno.estado === 'confirmada');
    const puedeCompletar = canUpdate && turno.estado === 'en_proceso';
    const puedeCancelar =
        canUpdate &&
        !['completada', 'cancelada', 'no_asistio'].includes(turno.estado);

    const mostrarDetalleRapido =
        turno.estado === 'completada' ||
        turno.estado === 'en_proceso' ||
        (turno.fotos?.length ?? 0) > 0;

    return (
        <div className="flex items-center justify-end gap-1">
            {mostrarDetalleRapido ? (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="h-8 gap-1.5 border-sky-500/40 bg-sky-500/10 text-sky-700 hover:bg-sky-500/15 hover:text-sky-800 dark:border-sky-400/40 dark:bg-sky-400/10 dark:text-sky-300 dark:hover:bg-sky-400/15 dark:hover:text-sky-200"
                    onClick={() => onDetalle(turno)}
                    aria-label={t('actions.detalle')}
                >
                    <Eye className="size-3.5" aria-hidden />
                    <span className="lg:hidden">{t('actions.detalle')}</span>
                </Button>
            ) : null}
            {puedeIniciar ? (
                <Button
                    type="button"
                    variant="default"
                    size="sm"
                    className="h-8 gap-1.5"
                    onClick={() => onEstado(turno, 'en_proceso')}
                >
                    <Play className="size-3.5" aria-hidden />
                    <span className="hidden lg:inline">{t('actions.iniciar')}</span>
                </Button>
            ) : null}
            {puedeCompletar ? (
                <Button
                    type="button"
                    variant="default"
                    size="sm"
                    className="h-8 gap-1.5"
                    onClick={() => onEstado(turno, 'completada')}
                >
                    <CheckCircle2 className="size-3.5" aria-hidden />
                    <span className="hidden lg:inline">{t('actions.completar')}</span>
                </Button>
            ) : null}
            {urlCobrar ? (
                <a
                    href={urlCobrar}
                    className={cn(
                        buttonVariants({ size: 'sm' }),
                        'h-8 gap-1.5 bg-emerald-600 text-white no-underline hover:bg-emerald-700 hover:text-white',
                    )}
                >
                    <Wallet className="size-3.5" aria-hidden />
                    <span className="hidden lg:inline">{t('actions.cobrar')}</span>
                </a>
            ) : null}
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8 cursor-pointer text-muted-foreground"
                        aria-label={t('columns.acciones')}
                    >
                        <MoreHorizontal className="size-4" strokeWidth={2.5} />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-56">
                    <DropdownMenuItem className="cursor-pointer gap-2" onClick={() => onDetalle(turno)}>
                        <Eye className="size-4" strokeWidth={2.25} />
                        {t('actions.detalle')}
                    </DropdownMenuItem>
                    {urlCargos ? (
                        <DropdownMenuItem asChild>
                            <Link href={urlCargos} className="flex cursor-pointer items-center gap-2">
                                <Receipt className="size-4" strokeWidth={2.25} />
                                {t('actions.cargos')}
                            </Link>
                        </DropdownMenuItem>
                    ) : null}
                    {urlCobrar ? (
                        <DropdownMenuItem asChild>
                            <a href={urlCobrar} className="flex cursor-pointer items-center gap-2">
                                <Wallet className="size-4" strokeWidth={2.25} />
                                {t('actions.cobrar')}
                            </a>
                        </DropdownMenuItem>
                    ) : null}
                    {canUpdate ? (
                        <DropdownMenuItem className="cursor-pointer gap-2" onClick={() => onEdit(turno)}>
                            <Pencil className="size-4" strokeWidth={2.25} />
                            {t('common:actions.edit')}
                        </DropdownMenuItem>
                    ) : null}
                    {puedeIniciar ? (
                        <DropdownMenuItem
                            className="cursor-pointer gap-2"
                            onClick={() => onEstado(turno, 'en_proceso')}
                        >
                            <Play className="size-4" strokeWidth={2.25} />
                            {t('actions.iniciar')}
                        </DropdownMenuItem>
                    ) : null}
                    {puedeCompletar ? (
                        <DropdownMenuItem
                            className="cursor-pointer gap-2"
                            onClick={() => onEstado(turno, 'completada')}
                        >
                            <CheckCircle2 className="size-4" strokeWidth={2.25} />
                            {t('actions.completar')}
                        </DropdownMenuItem>
                    ) : null}
                    {puedeCancelar ? (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                className="cursor-pointer gap-2"
                                onClick={() => onEstado(turno, 'cancelada')}
                            >
                                <Ban className="size-4" strokeWidth={2.25} />
                                {t('actions.cancelar_turno')}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                className="cursor-pointer gap-2"
                                onClick={() => onEstado(turno, 'no_asistio')}
                            >
                                <UserX className="size-4" strokeWidth={2.25} />
                                {t('actions.no_asistio')}
                            </DropdownMenuItem>
                        </>
                    ) : null}
                    {canDelete ? (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                                onClick={() => onDelete(turno)}
                            >
                                <Trash2 className="size-4" strokeWidth={2.25} />
                                {t('common:actions.delete')}
                            </DropdownMenuItem>
                        </>
                    ) : null}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
