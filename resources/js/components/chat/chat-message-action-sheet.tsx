import { Forward, Pencil, Reply, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type ChatMessageActionSheetLabels = {
    title: string;
    hint: string;
    reply: string;
    react: string;
    forward: string;
    edit: string;
    delete: string;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mine: boolean;
    preview?: string | null;
    reactionEmojis: readonly string[];
    labels: ChatMessageActionSheetLabels;
    onReply: () => void;
    onReact: (emoji: string) => void;
    onForward: () => void;
    onEdit?: () => void;
    onDelete?: () => void;
};

export function ChatMessageActionSheet({
    open,
    onOpenChange,
    mine,
    preview,
    reactionEmojis,
    labels,
    onReply,
    onReact,
    onForward,
    onEdit,
    onDelete,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-sm">
                <DialogHeader className="border-b border-border/60 px-4 py-3 text-left">
                    <DialogTitle className="text-base">
                        {labels.title}
                    </DialogTitle>
                    <DialogDescription className="line-clamp-2 text-xs">
                        {preview?.trim() || labels.hint}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex justify-center gap-1 border-b border-border/50 px-3 py-3">
                    {reactionEmojis.map((emoji) => (
                        <button
                            key={emoji}
                            type="button"
                            className="rounded-full px-2.5 py-1.5 text-xl hover:bg-muted active:bg-muted"
                            onClick={() => {
                                onReact(emoji);
                                onOpenChange(false);
                            }}
                            aria-label={labels.react}
                        >
                            {emoji}
                        </button>
                    ))}
                </div>

                <div className="flex flex-col p-2">
                    <Button
                        type="button"
                        variant="ghost"
                        className="h-11 justify-start gap-3"
                        onClick={() => {
                            onReply();
                            onOpenChange(false);
                        }}
                    >
                        <Reply className="size-4" />
                        {labels.reply}
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        className="h-11 justify-start gap-3"
                        onClick={() => {
                            onForward();
                            onOpenChange(false);
                        }}
                    >
                        <Forward className="size-4" />
                        {labels.forward}
                    </Button>
                    {mine && onEdit ? (
                        <Button
                            type="button"
                            variant="ghost"
                            className="h-11 justify-start gap-3"
                            onClick={() => {
                                onEdit();
                                onOpenChange(false);
                            }}
                        >
                            <Pencil className="size-4" />
                            {labels.edit}
                        </Button>
                    ) : null}
                    {mine && onDelete ? (
                        <Button
                            type="button"
                            variant="ghost"
                            className="h-11 justify-start gap-3 text-destructive hover:text-destructive"
                            onClick={() => {
                                onDelete();
                                onOpenChange(false);
                            }}
                        >
                            <Trash2 className="size-4" />
                            {labels.delete}
                        </Button>
                    ) : null}
                </div>
            </DialogContent>
        </Dialog>
    );
}
