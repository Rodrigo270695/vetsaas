import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { getSharedChatEcho, type BroadcastConfig } from '@/lib/chat-echo';

export const SALA_ESPERA_CHANGED_EVENT = 'vetsaas:sala-espera-changed';
export const SALA_ESPERA_LLAMAR_EVENT = 'vetsaas:sala-espera-llamar';

type SalaEsperaPayload = {
    action?: string;
    tipo?: string;
    actor_id?: string | null;
    item?: {
        id?: string;
        tipo?: string;
        paciente?: string;
        propietario?: string;
        numero?: number | null;
    };
};

export function useSalaEsperaRealtime(enabled: boolean): void {
    const { tenant, broadcast } = usePage().props;
    const tenantId = tenant?.id ?? null;
    const config = broadcast as BroadcastConfig | undefined;
    const wsEnabled = Boolean(config?.enabled && config.key);
    const wsKey = config?.key ?? null;
    const wsHost = config?.host ?? null;
    const wsPort = config?.port ?? null;
    const wsScheme = config?.scheme ?? null;

    useEffect(() => {
        if (!enabled || !tenantId || !wsEnabled || !config) {
            return;
        }

        let cancelled = false;
        let channelName: string | null = null;
        const echoConfig: BroadcastConfig = {
            enabled: true,
            key: wsKey,
            host: wsHost,
            port: wsPort ?? undefined,
            scheme: wsScheme ?? undefined,
        };

        void (async () => {
            const echo = await getSharedChatEcho(echoConfig);
            if (!echo || cancelled) {
                return;
            }

            channelName = `tenant.${tenantId}.sala-espera`;
            const channel = echo.private(channelName);
            channel.listen('.sala-espera.updated', (raw: unknown) => {
                const payload = (raw ?? {}) as SalaEsperaPayload;
                window.dispatchEvent(
                    new CustomEvent(SALA_ESPERA_CHANGED_EVENT, { detail: payload }),
                );
                if (payload.action === 'llamar') {
                    window.dispatchEvent(
                        new CustomEvent(SALA_ESPERA_LLAMAR_EVENT, { detail: payload }),
                    );
                }
            });
        })();

        return () => {
            cancelled = true;
            if (!channelName) {
                return;
            }
            void getSharedChatEcho(echoConfig).then((echo) => {
                echo?.leave?.(channelName as string);
            });
        };
    }, [config, enabled, tenantId, wsEnabled, wsHost, wsKey, wsPort, wsScheme]);
}
