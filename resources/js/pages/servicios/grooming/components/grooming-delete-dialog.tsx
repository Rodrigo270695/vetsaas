import { router } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormModal } from '@/components/forms';
import { Button } from '@/components/ui/button';
import servicios from '@/routes/servicios';
import type { GroomingTurnoRow } from '../types';

export type GroomingDeleteDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    turno: GroomingTurnoRow | null;
};

export function GroomingDeleteDialog({ open, onOpenChange, turno }: GroomingDeleteDialogProps) {
    const { t } = useTranslation(['grooming', 'common']);
    const [processing, setProcessing] = useState(false);

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!turno) {
            return;
        }

        setProcessing(true);
        router.delete(servicios.grooming.destroy({ grooming_turno: turno.id }).url, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={t('delete.title')}
            description={t('delete.description')}
            size="sm"
            onSubmit={onSubmit}
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" variant="destructive" disabled={processing} className="gap-2">
                        {processing && <Loader2 className="size-4 animate-spin" />}
                        {t('common:actions.delete')}
                    </Button>
                </>
            }
        >
            <p className="text-sm text-muted-foreground">
                {turno?.paciente?.nombre
                    ? `${turno.paciente.nombre} · ${turno.servicio_label ?? turno.servicio}`
                    : t('delete.description')}
            </p>
        </FormModal>
    );
}
