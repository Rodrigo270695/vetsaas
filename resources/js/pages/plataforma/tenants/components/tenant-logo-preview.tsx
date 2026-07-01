import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { VETSAAS_DEFAULT_LOGO } from '@/lib/brand';
import { cn } from '@/lib/utils';

export type TenantLogoPreviewProps = {
    logoUrl: string;
    tenantName: string;
    hasCustomLogo?: boolean;
    className?: string;
};

export function TenantLogoPreview({
    logoUrl,
    tenantName,
    hasCustomLogo = true,
    className,
}: TenantLogoPreviewProps) {
    const { t } = useTranslation('tenants');
    const [open, setOpen] = useState(false);

    const src = logoUrl || VETSAAS_DEFAULT_LOGO;
    const canPreview =
        hasCustomLogo &&
        src !== VETSAAS_DEFAULT_LOGO &&
        !src.endsWith('/logo.png');

    if (!canPreview) {
        return (
            <img
                src={src}
                alt=""
                className={cn(
                    'size-8 shrink-0 rounded-full border border-border/60 bg-background object-contain p-0.5',
                    className,
                )}
            />
        );
    }

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className={cn(
                    'size-8 shrink-0 cursor-zoom-in rounded-full border border-border/60 bg-background p-0.5 outline-none transition hover:border-primary/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                    className,
                )}
                aria-label={t('row.logo_preview', { name: tenantName })}
            >
                <img
                    src={src}
                    alt=""
                    className="size-full rounded-full object-contain"
                />
            </button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-w-lg sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>{tenantName}</DialogTitle>
                        <DialogDescription className="sr-only">
                            {t('row.logo_preview', { name: tenantName })}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex items-center justify-center rounded-lg border border-border/60 bg-muted/30 p-6">
                        <img
                            src={src}
                            alt={tenantName}
                            className="max-h-[min(60vh,420px)] w-full object-contain"
                        />
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
