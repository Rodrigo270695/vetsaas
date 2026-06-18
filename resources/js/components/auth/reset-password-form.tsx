import { Form } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import NewPasswordFields, {
    isNewPasswordReady,
} from '@/components/auth/new-password-fields';
import InputError from '@/components/input-error';
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
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');

    return (
        <Form
            {...update.form()}
            transform={(data) => ({ ...data, token, email })}
            resetOnSuccess={['password', 'password_confirmation']}
            className="flex flex-col"
            onSuccess={() => {
                setPassword('');
                setPasswordConfirmation('');
            }}
        >
            {({ processing, errors }) => {
                const ready = isNewPasswordReady(
                    password,
                    passwordConfirmation,
                );

                return (
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

                        <NewPasswordFields
                            password={password}
                            passwordConfirmation={passwordConfirmation}
                            onPasswordChange={setPassword}
                            onPasswordConfirmationChange={
                                setPasswordConfirmation
                            }
                            errors={errors}
                            autoFocus
                        />

                        <Button
                            type="submit"
                            size="lg"
                            className="mt-1 h-11 w-full text-base font-medium"
                            disabled={processing || !ready}
                            data-test="reset-password-button"
                        >
                            {processing && <Spinner />}
                            {processing
                                ? t('common.submit_loading')
                                : t('reset_password.submit')}
                        </Button>
                    </div>
                );
            }}
        </Form>
    );
}
