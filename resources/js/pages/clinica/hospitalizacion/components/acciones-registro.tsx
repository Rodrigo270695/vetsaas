import { MoreVertical, Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type Props = {
    onEdit: () => void;
    onDelete: () => void;
};

export function AccionesRegistro({ onEdit, onDelete }: Props) {
    const { t } = useTranslation('common');

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8 shrink-0 cursor-pointer rounded-full text-muted-foreground hover:text-foreground"
                    aria-label={t('actions.more', { defaultValue: 'Acciones' })}
                >
                    <MoreVertical className="size-4" strokeWidth={2.25} />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-40">
                <DropdownMenuItem className="cursor-pointer gap-2" onClick={onEdit}>
                    <Pencil className="size-3.5" />
                    {t('actions.edit')}
                </DropdownMenuItem>
                <DropdownMenuItem
                    className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                    onClick={onDelete}
                >
                    <Trash2 className="size-3.5" />
                    {t('actions.delete')}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
