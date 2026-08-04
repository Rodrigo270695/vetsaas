import { Link, router } from '@inertiajs/react';
import {
    Ban,
    CheckCircle2,
    ClipboardList,
    DoorOpen,
    MoreHorizontal,
    Pencil,
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
import type { HotelEstanciaRow } from '../types';

export type HotelEstadoTarget =
    | 'confirmada'
    | 'en_estancia'
    | 'completada'
    | 'cancelada'
    | 'no_presento';

export type HotelRowActionsProps = {
    estancia: HotelEstanciaRow;
    onEdit: (e: HotelEstanciaRow) => void;
    onDelete: (e: HotelEstanciaRow) => void;
    onDiarios: (e: HotelEstanciaRow) => void;
    canUpdate: boolean;
    canDelete: boolean;
    canCobrar: boolean;
    canDiarios: boolean;
};

const ESTADOS_CARGOS = new Set(['confirmada', 'en_estancia', 'completada']);

export function HotelRowActions({
    estancia,
    onEdit,
    onDelete,
    onDiarios,
    canUpdate,
    canDelete,
    canCobrar,
    canDiarios,
}: HotelRowActionsProps) {
    const { t } = useTranslation(['hotel', 'common']);

    const puedeVerCargos =
        (canUpdate || canCobrar) && ESTADOS_CARGOS.has(estancia.estado);
    const urlCargos = puedeVerCargos ? `/servicios/hotel/${estancia.id}/cargos` : null;

    const urlCobrarFromServer =
        typeof estancia.url_cobrar === 'string' && estancia.url_cobrar !== ''
            ? estancia.url_cobrar
            : null;
    const urlCobrarFallback =
        canCobrar &&
        ESTADOS_CARGOS.has(estancia.estado) &&
        estancia.cargo?.estado === 'confirmado' &&
        estancia.cargo.venta_id == null
            ? `/caja/ventas/desde-hotel/${estancia.id}`
            : null;
    const urlCobrar = urlCobrarFromServer ?? urlCobrarFallback;

    const puedeConfirmar = canUpdate && estancia.estado === 'programada';
    const puedeIngresar = canUpdate && estancia.estado === 'confirmada';
    const puedeCompletar = canUpdate && estancia.estado === 'en_estancia';
    const puedeCancelar =
        canUpdate && !['completada', 'cancelada', 'no_presento'].includes(estancia.estado);
    const puedeNoPresento =
        canUpdate && (estancia.estado === 'programada' || estancia.estado === 'confirmada');

    const postEstado = (target: HotelEstadoTarget) => {
        router.post(
            `/servicios/hotel/${estancia.id}/estado`,
            { estado: target },
            { preserveScroll: true },
        );
    };

    if (!canUpdate && !canDelete && !urlCargos && !urlCobrar && !canDiarios) {
        return null;
    }

    return (
        <div className="flex items-center justify-end gap-1">
            {puedeConfirmar ? (
                <Button
                    type="button"
                    variant="default"
                    size="sm"
                    className="h-8 gap-1.5"
                    onClick={() => postEstado('confirmada')}
                >
                    <CheckCircle2 className="size-3.5" aria-hidden />
                    <span className="hidden lg:inline">{t('actions.confirmar')}</span>
                </Button>
            ) : null}
            {puedeIngresar ? (
                <Button
                    type="button"
                    variant="default"
                    size="sm"
                    className="h-8 gap-1.5"
                    onClick={() => postEstado('en_estancia')}
                >
                    <DoorOpen className="size-3.5" aria-hidden />
                    <span className="hidden lg:inline">{t('actions.en_estancia')}</span>
                </Button>
            ) : null}
            {puedeCompletar ? (
                <Button
                    type="button"
                    variant="default"
                    size="sm"
                    className="h-8 gap-1.5"
                    onClick={() => postEstado('completada')}
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
                    {canDiarios ? (
                        <DropdownMenuItem
                            className="cursor-pointer gap-2"
                            onClick={() => onDiarios(estancia)}
                        >
                            <ClipboardList className="size-4 shrink-0" strokeWidth={2.25} />
                            {t('actions.diarios')}
                        </DropdownMenuItem>
                    ) : null}
                    {urlCargos ? (
                        <DropdownMenuItem asChild>
                            <Link href={urlCargos} className="flex cursor-pointer items-center gap-2">
                                <Receipt className="size-4 shrink-0" strokeWidth={2.25} />
                                {t('actions.cargos')}
                            </Link>
                        </DropdownMenuItem>
                    ) : null}
                    {urlCobrar ? (
                        <DropdownMenuItem asChild>
                            <a href={urlCobrar} className="flex cursor-pointer items-center gap-2">
                                <Wallet className="size-4 shrink-0" strokeWidth={2.25} />
                                {t('actions.cobrar')}
                            </a>
                        </DropdownMenuItem>
                    ) : null}
                    {canUpdate ? (
                        <DropdownMenuItem
                            className="cursor-pointer gap-2"
                            onClick={() => onEdit(estancia)}
                        >
                            <Pencil className="size-4 shrink-0" strokeWidth={2.25} />
                            {t('common:actions.edit')}
                        </DropdownMenuItem>
                    ) : null}
                    {puedeConfirmar ? (
                        <DropdownMenuItem
                            className="cursor-pointer gap-2"
                            onClick={() => postEstado('confirmada')}
                        >
                            <CheckCircle2 className="size-4 shrink-0" strokeWidth={2.25} />
                            {t('actions.confirmar')}
                        </DropdownMenuItem>
                    ) : null}
                    {puedeIngresar ? (
                        <DropdownMenuItem
                            className="cursor-pointer gap-2"
                            onClick={() => postEstado('en_estancia')}
                        >
                            <DoorOpen className="size-4 shrink-0" strokeWidth={2.25} />
                            {t('actions.en_estancia')}
                        </DropdownMenuItem>
                    ) : null}
                    {puedeCompletar ? (
                        <DropdownMenuItem
                            className="cursor-pointer gap-2"
                            onClick={() => postEstado('completada')}
                        >
                            <CheckCircle2 className="size-4 shrink-0" strokeWidth={2.25} />
                            {t('actions.completar')}
                        </DropdownMenuItem>
                    ) : null}
                    {puedeCancelar || puedeNoPresento ? (
                        <>
                            <DropdownMenuSeparator />
                            {puedeCancelar ? (
                                <DropdownMenuItem
                                    className="cursor-pointer gap-2"
                                    onClick={() => postEstado('cancelada')}
                                >
                                    <Ban className="size-4 shrink-0" strokeWidth={2.25} />
                                    {t('actions.cancelar')}
                                </DropdownMenuItem>
                            ) : null}
                            {puedeNoPresento ? (
                                <DropdownMenuItem
                                    className="cursor-pointer gap-2"
                                    onClick={() => postEstado('no_presento')}
                                >
                                    <UserX className="size-4 shrink-0" strokeWidth={2.25} />
                                    {t('actions.no_presento')}
                                </DropdownMenuItem>
                            ) : null}
                        </>
                    ) : null}
                    {canDelete ? (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                                onClick={() => onDelete(estancia)}
                            >
                                <Trash2 className="size-4 shrink-0" strokeWidth={2.25} />
                                {t('common:actions.delete')}
                            </DropdownMenuItem>
                        </>
                    ) : null}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
