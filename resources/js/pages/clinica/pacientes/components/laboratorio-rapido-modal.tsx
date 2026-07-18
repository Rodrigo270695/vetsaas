import { useForm } from '@inertiajs/react';
import { FlaskConical, Loader2, Save } from 'lucide-react';
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

export type ConsultaLabOpcion = {
    id: string;
    label: string;
    abierta: boolean;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    storeUrl: string;
    consultas: readonly ConsultaLabOpcion[];
};

function toDateInputValue(d: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function defaultConsultaId(consultas: readonly ConsultaLabOpcion[]): string {
    const abierta = consultas.find((c) => c.abierta);

    return abierta?.id ?? consultas[0]?.id ?? '';
}

export function LaboratorioRapidoModal({
    open,
    onOpenChange,
    storeUrl,
    consultas,
}: Props) {
    const { t } = useTranslation(['pacientes', 'common']);
    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm({
            consulta_id: '' as string,
            nombre_examen: '',
            fecha: toDateInputValue(new Date()),
            descripcion: '',
            documento: null as File | null,
        });

    useEffect(() => {
        if (!open) {
            return;
        }

        clearErrors();
        setData({
            consulta_id: defaultConsultaId(consultas),
            nombre_examen: '',
            fecha: toDateInputValue(new Date()),
            descripcion: '',
            documento: null,
        });
    }, [open, consultas, clearErrors, setData]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(storeUrl, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={t('historial.lab_rapido_title')}
            description={t('historial.lab_rapido_description')}
            size="md"
            onSubmit={submit}
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        className="cursor-pointer"
                        disabled={processing}
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="submit"
                        className="cursor-pointer gap-2"
                        disabled={processing}
                    >
                        {processing ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <Save className="size-4" strokeWidth={2.25} />
                        )}
                        {t('common:actions.save')}
                    </Button>
                </>
            }
        >
            <div className="grid gap-4">
                <div className="flex items-start gap-3 rounded-xl border border-sky-500/20 bg-sky-500/8 px-3 py-2.5 text-sm text-sky-950 dark:text-sky-100">
                    <FlaskConical className="mt-0.5 size-4 shrink-0" />
                    <p>{t('historial.lab_rapido_hint')}</p>
                </div>

                {consultas.length > 0 ? (
                    <FormField
                        id="lab_rapido_consulta"
                        label={t('historial.lab_rapido_consulta')}
                        error={errors.consulta_id}
                    >
                        <Select
                            value={data.consulta_id || '__none__'}
                            onValueChange={(v) =>
                                setData(
                                    'consulta_id',
                                    v === '__none__' ? '' : v,
                                )
                            }
                        >
                            <SelectTrigger id="lab_rapido_consulta" className="w-full">
                                <SelectValue
                                    placeholder={t(
                                        'historial.lab_rapido_consulta_placeholder',
                                    )}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none__">
                                    {t('historial.lab_rapido_sin_consulta')}
                                </SelectItem>
                                {consultas.map((c) => (
                                    <SelectItem key={c.id} value={c.id}>
                                        {c.label}
                                        {c.abierta
                                            ? ` · ${t('historial.badge_abierta')}`
                                            : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                ) : null}

                <FormField
                    id="lab_rapido_nombre"
                    label={t('historial.lab_rapido_nombre')}
                    required
                    error={errors.nombre_examen}
                >
                    <Input
                        id="lab_rapido_nombre"
                        value={data.nombre_examen}
                        onChange={(e) =>
                            setData('nombre_examen', e.target.value)
                        }
                        placeholder={t('historial.lab_rapido_nombre_ph')}
                        required
                    />
                </FormField>

                <FormField
                    id="lab_rapido_fecha"
                    label={t('historial.lab_rapido_fecha')}
                    required
                    error={errors.fecha}
                >
                    <Input
                        id="lab_rapido_fecha"
                        type="date"
                        value={data.fecha}
                        onChange={(e) => setData('fecha', e.target.value)}
                        required
                    />
                </FormField>

                <FormField
                    id="lab_rapido_doc"
                    label={t('historial.lab_rapido_documento')}
                    required
                    error={errors.documento}
                >
                    <Input
                        id="lab_rapido_doc"
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*"
                        onChange={(e) =>
                            setData('documento', e.target.files?.[0] ?? null)
                        }
                        required
                    />
                    <p className="mt-1 text-xs text-muted-foreground">
                        {t('historial.lab_rapido_documento_hint')}
                    </p>
                </FormField>

                <FormField
                    id="lab_rapido_desc"
                    label={t('historial.lab_rapido_descripcion')}
                    error={errors.descripcion}
                >
                    <Textarea
                        id="lab_rapido_desc"
                        value={data.descripcion}
                        onChange={(e) => setData('descripcion', e.target.value)}
                        rows={3}
                        placeholder={t('historial.lab_rapido_descripcion_ph')}
                    />
                </FormField>
            </div>
        </FormModal>
    );
}
