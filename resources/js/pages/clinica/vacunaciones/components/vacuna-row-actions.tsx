import { Link } from '@inertiajs/react';
import { Banknote, MoreHorizontal, Pencil, Receipt, Trash2, Wallet } from 'lucide-react';
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
import type { VacunaAplicadaRow } from '../types';

export type VacunaRowActionsProps = {
    vacuna: VacunaAplicadaRow;
    onEdit: (v: VacunaAplicadaRow) => void;
    onDelete: (v: VacunaAplicadaRow) => void;
    canUpdate: boolean;
    canDelete: boolean;
    canCobrar: boolean;
};

export function VacunaRowActions({
    vacuna,
    onEdit,
    onDelete,
    canUpdate,
    canDelete,
    canCobrar,
}: VacunaRowActionsProps) {
    const { t } = useTranslation(['vacunaciones', 'common']);

    const puedeVerCargos = canUpdate || canCobrar;
    const urlCargos =
        typeof vacuna.url_cargos === 'string' && vacuna.url_cargos !== ''
            ? vacuna.url_cargos
            : puedeVerCargos
              ? `/clinica/vacunaciones/${vacuna.id}/cargos`
              : null;

    const urlCobrarFromServer =
        typeof vacuna.url_cobrar === 'string' && vacuna.url_cobrar !== ''
            ? vacuna.url_cobrar
            : null;
    const urlCobrarFallback =
        canCobrar &&
        vacuna.cargo?.estado === 'confirmado' &&
        vacuna.cargo.venta_id == null
            ? `/caja/ventas/desde-vacuna/${vacuna.id}`
            : null;
    const urlCobrar = urlCobrarFromServer ?? urlCobrarFallback;

    if (!canUpdate && !canDelete && !urlCargos && !urlCobrar) {
        return null;
    }

    return (
        <div className="flex items-center justify-end gap-1">
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
                <DropdownMenuContent align="end" className="w-48">
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
                                <Banknote className="size-4" strokeWidth={2.25} />
                                {t('actions.cobrar')}
                            </a>
                        </DropdownMenuItem>
                    ) : null}
                    {canUpdate ? (
                        <DropdownMenuItem
                            className="cursor-pointer gap-2"
                            onClick={() => onEdit(vacuna)}
                        >
                            <Pencil className="size-4" strokeWidth={2.25} />
                            {t('common:actions.edit')}
                        </DropdownMenuItem>
                    ) : null}
                    {canDelete ? (
                        <>
                            {(urlCargos || urlCobrar || canUpdate) && <DropdownMenuSeparator />}
                            <DropdownMenuItem
                                className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                                onClick={() => onDelete(vacuna)}
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
