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

const DESTROY_URL = (id: number) => `/comunicaciones/bot-ia/conocimiento/${id}`;

export type KnowledgeDeleteDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    entry: KnowledgeEntry | null;
};

export function KnowledgeDeleteDialog({
    open,
    onOpenChange,
    entry,
}: KnowledgeDeleteDialogProps) {
    const { t } = useTranslation(['bot-ia', 'common']);
    const [processing, setProcessing] = useState(false);

    const onConfirm = () => {
        if (!entry) return;
        setProcessing(true);
        router.delete(DESTROY_URL(entry.id), {
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
                        {t('knowledge.delete.title')}
                    </DialogTitle>
                    <DialogDescription className="text-sm text-muted-foreground">
                        <Trans
                            i18nKey="knowledge.delete.description"
                            ns="bot-ia"
                            values={{ title: entry?.title ?? '' }}
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
                        {t('knowledge.delete.cancel')}
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={onConfirm}
                        disabled={processing}
                        className="gap-2"
                    >
                        {processing && <Loader2 className="size-4 animate-spin" />}
                        {t('knowledge.delete.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
