import { MoreHorizontal, Pencil, Printer, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import clinica from '@/routes/clinica';
import type { RecetaRow } from '../types';

export type RecetaRowActionsProps = {
    receta: RecetaRow;
    onEdit: (r: RecetaRow) => void;
    onDelete: (r: RecetaRow) => void;
    canUpdate: boolean;
    canDelete: boolean;
    canPrint: boolean;
};

export function RecetaRowActions({
    receta,
    onEdit,
    onDelete,
    canUpdate,
    canDelete,
    canPrint,
}: RecetaRowActionsProps) {
    const { t } = useTranslation(['recetas', 'common']);

    if (!canUpdate && !canDelete && !canPrint) {
        return null;
    }

    const openPdf = () => {
        const url = clinica.recetas.pdf.url({ receta: receta.id });
        window.open(url, '_blank', 'noopener,noreferrer');
    };

    return (
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
                {canPrint ? (
                    <DropdownMenuItem className="cursor-pointer gap-2" onClick={() => openPdf()}>
                        <Printer className="size-4" strokeWidth={2.25} />
                        {t('actions.print_pdf')}
                    </DropdownMenuItem>
                ) : null}
                {canUpdate ? (
                    <DropdownMenuItem
                        className="cursor-pointer gap-2"
                        onClick={() => onEdit(receta)}
                    >
                        <Pencil className="size-4" strokeWidth={2.25} />
                        {t('common:actions.edit')}
                    </DropdownMenuItem>
                ) : null}
                {canDelete ? (
                    <DropdownMenuItem
                        className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                        onClick={() => onDelete(receta)}
                    >
                        <Trash2 className="size-4" strokeWidth={2.25} />
                        {t('common:actions.delete')}
                    </DropdownMenuItem>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
