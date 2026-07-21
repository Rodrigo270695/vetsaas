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
import type { KnowledgeEntry } from '../types';

export function KnowledgeDeleteDialog({
    open,
    onOpenChange,
    entry,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    entry: KnowledgeEntry | null;
}) {
    const { t } = useTranslation('in-app-assistant-knowledge');
    const [processing, setProcessing] = useState(false);

    const confirm = () => {
        if (!entry) {
            return;
        }

        setProcessing(true);
        router.delete(`/plataforma/in-app-assistant-knowledge/${entry.id}`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <TriangleAlert className="size-10 rounded-full bg-destructive/10 p-2 text-destructive" />
                    <DialogTitle>{t('delete.title')}</DialogTitle>
                    <DialogDescription>
                        <Trans
                            ns="in-app-assistant-knowledge"
                            i18nKey="delete.description"
                            values={{ title: entry?.title ?? '' }}
                            components={{ strong: <strong /> }}
                        />
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={processing}
                        onClick={() => onOpenChange(false)}
                    >
                        {t('delete.cancel')}
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        disabled={processing}
                        onClick={confirm}
                    >
                        {processing && (
                            <Loader2 className="size-4 animate-spin" />
                        )}
                        {t('delete.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
