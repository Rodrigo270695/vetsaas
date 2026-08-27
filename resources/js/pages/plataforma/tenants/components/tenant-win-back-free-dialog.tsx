import { router } from '@inertiajs/react';
import { Loader2, Mail } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type TenantWinBackFreeDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** UUIDs de tenants Free vencidos (modo selección). */
    ids: string[];
    /** Si true, envía a todos los Free vencidos (ignora ids). */
    allFreeExpired?: boolean;
    onCompleted?: () => void;
};

/**
 * Confirma el envío masivo (o individual) de win-back Free por email.
 */
export function TenantWinBackFreeDialog({
    open,
    onOpenChange,
    ids,
    allFreeExpired = false,
    onCompleted,
}: TenantWinBackFreeDialogProps) {
    const { t } = useTranslation(['tenants', 'common']);
    const [processing, setProcessing] = useState(false);
    const [force, setForce] = useState(false);

    const count = allFreeExpired ? null : ids.length;

    const onConfirm = () => {
        if (!allFreeExpired && ids.length === 0) {
            return;
        }
        setProcessing(true);
        router.post(
            '/plataforma/tenants/win-back-free/send',
            allFreeExpired
                ? { scope: 'all_free_expired', force }
                : { scope: 'selected', ids, force },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    onCompleted?.();
                    onOpenChange(false);
                    setForce(false);
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="flex size-11 items-center justify-center rounded-full bg-sky-500/10 text-sky-600">
                        <Mail className="size-5" strokeWidth={2.5} aria-hidden="true" />
                    </div>
                    <DialogTitle className="pt-2 text-base">
                        {allFreeExpired
                            ? t('tenants:win_back_free.title_all')
                            : t('tenants:win_back_free.title', { count: count ?? 0 })}
                    </DialogTitle>
                    <DialogDescription className="text-sm">
                        {allFreeExpired
                            ? t('tenants:win_back_free.description_all')
                            : t('tenants:win_back_free.description', { count: count ?? 0 })}
                    </DialogDescription>
                </DialogHeader>

                <label className="flex cursor-pointer items-start gap-2 rounded-lg border border-border/60 bg-muted/30 px-3 py-2.5 text-[12px]">
                    <input
                        type="checkbox"
                        checked={force}
                        onChange={(e) => setForce(e.target.checked)}
                        className="mt-0.5 size-4 rounded border accent-sky-600"
                    />
                    <span>
                        <span className="font-medium text-foreground">
                            {t('tenants:win_back_free.force_label')}
                        </span>
                        <span className="mt-0.5 block text-muted-foreground">
                            {t('tenants:win_back_free.force_hint')}
                        </span>
                    </span>
                </label>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={processing}
                        onClick={() => onOpenChange(false)}
                        className="cursor-pointer"
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="button"
                        disabled={processing || (!allFreeExpired && ids.length === 0)}
                        onClick={onConfirm}
                        className="cursor-pointer gap-1.5"
                    >
                        {processing ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <Mail className="size-4" />
                        )}
                        {allFreeExpired
                            ? t('tenants:win_back_free.confirm_all')
                            : t('tenants:win_back_free.confirm', { count: count ?? 0 })}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
