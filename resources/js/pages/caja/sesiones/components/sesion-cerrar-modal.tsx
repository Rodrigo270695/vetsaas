import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect } from 'react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import caja from '@/routes/caja';
import type { QueryParams } from '@/wayfinder';
import type { CajaSesionRow } from '../types';

type SesionCerrarModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    sesion: CajaSesionRow | null;
    listQuery: QueryParams;
};

type FormData = {
    saldo_cierre_efectivo: string;
    notas: string;
};

const empty: FormData = {
    saldo_cierre_efectivo: '',
    notas: '',
};

export function SesionCerrarModal({ open, onOpenChange, sesion, listQuery }: SesionCerrarModalProps) {
    const { t } = useTranslation('caja');
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<FormData>(empty);

    useEffect(() => {
        if (!open || !sesion) {
            return;
        }

        reset();
        clearErrors();
        setData(empty);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, sesion?.id]);

    const onSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        if (!sesion) {
            return;
        }

        const actionUrl = caja.sesiones.cerrar.url({ caja_sesion: sesion.id }, { query: listQuery });
        post(actionUrl, {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                reset();
                clearErrors();
            },
        });
    };

    return (
        <FormModal
            open={open && sesion !== null}
            onOpenChange={onOpenChange}
            title={t('sesiones.dialog_cerrar.title')}
            description={t('sesiones.dialog_cerrar.description')}
            onSubmit={onSubmit}
            footer={
                <>
                    <Button type="button" variant="outline" className="cursor-pointer" onClick={() => onOpenChange(false)}>
                        {t('sesiones.dialog_cerrar.cancel')}
                    </Button>
                    <Button type="submit" disabled={processing || !sesion} className="cursor-pointer gap-2">
                        {processing ? <Loader2 className="size-4 animate-spin" aria-hidden /> : null}
                        {t('sesiones.dialog_cerrar.submit')}
                    </Button>
                </>
            }
        >
            <div className="flex w-full min-w-0 flex-col gap-4">
                <FormField
                    id="cerrar-saldo"
                    label={t('sesiones.fields.saldo_cierre')}
                    error={errors.saldo_cierre_efectivo}
                >
                    <Input
                        type="number"
                        inputMode="decimal"
                        min={0}
                        step="0.01"
                        value={data.saldo_cierre_efectivo}
                        onChange={(ev) => setData('saldo_cierre_efectivo', ev.target.value)}
                        className="tabular-nums"
                        autoFocus
                    />
                </FormField>

                <FormField id="cerrar-notas" label={t('sesiones.fields.notas_cierre')} error={errors.notas}>
                    <Textarea value={data.notas} onChange={(ev) => setData('notas', ev.target.value)} rows={3} className="resize-y" />
                </FormField>
            </div>
        </FormModal>
    );
}
