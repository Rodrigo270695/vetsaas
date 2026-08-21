import { Bell, BellOff, BellRing, LoaderCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { usePushNotifications } from '@/hooks/use-push-notifications';
import { cn } from '@/lib/utils';

type PushNotificationPromptProps = {
    /**
     * `icon` — campana compacta (p. ej. header del panel central).
     * `labeled` — control con etiqueta “Notificaciones push” (p. ej. chat de clínica).
     */
    variant?: 'icon' | 'labeled';
    /** Copia corta bajo el título del menú (contexto Meet vs chat). */
    description?: string;
    className?: string;
};

/**
 * Control Web Push del navegador.
 * Montarlo donde corresponda (panel central o página de chat); no asume tenant.
 */
export function PushNotificationPrompt({
    variant = 'icon',
    description,
    className,
}: PushNotificationPromptProps) {
    const [mounted, setMounted] = useState(false);
    const {
        browserSupported,
        configured,
        permission,
        subscribed,
        swReady,
        loading,
        error,
        enable,
        disable,
    } = usePushNotifications();

    useEffect(() => {
        setMounted(true);
    }, []);

    if (!mounted) {
        return null;
    }

    const labeled = variant === 'labeled';
    const helpUnsupported = 'Este navegador no soporta Web Push';
    const helpUnconfigured =
        'Falta configurar VAPID en el .env del servidor (php artisan config:clear)';

    if (!browserSupported) {
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        type="button"
                        variant={labeled ? 'outline' : 'ghost'}
                        size={labeled ? 'sm' : 'icon'}
                        className={cn(
                            labeled
                                ? 'h-8 gap-1.5 px-2.5 text-xs text-muted-foreground'
                                : 'relative size-9 shrink-0 text-muted-foreground',
                            className,
                        )}
                        aria-label="Push no soportado"
                        disabled
                    >
                        <BellOff className="size-4 text-amber-600 dark:text-amber-400" />
                        {labeled ? <span>Notificaciones push</span> : null}
                    </Button>
                </TooltipTrigger>
                <TooltipContent side="bottom" className="max-w-xs text-xs">
                    {helpUnsupported}
                </TooltipContent>
            </Tooltip>
        );
    }

    if (!configured) {
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        type="button"
                        variant={labeled ? 'outline' : 'ghost'}
                        size={labeled ? 'sm' : 'icon'}
                        className={cn(
                            labeled
                                ? 'relative h-8 gap-1.5 px-2.5 text-xs text-muted-foreground'
                                : 'relative size-9 shrink-0 text-muted-foreground',
                            className,
                        )}
                        aria-label="Push sin configurar"
                        disabled
                    >
                        <BellOff className="size-4 text-amber-600 dark:text-amber-400" />
                        {labeled ? <span>Notificaciones push</span> : null}
                        <span className="absolute top-1.5 right-1.5 size-2 rounded-full bg-amber-500 ring-2 ring-background" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent side="bottom" className="max-w-xs text-xs">
                    {helpUnconfigured}
                </TooltipContent>
            </Tooltip>
        );
    }

    const needsAttention = !subscribed && permission !== 'denied';
    const tooltipLabel = subscribed
        ? 'Notificaciones push activas en este navegador'
        : permission === 'denied'
          ? 'Permiso de notificaciones bloqueado en el navegador'
          : 'Activar notificaciones push del navegador';

    const menuDescription =
        description
        ?? (subscribed
            ? 'Alertas push de este navegador (no es silenciar un chat).'
            : 'Activa alertas push del navegador. Independiente de silenciar conversaciones.');

    const Icon = subscribed ? BellRing : permission === 'denied' ? BellOff : Bell;

    return (
        <DropdownMenu>
            <Tooltip>
                <TooltipTrigger asChild>
                    <DropdownMenuTrigger asChild>
                        <Button
                            type="button"
                            variant={labeled ? 'outline' : 'ghost'}
                            size={labeled ? 'sm' : 'icon'}
                            className={cn(
                                labeled
                                    ? 'relative h-8 gap-1.5 px-2.5 text-xs text-muted-foreground hover:text-foreground'
                                    : 'relative size-9 shrink-0 text-muted-foreground hover:text-foreground',
                                className,
                            )}
                            aria-label={tooltipLabel}
                        >
                            <Icon
                                className={cn(
                                    'size-4',
                                    subscribed && 'text-emerald-600 dark:text-emerald-400',
                                    permission === 'denied' && 'text-amber-600 dark:text-amber-400',
                                )}
                            />
                            {labeled ? <span>Notificaciones push</span> : null}
                            {needsAttention && (
                                <span className="absolute top-1.5 right-1.5 size-2 rounded-full bg-sky-500 ring-2 ring-background" />
                            )}
                        </Button>
                    </DropdownMenuTrigger>
                </TooltipTrigger>
                <TooltipContent side="bottom" className="max-w-xs text-xs">
                    {tooltipLabel}
                </TooltipContent>
            </Tooltip>

            <DropdownMenuContent align="end" className="w-72 p-3">
                {subscribed ? (
                    <div className="space-y-3">
                        <div className="space-y-1">
                            <p className="text-sm font-medium text-foreground">
                                Notificaciones push activas
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {menuDescription}
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-8 w-full text-xs"
                            onClick={() => void disable()}
                            disabled={loading}
                        >
                            {loading ? (
                                <LoaderCircle className="size-3.5 animate-spin" />
                            ) : (
                                'Desactivar'
                            )}
                        </Button>
                    </div>
                ) : (
                    <div className="space-y-3">
                        <div className="space-y-1">
                            <p className="text-sm font-medium text-foreground">
                                Activar notificaciones push
                            </p>
                            <p
                                className={cn(
                                    'text-xs',
                                    error ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground',
                                )}
                            >
                                {permission === 'denied'
                                    ? 'El navegador bloqueó las notificaciones. Actívalas en la configuración del sitio.'
                                    : (error ??
                                      (!swReady
                                          ? 'Preparando service worker… espera unos segundos.'
                                          : menuDescription))}
                            </p>
                        </div>
                        {permission !== 'denied' && (
                            <Button
                                type="button"
                                size="sm"
                                className="h-8 w-full text-xs"
                                onClick={() => void enable()}
                                disabled={loading || !swReady}
                            >
                                {loading ? (
                                    <LoaderCircle className="size-3.5 animate-spin" />
                                ) : (
                                    'Activar'
                                )}
                            </Button>
                        )}
                    </div>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
