import { router } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Power, PowerOff, Trash2 } from 'lucide-react';
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

export function KnowledgeRowActions({
    entry,
    onEdit,
    onDelete,
    canUpdate,
    canDelete,
}: {
    entry: KnowledgeEntry;
    onEdit: (entry: KnowledgeEntry) => void;
    onDelete: (entry: KnowledgeEntry) => void;
    canUpdate: boolean;
    canDelete: boolean;
}) {
    const { t } = useTranslation('in-app-assistant-knowledge');

    const toggle = () => {
        router.patch(
            `/plataforma/in-app-assistant-knowledge/${entry.id}`,
            { ...entry, is_active: !entry.is_active },
            { preserveScroll: true },
        );
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={t('actions.for_entry', { title: entry.title })}
                >
                    <MoreHorizontal className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {canUpdate && (
                    <>
                        <DropdownMenuItem onSelect={() => onEdit(entry)}>
                            <Pencil className="size-4" />
                            {t('actions.edit')}
                        </DropdownMenuItem>
                        <DropdownMenuItem onSelect={toggle}>
                            {entry.is_active ? (
                                <PowerOff className="size-4" />
                            ) : (
                                <Power className="size-4" />
                            )}
                            {t(
                                entry.is_active
                                    ? 'actions.deactivate'
                                    : 'actions.activate',
                            )}
                        </DropdownMenuItem>
                    </>
                )}
                {canUpdate && canDelete && <DropdownMenuSeparator />}
                {canDelete && (
                    <DropdownMenuItem
                        onSelect={() => onDelete(entry)}
                        className="text-destructive focus:text-destructive"
                    >
                        <Trash2 className="size-4" />
                        {t('actions.delete')}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
