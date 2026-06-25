import { MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { KnowledgeEntry } from '../types';

export type KnowledgeRowActionsProps = {
    entry: KnowledgeEntry;
    onEdit: (entry: KnowledgeEntry) => void;
    onDelete: (entry: KnowledgeEntry) => void;
    canUpdate?: boolean;
    canDelete?: boolean;
};

export function KnowledgeRowActions({
    entry,
    onEdit,
    onDelete,
    canUpdate = true,
    canDelete = true,
}: KnowledgeRowActionsProps) {
    const { t } = useTranslation(['salesbot-knowledge', 'common']);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={`Acciones para ${entry.title}`}
                    className="size-8 cursor-pointer"
                >
                    <MoreHorizontal className="size-4" strokeWidth={2.5} />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                {canUpdate && (
                    <DropdownMenuItem
                        onSelect={() => onEdit(entry)}
                        className="cursor-pointer gap-2"
                    >
                        <Pencil className="size-4" strokeWidth={2.25} />
                        {t('common:actions.edit')}
                    </DropdownMenuItem>
                )}
                {canUpdate && canDelete && <DropdownMenuSeparator />}
                {canDelete && (
                    <DropdownMenuItem
                        onSelect={() => onDelete(entry)}
                        className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                    >
                        <Trash2 className="size-4" strokeWidth={2.25} />
                        {t('salesbot-knowledge:actions.delete')}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
