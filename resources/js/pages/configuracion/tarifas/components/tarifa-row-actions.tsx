import { Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';
import type { GroomingTarifa, HotelTarifa } from '../types';

type TarifaRow = GroomingTarifa | HotelTarifa;

type TarifaRowActionsProps = {
    onEdit: () => void;
    onDelete: () => void;
    canUpdate: boolean;
    canDelete: boolean;
};

const iconButtonClass =
    'group inline-flex size-8 cursor-pointer items-center justify-center rounded-lg transition-colors focus-visible:outline-none focus-visible:ring-2';

export function TarifaRowActions({ onEdit, onDelete, canUpdate, canDelete }: TarifaRowActionsProps) {
    const { t } = useTranslation(['tarifas-servicios', 'common']);

    if (!canUpdate && !canDelete) {
        return null;
    }

    return (
        <div className="flex items-center justify-end gap-1">
            {canUpdate ? (
                <button
                    type="button"
                    aria-label={t('actions.editar')}
                    className={cn(iconButtonClass, 'focus-visible:ring-primary/30')}
                    onClick={onEdit}
                >
                    <Pencil className="size-4 text-primary/55 transition-colors group-hover:text-primary" />
                </button>
            ) : null}
            {canDelete ? (
                <button
                    type="button"
                    aria-label={t('actions.eliminar')}
                    className={cn(iconButtonClass, 'focus-visible:ring-destructive/30')}
                    onClick={onDelete}
                >
                    <Trash2 className="size-4 text-destructive/55 transition-colors group-hover:text-destructive" />
                </button>
            ) : null}
        </div>
    );
}

export type { TarifaRow };
