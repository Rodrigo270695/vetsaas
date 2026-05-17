import { Form } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { FieldWithIcon } from '@/components/ui/field-with-icon';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/password';

type ResetPasswordFormProps = {
    token: string;
    email: string;
};

/**
 * Define la nueva contraseña tras hacer click en el enlace del correo de recuperación.
 * El email viene pre-rellenado y se envía oculto junto al token firmado.
 */
export default function ResetPasswordForm({
    token,
    email,
}: ResetPasswordFormProps) {
    const { t } = useTranslation('auth');

    return (
        <Form
            {...update.form()}
            transform={(data) => ({ ...data, token, email })}
            resetOnSuccess={['password', 'password_confirmation']}
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
                            autoComplete="email"
                            defaultValue={email}
                            className="h-11"
                            readOnly
                            aria-invalid={!!errors.email}
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">
                            {t('common.password')}
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
                        className="mt-1 h-11 w-full text-base font-medium"
                        disabled={processing}
                        data-test="reset-password-button"
                    >
                        {processing && <Spinner />}
                        {processing
                            ? t('common.submit_loading')
                            : t('reset_password.submit')}
                    </Button>
                </div>
            )}
        </Form>
    );
}
