import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useMemo, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Combobox, type ComboboxOption } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import inventario from '@/routes/inventario';
import type { ProductoOptionMovimiento, SedeOptionMovimiento } from '../types';

type MovimientoFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    sedeOptions: SedeOptionMovimiento[];
    productoOptions: ProductoOptionMovimiento[];
    defaultSedeId: string;
};

type FormData = {
    producto_id: string | null;
    sede_id: string;
    tipo: string;
    cantidad: string;
    notas: string;
};

const empty: FormData = {
    producto_id: null,
    sede_id: '',
    tipo: 'entrada',
    cantidad: '',
    notas: '',
};

const TIPOS_FORM = ['entrada', 'salida', 'merma'] as const;

export function MovimientoFormModal({
    open,
    onOpenChange,
    sedeOptions,
    productoOptions,
    defaultSedeId,
}: MovimientoFormModalProps) {
    const { t } = useTranslation(['movimientos-inventario', 'common']);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<FormData>(empty);

    useEffect(() => {
        if (!open) {
            return;
        }
        reset();
        clearErrors();
        setData({
            ...empty,
            sede_id: defaultSedeId && defaultSedeId !== '' ? defaultSedeId : (sedeOptions[0]?.id ?? ''),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, defaultSedeId, sedeOptions]);

    const productoComboboxOptions = useMemo<readonly ComboboxOption[]>(
        () =>
            productoOptions.map((p) => ({
                value: p.id,
                label: p.sku ? `${p.nombre} (${p.sku})` : p.nombre,
            })),
        [productoOptions],
    );

    const sinOpciones = sedeOptions.length === 0 || productoOptions.length === 0;

    const onSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (sinOpciones) {
            return;
        }

        const onSuccess = () => {
            onOpenChange(false);
            reset();
            clearErrors();
        };

        post(inventario.movimientos.store.url(), {
            preserveScroll: true,
            onSuccess,
        });
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={t('modal.title')}
            description={t('modal.description')}
            size="md"
            onSubmit={onSubmit}
            footer={
                <>
                    <Button type="button" variant="outline" disabled={processing} onClick={() => onOpenChange(false)}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" disabled={processing || sinOpciones} className="gap-2">
                        {processing && <Loader2 className="size-4 animate-spin" />}
                        {t('modal.submit')}
                    </Button>
                </>
            }
        >
            <div className="grid gap-4">
                <FormField id="mov-sede" label={t('modal.sede')} error={errors.sede_id} required className="min-w-0">
                    <Select value={data.sede_id} onValueChange={(v) => setData('sede_id', v)} disabled={processing || sinOpciones}>
                        <SelectTrigger id="mov-sede" className="h-10 w-full min-w-0 cursor-pointer">
                            <SelectValue placeholder={t('filter_sede_placeholder')} />
                        </SelectTrigger>
                        <SelectContent>
                            {sedeOptions.map((s) => (
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

                <FormField
                    id="mov-producto"
                    label={t('modal.producto')}
                    error={errors.producto_id}
                    required
                    className="min-w-0"
                >
                    <Combobox
                        id="mov-producto"
                        options={productoComboboxOptions}
                        value={data.producto_id}
                        onChange={(v) => setData('producto_id', v)}
                        placeholder={t('modal.producto_placeholder')}
                        searchPlaceholder={t('modal.producto_search')}
                        emptyMessage={t('modal.producto_empty')}
                        disabled={processing || sinOpciones}
                        clearable={false}
                        aria-invalid={Boolean(errors.producto_id)}
                    />
                </FormField>

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField id="mov-tipo" label={t('modal.tipo')} error={errors.tipo} required className="min-w-0">
                        <Select value={data.tipo} onValueChange={(v) => setData('tipo', v)} disabled={processing || sinOpciones}>
                            <SelectTrigger id="mov-tipo" className="h-10 w-full min-w-0 cursor-pointer">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {TIPOS_FORM.map((tp) => (
                                    <SelectItem key={tp} value={tp}>
                                        {t(`tipos.${tp}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField id="mov-cantidad" label={t('modal.cantidad')} error={errors.cantidad} required hint={t('modal.cantidad_hint')} className="min-w-0">
                        <Input
                            id="mov-cantidad"
                            type="text"
                            inputMode="decimal"
                            autoComplete="off"
                            value={data.cantidad}
                            onChange={(e) => setData('cantidad', e.target.value)}
                            disabled={processing || sinOpciones}
                            className="h-10 w-full"
                            aria-invalid={Boolean(errors.cantidad)}
                        />
                    </FormField>
                </div>

                <FormField id="mov-notas" label={t('modal.notas')} error={errors.notas} className="min-w-0">
                    <Textarea
                        id="mov-notas"
                        rows={3}
                        value={data.notas}
                        onChange={(e) => setData('notas', e.target.value)}
                        disabled={processing || sinOpciones}
                        className="min-h-20 w-full resize-y text-sm"
                    />
                </FormField>
            </div>
        </FormModal>
    );
}
