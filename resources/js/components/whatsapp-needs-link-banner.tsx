import { Link, usePage } from '@inertiajs/react';
import { MessageCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import type { WhatsAppConnectionShared } from '@/types/whatsapp-connection';

/**
 * Banner global: WhatsApp de la clínica (o plataforma) sin vincular.
 * Visible en cualquier pantalla hasta que la sesión esté ready.
 */
export function WhatsAppNeedsLinkBanner() {
    const { t } = useTranslation('common');
    const connection = usePage().props.whatsapp_connection as WhatsAppConnectionShared | null;

    if (!connection?.disconnected || !connection.manage_url) {
        return null;
    }

    const isPlatform = connection.scope === 'platform';

    return (
        <div
            className="border-b border-amber-500/40 bg-amber-500/15 px-4 py-3 text-amber-950 dark:text-amber-50"
            role="status"
        >
            <div className="mx-auto flex max-w-6xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex min-w-0 items-start gap-3">
                    <div className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/20 text-amber-800 dark:text-amber-200">
                        <MessageCircle className="size-4" aria-hidden />
                    </div>
                    <div className="min-w-0">
                        <p className="text-sm font-semibold">{t('whatsapp.banner_title')}</p>
                        <p className="mt-0.5 text-xs text-amber-900/85 dark:text-amber-100/85">
                            {isPlatform
                                ? t('whatsapp.banner_body_platform')
                                : t('whatsapp.banner_body_tenant')}
                        </p>
                    </div>
                </div>
                <Button asChild size="sm" className="shrink-0">
                    <Link href={connection.manage_url}>{t('whatsapp.reconnect')}</Link>
                </Button>
            </div>
        </div>
    );
}
