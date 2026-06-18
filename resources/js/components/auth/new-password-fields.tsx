import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import PasswordStrengthMeter from '@/components/auth/password-strength-meter';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Label } from '@/components/ui/label';
import {
    canSubmitNewPassword,
    passwordsMatch,
} from '@/lib/passwordPolicy';
import { cn } from '@/lib/utils';

type NewPasswordFieldsProps = {
    password: string;
    passwordConfirmation: string;
    onPasswordChange: (value: string) => void;
    onPasswordConfirmationChange: (value: string) => void;
    errors: {
        password?: string;
        password_confirmation?: string;
    };
    passwordLabel?: string;
    autoFocus?: boolean;
};

export function isNewPasswordReady(
    password: string,
    passwordConfirmation: string,
): boolean {
    return canSubmitNewPassword(password, passwordConfirmation);
}

export default function NewPasswordFields({
    password,
    passwordConfirmation,
    onPasswordChange,
    onPasswordConfirmationChange,
    errors,
    passwordLabel,
    autoFocus = false,
}: NewPasswordFieldsProps) {
    const { t } = useTranslation('auth');
    const ready = useMemo(
        () => canSubmitNewPassword(password, passwordConfirmation),
        [password, passwordConfirmation],
    );
    const showPolicyHint =
        import.meta.env.PROD && password.length > 0 && !ready;
    const confirmationMismatch =
        passwordConfirmation.length > 0 &&
        !passwordsMatch(password, passwordConfirmation);

    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="password">
                    {passwordLabel ?? t('common.password')}
                </Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autoFocus={autoFocus}
                    autoComplete="new-password"
                    placeholder={t('common.password_placeholder')}
                    className="h-11"
                    value={password}
                    onChange={(event) =>
                        onPasswordChange(event.target.value)
                    }
                    aria-invalid={!!errors.password}
                    aria-describedby="password-policy-feedback"
                />
                <InputError message={errors.password} />
                <div id="password-policy-feedback">
                    <PasswordStrengthMeter
                        password={password}
                        className="rounded-xl border border-border/60 bg-muted/20 px-3 py-3"
                    />
                </div>
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
                    value={passwordConfirmation}
                    onChange={(event) =>
                        onPasswordConfirmationChange(event.target.value)
                    }
                    aria-invalid={
                        !!errors.password_confirmation || confirmationMismatch
                    }
                />
                <InputError message={errors.password_confirmation} />
                {confirmationMismatch && (
                    <p
                        className={cn('text-sm text-destructive')}
                        role="alert"
                    >
                        {t('password_policy.confirmation_mismatch')}
                    </p>
                )}
                {showPolicyHint && (
                    <p className="text-xs text-muted-foreground">
                        {t('password_policy.submit_hint')}
                    </p>
                )}
            </div>
        </>
    );
}
