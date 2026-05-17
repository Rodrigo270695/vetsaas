import { Head } from '@inertiajs/react';
import { Building2, HelpCircle } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

/**
 * Pantalla que ve el usuario cuando entra a un subdominio que NO existe
 * (slug no encontrado en la tabla `tenants`).
 *
 * Renderizada por el handler global de `TenantNotFoundException` definido
 * en `bootstrap/app.php`. Se mantiene deliberadamente genérica: no
 * confirma si el slug existió alguna vez, fue borrado o nunca fue creado,
 * para no filtrar información de otros tenants.
 */
type NotFoundProps = {
    slug: string;
};

export default function TenantNotFound({ slug }: NotFoundProps) {
    return (
        <>
            <Head title="Clínica no encontrada" />

            <div className="mx-auto flex min-h-screen w-full max-w-2xl flex-col items-center justify-center gap-6 px-4 py-12 text-center">
                <div className="flex h-20 w-20 items-center justify-center rounded-2xl bg-muted text-muted-foreground ring-1 ring-border">
                    <Building2 className="size-10" />
                </div>

                <div className="space-y-2">
                    <h1 className="text-3xl font-semibold tracking-tight">No encontramos esta clínica</h1>
                    <p className="text-base text-muted-foreground">
                        La dirección <span className="font-mono text-foreground">{slug}</span> no está registrada en VetSaaS.
                    </p>
                </div>

                <Card className="w-full text-left">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <HelpCircle className="size-5 text-primary" />
                            ¿Qué puedes hacer?
                        </CardTitle>
                        <CardDescription>Antes de contactar a soporte, revisa lo siguiente:</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="grid gap-3 text-sm text-muted-foreground">
                            <li className="flex items-start gap-2">
                                <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary" />
                                Verifica que escribiste correctamente la dirección. Las direcciones de VetSaaS no llevan
                                espacios, mayúsculas ni caracteres especiales.
                            </li>
                            <li className="flex items-start gap-2">
                                <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary" />
                                Si recién contrataste el servicio, espera unos minutos: la activación puede demorar.
                            </li>
                            <li className="flex items-start gap-2">
                                <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary" />
                                Si crees que esta clínica debería existir, escríbenos por los canales oficiales de soporte.
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <footer className="mt-auto pt-4 text-xs text-muted-foreground">VetSaaS · {new Date().getFullYear()}</footer>
            </div>
        </>
    );
}
