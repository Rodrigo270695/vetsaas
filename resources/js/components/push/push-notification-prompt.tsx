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

/**
 * Campana Web Push — solo se monta en panel central (superadmin).
 */
export function PushNotificationPrompt() {
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

    if (!browserSupported) {
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="relative size-9 shrink-0 text-muted-foreground"
                        aria-label="Push no soportado"
                        disabled
                    >
                        <BellOff className="size-4 text-amber-600 dark:text-amber-400" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent side="bottom" className="max-w-xs text-xs">
                    Este navegador no soporta Web Push
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
                        variant="ghost"
                        size="icon"
                        className="relative size-9 shrink-0 text-muted-foreground"
                        aria-label="Push sin configurar"
                        disabled
                    >
                        <BellOff className="size-4 text-amber-600 dark:text-amber-400" />
                        <span className="absolute top-1.5 right-1.5 size-2 rounded-full bg-amber-500 ring-2 ring-background" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent side="bottom" className="max-w-xs text-xs">
                    Falta configurar VAPID en el .env del servidor (php artisan config:clear)
                </TooltipContent>
            </Tooltip>
        );
    }

    const needsAttention = !subscribed && permission !== 'denied';
    const tooltipLabel = subscribed
        ? 'Notificaciones activas'
        : permission === 'denied'
          ? 'Permiso de notificaciones bloqueado en el navegador'
          : 'Activa alertas de reuniones VetSaaS';

    const Icon = subscribed ? BellRing : permission === 'denied' ? BellOff : Bell;

    return (
        <DropdownMenu>
            <Tooltip>
                <TooltipTrigger asChild>
                    <DropdownMenuTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="relative size-9 shrink-0 text-muted-foreground hover:text-foreground"
                            aria-label={tooltipLabel}
                        >
                            <Icon
                                className={cn(
                                    'size-4',
                                    subscribed && 'text-emerald-600 dark:text-emerald-400',
                                    permission === 'denied' && 'text-amber-600 dark:text-amber-400',
                                )}
                            />
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
                                Notificaciones activas
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Te avisaremos cuando el SalesBot confirme una reunión Meet.
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
                                Activar notificaciones
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
                                          : 'Recibe alertas al confirmarse un tour Meet.'))}
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
