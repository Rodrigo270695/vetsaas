import { usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Megaphone, Sparkles } from 'lucide-react';
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
import { cn } from '@/lib/utils';

export const OPEN_IN_APP_ASSISTANT_EVENT = 'vetsaas:open-in-app-assistant';

const STORAGE_PREFIX = 'vetsaas.clinic.announcements.v2';

type ClinicAnnouncement = {
    active: boolean;
    version: number;
    id?: string;
    title?: string | null;
    body?: string | null;
    features?: string[];
};

function storageKey(tenantId: string, userId: string): string {
    return `${STORAGE_PREFIX}.${tenantId}.${userId}`;
}

function readSeen(tenantId: string, userId: string): Record<string, number> {
    if (typeof window === 'undefined' || !tenantId || !userId) {
        return {};
    }

    try {
        const raw = localStorage.getItem(storageKey(tenantId, userId));
        if (!raw) {
            return {};
        }
        const parsed: unknown = JSON.parse(raw);
        if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
            return {};
        }

        const out: Record<string, number> = {};
        for (const [id, version] of Object.entries(parsed as Record<string, unknown>)) {
            if (typeof version === 'number' && Number.isFinite(version)) {
                out[id] = version;
            }
        }

        return out;
    } catch {
        return {};
    }
}

function writeSeen(tenantId: string, userId: string, seen: Record<string, number>): void {
    if (typeof window === 'undefined' || !tenantId || !userId) {
        return;
    }

    localStorage.setItem(storageKey(tenantId, userId), JSON.stringify(seen));
}

export function InAppAssistantAnnouncementModal() {
    const { t } = useTranslation('in-app-assistant');
    const { auth, tenant, clinic_announcements: sharedList, in_app_assistant } = usePage().props;

    const items = useMemo((): ClinicAnnouncement[] => {
        if (Array.isArray(sharedList) && sharedList.length > 0) {
            return sharedList.filter((row) => row.active && (row.version ?? 0) >= 1);
        }

        const fromAssistant = in_app_assistant?.announcements;
        if (Array.isArray(fromAssistant) && fromAssistant.length > 0) {
            return fromAssistant.filter((row) => row.active && (row.version ?? 0) >= 1);
        }

        const single = in_app_assistant?.announcement;
        if (single?.active && (single.version ?? 0) >= 1) {
            return [single];
        }

        return [];
    }, [sharedList, in_app_assistant?.announcements, in_app_assistant?.announcement]);

    const tenantId = tenant?.id ?? '';
    const userId = auth.user?.id ?? '';
    const [seen, setSeen] = useState<Record<string, number>>({});
    const [seenReady, setSeenReady] = useState(false);
    const [open, setOpen] = useState(false);
    const [index, setIndex] = useState(0);

    useEffect(() => {
        setSeen(readSeen(tenantId, userId));
        setSeenReady(true);
    }, [tenantId, userId, items]);

    const pending = useMemo(() => {
        return items.filter((item) => {
            const id = item.id;
            if (!id) {
                return true;
            }
            return seen[id] !== item.version;
        });
    }, [items, seen]);

    useEffect(() => {
        if (!seenReady || !tenantId || !userId || pending.length === 0) {
            setOpen(false);
            return;
        }

        setIndex(0);
        const timer = window.setTimeout(() => setOpen(true), 450);
        return () => window.clearTimeout(timer);
    }, [seenReady, pending, tenantId, userId]);

    const current = pending[index] ?? null;
    const total = pending.length;
    const isLast = index >= total - 1;

    const dismissAll = () => {
        const nextSeen = { ...seen };
        for (const item of pending) {
            if (item.id) {
                nextSeen[item.id] = item.version;
            }
        }
        writeSeen(tenantId, userId, nextSeen);
        setSeen(nextSeen);
        setOpen(false);
    };

    const goNext = () => {
        if (isLast) {
            dismissAll();
            return;
        }
        setIndex((prev) => Math.min(prev + 1, total - 1));
    };

    const goPrev = () => {
        setIndex((prev) => Math.max(prev - 1, 0));
    };

    const title = current?.title?.trim() || t('announcement.title');
    const body = current?.body?.trim() || t('announcement.body');
    const features = (current?.features ?? [])
        .map((item) => (typeof item === 'string' ? item.trim() : ''))
        .filter((item) => item !== '')
        .slice(0, 4);

    return (
        <Dialog
            open={open && current !== null}
            onOpenChange={(next) => {
                if (!next) {
                    dismissAll();
                }
            }}
        >
            <DialogContent
                className="gap-0 overflow-hidden border-0 p-0 shadow-2xl shadow-emerald-900/20 sm:max-w-lg dark:shadow-emerald-950/50"
                onPointerDownOutside={(event) => event.preventDefault()}
                onInteractOutside={(event) => event.preventDefault()}
                onEscapeKeyDown={(event) => event.preventDefault()}
            >
                <div className="relative overflow-hidden bg-linear-to-br from-emerald-600 via-teal-600 to-emerald-800 px-6 pb-6 pt-7 text-white">
                    <div className="pointer-events-none absolute -right-8 -top-10 size-36 rounded-full bg-white/15 blur-2xl" />
                    <div className="pointer-events-none absolute -bottom-12 left-10 size-28 rounded-full bg-teal-300/20 blur-xl" />
                    <DialogHeader className="relative gap-3 text-left">
                        <div className="flex items-center justify-between gap-3">
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-2.5 py-1 text-[11px] font-semibold tracking-wide uppercase backdrop-blur-sm">
                                <Sparkles className="size-3.5" strokeWidth={2.5} />
                                {t('announcement.badge')}
                            </span>
                            {total > 1 ? (
                                <span className="rounded-full bg-black/20 px-2.5 py-1 text-[11px] font-semibold tabular-nums">
                                    {t('announcement.counter', { current: index + 1, total })}
                                </span>
                            ) : null}
                        </div>
                        <div className="flex items-start gap-3">
                            <span className="mt-0.5 flex size-11 shrink-0 items-center justify-center rounded-2xl bg-white text-emerald-700 shadow-lg">
                                <Megaphone className="size-5" strokeWidth={2.25} />
                            </span>
                            <div className="min-w-0 space-y-1.5">
                                <DialogTitle className="text-xl font-semibold tracking-tight text-white">
                                    {title}
                                </DialogTitle>
                                <DialogDescription className="text-sm leading-relaxed text-emerald-50/95">
                                    {body}
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>
                    {total > 1 ? (
                        <div className="relative mt-5 flex justify-center gap-1.5">
                            {pending.map((item, i) => (
                                <button
                                    key={item.id ?? i}
                                    type="button"
                                    className={cn(
                                        'h-1.5 rounded-full transition-all',
                                        i === index ? 'w-7 bg-white' : 'w-2 bg-white/40 hover:bg-white/70',
                                    )}
                                    aria-label={t('announcement.go_to', { n: i + 1 })}
                                    onClick={() => setIndex(i)}
                                />
                            ))}
                        </div>
                    ) : null}
                </div>

                {features.length > 0 ? (
                    <div className="space-y-2 bg-background px-6 py-4">
                        {features.map((text) => (
                            <div
                                key={text}
                                className="flex items-start gap-3 rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-3 py-2.5"
                            >
                                <span className="mt-1 size-1.5 shrink-0 rounded-full bg-emerald-500" />
                                <p className="text-sm leading-snug text-foreground/90">{text}</p>
                            </div>
                        ))}
                    </div>
                ) : null}

                <DialogFooter className="gap-2 border-t border-border/60 bg-muted/20 px-6 py-4 sm:justify-between">
                    {total > 1 ? (
                        <Button
                            type="button"
                            variant="ghost"
                            className="cursor-pointer gap-1"
                            disabled={index === 0}
                            onClick={goPrev}
                        >
                            <ChevronLeft className="size-4" />
                            {t('announcement.prev')}
                        </Button>
                    ) : (
                        <span />
                    )}
                    <Button
                        type="button"
                        className="cursor-pointer gap-2 bg-emerald-600 text-white hover:bg-emerald-600/90"
                        onClick={goNext}
                    >
                        {isLast ? t('announcement.dismiss') : t('announcement.next')}
                        {!isLast ? <ChevronRight className="size-4" /> : null}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
