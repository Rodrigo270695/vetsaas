import { router } from '@inertiajs/react';
import { Loader2, MessageCircle, Sparkles } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { toastManager } from '@/lib/toast';
import type { SubscriptionPayment } from '../types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    payment: SubscriptionPayment | null;
};

function readCsrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

/**
 * Reenganche de clínicas vencidas: mensaje editable + Generar con IA + envío WhatsApp.
 * La oferta de 1 mes se activa cuando el cliente responde «Sí» en Conversaciones.
 */
export function PaymentWinBackWhatsAppDialog({
    open,
    onOpenChange,
    payment,
}: Props) {
    const { t } = useTranslation(['cobros', 'common']);
    const [message, setMessage] = useState('');
    const [offerFreeMonth, setOfferFreeMonth] = useState(true);
    const [generating, setGenerating] = useState(false);
    const [sending, setSending] = useState(false);

    const subscriptionId = payment?.subscription?.id ?? payment?.subscription_id ?? null;
    const tenantName =
        payment?.tenant?.nombre_comercial ??
        payment?.tenant?.razon_social ??
        payment?.tenant?.slug ??
        '';

    useEffect(() => {
        if (!open) {
            return;
        }
        setOfferFreeMonth(true);
        setMessage(
            t('cobros:win_back.default_message', {
                name: tenantName || 'equipo',
            }),
        );
    }, [open, payment?.id, tenantName, t]);

    const onGenerate = async () => {
        if (!subscriptionId) {
            return;
        }
        setGenerating(true);
        try {
            const res = await fetch(
                `/plataforma/suscripciones/${subscriptionId}/win-back/generate`,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': readCsrfToken(),
                    },
                    body: JSON.stringify({ message }),
                },
            );
            const body = (await res.json().catch(() => ({}))) as {
                ok?: boolean;
                message?: string;
            };
            if (!res.ok || !body.message) {
                toastManager.error({
                    title: t('cobros:win_back.generate_error'),
                });
                return;
            }
            setMessage(body.message);
            toastManager.success({
                title: t('cobros:win_back.generate_ok'),
                duration: 2500,
            });
        } catch {
            toastManager.error({
                title: t('cobros:win_back.generate_error'),
            });
        } finally {
            setGenerating(false);
        }
    };

    const onSend = () => {
        if (!subscriptionId || message.trim().length < 20) {
            return;
        }
        setSending(true);
        router.post(
            `/plataforma/suscripciones/${subscriptionId}/win-back/send`,
            {
                message: message.trim(),
                grant_free_month: offerFreeMonth,
            },
            {
                preserveScroll: true,
                onFinish: () => setSending(false),
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    const busy = generating || sending;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="border-violet-200 sm:max-w-lg dark:border-violet-800">
                <DialogHeader>
                    <div className="flex size-11 items-center justify-center rounded-full bg-violet-100 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300">
                        <MessageCircle className="size-5" strokeWidth={2.5} />
                    </div>
                    <DialogTitle className="pt-2">
                        {t('cobros:win_back.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('cobros:win_back.description', { name: tenantName })}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-3">
                    <div className="flex items-center justify-between gap-2">
                        <p className="text-sm font-medium text-foreground">
                            {t('cobros:win_back.message_label')}
                        </p>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="cursor-pointer gap-1.5 border-violet-300 text-violet-800 hover:bg-violet-500/10 dark:border-violet-700 dark:text-violet-200"
                            disabled={busy || !subscriptionId}
                            onClick={() => void onGenerate()}
                        >
                            {generating ? (
                                <Loader2 className="size-3.5 animate-spin" />
                            ) : (
                                <Sparkles className="size-3.5" />
                            )}
                            {t('cobros:win_back.generate_ai')}
                        </Button>
                    </div>
                    <Textarea
                        value={message}
                        onChange={(e) => setMessage(e.target.value)}
                        rows={12}
                        disabled={busy}
                        className="min-h-[220px] font-sans text-sm"
                        placeholder={t('cobros:win_back.message_placeholder')}
                    />
                    <p className="text-xs text-muted-foreground">
                        {t('cobros:win_back.generate_hint')}
                    </p>

                    <label className="flex cursor-pointer items-start gap-2 rounded-lg border border-border/70 bg-muted/20 px-3 py-2.5 text-sm">
                        <Checkbox
                            checked={offerFreeMonth}
                            onCheckedChange={(v) => setOfferFreeMonth(v === true)}
                            disabled={busy}
                            className="mt-0.5"
                        />
                        <span>
                            <span className="font-medium text-foreground">
                                {t('cobros:win_back.grant_month')}
                            </span>
                            <span className="mt-0.5 block text-xs text-muted-foreground">
                                {t('cobros:win_back.grant_month_hint')}
                            </span>
                        </span>
                    </label>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={busy}
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="button"
                        className="gap-2 bg-violet-600 text-white hover:bg-violet-700"
                        disabled={busy || !subscriptionId || message.trim().length < 20}
                        onClick={onSend}
                    >
                        {sending ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <MessageCircle className="size-4" />
                        )}
                        {t('cobros:win_back.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
