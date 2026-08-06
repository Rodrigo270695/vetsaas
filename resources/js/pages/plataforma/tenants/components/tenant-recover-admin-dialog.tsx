import { router } from '@inertiajs/react';
import { KeyRound, Loader2 } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import tenants from '@/routes/plataforma/tenants';
import type { Tenant } from '../types';

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export type TenantRecoverAdminDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    tenant: Tenant | null;
};

export function TenantRecoverAdminDialog({
    open,
    onOpenChange,
    tenant,
}: TenantRecoverAdminDialogProps) {
    const { t } = useTranslation(['tenants', 'common']);
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [mustChangePassword, setMustChangePassword] = useState(true);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (open && tenant) {
            setEmail(tenant.email_admin ?? '');
            setPassword('');
            setPasswordConfirmation('');
            setMustChangePassword(true);
            setErrors({});
        }
    }, [open, tenant]);

    const canSubmit =
        EMAIL_REGEX.test(email.trim()) &&
        password.length >= 8 &&
        password === passwordConfirmation;

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!tenant || !canSubmit) {
            return;
        }

        setProcessing(true);
        setErrors({});

        router.post(
            tenants.recoverAdminAccess(tenant.id).url,
            {
                email: email.trim().toLowerCase(),
                password,
                password_confirmation: passwordConfirmation,
                must_change_password: mustChangePassword,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => onOpenChange(false),
                onError: (errs) => {
                    const next: Record<string, string> = {};
                    for (const [key, value] of Object.entries(errs)) {
                        next[key] = Array.isArray(value) ? String(value[0]) : String(value);
                    }
                    setErrors(next);
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="flex size-11 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <KeyRound className="size-5" strokeWidth={2.5} />
                    </div>
                    <DialogTitle className="pt-2 text-base">
                        {t('recover_admin.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('recover_admin.description', {
                            clinic: tenant?.nombre_comercial || tenant?.razon_social || '',
                        })}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={onSubmit} className="space-y-4">
                    <div className="rounded-lg border border-border/70 bg-muted/30 px-3 py-2 text-xs text-muted-foreground">
                        {t('recover_admin.current_email')}:{' '}
                        <span className="font-medium text-foreground">
                            {tenant?.email_admin || '—'}
                        </span>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="recover-admin-email">
                            {t('recover_admin.fields.email')}
                        </Label>
                        <Input
                            id="recover-admin-email"
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            autoComplete="off"
                            className="cursor-pointer"
                        />
                        {errors.email ? (
                            <p className="text-xs text-destructive">{errors.email}</p>
                        ) : null}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="recover-admin-password">
                            {t('recover_admin.fields.password')}
                        </Label>
                        <Input
                            id="recover-admin-password"
                            type="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            autoComplete="new-password"
                        />
                        {errors.password ? (
                            <p className="text-xs text-destructive">{errors.password}</p>
                        ) : (
                            <p className="text-xs text-muted-foreground">
                                {t('recover_admin.fields.password_hint')}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="recover-admin-password-confirmation">
                            {t('recover_admin.fields.password_confirmation')}
                        </Label>
                        <Input
                            id="recover-admin-password-confirmation"
                            type="password"
                            value={passwordConfirmation}
                            onChange={(e) => setPasswordConfirmation(e.target.value)}
                            autoComplete="new-password"
                        />
                    </div>

                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="recover-must-change"
                            checked={mustChangePassword}
                            onCheckedChange={(checked) =>
                                setMustChangePassword(checked === true)
                            }
                            className="mt-0.5"
                        />
                        <Label
                            htmlFor="recover-must-change"
                            className="cursor-pointer text-sm font-normal leading-snug"
                        >
                            {t('recover_admin.fields.must_change_password')}
                        </Label>
                    </div>

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                            className="cursor-pointer"
                        >
                            {t('common:actions.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            disabled={!canSubmit || processing}
                            className="cursor-pointer gap-2"
                        >
                            {processing ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <KeyRound className="size-4" strokeWidth={2.5} />
                            )}
                            {t('recover_admin.submit')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
