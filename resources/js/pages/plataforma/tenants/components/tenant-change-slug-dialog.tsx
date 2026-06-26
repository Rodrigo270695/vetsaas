import { router } from '@inertiajs/react';
import { Globe, Loader2 } from 'lucide-react';
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { Trans, useTranslation } from 'react-i18next';
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
import { useTenancy } from '@/lib/tenancy-url';
import tenants from '@/routes/plataforma/tenants';
import type { Tenant } from '../types';

const SLUG_REGEX = /^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/;

export type TenantChangeSlugDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    tenant: Tenant | null;
};

/**
 * Diálogo para cambiar el subdominio de un tenant activo en producción.
 * Dispara POST /tenants/{id}/change-slug y el backend notifica por correo y WhatsApp.
 */
export function TenantChangeSlugDialog({
    open,
    onOpenChange,
    tenant,
}: TenantChangeSlugDialogProps) {
    const { t } = useTranslation(['tenants', 'common']);
    const tenancy = useTenancy();
    const [slug, setSlug] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const rootDomain = tenancy.root_domain;

    useEffect(() => {
        if (open) {
            setSlug('');
            setError(null);
        }
    }, [open, tenant?.id]);

    const previewHost = useMemo(() => {
        const value = slug.trim().toLowerCase();
        if (!value) {
            return `nuevo-subdominio.${rootDomain}`;
        }

        return `${value}.${rootDomain}`;
    }, [slug, rootDomain]);

    const canSubmit =
        slug.trim().length >= 3 &&
        SLUG_REGEX.test(slug.trim().toLowerCase()) &&
        slug.trim().toLowerCase() !== tenant?.slug;

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!tenant || !canSubmit) return;

        setProcessing(true);
        setError(null);

        router.post(
            tenants.changeSlug(tenant.id).url,
            { slug: slug.trim().toLowerCase() },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => onOpenChange(false),
                onError: (errors) => {
                    const specific =
                        (errors as Record<string, string | undefined>)?.slug ??
                        null;
                    setError(specific ?? t('common:feedback.save_error'));
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={onSubmit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <div className="flex size-11 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <Globe
                                className="size-5"
                                strokeWidth={2.5}
                                aria-hidden="true"
                            />
                        </div>
                        <DialogTitle className="pt-2 text-base">
                            {t('tenants:change_slug.title')}
                        </DialogTitle>
                        <DialogDescription className="text-sm" asChild>
                            <p>
                                <Trans
                                    ns="tenants"
                                    i18nKey="change_slug.description"
                                    values={{
                                        name:
                                            tenant?.razon_social ??
                                            tenant?.slug ??
                                            '',
                                        current: tenant?.slug ?? '',
                                    }}
                                    components={{
                                        strong: (
                                            <strong className="text-foreground" />
                                        ),
                                        code: (
                                            <code className="rounded bg-muted px-1 py-0.5 font-mono text-xs" />
                                        ),
                                    }}
                                />
                            </p>
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="tenant-new-slug">
                            {t('tenants:change_slug.slug_label')}
                        </Label>
                        <Input
                            id="tenant-new-slug"
                            value={slug}
                            onChange={(e) =>
                                setSlug(
                                    e.target.value
                                        .toLowerCase()
                                        .replace(/\s+/g, '-'),
                                )
                            }
                            placeholder={t(
                                'tenants:change_slug.slug_placeholder',
                            )}
                            autoComplete="off"
                            autoFocus
                            className="font-mono"
                        />
                        <p className="text-xs text-muted-foreground">
                            {t('tenants:change_slug.preview', {
                                host: previewHost,
                            })}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {t('tenants:change_slug.notify_hint')}
                        </p>
                        {error && (
                            <p className="text-xs text-destructive">{error}</p>
                        )}
                    </div>

                    <DialogFooter>
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
                            disabled={processing || !canSubmit}
                            className="cursor-pointer gap-2"
                        >
                            {processing && (
                                <Loader2
                                    className="size-4 animate-spin"
                                    aria-hidden="true"
                                />
                            )}
                            {processing
                                ? t('tenants:change_slug.loading')
                                : t('tenants:change_slug.confirm')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
