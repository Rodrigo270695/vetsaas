import { router } from '@inertiajs/react';
import { Megaphone, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

import type { AnnouncementEntry } from '../types';

const ROUTE_URL = '/plataforma/bot-ia-announcements';

type Props = {
    entry: AnnouncementEntry;
    activeAnnouncementId: string | null;
    onEdit: (entry: AnnouncementEntry) => void;
    onDelete: (entry: AnnouncementEntry) => void;
    canUpdate?: boolean;
    canDelete?: boolean;
};

export function AnnouncementRowActions({
    entry,
    activeAnnouncementId,
    onEdit,
    onDelete,
    canUpdate = true,
    canDelete = true,
}: Props) {
    const { t } = useTranslation(['bot-ia-announcements', 'common']);
    const isLive = activeAnnouncementId === entry.id;

    const activate = () => {
        router.post(`${ROUTE_URL}/${entry.id}/activate`, {}, { preserveScroll: true });
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button type="button" variant="ghost" size="icon" className="size-8" aria-label={entry.title}>
                    <MoreHorizontal className="size-4" strokeWidth={2.5} />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                {canUpdate ? (
                    <DropdownMenuItem onSelect={() => onEdit(entry)} className="gap-2">
                        <Pencil className="size-4" strokeWidth={2.25} />
                        {t('common:actions.edit')}
                    </DropdownMenuItem>
                ) : null}
                {canUpdate && !isLive ? (
                    <DropdownMenuItem onSelect={activate} className="gap-2">
                        <Megaphone className="size-4" strokeWidth={2.25} />
                        {t('bot-ia-announcements:actions.activate')}
                    </DropdownMenuItem>
                ) : null}
                {canUpdate && canDelete ? <DropdownMenuSeparator /> : null}
                {canDelete ? (
                    <DropdownMenuItem
                        onSelect={() => onDelete(entry)}
                        className="gap-2 text-destructive focus:text-destructive"
                    >
                        <Trash2 className="size-4" strokeWidth={2.25} />
                        {t('bot-ia-announcements:actions.delete')}
                    </DropdownMenuItem>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
