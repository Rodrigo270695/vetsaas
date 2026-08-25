import { Check, CheckCheck } from 'lucide-react';
import { cn } from '@/lib/utils';

export type ChatDeliveryStatus = 'sent' | 'delivered' | 'read';

type Reader = {
    user_id: string;
    name: string;
};

type Props = {
    status?: ChatDeliveryStatus | null;
    readers?: Reader[];
    excludeUserId?: string | null;
    labels: {
        sent: string;
        delivered: string;
        read: string;
        seen: string;
        seenBy: (names: string) => string;
    };
    className?: string;
};

export function ChatDeliveryReceipt({
    status: statusProp,
    readers = [],
    excludeUserId,
    labels,
    className,
}: Props) {
    const filtered = excludeUserId
        ? readers.filter((r) => r.user_id !== excludeUserId)
        : readers;

    const status: ChatDeliveryStatus =
        statusProp
        ?? (filtered.length > 0 ? 'read' : 'sent');

    const label =
        status === 'read'
            ? labels.read
            : status === 'delivered'
              ? labels.delivered
              : labels.sent;

    const Icon = status === 'sent' ? Check : CheckCheck;
    const seenNames =
        status === 'read' && filtered.length > 0
            ? filtered.length === 1
                ? labels.seenBy(filtered[0].name)
                : filtered.length <= 3
                  ? labels.seenBy(filtered.map((r) => r.name).join(', '))
                  : `${labels.seen} · ${filtered.length}`
            : null;

    return (
        <p
            className={cn(
                'flex items-center justify-end gap-1 px-1 text-[10px] text-muted-foreground',
                className,
            )}
        >
            <Icon
                className={cn(
                    'size-3 shrink-0',
                    status === 'read' && 'text-sky-600 dark:text-sky-400',
                    status === 'delivered' && 'text-muted-foreground',
                )}
                aria-hidden
            />
            <span>{seenNames ?? label}</span>
        </p>
    );
}
