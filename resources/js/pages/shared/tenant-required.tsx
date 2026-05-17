import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Building2, LayoutGrid, Server } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

/**
 * Pantalla informativa que aparece cuando un usuario (típicamente
 * `superadmin`) accede a una ruta tenant-only desde el host central.
 *
 * En lugar de un 404 seco, le mostramos un mensaje claro y un atajo
 * para entrar al listado de tenants (de donde, en Fase 5, podrá usar
 * impersonation para "entrar" como soporte a una clínica concreta).
 *
 * Para roles operativos que no son superadmin este componente nunca
 * se renderiza: ven 404 directo desde {@see \App\Http\Middleware\EnsureTenant}.
 *
 * IMPORTANTE: **no** importar `AppLayout` aquí. En `resources/js/app.tsx`,
 * `createInertiaApp({ layout: … })` ya envuelve el `default` de casi
 * todas las páginas con `AppLayout`. Anidar otro `AppLayout` duplicaba
 * sidebar, `SidebarInset` y el botón de colapsar (layout dentro de layout).
 *
 * Decisiones de UX:
 *
 *  · Sin breadcrumbs (no se pasan vía layout global): pantalla "punto final".
 *  · Contenido top-aligned (no centrado vertical en toda la ventana).
 *  · CTAs primero, contexto técnico después de una divisoria sutil.
 */
type Props = {
    attempted_path: string;
};

export default function TenantRequired({ attempted_path }: Props) {
    const { t } = useTranslation('common');

    return (
        <>
            <Head title={t('tenant_required.page_title')} />

            <div className="mx-auto w-full max-w-2xl px-6 pt-12 pb-16 sm:pt-16">
                <div className="flex flex-col items-center gap-6 text-center">
                    {/* Hero icon */}
                    <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10 ring-1 ring-primary/20">
                        <Building2 className="size-8 text-primary" />
                    </div>

                    {/* Headline + descripción */}
                    <div className="space-y-2">
                        <h1 className="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                            {t('tenant_required.title')}
                        </h1>
                        <p className="mx-auto max-w-lg text-sm text-muted-foreground sm:text-base">
                            {t('tenant_required.description')}
                        </p>
                    </div>

                    {/* CTAs (acción principal en primer plano) */}
                    <div className="mt-1 flex w-full flex-col items-center gap-2 sm:w-auto sm:flex-row sm:gap-3">
                        <Button asChild size="lg" className="w-full gap-2 sm:w-auto">
                            <Link href="/plataforma/tenants" prefetch>
                                <Server className="size-4" />
                                {t('tenant_required.cta_primary')}
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                        <Button
                            asChild
                            size="lg"
                            variant="ghost"
                            className="w-full gap-2 sm:w-auto"
                        >
                            <Link href={dashboard()} prefetch>
                                <LayoutGrid className="size-4" />
                                {t('tenant_required.cta_secondary')}
                            </Link>
                        </Button>
                    </div>
                </div>

                {/*
                  Bloque de "contexto" — separado del hero por una
                  divisoria sutil. Aquí va la ruta solicitada y la
                  explicación larga. Si el usuario ya sabe lo que tiene
                  que hacer, dispara los CTAs y nunca llega a leer esto.
                */}
                <div className="mt-12 space-y-4 border-t border-border/60 pt-6">
                    {attempted_path && (
                        <div className="flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                            <span>{t('tenant_required.attempted_path')}:</span>
                            <code className="rounded bg-muted/60 px-1.5 py-0.5 font-mono text-foreground">
                                {attempted_path}
                            </code>
                        </div>
                    )}

                    <p className="mx-auto max-w-xl text-center text-xs leading-relaxed text-muted-foreground sm:text-sm">
                        {t('tenant_required.helper_inline')}
                    </p>
                </div>
            </div>
        </>
    );
}
