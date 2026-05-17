import { Head, Link } from '@inertiajs/react';
import { Building2, CheckCircle2, Clock, Database, LogIn, Sparkles } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { TenantEstado } from '@/types/tenant';

/**
 * Página de "bienvenida" del subdominio de tenant.
 *
 * Es un placeholder visual de Fase 2: confirma al desarrollador / cliente
 * que la resolución por subdominio funciona y muestra la información
 * básica del tenant resuelto. Cuando entre Fase 2.5 (autenticación) y
 * Fase 4 (módulos clínicos), esta ruta evolucionará al dashboard real
 * del empleado de la clínica.
 *
 * Información que muestra:
 *   - Razón social y nombre comercial (lo que verá el cliente).
 *   - Slug y schema técnicos (útil para soporte).
 *   - Estado y días restantes de trial (si aplica).
 */
type TenantWelcomeProps = {
    tenant: {
        id: string;
        slug: string;
        schema: string;
        razon_social: string;
        nombre_comercial: string | null;
        estado: TenantEstado;
        trial_ends_at: string | null;
        logo_url: string | null;
    };
};

const ESTADO_LABELS: Record<TenantEstado, string> = {
    trial: 'Periodo de prueba',
    active: 'Activo',
    grace: 'Periodo de gracia',
    suspended: 'Suspendido',
    cancelled: 'Cancelado',
};

const ESTADO_TONE: Record<TenantEstado, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    trial: 'secondary',
    active: 'default',
    grace: 'outline',
    suspended: 'destructive',
    cancelled: 'destructive',
};

function daysUntil(iso: string | null): number | null {
    if (!iso) return null;
    const target = new Date(iso).getTime();
    const now = Date.now();
    const diffMs = target - now;
    if (diffMs <= 0) return 0;
    return Math.ceil(diffMs / (1000 * 60 * 60 * 24));
}

export default function TenantWelcome({ tenant }: TenantWelcomeProps) {
    const trialDays = daysUntil(tenant.trial_ends_at);
    const titulo = tenant.nombre_comercial || tenant.razon_social;

    return (
        <>
            <Head title={titulo} />

            <div className="mx-auto flex min-h-screen w-full max-w-5xl flex-col gap-8 px-4 py-12 sm:px-6 lg:px-8">
                {/* Hero */}
                <header className="flex flex-col gap-4 text-center">
                    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10 text-primary ring-1 ring-primary/20">
                        <Building2 className="size-8" />
                    </div>
                    <div className="space-y-2">
                        <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">{titulo}</h1>
                        <p className="text-sm text-muted-foreground sm:text-base">
                            Bienvenido a tu espacio en VetSaaS. Aquí pronto encontrarás el panel de tu clínica.
                        </p>
                    </div>
                    <div className="flex items-center justify-center gap-3">
                        <Badge variant={ESTADO_TONE[tenant.estado]}>{ESTADO_LABELS[tenant.estado]}</Badge>
                        {trialDays !== null && tenant.estado === 'trial' && (
                            <Badge variant="outline" className="gap-1">
                                <Clock className="size-3.5" />
                                {trialDays === 0 ? 'Trial finalizado' : `${trialDays} día${trialDays === 1 ? '' : 's'} restantes`}
                            </Badge>
                        )}
                    </div>
                    <div className="mt-2">
                        <Button asChild size="lg">
                            <Link href="/login">
                                <LogIn className="size-4" />
                                Ingresar a mi clínica
                            </Link>
                        </Button>
                    </div>
                </header>

                {/* Estado de la integración */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <CheckCircle2 className="size-5 text-emerald-600" />
                            Aislamiento activo
                        </CardTitle>
                        <CardDescription>
                            El sistema ya identificó tu clínica y todas tus operaciones se ejecutarán dentro de tu propio espacio
                            de datos.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 text-sm sm:grid-cols-2">
                        <div className="rounded-lg border bg-muted/30 p-3">
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Subdominio</p>
                            <p className="mt-1 font-mono text-sm">{tenant.slug}</p>
                        </div>
                        <div className="rounded-lg border bg-muted/30 p-3">
                            <p className="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                <Database className="size-3.5" />
                                Schema
                            </p>
                            <p className="mt-1 font-mono text-sm">{tenant.schema}</p>
                        </div>
                    </CardContent>
                </Card>

                {/* Próximos pasos */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Sparkles className="size-5 text-primary" />
                            En construcción
                        </CardTitle>
                        <CardDescription>
                            Estamos terminando el panel de operaciones de tu clínica. Pronto encontrarás aquí:
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="grid gap-2 text-sm text-muted-foreground sm:grid-cols-2">
                            <li className="flex items-start gap-2">
                                <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary" />
                                Inicio de sesión propio de tu clínica
                            </li>
                            <li className="flex items-start gap-2">
                                <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary" />
                                Gestión de pacientes y propietarios
                            </li>
                            <li className="flex items-start gap-2">
                                <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary" />
                                Historias clínicas y vacunaciones
                            </li>
                            <li className="flex items-start gap-2">
                                <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary" />
                                Agenda y citas
                            </li>
                            <li className="flex items-start gap-2">
                                <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary" />
                                Inventario y caja
                            </li>
                            <li className="flex items-start gap-2">
                                <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary" />
                                Facturación electrónica (SUNAT)
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <footer className="mt-auto pt-8 text-center text-xs text-muted-foreground">
                    VetSaaS · {new Date().getFullYear()}
                </footer>
            </div>
        </>
    );
}
