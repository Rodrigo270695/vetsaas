import { Megaphone } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

import type { TenantAnnouncement } from '@/pages/plataforma/bot-ia-announcements/types';

type Props = {
    announcement: TenantAnnouncement;
};

function storageKey(id: string): string {
    return `vetsaas.bot-ia.announcement.${id}`;
}

function readDismissed(id: string): boolean {
    try {
        return localStorage.getItem(storageKey(id)) === '1';
    } catch {
        return false;
    }
}

function writeDismissed(id: string): void {
    try {
        localStorage.setItem(storageKey(id), '1');
    } catch {
        /* ignore */
    }
}

export function BotIaUpdateBanner({ announcement }: Props) {
    const { t } = useTranslation('bot-ia');
    const [dismissed, setDismissed] = useState(true);
    const [guideOpen, setGuideOpen] = useState(false);

    useEffect(() => {
        setDismissed(readDismissed(announcement.id));
    }, [announcement.id]);

    const dismiss = useCallback(() => {
        writeDismissed(announcement.id);
        setDismissed(true);
    }, [announcement.id]);

    if (dismissed) {
        return null;
    }

    const hasGuide =
        Boolean(announcement.guide_title) ||
        Boolean(announcement.guide_body) ||
        announcement.guide_tips.length > 0;

    return (
        <Alert className="border-violet-500/30 bg-violet-500/5">
            <Megaphone className="size-4 text-violet-600" />
            <AlertTitle className="text-foreground">{announcement.title}</AlertTitle>
            <AlertDescription className="flex flex-col gap-3">
                <ul className="list-disc space-y-1 pl-4 text-sm text-muted-foreground">
                    {announcement.bullets.map((bullet) => (
                        <li key={bullet}>{bullet}</li>
                    ))}
                </ul>

                {guideOpen && hasGuide ? (
                    <div className="rounded-md border border-violet-500/20 bg-background/80 p-3 text-sm text-muted-foreground">
                        {announcement.guide_title ? (
                            <p className="font-medium text-foreground">{announcement.guide_title}</p>
                        ) : null}
                        {announcement.guide_body ? <p className="mt-2">{announcement.guide_body}</p> : null}
                        {announcement.guide_tips.length > 0 ? (
                            <ul className="mt-2 list-disc space-y-1 pl-4">
                                {announcement.guide_tips.map((tip) => (
                                    <li key={tip}>{tip}</li>
                                ))}
                            </ul>
                        ) : null}
                    </div>
                ) : null}

                <div className="flex flex-wrap gap-2">
                    <Button type="button" size="sm" onClick={dismiss}>
                        {t('announcement.dismiss')}
                    </Button>
                    {hasGuide ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => setGuideOpen((open) => !open)}
                        >
                            {guideOpen ? t('announcement.hide_guide') : t('announcement.show_guide')}
                        </Button>
                    ) : null}
                </div>
            </AlertDescription>
        </Alert>
    );
}
