import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { toastManager } from '@/lib/toast';
import type { WhatsAppConnectionShared } from '@/types/whatsapp-connection';

const TOAST_ID = 'wa-needs-link-v2';

function dismissStorageKey(connection: WhatsAppConnectionShared): string {
    return `vetsaas:wa-needs-link-dismissed:v2:${connection.scope}:${connection.session_id ?? 'none'}`;
}

function fingerprint(connection: WhatsAppConnectionShared): string {
    return [
        connection.session_id ?? '',
        connection.status ?? '',
        connection.last_synced_at ?? '',
    ].join('|');
}

/**
 * Toast global cuando WhatsApp (clínica o plataforma) está desconectado.
 * No se cierra solo: el usuario lo descarta con la X (o va a Reconectar).
 * Si lo cierra, no vuelve a aparecer hasta un nuevo episodio de desconexión.
 */
export function useWhatsAppDisconnectedToast(): void {
    const { t } = useTranslation('common');
    const connection = usePage().props.whatsapp_connection ?? null;
    const shownFingerprintRef = useRef<string | null>(null);

    useEffect(() => {
        if (!connection?.disconnected || !connection.session_id) {
            toastManager.close(TOAST_ID);
            shownFingerprintRef.current = null;

            return;
        }

        const fp = fingerprint(connection);
        const storageKey = dismissStorageKey(connection);

        try {
            if (localStorage.getItem(storageKey) === fp) {
                return;
            }
        } catch {
            // localStorage puede fallar en modo privado; igual mostramos el toast.
        }

        if (shownFingerprintRef.current === fp) {
            return;
        }

        shownFingerprintRef.current = fp;

        const description =
            connection.scope === 'platform'
                ? t('whatsapp.disconnected_body_platform')
                : t('whatsapp.disconnected_body_tenant');

        toastManager.warning({
            id: TOAST_ID,
            title: t('whatsapp.disconnected_title'),
            description,
            duration: Infinity,
            action: connection.manage_url
                ? {
                      label: t('whatsapp.reconnect'),
                      onClick: () => {
                          router.visit(connection.manage_url as string);
                      },
                  }
                : undefined,
            onDismiss: () => {
                try {
                    localStorage.setItem(storageKey, fp);
                } catch {
                    // ignore
                }
            },
        });
    }, [connection, t]);
}
