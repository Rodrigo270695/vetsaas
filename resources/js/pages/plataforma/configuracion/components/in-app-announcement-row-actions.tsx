import { router } from '@inertiajs/react';
import { Megaphone, MoreHorizontal, Pencil, Trash2, VolumeX } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

import type { InAppAnnouncementRecord } from '../types';

const ROUTE_URL = '/plataforma/configuracion/novedades';

type Props = {
    entry: InAppAnnouncementRecord;
    isLive: boolean;
    canActivateMore: boolean;
    onEdit: (entry: InAppAnnouncementRecord) => void;
    onDelete: (entry: InAppAnnouncementRecord) => void;
    canUpdate?: boolean;
};

export function InAppAnnouncementRowActions({
    entry,
    isLive,
    canActivateMore,
    onEdit,
    onDelete,
    canUpdate = true,
}: Props) {
    const { t } = useTranslation(['platform', 'common']);

    const activate = () => {
        router.post(`${ROUTE_URL}/${entry.id}/activar`, {}, { preserveScroll: true });
    };

    const deactivate = () => {
        router.post(`${ROUTE_URL}/${entry.id}/desactivar`, {}, { preserveScroll: true });
    };

    const republish = () => {
        router.post(`${ROUTE_URL}/${entry.id}/republicar`, {}, { preserveScroll: true });
    };

    if (!canUpdate) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    aria-label={entry.title}
                >
                    <MoreHorizontal className="size-4" strokeWidth={2.5} />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48">
                <DropdownMenuItem onSelect={() => onEdit(entry)} className="gap-2">
                    <Pencil className="size-4" strokeWidth={2.25} />
                    {t('common:actions.edit')}
                </DropdownMenuItem>
                {isLive ? (
                    <>
                        <DropdownMenuItem onSelect={republish} className="gap-2">
                            <Megaphone className="size-4" strokeWidth={2.25} />
                            {t('platform:announcements.actions.republish')}
                        </DropdownMenuItem>
                        <DropdownMenuItem onSelect={deactivate} className="gap-2">
                            <VolumeX className="size-4" strokeWidth={2.25} />
                            {t('platform:announcements.actions.deactivate')}
                        </DropdownMenuItem>
                    </>
                ) : (
                    <DropdownMenuItem
                        onSelect={activate}
                        disabled={!canActivateMore}
                        className="gap-2"
                    >
                        <Megaphone className="size-4" strokeWidth={2.25} />
                        {t('platform:announcements.actions.activate')}
                    </DropdownMenuItem>
                )}
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    onSelect={() => onDelete(entry)}
                    className="gap-2 text-destructive focus:text-destructive"
                >
                    <Trash2 className="size-4" strokeWidth={2.25} />
                    {t('common:actions.delete')}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
