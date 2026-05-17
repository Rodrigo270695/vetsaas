import { Form, setLayoutProps } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { KeyRound, ShieldCheck } from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { FieldWithIcon } from '@/components/ui/field-with-icon';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { store } from '@/routes/two-factor/login';

type ModeContent = {
    title: string;
    description: string;
    toggleLabel: string;
};

const CONTENT: Record<'otp' | 'recovery', ModeContent> = {
    otp: {
        title: 'Verifica tu identidad',
        description:
            'Ingresa el código de 6 dígitos que muestra tu app de autenticación.',
        toggleLabel: 'Usar un código de recuperación',
    },
    recovery: {
        title: 'Código de recuperación',
        description:
            'Ingresa uno de los códigos de emergencia que guardaste al activar la verificación.',
        toggleLabel: 'Volver al código de la app',
    },
};

/**
 * Reto de doble factor: pide el código de 6 dígitos del autenticador
 * o un código de recuperación de un solo uso.
 *
 * Sincroniza dinámicamente el `title`/`description` del layout editorial
 * según el modo activo a través de `setLayoutProps`.
 */
export default function TwoFactorForm() {
    const [showRecovery, setShowRecovery] = useState(false);
    const [code, setCode] = useState('');

    const content = useMemo<ModeContent>(
        () => (showRecovery ? CONTENT.recovery : CONTENT.otp),
        [showRecovery],
    );

    setLayoutProps({
        title: content.title,
        description: content.description,
    });

    const toggleMode = (clearErrors: () => void) => {
        setShowRecovery((prev) => !prev);
        clearErrors();
        setCode('');
    };

    return (
        <Form
            {...store.form()}
            resetOnError
            resetOnSuccess={!showRecovery}
            className="flex flex-col"
        >
            {({ errors, processing, clearErrors }) => (
                <div className="grid gap-6">
                    {showRecovery ? (
                        <div className="grid gap-2">
                            <FieldWithIcon
                                name="recovery_code"
                                type="text"
                                icon={KeyRound}
                                placeholder="xxxxxxxx-xxxxxxxx"
                                autoFocus
                                required
                                autoComplete="one-time-code"
                                className="h-11"
                                aria-invalid={!!errors.recovery_code}
                            />
                            <InputError message={errors.recovery_code} />
                        </div>
                    ) : (
                        <div className="flex flex-col items-center gap-3">
                            <InputOTP
                                name="code"
                                maxLength={OTP_MAX_LENGTH}
                                value={code}
                                onChange={(value) => setCode(value)}
                                disabled={processing}
                                pattern={REGEXP_ONLY_DIGITS}
                                autoFocus
                            >
                                <InputOTPGroup>
                                    {Array.from(
                                        { length: OTP_MAX_LENGTH },
                                        (_, index) => (
                                            <InputOTPSlot
                                                key={index}
                                                index={index}
                                            />
                                        ),
                                    )}
                                </InputOTPGroup>
                            </InputOTP>
                            <InputError message={errors.code} />
                        </div>
                    )}

                    <Button
                        type="submit"
                        size="lg"
                        className="h-11 w-full text-base font-medium"
                        disabled={processing}
                        data-test="two-factor-submit-button"
                    >
                        {processing ? (
                            <Spinner />
                        ) : (
                            <ShieldCheck
                                className="size-4"
                                strokeWidth={2.25}
                            />
                        )}
                        {processing ? 'Verificando…' : 'Verificar y continuar'}
                    </Button>

                    <div className="text-center text-sm text-muted-foreground">
                        <button
                            type="button"
                            className="cursor-pointer rounded-sm text-foreground underline decoration-border underline-offset-4 transition-colors hover:decoration-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                            onClick={() => toggleMode(clearErrors)}
                        >
                            {content.toggleLabel}
                        </button>
                    </div>
                </div>
            )}
        </Form>
    );
}
