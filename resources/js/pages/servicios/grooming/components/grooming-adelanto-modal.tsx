import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
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
import type { GroomingTurnoRow } from '../types';

const METODOS = ['efectivo', 'yape', 'plin', 'tarjeta', 'transferencia'] as const;

export type GroomingAdelantoModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    turno: GroomingTurnoRow | null;
};

type FormShape = {
    monto: string;
    metodo_pago: (typeof METODOS)[number];
    monto_recibido: string;
    notas: string;
};

export function GroomingAdelantoModal({ open, onOpenChange, turno }: GroomingAdelantoModalProps) {
    const { t } = useTranslation(['grooming', 'common', 'caja']);

    const { data, setData, post, processing, errors, clearErrors, reset } = useForm<FormShape>({
        monto: '',
        metodo_pago: 'efectivo',
        monto_recibido: '',
        notas: '',
    });

    useEffect(() => {
        if (!open) {
            return;
        }
        clearErrors();
        reset();
        setData({
            monto: '',
            metodo_pago: 'efectivo',
            monto_recibido: '',
            notas: '',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, turno?.id]);

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!turno) {
            return;
        }

        post(`/caja/ventas/adelanto-grooming/${turno.id}`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={t('adelanto.title')}
            description={
                turno
                    ? t('adelanto.description', { paciente: turno.paciente?.nombre ?? '—' })
                    : undefined
            }
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
                    <Button type="submit" disabled={processing || !turno} className="gap-2">
                        {processing ? <Loader2 className="size-4 animate-spin" aria-hidden /> : null}
                        {t('adelanto.submit')}
                    </Button>
                </>
            }
        >
            <div className="grid gap-4">
                <FormField
                    id="ga-monto"
                    label={t('adelanto.monto')}
                    required
                    error={errors.monto as string | undefined}
                >
                    <Input
                        id="ga-monto"
                        type="number"
                        min={0.01}
                        step="0.01"
                        className="h-10"
                        value={data.monto}
                        onChange={(e) => setData('monto', e.target.value)}
                        aria-invalid={Boolean(errors.monto)}
                        disabled={processing}
                    />
                </FormField>

                <FormField
                    id="ga-metodo"
                    label={t('adelanto.metodo')}
                    required
                    error={errors.metodo_pago as string | undefined}
                >
                    <Select
                        value={data.metodo_pago}
                        onValueChange={(v) =>
                            setData('metodo_pago', v as FormShape['metodo_pago'])
                        }
                        disabled={processing}
                    >
                        <SelectTrigger id="ga-metodo" className="h-10" aria-invalid={Boolean(errors.metodo_pago)}>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {METODOS.map((m) => (
                                <SelectItem key={m} value={m}>
                                    {t(`caja:ventas.create.mp_${m}`, { defaultValue: m })}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>

                {data.metodo_pago === 'efectivo' ? (
                    <FormField
                        id="ga-recibido"
                        label={t('adelanto.monto_recibido')}
                        error={errors.monto_recibido as string | undefined}
                    >
                        <Input
                            id="ga-recibido"
                            type="number"
                            min={0}
                            step="0.01"
                            className="h-10"
                            value={data.monto_recibido}
                            onChange={(e) => setData('monto_recibido', e.target.value)}
                            placeholder={t('adelanto.monto_recibido_hint')}
                            aria-invalid={Boolean(errors.monto_recibido)}
                            disabled={processing}
                        />
                    </FormField>
                ) : null}

                <FormField id="ga-notas" label={t('adelanto.notas')} error={errors.notas as string | undefined}>
                    <Textarea
                        id="ga-notas"
                        rows={2}
                        className="resize-y text-sm"
                        value={data.notas}
                        onChange={(e) => setData('notas', e.target.value)}
                        disabled={processing}
                    />
                </FormField>

                {(errors.caja_sesion_id || errors.grooming_turno_id) && (
                    <p className="text-sm text-destructive">
                        {(errors.caja_sesion_id as string | undefined) ??
                            (errors.grooming_turno_id as string | undefined)}
                    </p>
                )}

                <p className="text-xs text-muted-foreground">{t('adelanto.hint')}</p>
            </div>
        </FormModal>
    );
}
