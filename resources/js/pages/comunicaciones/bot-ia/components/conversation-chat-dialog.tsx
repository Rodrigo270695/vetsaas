import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatWhatsAppPhone } from '@/lib/format-whatsapp-phone';
import { cn } from '@/lib/utils';
import { useTranslation } from 'react-i18next';
import type { ConversationEntry } from '../types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    conversation: ConversationEntry | null;
};

export function ConversationChatDialog({ open, onOpenChange, conversation }: Props) {
    const { t } = useTranslation('bot-ia');

    const phoneLabel = conversation ? formatWhatsAppPhone(conversation.phone) : '';
    const name = conversation?.client_name?.trim();
    const title = name || phoneLabel || t('conversations.chat_title');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[min(85dvh,640px)] flex-col gap-0 p-0 sm:max-w-md">
                <DialogHeader className="border-b px-4 py-3">
                    <DialogTitle className="text-base">{title}</DialogTitle>
                    {name ? (
                        <p className="font-mono text-xs text-muted-foreground">{phoneLabel}</p>
                    ) : null}
                </DialogHeader>

                <div className="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto p-4">
                    {(conversation?.messages ?? []).length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('conversations.chat_empty')}
                        </p>
                    ) : (
                        conversation?.messages.map((message, index) => {
                            const isUser = message.role === 'user';

                            return (
                                <div
                                    key={`${message.role}-${index}`}
                                    className={cn(
                                        'max-w-[90%] rounded-lg px-3 py-2 text-sm',
                                        isUser
                                            ? 'mr-auto bg-muted text-foreground'
                                            : 'ml-auto bg-emerald-600/10 text-foreground',
                                    )}
                                >
                                    <p className="mb-0.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                                        {isUser
                                            ? t('conversations.role_client')
                                            : t('conversations.role_assistant')}
                                    </p>
                                    <p className="whitespace-pre-wrap">{message.content}</p>
                                </div>
                            );
                        })
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
