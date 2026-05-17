import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

/**
 * Re-confirmación de contraseña antes de entrar a una sección sensible
 * (ajustes de seguridad, eliminación de cuenta, etc.).
 */
export default function ConfirmPasswordForm() {
    return (
        <Form
            {...store.form()}
            resetOnSuccess={['password']}
            className="flex flex-col"
        >
            {({ processing, errors }) => (
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="password">Contraseña actual</Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            required
                            autoFocus
                            autoComplete="current-password"
                            placeholder="Tu contraseña"
                            className="h-11"
                            aria-invalid={!!errors.password}
                        />
                        <InputError message={errors.password} />
                    </div>

                    <Button
                        type="submit"
                        size="lg"
                        className="h-11 w-full text-base font-medium"
                        disabled={processing}
                        data-test="confirm-password-button"
                    >
                        {processing && <Spinner />}
                        {processing ? 'Verificando…' : 'Confirmar contraseña'}
                    </Button>
                </div>
            )}
        </Form>
    );
}
