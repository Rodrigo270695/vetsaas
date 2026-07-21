import { Head } from '@inertiajs/react';
import { Ban, CalendarX, Headphones, Lock, MessageCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { TenantEstado } from '@/types/tenant';

/**
 * Pantalla que ve el usuario cuando su tenant existe pero está en un
 * estado que bloquea el acceso (`suspended` o `cancelled`).
 *
 * Renderizada por el handler global de `TenantSuspendedException`. Cada
 * estado tiene su propia variante visual y mensaje para que el cliente
 * entienda con precisión la razón del bloqueo y los pasos a seguir.
 */
type BlockType = 'suspended' | 'cancelled' | 'expired';

type BlockedProps = {
    block_type?: BlockType;
    estado: TenantEstado;
    razon_social: string;
    reason: string | null;
    suspended_at: string | null;
    cancelled_at: string | null;
    support_whatsapp_phone?: string;
};

const CONFIG: Record<
    BlockType,
    {
        icon: typeof Lock;
        title: string;
        subtitle: string;
        accent: string;
        bg: string;
    }
> = {
    expired: {
        icon: CalendarX,
        title: 'Plan vencido',
        subtitle: 'Tu suscripción ha expirado. Renueva tu plan en Orvae para volver a usar VetSaaS.',
        accent: 'text-orange-700 dark:text-orange-400',
        bg: 'bg-orange-50 dark:bg-orange-950/30 ring-orange-200 dark:ring-orange-900',
    },
    suspended: {
        icon: Lock,
        title: 'Acceso suspendido',
        subtitle: 'Tu cuenta está temporalmente bloqueada. El equipo de soporte ya tiene la información para reactivarte.',
        accent: 'text-amber-700 dark:text-amber-400',
        bg: 'bg-amber-50 dark:bg-amber-950/30 ring-amber-200 dark:ring-amber-900',
    },
    cancelled: {
        icon: Ban,
        title: 'Cuenta cancelada',
        subtitle: 'Esta cuenta fue cancelada y ya no está disponible. Si crees que es un error, contacta a soporte.',
        accent: 'text-red-700 dark:text-red-400',
        bg: 'bg-red-50 dark:bg-red-950/30 ring-red-200 dark:ring-red-900',
    },
};

function formatDate(iso: string | null): string | null {
    if (!iso) return null;
    return new Date(iso).toLocaleDateString('es-PE', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

export default function TenantBlocked({
    block_type,
    estado,
    razon_social,
    reason,
    suspended_at,
    cancelled_at,
    support_whatsapp_phone = '51976809804',
}: BlockedProps) {
    const variant: BlockType =
        block_type ?? (estado === 'cancelled' ? 'cancelled' : 'suspended');
    const config = CONFIG[variant];
    const Icon = config.icon;
    const fechaAccion = formatDate(
        variant === 'cancelled' ? cancelled_at : variant === 'suspended' ? suspended_at : null,
    );
    const supportMessage = encodeURIComponent(
        `Hola, soy de ${razon_social}. No podemos ingresar a VetSaaS por un pago pendiente. ¿Podrían enviarnos el método y enlace de pago para reactivar la cuenta?`,
    );
    const supportUrl = `https://wa.me/${support_whatsapp_phone.replace(/\D/g, '')}?text=${supportMessage}`;

    return (
        <>
            <Head title={config.title} />

            <div className="mx-auto flex min-h-screen w-full max-w-2xl flex-col items-center justify-center gap-6 px-4 py-12 text-center">
                <div
                    className={`flex h-20 w-20 items-center justify-center rounded-2xl ring-1 ${config.bg} ${config.accent}`}
                >
                    <Icon className="size-10" />
                </div>

                <div className="space-y-2">
                    <h1 className="text-3xl font-semibold tracking-tight">{config.title}</h1>
                    <p className="text-base text-muted-foreground">
                        Cuenta de <span className="font-medium text-foreground">{razon_social}</span>
                    </p>
                    <p className="mx-auto max-w-md text-sm text-muted-foreground">{config.subtitle}</p>
                </div>

                {reason && (
                    <Card className="w-full text-left">
                        <CardHeader>
                            <CardTitle className="text-base">Motivo</CardTitle>
                            {fechaAccion && <CardDescription>Desde el {fechaAccion}</CardDescription>}
                        </CardHeader>
                        <CardContent>
                            <p className="whitespace-pre-line text-sm text-muted-foreground">{reason}</p>
                        </CardContent>
                    </Card>
                )}

                <Card className="w-full text-left">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Headphones className="size-5 text-primary" />
                            Reactiva tu cuenta
                        </CardTitle>
                        <CardDescription>
                            Contáctanos por WhatsApp para recibir el método de pago y reactivar tu clínica.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="grid gap-2 text-sm text-muted-foreground">
                            <li className="flex items-start gap-2">
                                <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary" />
                                Verifica con tu administrador el estado de tu suscripción.
                            </li>
                            <li className="flex items-start gap-2">
                                <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary" />
                                Te enviaremos el importe y enlace seguro de pago de tu suscripción.
                            </li>
                            <li className="flex items-start gap-2">
                                <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary" />
                                Cuando se confirme el pago, el acceso podrá habilitarse nuevamente.
                            </li>
                        </ul>
                        <Button
                            asChild
                            className="mt-5 w-full gap-2 bg-emerald-600 text-white hover:bg-emerald-700"
                        >
                            <a href={supportUrl} target="_blank" rel="noreferrer">
                                <MessageCircle className="size-4" />
                                Solicitar método de pago por WhatsApp
                            </a>
                        </Button>
                    </CardContent>
                </Card>

                <footer className="mt-auto pt-4 text-xs text-muted-foreground">VetSaaS · {new Date().getFullYear()}</footer>
            </div>
        </>
    );
}
