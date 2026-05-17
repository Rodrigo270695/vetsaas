import {
    Building2,
    Calendar,
    Copy,
    CreditCard,
    FileCheck,
    FileX,
    Hash,
    Receipt,
    Sparkles,
    StickyNote,
    UserCog,
    X,
} from 'lucide-react';
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
import { toastManager } from '@/lib/toast';
import { cn } from '@/lib/utils';
import type { PaymentEstado, SubscriptionPayment } from '../types';

export type PaymentDetailModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    payment: SubscriptionPayment | null;
};

const formatPrice = (value: string | null, moneda = 'PEN'): string => {
    if (value === null) return '—';
    const num = Number(value);
    if (Number.isNaN(num)) return '—';
    const prefix = moneda === 'PEN' ? 'S/.' : moneda;
    return `${prefix} ${num.toFixed(2)}`;
};

const formatDate = (value: string | null): string => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleDateString('es-PE', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const formatDateTime = (value: string | null): string => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleString('es-PE', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const estadoColor: Record<PaymentEstado, string> = {
    procesado: 'bg-emerald-500/10 text-emerald-700 ring-emerald-500/25',
    pendiente: 'bg-sky-500/10 text-sky-700 ring-sky-500/25',
    fallido: 'bg-red-500/10 text-red-700 ring-red-500/25',
    reembolsado: 'bg-muted/60 text-muted-foreground ring-border/60',
};

/**
 * Modal de detalle del cobro.
 *
 * Diseño:
 *   - Header con monto total, estado y badge del plan.
 *   - Grid de info clave: tenant, plan, periodo, transacción de pasarela.
 *   - Sección de factura electrónica (FEL).
 *   - Si fue reembolsado, sección de auditoría (quién, cuándo, razón).
 *   - Si hay nota interna, se muestra.
 *   - JSON crudo del webhook (`pasarela_response`) en un bloque
 *     <pre> con scroll para debugging técnico.
 */
export function PaymentDetailModal({
    open,
    onOpenChange,
    payment,
}: PaymentDetailModalProps) {
    const { t } = useTranslation(['cobros', 'common']);

    if (!payment) return null;

    const handleCopy = async (value: string | null | undefined, label: string) => {
        if (!value) return;
        try {
            await navigator.clipboard.writeText(value);
            toastManager.success({
                title: t('cobros:toast.copied', { label }),
                duration: 2000,
            });
        } catch {
            toastManager.error({ title: t('common:feedback.copy_error') });
        }
    };

    const planHex =
        payment.plan?.color_hex && /^#[0-9a-fA-F]{3,6}$/.test(payment.plan.color_hex)
            ? payment.plan.color_hex
            : '#1F6E4A';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[90vh] flex-col gap-0 p-0 sm:max-w-2xl">
                <DialogHeader className="border-b border-border/60 px-5 pt-5 pb-3">
                    <div className="flex items-start gap-3">
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Receipt
                                className="size-5"
                                strokeWidth={2.5}
                                aria-hidden
                            />
                        </div>
                        <div className="min-w-0 flex-1">
                            <DialogTitle className="text-base font-semibold tracking-tight">
                                {t('cobros:detail.title')}
                            </DialogTitle>
                            <DialogDescription className="text-xs text-muted-foreground">
                                {t('cobros:detail.description')}
                            </DialogDescription>
                        </div>
                        <div className="flex flex-col items-end gap-1 leading-tight">
                            <span className="font-mono text-xl font-bold tabular-nums text-foreground">
                                {formatPrice(payment.total, payment.moneda)}
                            </span>
                            <span
                                className={cn(
                                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 ring-inset',
                                    estadoColor[payment.estado],
                                )}
                            >
                                {t(`cobros:estados.${payment.estado}`)}
                            </span>
                        </div>
                    </div>
                </DialogHeader>

                <div className="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                    <div className="flex flex-col gap-5">
                        <DetailSection
                            icon={Building2}
                            title={t('cobros:detail.section_cliente')}
                        >
                            <DetailRow
                                label={t('cobros:detail.fields.tenant')}
                                value={
                                    payment.tenant?.razon_social ??
                                    payment.tenant?.slug ??
                                    '—'
                                }
                                hint={payment.tenant?.email_admin ?? undefined}
                            />
                            <DetailRow
                                label={t('cobros:detail.fields.slug')}
                                value={payment.tenant?.slug ?? '—'}
                                mono
                            />
                        </DetailSection>

                        <DetailSection
                            icon={Sparkles}
                            title={t('cobros:detail.section_plan')}
                        >
                            <DetailRow
                                label={t('cobros:detail.fields.plan')}
                                value={
                                    <div className="flex items-center gap-2">
                                        <span
                                            className="size-2.5 shrink-0 rounded-full"
                                            style={{ backgroundColor: planHex }}
                                        />
                                        <span className="font-medium">
                                            {payment.plan?.nombre ?? '—'}
                                        </span>
                                        {payment.plan?.badge && (
                                            <span
                                                className="rounded-full px-1.5 py-0.5 text-[10px] font-semibold ring-1 ring-inset"
                                                style={{
                                                    color: planHex,
                                                    backgroundColor: `${planHex}1A`,
                                                }}
                                            >
                                                {payment.plan.badge}
                                            </span>
                                        )}
                                    </div>
                                }
                                hint={payment.plan?.codigo ?? undefined}
                            />
                        </DetailSection>

                        <DetailSection
                            icon={CreditCard}
                            title={t('cobros:detail.section_transaccion')}
                        >
                            <DetailRow
                                label={t('cobros:detail.fields.pasarela')}
                                value={payment.pasarela ?? '—'}
                            />
                            <DetailRow
                                label={t('cobros:detail.fields.transaction_id')}
                                value={
                                    <div className="flex items-center gap-1">
                                        <span className="font-mono text-xs">
                                            {payment.pasarela_transaction_id ?? '—'}
                                        </span>
                                        {payment.pasarela_transaction_id && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-6 cursor-pointer"
                                                onClick={() =>
                                                    handleCopy(
                                                        payment.pasarela_transaction_id,
                                                        t(
                                                            'cobros:detail.fields.transaction_id',
                                                        ),
                                                    )
                                                }
                                                aria-label={t(
                                                    'cobros:detail.copy_tx_id',
                                                )}
                                            >
                                                <Copy
                                                    className="size-3"
                                                    strokeWidth={2.5}
                                                />
                                            </Button>
                                        )}
                                    </div>
                                }
                                mono
                            />
                            <DetailRow
                                label={t('cobros:detail.fields.pagado_at')}
                                value={formatDateTime(payment.pagado_at)}
                            />
                            <DetailRow
                                label={t('cobros:detail.fields.created_at')}
                                value={formatDateTime(payment.created_at)}
                            />
                        </DetailSection>

                        <DetailSection
                            icon={Hash}
                            title={t('cobros:detail.section_montos')}
                        >
                            <DetailRow
                                label={t('cobros:detail.fields.monto')}
                                value={formatPrice(payment.monto, payment.moneda)}
                                mono
                            />
                            <DetailRow
                                label={t('cobros:detail.fields.igv_monto')}
                                value={formatPrice(payment.igv_monto, payment.moneda)}
                                mono
                            />
                            <DetailRow
                                label={t('cobros:detail.fields.descuento_monto')}
                                value={formatPrice(
                                    payment.descuento_monto,
                                    payment.moneda,
                                )}
                                mono
                            />
                            <DetailRow
                                label={t('cobros:detail.fields.total')}
                                value={
                                    <span className="font-mono text-base font-semibold text-foreground">
                                        {formatPrice(payment.total, payment.moneda)}
                                    </span>
                                }
                                strong
                            />
                        </DetailSection>

                        <DetailSection
                            icon={Calendar}
                            title={t('cobros:detail.section_periodo')}
                        >
                            <DetailRow
                                label={t('cobros:detail.fields.periodo_inicio')}
                                value={formatDate(payment.periodo_inicio)}
                            />
                            <DetailRow
                                label={t('cobros:detail.fields.periodo_fin')}
                                value={formatDate(payment.periodo_fin)}
                            />
                        </DetailSection>

                        <DetailSection
                            icon={payment.fel_emitido ? FileCheck : FileX}
                            title={t('cobros:detail.section_factura')}
                            tone={payment.fel_emitido ? 'success' : 'muted'}
                        >
                            <DetailRow
                                label={t('cobros:detail.fields.fel_emitido')}
                                value={
                                    payment.fel_emitido
                                        ? t('cobros:detail.yes')
                                        : t('cobros:detail.no')
                                }
                            />
                            {payment.fel_numero && (
                                <DetailRow
                                    label={t('cobros:detail.fields.fel_numero')}
                                    value={payment.fel_numero}
                                    mono
                                />
                            )}
                            {payment.invoice_resent_at && (
                                <DetailRow
                                    label={t(
                                        'cobros:detail.fields.invoice_resent_at',
                                    )}
                                    value={formatDateTime(
                                        payment.invoice_resent_at,
                                    )}
                                />
                            )}
                        </DetailSection>

                        {payment.estado === 'fallido' && payment.error_mensaje && (
                            <DetailSection
                                icon={FileX}
                                title={t('cobros:detail.section_error')}
                                tone="danger"
                            >
                                <p className="rounded-md bg-red-500/5 px-3 py-2 text-xs text-red-700 ring-1 ring-inset ring-red-500/20 dark:text-red-300">
                                    {payment.error_mensaje}
                                </p>
                            </DetailSection>
                        )}

                        {payment.estado === 'reembolsado' && (
                            <DetailSection
                                icon={UserCog}
                                title={t('cobros:detail.section_reembolso')}
                                tone="warning"
                            >
                                <DetailRow
                                    label={t(
                                        'cobros:detail.fields.refunded_at',
                                    )}
                                    value={formatDateTime(payment.refunded_at)}
                                />
                                {payment.refundedBy && (
                                    <DetailRow
                                        label={t(
                                            'cobros:detail.fields.refunded_by',
                                        )}
                                        value={payment.refundedBy.name}
                                        hint={payment.refundedBy.email}
                                    />
                                )}
                                {payment.refund_reason && (
                                    <div className="flex flex-col gap-1">
                                        <span className="text-[11px] font-medium text-muted-foreground">
                                            {t(
                                                'cobros:detail.fields.refund_reason',
                                            )}
                                        </span>
                                        <p className="rounded-md bg-amber-500/5 px-3 py-2 text-xs text-amber-800 ring-1 ring-inset ring-amber-500/20 dark:text-amber-300">
                                            {payment.refund_reason}
                                        </p>
                                    </div>
                                )}
                            </DetailSection>
                        )}

                        {payment.internal_note && (
                            <DetailSection
                                icon={StickyNote}
                                title={t('cobros:detail.section_nota')}
                                tone="info"
                            >
                                <p className="rounded-md bg-sky-500/5 px-3 py-2 text-xs whitespace-pre-line text-sky-800 ring-1 ring-inset ring-sky-500/20 dark:text-sky-300">
                                    {payment.internal_note}
                                </p>
                            </DetailSection>
                        )}

                        {payment.pasarela_response && (
                            <DetailSection
                                icon={Hash}
                                title={t('cobros:detail.section_raw')}
                            >
                                <p className="text-[11px] text-muted-foreground">
                                    {t('cobros:detail.raw_hint')}
                                </p>
                                <pre className="max-h-64 overflow-auto rounded-md bg-muted/40 p-3 text-[11px] leading-relaxed text-foreground/80">
                                    {JSON.stringify(
                                        payment.pasarela_response,
                                        null,
                                        2,
                                    )}
                                </pre>
                            </DetailSection>
                        )}
                    </div>
                </div>

                <DialogFooter className="border-t border-border/60 px-5 py-3">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        className="cursor-pointer gap-2"
                    >
                        <X className="size-4" strokeWidth={2.5} />
                        {t('common:actions.close')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

type DetailSectionTone = 'default' | 'success' | 'warning' | 'danger' | 'muted' | 'info';

const toneStyles: Record<DetailSectionTone, string> = {
    default: 'bg-muted/30 text-muted-foreground',
    success: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    warning: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
    danger: 'bg-red-500/10 text-red-700 dark:text-red-400',
    muted: 'bg-muted/40 text-muted-foreground',
    info: 'bg-sky-500/10 text-sky-700 dark:text-sky-400',
};

function DetailSection({
    icon: Icon,
    title,
    tone = 'default',
    children,
}: {
    icon: React.ComponentType<{ className?: string; strokeWidth?: number }>;
    title: string;
    tone?: DetailSectionTone;
    children: React.ReactNode;
}) {
    return (
        <section className="flex flex-col gap-2">
            <div className="flex items-center gap-2">
                <span
                    className={cn(
                        'flex size-6 shrink-0 items-center justify-center rounded-md',
                        toneStyles[tone],
                    )}
                >
                    <Icon className="size-3.5" strokeWidth={2.5} />
                </span>
                <h3 className="text-xs font-semibold tracking-wide text-foreground/80 uppercase">
                    {title}
                </h3>
            </div>
            <div className="flex flex-col gap-1.5 pl-8">{children}</div>
        </section>
    );
}

function DetailRow({
    label,
    value,
    hint,
    mono = false,
    strong = false,
}: {
    label: string;
    value: React.ReactNode;
    hint?: string;
    mono?: boolean;
    strong?: boolean;
}) {
    return (
        <div className="flex items-start justify-between gap-3 text-xs">
            <span className="shrink-0 text-muted-foreground">{label}</span>
            <div className="flex min-w-0 flex-col items-end text-right">
                <span
                    className={cn(
                        'truncate',
                        mono && 'font-mono',
                        strong ? 'font-semibold text-foreground' : 'text-foreground/90',
                    )}
                >
                    {value}
                </span>
                {hint && (
                    <span className="text-[10px] text-muted-foreground">
                        {hint}
                    </span>
                )}
            </div>
        </div>
    );
}
