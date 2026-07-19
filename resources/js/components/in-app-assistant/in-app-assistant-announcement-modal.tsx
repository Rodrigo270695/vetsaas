import { usePage } from '@inertiajs/react';
import { Bot, CalendarDays, MessageSquareText, Sparkles, Zap } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
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

export const OPEN_IN_APP_ASSISTANT_EVENT = 'vetsaas:open-in-app-assistant';

const STORAGE_PREFIX = 'vetsaas.in-app-assistant.announcement';

const FEATURE_ICONS = [MessageSquareText, Bot, CalendarDays, Zap] as const;

function storageKey(tenantId: string, userId: string): string {
    return `${STORAGE_PREFIX}.${tenantId}.${userId}`;
}

function dismissalToken(id: string | undefined, version: number): string {
    return id ? `${id}:${version}` : String(version);
}

function wasDismissed(
    tenantId: string,
    userId: string,
    id: string | undefined,
    version: number,
): boolean {
    if (typeof window === 'undefined') {
        return true;
    }

    return localStorage.getItem(storageKey(tenantId, userId)) === dismissalToken(id, version);
}

function markDismissed(
    tenantId: string,
    userId: string,
    id: string | undefined,
    version: number,
): void {
    if (typeof window === 'undefined') {
        return;
    }

    localStorage.setItem(storageKey(tenantId, userId), dismissalToken(id, version));
}

export function InAppAssistantAnnouncementModal() {
    const { t } = useTranslation('in-app-assistant');
    const { auth, tenant, in_app_assistant } = usePage().props;

    const announcement = in_app_assistant?.announcement ?? null;
    const enabled = in_app_assistant?.enabled === true && in_app_assistant?.configured === true;
    const version = announcement?.version ?? 0;
    const announcementId = announcement?.id;
    const tenantId = tenant?.id ?? '';
    const userId = auth.user?.id ?? '';

    const [open, setOpen] = useState(false);

    const title = announcement?.title?.trim() || t('announcement.title');
    const body = announcement?.body?.trim() || t('announcement.body');

    const features = useMemo(() => {
        const custom = (announcement?.features ?? [])
            .map((item) => (typeof item === 'string' ? item.trim() : ''))
            .filter((item) => item !== '')
            .slice(0, 4);

        if (custom.length > 0) {
            return custom.map((text, index) => ({
                icon: FEATURE_ICONS[index] ?? MessageSquareText,
                text,
            }));
        }

        return [
            { icon: MessageSquareText, text: t('announcement.features.help') },
            { icon: Bot, text: t('announcement.features.lookup') },
            { icon: CalendarDays, text: t('announcement.features.agenda') },
            { icon: Zap, text: t('announcement.features.nav') },
        ];
    }, [announcement?.features, t]);

    useEffect(() => {
        if (!enabled || !announcement?.active || version < 1 || !tenantId || !userId) {
            setOpen(false);
            return;
        }

        if (wasDismissed(tenantId, userId, announcementId, version)) {
            setOpen(false);
            return;
        }

        const timer = window.setTimeout(() => setOpen(true), 600);
        return () => window.clearTimeout(timer);
    }, [enabled, announcement?.active, version, announcementId, tenantId, userId]);

    const dismiss = () => {
        if (tenantId && userId && version > 0) {
            markDismissed(tenantId, userId, announcementId, version);
        }
        setOpen(false);
    };

    const tryNow = () => {
        dismiss();
        window.dispatchEvent(new CustomEvent(OPEN_IN_APP_ASSISTANT_EVENT));
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    dismiss();
                }
            }}
        >
            <DialogContent className="gap-0 overflow-hidden border-sky-200/80 p-0 sm:max-w-md dark:border-sky-800/70">
                <div className="bg-linear-to-br from-sky-50 via-white to-slate-50 px-6 pb-5 pt-6 dark:from-sky-950/40 dark:via-background dark:to-background">
                    <DialogHeader className="gap-3 text-left">
                        <div className="flex size-11 items-center justify-center rounded-xl bg-sky-600 text-white shadow-md shadow-sky-600/25">
                            <Sparkles className="size-5" strokeWidth={2.25} />
                        </div>
                        <div className="space-y-1.5">
                            <p className="text-[11px] font-semibold tracking-wide text-sky-700 uppercase dark:text-sky-300">
                                {t('announcement.badge')}
                            </p>
                            <DialogTitle className="text-xl font-semibold tracking-tight">
                                {title}
                            </DialogTitle>
                            <DialogDescription className="text-sm leading-relaxed">
                                {body}
                            </DialogDescription>
                        </div>
                    </DialogHeader>
                </div>

                <div className="space-y-2 border-t border-border/60 px-6 py-4">
                    {features.map(({ icon: Icon, text }) => (
                        <div
                            key={text}
                            className="flex items-start gap-3 rounded-xl border border-border/60 bg-muted/20 px-3 py-2.5"
                        >
                            <span className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg bg-sky-600/10 text-sky-700 dark:text-sky-300">
                                <Icon className="size-3.5" strokeWidth={2.25} />
                            </span>
                            <p className="text-sm leading-snug text-foreground/90">{text}</p>
                        </div>
                    ))}
                </div>

                <DialogFooter className="gap-2 border-t border-border/60 bg-muted/10 px-6 py-4 sm:justify-between">
                    <Button type="button" variant="ghost" onClick={dismiss}>
                        {t('announcement.dismiss')}
                    </Button>
                    <Button
                        type="button"
                        className="gap-2 bg-sky-600 text-white hover:bg-sky-600/90"
                        onClick={tryNow}
                    >
                        <Sparkles className="size-4" />
                        {t('announcement.try')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
