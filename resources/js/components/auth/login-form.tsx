import { Form } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldWithIcon } from '@/components/ui/field-with-icon';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type LoginFormProps = {
    canResetPassword: boolean;
    /**
     * Si se pasa, el link "¿La olvidaste?" actúa como botón que dispara este
     * callback (para hacer un flip-card en vez de navegar a otra ruta).
     */
    onForgotPassword?: () => void;
};

/**
 * Formulario de inicio de sesión.
 *
 * Maneja su propio estado de envío a través de `<Form>` de Inertia y muestra
 * errores de validación inline por campo.
 */
export default function LoginForm({
    canResetPassword,
    onForgotPassword,
}: LoginFormProps) {
    const { t } = useTranslation('auth');

    return (
        <Form
            {...store.form()}
            resetOnSuccess={['password']}
            className="flex flex-col"
        >
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
                            tabIndex={1}
                            autoComplete="email"
                            placeholder={t('common.email_placeholder')}
                            className="h-11"
                            aria-invalid={!!errors.email}
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <div className="flex items-center justify-between">
                            <Label htmlFor="password">
                                {t('common.password')}
                            </Label>
                            {canResetPassword &&
                                (onForgotPassword ? (
                                    <button
                                        type="button"
                                        onClick={onForgotPassword}
                                        tabIndex={5}
                                        className="cursor-pointer rounded-sm text-xs font-medium text-primary underline-offset-4 transition-colors duration-200 hover:text-brand-700 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                    >
                                        {t('login.forgot_password')}
                                    </button>
                                ) : (
                                    <TextLink
                                        href={request()}
                                        className="text-xs"
                                        tabIndex={5}
                                    >
                                        {t('login.forgot_password')}
                                    </TextLink>
                                ))}
                        </div>
                        <PasswordInput
                            id="password"
                            name="password"
                            required
                            tabIndex={2}
                            autoComplete="current-password"
                            placeholder={t('common.password_placeholder')}
                            className="h-11"
                            aria-invalid={!!errors.password}
                        />
                        <InputError message={errors.password} />
                    </div>

                    <label
                        htmlFor="remember"
                        className="group flex cursor-pointer items-center gap-3 select-none"
                    >
                        <Checkbox id="remember" name="remember" tabIndex={3} />
                        <span className="text-sm text-muted-foreground transition-colors group-hover:text-foreground">
                            {t('common.remember')}
                        </span>
                    </label>

                    <Button
                        type="submit"
                        size="lg"
                        className="mt-1 h-11 w-full text-base font-medium"
                        tabIndex={4}
                        disabled={processing}
                        data-test="login-button"
                    >
                        {processing && <Spinner />}
                        {processing
                            ? t('common.submit_loading')
                            : t('login.submit')}
                    </Button>
                </div>
            )}
        </Form>
    );
}
