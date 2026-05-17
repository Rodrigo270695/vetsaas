import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect } from 'react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import caja from '@/routes/caja';
import type { QueryParams } from '@/wayfinder';
import type { SedeOpcion } from '../types';

type SesionAbrirModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    sedes: readonly SedeOpcion[];
    listQuery: QueryParams;
};

type FormData = {
    sede_id: string;
    moneda: string;
    saldo_apertura: string;
    notas: string;
};

const empty = (sedeId: string): FormData => ({
    sede_id: sedeId,
    moneda: 'PEN',
    saldo_apertura: '0',
    notas: '',
});

export function SesionAbrirModal({ open, onOpenChange, sedes, listQuery }: SesionAbrirModalProps) {
    const { t } = useTranslation('caja');
    const firstSede = sedes[0]?.id ?? '';

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<FormData>(empty(firstSede));

    useEffect(() => {
        if (!open) {
            return;
        }

        reset();
        clearErrors();
        setData(empty(sedes[0]?.id ?? ''));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, sedes[0]?.id]);

    const onSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const actionUrl = caja.sesiones.store.url({ query: listQuery });
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
            open={open}
            onOpenChange={onOpenChange}
            title={t('sesiones.dialog_abrir.title')}
            description={t('sesiones.dialog_abrir.description')}
            onSubmit={onSubmit}
            footer={
                <>
                    <Button type="button" variant="outline" className="cursor-pointer" onClick={() => onOpenChange(false)}>
                        {t('sesiones.dialog_abrir.cancel')}
                    </Button>
                    <Button type="submit" disabled={processing || sedes.length === 0} className="cursor-pointer gap-2">
                        {processing ? <Loader2 className="size-4 animate-spin" aria-hidden /> : null}
                        {t('sesiones.dialog_abrir.submit')}
                    </Button>
                </>
            }
        >
            <div className="flex w-full min-w-0 flex-col gap-4">
                <FormField id="abrir-sede" label={t('sesiones.fields.sede')} error={errors.sede_id}>
                    <Select
                        value={data.sede_id || firstSede}
                        onValueChange={(v) => setData('sede_id', v)}
                        disabled={sedes.length === 0}
                    >
                        <SelectTrigger className="h-9 w-full min-w-0 cursor-pointer justify-between">
                            <SelectValue placeholder={t('sesiones.filter_sede_label')} />
                        </SelectTrigger>
                        <SelectContent>
                            {sedes.map((s) => (
                                <SelectItem key={s.id} value={s.id}>
                                    <span>
                                        {s.nombre}
                                        <span className="ml-2 font-mono text-xs text-muted-foreground">{s.codigo}</span>
                                    </span>
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>

                <FormField id="abrir-moneda" label={t('sesiones.fields.moneda')} error={errors.moneda}>
                    <Select value={data.moneda} onValueChange={(v) => setData('moneda', v)}>
                        <SelectTrigger className="h-9 w-full min-w-0 cursor-pointer justify-between">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="PEN">PEN</SelectItem>
                            <SelectItem value="USD">USD</SelectItem>
                        </SelectContent>
                    </Select>
                </FormField>

                <FormField id="abrir-saldo" label={t('sesiones.fields.saldo_apertura')} error={errors.saldo_apertura}>
                    <Input
                        type="number"
                        inputMode="decimal"
                        min={0}
                        step="0.01"
                        value={data.saldo_apertura}
                        onChange={(ev) => setData('saldo_apertura', ev.target.value)}
                        className="tabular-nums"
                    />
                </FormField>

                <FormField id="abrir-notas" label={t('sesiones.fields.notas_apertura')} error={errors.notas}>
                    <Textarea value={data.notas} onChange={(ev) => setData('notas', ev.target.value)} rows={3} className="resize-y" />
                </FormField>
            </div>
        </FormModal>
    );
}
