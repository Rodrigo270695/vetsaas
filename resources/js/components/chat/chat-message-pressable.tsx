import type { ReactNode } from 'react';
import { useLongPress } from '@/hooks/use-long-press';
import { cn } from '@/lib/utils';

type Props = {
    disabled?: boolean;
    onLongPress: () => void;
    className?: string;
    children: ReactNode;
};

export function ChatMessagePressable({
    disabled,
    onLongPress,
    className,
    children,
}: Props) {
    const handlers = useLongPress(onLongPress);

    if (disabled) {
        return <div className={className}>{children}</div>;
    }

    return (
        <div
            className={cn('select-none touch-manipulation', className)}
            {...handlers}
        >
            {children}
        </div>
    );
}
