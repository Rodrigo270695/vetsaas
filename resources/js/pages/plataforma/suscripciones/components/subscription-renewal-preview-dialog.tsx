import { Loader2, MessageCircle } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
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
import suscripciones from '@/routes/plataforma/suscripciones';
import type { Subscription } from '../types';

export type RenewalReminderPreview = {
    would_send: boolean;
    skip_code: string | null;
    skip_reason: string | null;
    message: string | null;
    anchor_at: string | null;
    anchor_source: string | null;
    days_until: number | null;
    reminder_kind: string | null;
    destinatario: string | null;
    already_sent: boolean;
    whatsapp_ready: boolean;
    reminder_days: number[];
};

export type SubscriptionRenewalPreviewDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    subscription: Subscription | null;
};

function previewUrl(subscriptionId: string): string {
    const base = suscripciones.index().url.replace(/\/$/, '');

    return `${base}/${subscriptionId}/renewal-reminder-preview`;
}

function formatPreviewDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleString('es-PE', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function SubscriptionRenewalPreviewDialog({
    open,
    onOpenChange,
    subscription,
}: SubscriptionRenewalPreviewDialogProps) {
    const { t } = useTranslation(['suscripciones', 'common']);
    const [loading, setLoading] = useState(false);
    const [preview, setPreview] = useState<RenewalReminderPreview | null>(null);
    const [error, setError] = useState<string | null>(null);

    const loadPreview = useCallback(async () => {
        if (!subscription) {
            return;
        }

        setLoading(true);
        setError(null);

        try {
            const res = await fetch(previewUrl(subscription.id), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!res.ok) {
                throw new Error('preview_failed');
            }

            const data = (await res.json()) as RenewalReminderPreview;
            setPreview(data);
        } catch {
            setPreview(null);
            setError(t('suscripciones:renewal_preview.load_error'));
        } finally {
            setLoading(false);
        }
    }, [subscription, t]);

    useEffect(() => {
        if (open && subscription) {
            void loadPreview();
        } else {
            setPreview(null);
            setError(null);
        }
    }, [open, subscription, loadPreview]);

    const tenantName =
        subscription?.tenant?.nombre_comercial ??
        subscription?.tenant?.razon_social ??
        subscription?.tenant?.slug ??
        '';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <div className="flex size-11 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">
                        <MessageCircle className="size-5" strokeWidth={2.5} />
                    </div>
                    <DialogTitle className="pt-2 text-base">
                        {t('suscripciones:renewal_preview.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('suscripciones:renewal_preview.description', {
                            name: tenantName,
                        })}
                    </DialogDescription>
                </DialogHeader>

                {loading && (
                    <div className="flex items-center justify-center gap-2 py-8 text-sm text-muted-foreground">
                        <Loader2 className="size-4 animate-spin" />
                        {t('common:actions.loading')}
                    </div>
                )}

                {!loading && error && (
                    <p className="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive">
                        {error}
                    </p>
                )}

                {!loading && preview && (
                    <div className="space-y-4">
                        <div className="grid gap-2 rounded-lg border bg-muted/30 p-3 text-xs sm:grid-cols-2">
                            <div>
                                <span className="text-muted-foreground">
                                    {t('suscripciones:renewal_preview.anchor')}
                                </span>
                                <p className="font-medium">
                                    {formatPreviewDate(preview.anchor_at)}
                                </p>
                            </div>
                            <div>
                                <span className="text-muted-foreground">
                                    {t('suscripciones:renewal_preview.days_until')}
                                </span>
                                <p className="font-medium">
                                    {preview.days_until ?? '—'}
                                </p>
                            </div>
                            <div>
                                <span className="text-muted-foreground">
                                    {t('suscripciones:renewal_preview.reminder_days')}
                                </span>
                                <p className="font-medium">
                                    {preview.reminder_days.join(', ')}
                                </p>
                            </div>
                            <div>
                                <span className="text-muted-foreground">
                                    {t('suscripciones:renewal_preview.whatsapp')}
                                </span>
                                <p className="font-medium">
                                    {preview.whatsapp_ready
                                        ? t('suscripciones:renewal_preview.whatsapp_ready')
                                        : t('suscripciones:renewal_preview.whatsapp_off')}
                                </p>
                            </div>
                        </div>

                        <div
                            className={
                                preview.would_send
                                    ? 'rounded-md border border-emerald-500/30 bg-emerald-500/5 px-3 py-2 text-sm text-emerald-800 dark:text-emerald-300'
                                    : 'rounded-md border border-amber-500/30 bg-amber-500/5 px-3 py-2 text-sm text-amber-900 dark:text-amber-200'
                            }
                        >
                            {preview.would_send
                                ? t('suscripciones:renewal_preview.would_send')
                                : preview.skip_reason ??
                                  t('suscripciones:renewal_preview.would_skip')}
                        </div>

                        {preview.message && (
                            <div className="space-y-2">
                                <p className="text-xs font-medium text-muted-foreground">
                                    {t('suscripciones:renewal_preview.message_label')}
                                </p>
                                <pre className="max-h-64 overflow-auto whitespace-pre-wrap rounded-lg border bg-background p-3 font-sans text-sm leading-relaxed">
                                    {preview.message}
                                </pre>
                            </div>
                        )}
                    </div>
                )}

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        className="cursor-pointer"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common:actions.close')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
