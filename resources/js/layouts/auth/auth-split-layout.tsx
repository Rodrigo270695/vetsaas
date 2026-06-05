import { usePage } from '@inertiajs/react';
import AuthAuroraBackground from '@/components/auth/auth-aurora-background';
import AuthBentoOrbit from '@/components/auth/auth-bento-orbit';
import AuthFooter from '@/components/auth/auth-footer';
import AuthFormCard from '@/components/auth/auth-form-card';
import AuthGreeting from '@/components/auth/auth-greeting';
import AuthHeader from '@/components/auth/auth-header';
import type { AuthLayoutProps } from '@/types';
import type { TenantShared } from '@/types/tenant';

/**
 * Páginas que renderizan su propia card (para efectos como flip 3D)
 * y por lo tanto NO deben envolverse en el `AuthFormCard` estándar.
 */
const PAGES_WITH_OWN_CARD = new Set([
    'auth/login',
    'errors/forbidden',
    'errors/not-found',
]);

/**
 * Layout editorial de autenticación.
 *
 * Composición:
 * - Aurora background animado + grain noise.
 * - Header con logo, CTA de contacto y theme toggle.
 * - Centro: saludo dinámico + card glass con el formulario (children).
 * - Bento orbital decorativo (solo xl+).
 * - Footer con copyright + links legales.
 *
 * Las páginas listadas en `PAGES_WITH_OWN_CARD` reciben children "crudos"
 * para poder renderizar su propia card (e.g. con efecto flip 3D).
 */
export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const page = usePage();
    const brandName = page.props.name as string;
    const tenant = page.props.tenant as TenantShared | null;
    const skipFormCard = PAGES_WITH_OWN_CARD.has(page.component);

    // Branding contextual: si estamos en un subdominio de tenant
    // (mi-clinica.localhost), el título / descripción del saludo se
    // personalizan con el nombre de la clínica. En el panel central
    // se mantienen los textos genéricos definidos por la página.
    const tenantTitle = tenant
        ? (tenant.nombre_comercial || tenant.razon_social).trim() + '.'
        : title;
    const tenantDescription = tenant
        ? 'Ingresa con tu cuenta para gestionar agenda, historia clínica y caja.'
        : description;
    const headerBrand = tenant
        ? tenant.nombre_comercial || tenant.razon_social
        : brandName;

    return (
        <div className="relative isolate flex min-h-svh flex-col overflow-hidden bg-background text-foreground">
            <AuthAuroraBackground />
            <AuthHeader brandName={headerBrand} />

            <main className="relative z-10 flex flex-1 items-center justify-center px-4 py-8 sm:py-12 lg:py-16">
                <AuthBentoOrbit />

                <article className="relative z-10 mx-auto w-full max-w-md">
                    <AuthGreeting
                        title={tenantTitle}
                        description={tenantDescription}
                    />
                    {skipFormCard ? (
                        children
                    ) : (
                        <AuthFormCard>{children}</AuthFormCard>
                    )}
                </article>
            </main>

            <AuthFooter brandName={brandName} />
        </div>
    );
}
