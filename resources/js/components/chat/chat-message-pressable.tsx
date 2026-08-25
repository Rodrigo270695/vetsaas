import type { ReactNode } from 'react';
import { useLongPress } from '@/hooks/use-long-press';
import { useSwipeToReply } from '@/hooks/use-swipe-to-reply';
import { cn } from '@/lib/utils';

type Props = {
    disabled?: boolean;
    onLongPress?: () => void;
    onSwipeReply?: () => void;
    className?: string;
    children: ReactNode;
};

export function ChatMessagePressable({
    disabled,
    onLongPress,
    onSwipeReply,
    className,
    children,
}: Props) {
    const longPress = useLongPress(onLongPress ?? (() => undefined));
    const swipe = useSwipeToReply(onSwipeReply ?? (() => undefined));

    if (disabled || (!onLongPress && !onSwipeReply)) {
        return <div className={className}>{children}</div>;
    }

    return (
        <div
            className={cn('select-none touch-manipulation', className)}
            onPointerDown={(e) => {
                if (onLongPress) longPress.onPointerDown(e);
                if (onSwipeReply) swipe.onPointerDown(e);
            }}
            onPointerMove={(e) => {
                if (onLongPress) longPress.onPointerMove(e);
                if (onSwipeReply) swipe.onPointerMove(e);
            }}
            onPointerUp={() => {
                if (onLongPress) longPress.onPointerUp();
                if (onSwipeReply) swipe.onPointerUp();
            }}
            onPointerCancel={() => {
                if (onLongPress) longPress.onPointerCancel();
                if (onSwipeReply) swipe.onPointerCancel();
            }}
            onClickCapture={onLongPress ? longPress.onClickCapture : undefined}
            onContextMenu={onLongPress ? longPress.onContextMenu : undefined}
        >
            {children}
        </div>
    );
}
