import { router } from '@inertiajs/react';
import { Loader2, Lock, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
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
import tenants from '@/routes/plataforma/tenants';
import type { Tenant } from '../types';

export type TenantDeleteDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    tenant: Tenant | null;
};

/**
 * Diálogo de confirmación para eliminar un tenant.
 *
 * Reglas:
 *   - Si el tenant está `active` mostramos un lock (el backend lo
 *     rechaza también; este es defensa en UI).
 *   - El delete es **soft**: marca el tenant como `cancelled` y le
 *     pone `deleted_at`. El schema físico NO se destruye desde el CRUD.
 */
export function TenantDeleteDialog({
    open,
    onOpenChange,
    tenant,
}: TenantDeleteDialogProps) {
    const { t } = useTranslation(['tenants', 'common']);
    const [processing, setProcessing] = useState(false);

    const isProtected = tenant !== null && tenant.estado === 'active';

    const onConfirm = () => {
        if (!tenant || isProtected) {
            return;
        }
        setProcessing(true);
        router.delete(tenants.destroy(tenant.id).url, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div
                        className={
                            isProtected
                                ? 'flex size-11 items-center justify-center rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                : 'flex size-11 items-center justify-center rounded-full bg-destructive/10 text-destructive'
                        }
                    >
                        {isProtected ? (
                            <Lock
                                className="size-5"
                                strokeWidth={2.5}
                                aria-hidden="true"
                            />
                        ) : (
                            <TriangleAlert
                                className="size-5"
                                strokeWidth={2.5}
                                aria-hidden="true"
                            />
                        )}
                    </div>
                    <DialogTitle className="pt-2 text-base">
                        {t('tenants:delete.title')}
                    </DialogTitle>
                    <DialogDescription className="text-sm" asChild>
                        {isProtected ? (
                            <p>{t('tenants:delete.active_blocked')}</p>
                        ) : (
                            <p>
                                <Trans
                                    ns="tenants"
                                    i18nKey="delete.description"
                                    values={{
                                        name:
                                            tenant?.razon_social ??
                                            tenant?.slug ??
                                            '',
                                    }}
                                    components={{
                                        strong: (
                                            <strong className="text-foreground" />
                                        ),
                                    }}
                                />
                            </p>
                        )}
                    </DialogDescription>
                </DialogHeader>

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
                    {!isProtected && (
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={onConfirm}
                            disabled={processing}
                            className="cursor-pointer gap-2"
                        >
                            {processing && (
                                <Loader2
                                    className="size-4 animate-spin"
                                    aria-hidden="true"
                                />
                            )}
                            {processing
                                ? t('tenants:delete.loading')
                                : t('tenants:delete.confirm')}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
