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
import roles from '@/routes/configuracion/roles';
import type { Role } from '../types';

export type RoleDeleteDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    role: Role | null;
};

/**
 * Diálogo de confirmación para eliminar un rol.
 *
 * - Si el rol es del sistema, muestra un mensaje informativo y bloquea
 *   el botón "Sí, eliminar". El backend también lo rechaza, esto es
 *   solo una capa extra para evitar la llamada.
 * - Hace un DELETE a `roles.destroy` con `router.delete`.
 */
export function RoleDeleteDialog({
    open,
    onOpenChange,
    role,
}: RoleDeleteDialogProps) {
    const { t } = useTranslation(['roles', 'common']);
    const [processing, setProcessing] = useState(false);

    const isSystem = role?.is_system === true;

    const onConfirm = () => {
        if (!role || isSystem) {
            return;
        }
        setProcessing(true);
        router.delete(roles.destroy(role.id).url, {
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
                            isSystem
                                ? 'flex size-11 items-center justify-center rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                : 'flex size-11 items-center justify-center rounded-full bg-destructive/10 text-destructive'
                        }
                    >
                        {isSystem ? (
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
                        {t('roles:delete.title')}
                    </DialogTitle>
                    <DialogDescription className="text-sm" asChild>
                        {isSystem ? (
                            <p>{t('roles:delete.system_blocked')}</p>
                        ) : (
                            <p>
                                <Trans
                                    ns="roles"
                                    i18nKey="delete.description"
                                    values={{ name: role?.name ?? '' }}
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
                    {!isSystem && (
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
                                ? t('roles:delete.loading')
                                : t('roles:delete.confirm')}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
