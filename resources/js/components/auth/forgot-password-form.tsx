import { Form } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import AuthBackToLogin from '@/components/auth/auth-back-to-login';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { FieldWithIcon } from '@/components/ui/field-with-icon';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { email } from '@/routes/password';

type ForgotPasswordFormProps = {
    /**
     * Si se pasa, el link "Volver a iniciar sesión" actúa como botón
     * que dispara este callback (para flip-card en vez de navegar).
     */
    onBackToLogin?: () => void;
};

/**
 * Solicita un correo para enviar el enlace de recuperación de contraseña.
 */
export default function ForgotPasswordForm({
    onBackToLogin,
}: ForgotPasswordFormProps = {}) {
    const { t } = useTranslation('auth');

    return (
        <>
            <Form {...email.form()} className="flex flex-col">
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="email">{t('common.email')}</Label>
                            <FieldWithIcon
                                id="email"
                                type="email"
                                name="email"
                                icon={Mail}
                                required
                                autoFocus
                                autoComplete="email"
                                placeholder={t('common.email_placeholder')}
                                className="h-11"
                                aria-invalid={!!errors.email}
                            />
                            <InputError message={errors.email} />
                        </div>

                        <Button
                            type="submit"
                            size="lg"
                            className="h-11 w-full text-base font-medium"
                            disabled={processing}
                            data-test="email-password-reset-link-button"
                        >
                            {processing && <Spinner />}
                            {processing
                                ? t('common.submit_loading')
                                : t('forgot_password.submit')}
                        </Button>
                    </div>
                )}
            </Form>
            <AuthBackToLogin onClick={onBackToLogin} />
        </>
    );
}
