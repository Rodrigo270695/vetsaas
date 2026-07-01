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
};

export function KnowledgeRowActions({
    entry,
    onEdit,
    onDelete,
}: KnowledgeRowActionsProps) {
    const { t } = useTranslation(['bot-ia', 'common']);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={`Acciones para ${entry.title}`}
                    className="size-8"
                >
                    <MoreHorizontal className="size-4" strokeWidth={2.5} />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                <DropdownMenuItem onSelect={() => onEdit(entry)} className="gap-2">
                    <Pencil className="size-4" strokeWidth={2.25} />
                    {t('common:actions.edit')}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    onSelect={() => onDelete(entry)}
                    className="gap-2 text-destructive focus:text-destructive"
                >
                    <Trash2 className="size-4" strokeWidth={2.25} />
                    {t('knowledge.actions.delete')}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
