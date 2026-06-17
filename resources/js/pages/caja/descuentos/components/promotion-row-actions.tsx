import { Percent, Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Promotion } from '../types';

type Props = {
    promotion: Promotion;
    canUpdate: boolean;
    canDelete: boolean;
    onEdit: (row: Promotion) => void;
    onDelete: (row: Promotion) => void;
};

export function PromotionRowActions({ promotion, canUpdate, canDelete, onEdit, onDelete }: Props) {
    const { t } = useTranslation('common');

    if (!canUpdate && !canDelete) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="size-8" aria-label={t('actions.open_menu')}>
                    <span className="sr-only">{t('actions.open_menu')}</span>
                    <span className="text-lg leading-none">⋯</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {canUpdate ? (
                    <DropdownMenuItem onClick={() => onEdit(promotion)}>
                        <Pencil className="size-4" aria-hidden />
                        {t('actions.edit')}
                    </DropdownMenuItem>
                ) : null}
                {canDelete ? (
                    <DropdownMenuItem className="text-destructive focus:text-destructive" onClick={() => onDelete(promotion)}>
                        <Trash2 className="size-4" aria-hidden />
                        {t('actions.delete')}
                    </DropdownMenuItem>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
