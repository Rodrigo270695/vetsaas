import { usePage } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { OPEN_IN_APP_ASSISTANT_EVENT } from '@/components/in-app-assistant/in-app-assistant-announcement-modal';
import { InAppAssistantPanel } from '@/components/in-app-assistant/in-app-assistant-panel';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { t } = useTranslation('in-app-assistant');
    const { in_app_assistant } = usePage().props;
    const [assistantOpen, setAssistantOpen] = useState(false);

    const showAssistant =
        in_app_assistant !== null &&
        in_app_assistant !== undefined &&
        in_app_assistant.enabled === true;

    useEffect(() => {
        if (!showAssistant) {
            return;
        }

        const onOpen = () => setAssistantOpen(true);
        window.addEventListener(OPEN_IN_APP_ASSISTANT_EVENT, onOpen);
        return () => window.removeEventListener(OPEN_IN_APP_ASSISTANT_EVENT, onOpen);
    }, [showAssistant]);

    return (
        <>
            <header className="flex h-16 shrink-0 items-center gap-2 border-b border-border/60 bg-white px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4 dark:bg-background">
                <div className="flex min-w-0 flex-1 items-center gap-2">
                    <SidebarTrigger className="-ml-1" />
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>

                {showAssistant && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="ml-auto h-9 shrink-0 gap-1.5 px-2.5 text-sky-700 hover:bg-sky-50 hover:text-sky-800 dark:text-sky-300 dark:hover:bg-sky-950/50 dark:hover:text-sky-200"
                                onClick={() => setAssistantOpen(true)}
                                aria-label={t('button.label')}
                            >
                                <Sparkles className="size-4" strokeWidth={2.25} />
                                <span className="hidden text-xs font-medium sm:inline">
                                    {t('button.label')}
                                </span>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="bottom">
                            <p>{t('button.tooltip')}</p>
                        </TooltipContent>
                    </Tooltip>
                )}
            </header>

            {showAssistant && (
                <InAppAssistantPanel open={assistantOpen} onOpenChange={setAssistantOpen} />
            )}
        </>
    );
}
