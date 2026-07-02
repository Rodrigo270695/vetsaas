import { router } from '@inertiajs/react';
import { Loader2, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { Trans, useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

import type { AnnouncementEntry } from '../types';

const ROUTE_URL = '/plataforma/bot-ia-announcements';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    entry: AnnouncementEntry | null;
};

export function AnnouncementDeleteDialog({ open, onOpenChange, entry }: Props) {
    const { t } = useTranslation(['bot-ia-announcements', 'common']);
    const [processing, setProcessing] = useState(false);

    const onConfirm = () => {
        if (!entry) {
            return;
        }

        setProcessing(true);
        router.delete(`${ROUTE_URL}/${entry.id}`, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="flex size-11 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                        <TriangleAlert className="size-5" strokeWidth={2.5} aria-hidden="true" />
                    </div>
                    <DialogTitle className="pt-2 text-base">
                        {t('bot-ia-announcements:delete.title')}
                    </DialogTitle>
                    <DialogDescription className="text-sm text-muted-foreground">
                        <Trans
                            i18nKey="bot-ia-announcements:delete.description"
                            values={{ title: entry?.title ?? '' }}
                            components={{ strong: <strong /> }}
                        />
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        {t('bot-ia-announcements:delete.cancel')}
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={onConfirm}
                        disabled={processing}
                        className="gap-2"
                    >
                        {processing ? <Loader2 className="size-4 animate-spin" /> : null}
                        {t('bot-ia-announcements:delete.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
