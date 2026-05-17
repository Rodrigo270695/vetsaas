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
import usuarios from '@/routes/configuracion/usuarios';
import type { User } from '../types';

export type UserDeleteDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    user: User | null;
    /** ID del usuario autenticado: bloquea el self-delete. */
    currentUserId: string | null;
};

/**
 * Diálogo de confirmación para eliminar un usuario.
 *
 * - Si la cuenta a eliminar es del propio usuario o de un superadmin,
 *   muestra un mensaje informativo y NO renderiza el botón de eliminar.
 *   El backend también lo rechaza, esto es defensa en UI.
 */
export function UserDeleteDialog({
    open,
    onOpenChange,
    user,
    currentUserId,
}: UserDeleteDialogProps) {
    const { t } = useTranslation(['usuarios', 'common']);
    const [processing, setProcessing] = useState(false);

    const isSelf = user !== null && currentUserId === user.id;
    const isSuperadmin =
        user !== null && user.roles.some((r) => r.name === 'superadmin');
    const isProtected = isSelf || isSuperadmin;

    const onConfirm = () => {
        if (!user || isProtected) {
            return;
        }
        setProcessing(true);
        router.delete(usuarios.destroy(user.id).url, {
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
                        {t('usuarios:delete.title')}
                    </DialogTitle>
                    <DialogDescription className="text-sm" asChild>
                        {isProtected ? (
                            <p>
                                {isSelf
                                    ? t('usuarios:delete.self_blocked')
                                    : t('usuarios:delete.superadmin_blocked')}
                            </p>
                        ) : (
                            <p>
                                <Trans
                                    ns="usuarios"
                                    i18nKey="delete.description"
                                    values={{ name: user?.name ?? '' }}
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
                                ? t('usuarios:delete.loading')
                                : t('usuarios:delete.confirm')}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
