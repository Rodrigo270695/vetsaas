import { router } from '@inertiajs/react';
import { Check, Copy, KeyRound, Loader2 } from 'lucide-react';
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
import { useClipboard } from '@/hooks/use-clipboard';
import { toastManager } from '@/lib/toast';
import type { Tenant } from '../types';

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

type RecoverResult = {
    email: string;
    copyUrl: string;
    warning: string;
    info: string;
};

type FlashProps = {
    copy_url?: string | null;
    warning?: string | null;
    info?: string | null;
};

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
    const [, copy] = useClipboard();
    const [copiedKey, setCopiedKey] = useState<string | null>(null);
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [mustChangePassword, setMustChangePassword] = useState(true);
    const [notifyClient, setNotifyClient] = useState(true);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [result, setResult] = useState<RecoverResult | null>(null);

    useEffect(() => {
        if (open && tenant) {
            setEmail(tenant.email_admin ?? '');
            setPassword('');
            setPasswordConfirmation('');
            setMustChangePassword(true);
            setNotifyClient(true);
            setErrors({});
            setResult(null);
            setCopiedKey(null);
        }
    }, [open, tenant]);

    const canSubmit =
        EMAIL_REGEX.test(email.trim()) &&
        password.length >= 8 &&
        password === passwordConfirmation;

    const copyValue = async (key: string, value: string) => {
        const ok = await copy(value);
        if (ok) {
            setCopiedKey(key);
            toastManager.success({ title: t('recover_admin.result.copied') });
            window.setTimeout(() => setCopiedKey((current) => (current === key ? null : current)), 2000);
            return;
        }

        toastManager.error({ title: 'No se pudo copiar' });
    };

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!tenant || !canSubmit) {
            return;
        }

        setProcessing(true);
        setErrors({});

        router.post(
            `/plataforma/tenants/${tenant.id}/recover-admin-access`,
            {
                email: email.trim().toLowerCase(),
                password,
                password_confirmation: passwordConfirmation,
                must_change_password: mustChangePassword,
                notify_client: notifyClient,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: (page) => {
                    const flash = (page.props as { flash?: FlashProps | null }).flash;
                    const copyUrl =
                        typeof flash?.copy_url === 'string' ? flash.copy_url : '';

                    setResult({
                        email: email.trim().toLowerCase(),
                        copyUrl,
                        warning: typeof flash?.warning === 'string' ? flash.warning : '',
                        info: typeof flash?.info === 'string' ? flash.info : '',
                    });
                    setPassword('');
                    setPasswordConfirmation('');
                },
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

    const phoneLabel = tenant?.telefono?.trim() || 'sin teléfono';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="flex size-11 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <KeyRound className="size-5" strokeWidth={2.5} />
                    </div>
                    <DialogTitle className="pt-2 text-base">
                        {result
                            ? t('recover_admin.result.title')
                            : t('recover_admin.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {result
                            ? t('recover_admin.result.description')
                            : t('recover_admin.description', {
                                  clinic: tenant?.nombre_comercial || tenant?.razon_social || '',
                              })}
                    </DialogDescription>
                </DialogHeader>

                {result ? (
                    <div className="space-y-3">
                        {result.info ? (
                            <p className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200">
                                {result.info}
                            </p>
                        ) : null}
                        {result.warning ? (
                            <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
                                {result.warning}
                            </p>
                        ) : null}

                        <CopyRow
                            label={t('recover_admin.result.email')}
                            value={result.email}
                            copied={copiedKey === 'email'}
                            copyLabel={t('recover_admin.result.copy')}
                            onCopy={() => void copyValue('email', result.email)}
                        />

                        {result.copyUrl ? (
                            <CopyRow
                                label={t('recover_admin.result.welcome')}
                                value={result.copyUrl}
                                copied={copiedKey === 'welcome'}
                                copyLabel={t('recover_admin.result.copy')}
                                onCopy={() => void copyValue('welcome', result.copyUrl)}
                            />
                        ) : null}

                        <DialogFooter>
                            <Button
                                type="button"
                                onClick={() => onOpenChange(false)}
                                className="cursor-pointer"
                            >
                                {t('recover_admin.result.close')}
                            </Button>
                        </DialogFooter>
                    </div>
                ) : (
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

                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="recover-notify-client"
                                checked={notifyClient}
                                onCheckedChange={(checked) =>
                                    setNotifyClient(checked === true)
                                }
                                className="mt-0.5"
                            />
                            <div className="space-y-1">
                                <Label
                                    htmlFor="recover-notify-client"
                                    className="cursor-pointer text-sm font-normal leading-snug"
                                >
                                    {t('recover_admin.fields.notify_client')}
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    {t('recover_admin.fields.notify_hint', { phone: phoneLabel })}
                                </p>
                            </div>
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
                )}
            </DialogContent>
        </Dialog>
    );
}

function CopyRow({
    label,
    value,
    copied,
    copyLabel,
    onCopy,
}: {
    label: string;
    value: string;
    copied: boolean;
    copyLabel: string;
    onCopy: () => void;
}) {
    return (
        <div className="space-y-1.5">
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <div className="flex items-start gap-2">
                <p className="min-w-0 flex-1 break-all rounded-md border bg-muted/40 px-2.5 py-2 font-mono text-[11px] leading-snug text-foreground">
                    {value}
                </p>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="shrink-0 cursor-pointer gap-1.5"
                    onClick={onCopy}
                >
                    {copied ? (
                        <Check className="size-3.5" />
                    ) : (
                        <Copy className="size-3.5" />
                    )}
                    {copyLabel}
                </Button>
            </div>
        </div>
    );
}
