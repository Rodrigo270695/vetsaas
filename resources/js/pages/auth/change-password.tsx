import { Form, Head, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { TenantShared } from '@/types/tenant';

/**
 * Cambio obligatorio de contraseña.
 *
 * Visible solo cuando el usuario tiene `must_change_password = true`.
 * El middleware EnsurePasswordIsChanged redirige aquí en cada request
 * hasta que defina su clave. Por eso esta vista NO ofrece "Saltar":
 * la única forma de continuar usando la app es completar el cambio
 * (o cerrar sesión, lo cual sigue disponible desde el menú del topbar).
 */
export default function ChangePassword() {
    const { t } = useTranslation('auth');
    const page = usePage();
    const tenant = page.props.tenant as TenantShared | null;

    const brandName = tenant
        ? tenant.nombre_comercial || tenant.razon_social
        : (page.props.name as string);

    return (
        <>
            <Head title={t('change_password.title')} />

            <div className="mb-6 flex items-start gap-3 rounded-xl border border-warning/20 bg-warning/10 px-4 py-3 text-sm font-medium text-warning">
                <ShieldCheck
                    aria-hidden="true"
                    strokeWidth={2.25}
                    className="mt-0.5 size-4 shrink-0"
                />
                <span className="text-pretty">
                    {t('change_password.intro', { brand: brandName })}
                </span>
            </div>

            <Form
                action="/cuenta/cambiar-password"
                method="post"
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">
                                {t('change_password.new_password')}
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoFocus
                                autoComplete="new-password"
                                placeholder={t('common.password_placeholder')}
                                className="h-11"
                                aria-invalid={!!errors.password}
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                {t('reset_password.password_confirm')}
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                                placeholder={t('common.password_placeholder')}
                                className="h-11"
                                aria-invalid={!!errors.password_confirmation}
                            />
                            <InputError message={errors.password_confirmation} />
                        </div>

                        <Button
                            type="submit"
                            size="lg"
                            className="h-11 w-full text-base font-medium"
                            disabled={processing}
                            data-test="change-password-button"
                        >
                            {processing && <Spinner />}
                            {processing
                                ? t('common.submit_loading')
                                : t('change_password.submit')}
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

ChangePassword.layout = {
    title: 'protege tu cuenta.',
    description:
        'Por seguridad, define una nueva contraseña antes de continuar.',
};
