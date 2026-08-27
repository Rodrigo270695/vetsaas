import { History, Mail, MessageCircle } from 'lucide-react';
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

export type WinBackRecentRow = {
    id: string;
    channel: string | null;
    pending_at: string | null;
    accepted_at: string | null;
    status: 'pending' | 'accepted' | 'unknown';
    contact_email: string | null;
    contact_phone: string | null;
    tenant: {
        id: string;
        slug: string;
        name: string | null;
    } | null;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    rows: readonly WinBackRecentRow[];
};

function fmtDate(value: string | null): string {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleString('es-PE', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function TenantWinBackHistoryDialog({ open, onOpenChange, rows }: Props) {
    const { t } = useTranslation(['tenants', 'common']);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[85vh] max-w-3xl flex-col gap-0 overflow-hidden p-0 sm:max-w-3xl">
                <DialogHeader className="border-b border-border/60 px-5 py-4">
                    <DialogTitle className="flex items-center gap-2 text-base">
                        <History className="size-4 text-sky-600" />
                        {t('tenants:win_back_history.title')}
                    </DialogTitle>
                    <DialogDescription className="text-sm">
                        {t('tenants:win_back_history.description')}
                    </DialogDescription>
                </DialogHeader>

                <div className="min-h-0 flex-1 overflow-auto px-2 py-2 sm:px-4">
                    {rows.length === 0 ? (
                        <p className="px-3 py-8 text-center text-sm text-muted-foreground">
                            {t('tenants:win_back_history.empty')}
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[640px] text-left text-[12px]">
                                <thead className="sticky top-0 bg-background text-[10px] uppercase tracking-wide text-muted-foreground">
                                    <tr className="border-b border-border/50">
                                        <th className="px-2 py-2 font-medium">
                                            {t('tenants:win_back_history.col_tenant')}
                                        </th>
                                        <th className="px-2 py-2 font-medium">
                                            {t('tenants:win_back_history.col_channel')}
                                        </th>
                                        <th className="px-2 py-2 font-medium">
                                            {t('tenants:win_back_history.col_status')}
                                        </th>
                                        <th className="px-2 py-2 font-medium">
                                            {t('tenants:win_back_history.col_when')}
                                        </th>
                                        <th className="px-2 py-2 font-medium">
                                            {t('tenants:win_back_history.col_contact')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-b border-border/40 last:border-0"
                                        >
                                            <td className="px-2 py-2.5">
                                                <div className="font-medium text-foreground">
                                                    {row.tenant?.name ?? '—'}
                                                </div>
                                                <div className="font-mono text-[10px] text-muted-foreground">
                                                    {row.tenant?.slug ?? '—'}
                                                </div>
                                            </td>
                                            <td className="px-2 py-2.5">
                                                {row.channel === 'whatsapp' ? (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
                                                        <MessageCircle className="size-3" />
                                                        WhatsApp
                                                    </span>
                                                ) : row.channel === 'email' ? (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-sky-500/10 px-2 py-0.5 text-[11px] font-medium text-sky-700">
                                                        <Mail className="size-3" />
                                                        Email
                                                    </span>
                                                ) : (
                                                    <span className="text-muted-foreground">—</span>
                                                )}
                                            </td>
                                            <td className="px-2 py-2.5">
                                                {row.status === 'accepted' ? (
                                                    <span className="rounded-full bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
                                                        {t('tenants:win_back_history.status_accepted')}
                                                    </span>
                                                ) : row.status === 'pending' ? (
                                                    <span className="rounded-full bg-amber-500/10 px-2 py-0.5 text-[11px] font-medium text-amber-700">
                                                        {t('tenants:win_back_history.status_pending')}
                                                    </span>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="px-2 py-2.5 tabular-nums text-muted-foreground">
                                                {fmtDate(row.accepted_at ?? row.pending_at)}
                                            </td>
                                            <td className="px-2 py-2.5 text-muted-foreground">
                                                <div className="truncate max-w-[180px]">
                                                    {row.contact_email ?? '—'}
                                                </div>
                                                <div className="font-mono text-[10px]">
                                                    {row.contact_phone ?? ''}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <DialogFooter className="border-t border-border/60 px-5 py-3">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        className="cursor-pointer"
                    >
                        {t('common:actions.close')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
