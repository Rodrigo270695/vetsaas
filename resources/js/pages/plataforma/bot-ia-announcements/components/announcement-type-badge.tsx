import { useTranslation } from 'react-i18next';

import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

import type { AnnouncementBadge } from '../types';

const BADGE_STYLES: Record<AnnouncementBadge, string> = {
    nuevo: 'border-violet-500/30 bg-violet-500/15 text-violet-700 dark:text-violet-300',
    mejora: 'border-emerald-500/30 bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    importante: 'border-amber-500/30 bg-amber-500/15 text-amber-800 dark:text-amber-300',
};

type Props = {
    badge: AnnouncementBadge;
    className?: string;
};

export function AnnouncementTypeBadge({ badge, className }: Props) {
    const { t } = useTranslation('bot-ia-announcements');

    return (
        <Badge variant="outline" className={cn(BADGE_STYLES[badge], className)}>
            {t(`badges.${badge}`)}
        </Badge>
    );
}
