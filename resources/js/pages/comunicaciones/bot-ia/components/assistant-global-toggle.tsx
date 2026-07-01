import { router } from '@inertiajs/react';
import { Bot } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { StatBadge } from '@/components/data-page';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { AssistantSettings } from '../types';

const ROUTE_URL = '/comunicaciones/bot-ia';

type Props = {
    assistant: AssistantSettings;
    canManage: boolean;
};

export function AssistantGlobalToggle({ assistant, canManage }: Props) {
    const { t } = useTranslation('bot-ia');

    const toggle = (respuestas_activas: boolean) => {
        router.post(
            `${ROUTE_URL}/asistente/toggle`,
            { respuestas_activas },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex flex-wrap items-center gap-2 sm:border-l sm:pl-3">
            <Bot className="size-4 text-violet-600" aria-hidden />
            <StatBadge
                label={t('assistant.label')}
                value={
                    assistant.respuestas_activas
                        ? t('assistant.status_on')
                        : t('assistant.status_off')
                }
                variant={assistant.respuestas_activas ? 'success' : 'warning'}
            />
            {canManage ? (
                <Label
                    htmlFor="assistant-global-toggle"
                    className="flex cursor-pointer items-center gap-2 text-xs text-muted-foreground"
                >
                    <Checkbox
                        id="assistant-global-toggle"
                        checked={assistant.respuestas_activas}
                        onCheckedChange={(checked) => toggle(checked === true)}
                    />
                    {t('assistant.toggle_hint')}
                </Label>
            ) : null}
        </div>
    );
}
