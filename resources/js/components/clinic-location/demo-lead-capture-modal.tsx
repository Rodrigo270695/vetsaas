import { usePage } from '@inertiajs/react';
import { Mail, Phone, Building2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
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

type TenantProp = {
    is_demo?: boolean;
} | null;

const DONE_SESSION_KEY = 'vetsaas.demo_lead_done';
const SKIP_UNTIL_KEY = 'vetsaas.demo_lead_skip_until';
const LOG_ID_KEY = 'vetsaas.demo_access_log_id';
const OPEN_DELAY_MS = 45_000;
const SKIP_COOLDOWN_MS = 7 * 24 * 60 * 60 * 1000;

function csrfHeaders(): Record<string, string> {
    const meta =
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? '';
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    const xsrf = match?.[1] ? decodeURIComponent(match[1]) : '';

    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(meta ? { 'X-CSRF-TOKEN': meta } : {}),
        ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
    };
}

function alreadyHandled(): boolean {
    if (typeof window === 'undefined') {
        return true;
    }
    if (sessionStorage.getItem(DONE_SESSION_KEY) === '1') {
        return true;
    }
    const until = Number(localStorage.getItem(SKIP_UNTIL_KEY) || '0');
    if (until > Date.now()) {
        return true;
    }
    return false;
}

function markDone(skipped: boolean): void {
    sessionStorage.setItem(DONE_SESSION_KEY, '1');
    if (skipped) {
        localStorage.setItem(
            SKIP_UNTIL_KEY,
            String(Date.now() + SKIP_COOLDOWN_MS),
        );
    } else {
        localStorage.removeItem(SKIP_UNTIL_KEY);
    }
}

/**
 * Tras unos segundos en la demo, pide celular o correo (clínica opcional)
 * para poder recontactar al prospecto.
 */
export function DemoLeadCaptureModal() {
    const { t } = useTranslation(['demo-lead', 'common']);
    const page = usePage<{ tenant?: TenantProp }>();
    const isDemo = Boolean(page.props.tenant?.is_demo);
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [clinicName, setClinicName] = useState('');
    const [phone, setPhone] = useState('');
    const [email, setEmail] = useState('');
    const started = useRef(false);

    useEffect(() => {
        if (!isDemo || alreadyHandled() || started.current) {
            return;
        }
        started.current = true;

        const timer = window.setTimeout(() => {
            if (!alreadyHandled()) {
                setOpen(true);
            }
        }, OPEN_DELAY_MS);

        return () => window.clearTimeout(timer);
    }, [isDemo]);

    if (!isDemo) {
        return null;
    }

    async function submit(skip: boolean): Promise<void> {
        setError(null);

        const trimmedPhone = phone.trim();
        const trimmedEmail = email.trim();
        const trimmedClinic = clinicName.trim();

        if (!skip && !trimmedPhone && !trimmedEmail) {
            setError(t('demo-lead:errors.contact_required'));
            return;
        }

        setBusy(true);
        try {
            const logId = sessionStorage.getItem(LOG_ID_KEY);
            const res = await fetch('/demo/access-lead', {
                method: 'POST',
                credentials: 'same-origin',
                headers: csrfHeaders(),
                body: JSON.stringify({
                    log_id: logId || null,
                    skip,
                    clinic_name: trimmedClinic || null,
                    phone: trimmedPhone || null,
                    email: trimmedEmail || null,
                }),
            });

            const data = (await res.json().catch(() => null)) as {
                ok?: boolean;
                id?: string;
                message?: string;
                errors?: Record<string, string[]>;
            } | null;

            if (!res.ok || !data?.ok) {
                const firstError =
                    data?.errors?.phone?.[0] ??
                    data?.errors?.email?.[0] ??
                    data?.message ??
                    t('demo-lead:errors.generic');
                setError(firstError);
                return;
            }

            if (data.id) {
                sessionStorage.setItem(LOG_ID_KEY, data.id);
            }
            markDone(skip);
            setOpen(false);
        } catch {
            setError(t('demo-lead:errors.generic'));
        } finally {
            setBusy(false);
        }
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next && !busy && !alreadyHandled()) {
                    void submit(true);
                }
            }}
        >
            <DialogContent
                hideCloseButton
                className="sm:max-w-md"
                onPointerDownOutside={(e) => e.preventDefault()}
                onEscapeKeyDown={(e) => e.preventDefault()}
            >
                <DialogHeader>
                    <DialogTitle>{t('demo-lead:title')}</DialogTitle>
                    <DialogDescription>
                        {t('demo-lead:description')}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-3 py-1">
                    <div className="grid gap-1.5">
                        <Label htmlFor="demo-lead-clinic">
                            {t('demo-lead:fields.clinic_name')}
                            <span className="ml-1 font-normal text-muted-foreground">
                                ({t('demo-lead:optional')})
                            </span>
                        </Label>
                        <div className="relative">
                            <Building2 className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                id="demo-lead-clinic"
                                value={clinicName}
                                onChange={(e) => setClinicName(e.target.value)}
                                disabled={busy}
                                maxLength={150}
                                autoComplete="organization"
                                className="pl-9"
                                placeholder={t(
                                    'demo-lead:placeholders.clinic_name',
                                )}
                            />
                        </div>
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="demo-lead-phone">
                            {t('demo-lead:fields.phone')}
                        </Label>
                        <div className="relative">
                            <Phone className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                id="demo-lead-phone"
                                type="tel"
                                value={phone}
                                onChange={(e) => setPhone(e.target.value)}
                                disabled={busy}
                                maxLength={20}
                                autoComplete="tel"
                                className="pl-9"
                                placeholder={t('demo-lead:placeholders.phone')}
                            />
                        </div>
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="demo-lead-email">
                            {t('demo-lead:fields.email')}
                        </Label>
                        <div className="relative">
                            <Mail className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                id="demo-lead-email"
                                type="email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                disabled={busy}
                                maxLength={150}
                                autoComplete="email"
                                className="pl-9"
                                placeholder={t('demo-lead:placeholders.email')}
                            />
                        </div>
                    </div>

                    <p className="text-xs text-muted-foreground">
                        {t('demo-lead:hint_one_of')}
                    </p>

                    {error ? (
                        <p className="text-sm text-destructive">{error}</p>
                    ) : null}
                </div>

                <DialogFooter className="flex-col gap-2 sm:flex-row sm:justify-between">
                    <Button
                        type="button"
                        variant="ghost"
                        disabled={busy}
                        onClick={() => void submit(true)}
                        className="cursor-pointer order-2 sm:order-1"
                    >
                        {t('demo-lead:actions.skip')}
                    </Button>
                    <Button
                        type="button"
                        disabled={busy}
                        onClick={() => void submit(false)}
                        className="cursor-pointer order-1 w-full sm:order-2 sm:w-auto"
                    >
                        {busy
                            ? t('common:actions.loading')
                            : t('demo-lead:actions.save')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
