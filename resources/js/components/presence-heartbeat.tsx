import { usePresenceHeartbeat } from '@/hooks/use-presence-heartbeat';

/**
 * Montado en el árbol de la app autenticada vía withApp.
 * Dispara el heartbeat de presencia sin tocar cada página.
 */
export function PresenceHeartbeat(): null {
    usePresenceHeartbeat();

    return null;
}
