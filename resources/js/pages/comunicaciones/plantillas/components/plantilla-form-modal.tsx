import { useForm } from '@inertiajs/react';
import { Loader2, RotateCcw } from 'lucide-react';
import { useEffect, useMemo, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { PlantillaItem } from '../types';

type FormData = {
    cuerpo: string;
    activo: boolean;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    plantilla: PlantillaItem | null;
};

export function PlantillaFormModal({ open, onOpenChange, plantilla }: Props) {
    const { t } = useTranslation(['comunicaciones', 'common']);
    const { data, setData, put, post, processing, errors, reset, clearErrors } =
        useForm<FormData>({
            cuerpo: '',
            activo: true,
        });

    useEffect(() => {
        if (!open || !plantilla) {
            return;
        }

        clearErrors();
        setData({
            cuerpo: plantilla.cuerpo,
            activo: plantilla.activo,
        });
    }, [open, plantilla, clearErrors, setData]);

    const preview = useMemo(() => {
        if (!plantilla) {
            return '';
        }

        // Preview simple en cliente con las mismas variables de ejemplo del backend.
        const sample: Record<string, string> = {
            propietario: 'María Pérez',
            mascota: 'Firulais',
            clinica: 'Clínica Demo',
            motivo_linea: '📋 Motivo: *Control*\n',
            fecha: '24/08/2026',
            hora: '10:30',
            vacuna: 'Antirrábica',
            servicio: 'Baño completo',
            adelanto_linea: '\n💵 Adelanto recibido: *PEN 30.00*',
            fecha_ingreso: '24/08/2026',
            hora_ingreso: '09:00',
            egreso_linea: '\n📅 Egreso previsto: *26/08/2026*',
            notas: 'Comió bien y descansó.',
        };

        return data.cuerpo.replace(/\{\{(\w+)\}\}/g, (_, key: string) => {
            return sample[key] ?? `{{${key}}}`;
        });
    }, [data.cuerpo, plantilla]);

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        if (!plantilla) {
            return;
        }

        put(`/comunicaciones/plantillas/${plantilla.id}`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    const onRestore = () => {
        if (!plantilla) {
            return;
        }

        post(`/comunicaciones/plantillas/${plantilla.id}/restaurar`, {
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
            title={
                plantilla
                    ? t(`plantillas.tipos.${plantilla.tipo}`, {
                          defaultValue: plantilla.tipo,
                      })
                    : t('plantillas.edit_title')
            }
            description={t('plantillas.edit_description')}
            size="lg"
            onSubmit={onSubmit}
            footer={
                <div className="flex w-full flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <Button
                        type="button"
                        variant="ghost"
                        disabled={processing || !plantilla}
                        onClick={onRestore}
                        className="justify-start"
                    >
                        <RotateCcw className="size-4" />
                        {t('plantillas.restore')}
                    </Button>
                    <div className="flex gap-2 sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={() => onOpenChange(false)}
                        >
                            {t('common:actions.cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : null}
                            {t('common:actions.save')}
                        </Button>
                    </div>
                </div>
            }
        >
            {plantilla ? (
                <div className="space-y-4">
                    <div className="flex flex-wrap gap-1.5">
                        {plantilla.variables.map((variable) => (
                            <button
                                key={variable}
                                type="button"
                                className="rounded-md border border-border bg-muted/40 px-2 py-0.5 font-mono text-xs text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                onClick={() =>
                                    setData(
                                        'cuerpo',
                                        `${data.cuerpo}{{${variable}}}`,
                                    )
                                }
                            >
                                {`{{${variable}}}`}
                            </button>
                        ))}
                    </div>

                    <FormField
                        label={t('plantillas.fields.cuerpo')}
                        error={errors.cuerpo}
                    >
                        <Textarea
                            value={data.cuerpo}
                            onChange={(e) => setData('cuerpo', e.target.value)}
                            rows={10}
                            className="min-h-40 font-mono text-sm"
                        />
                    </FormField>

                    <div className="flex items-start gap-2">
                        <Checkbox
                            id="plantilla-activo"
                            checked={data.activo}
                            onCheckedChange={(checked) =>
                                setData('activo', checked === true)
                            }
                        />
                        <div className="space-y-0.5">
                            <Label htmlFor="plantilla-activo">
                                {t('plantillas.fields.activo')}
                            </Label>
                            <p className="text-xs text-muted-foreground">
                                {t('plantillas.fields.activo_hint')}
                            </p>
                        </div>
                    </div>
                    {errors.activo ? (
                        <p className="text-sm text-destructive">
                            {errors.activo}
                        </p>
                    ) : null}

                    <div className="rounded-lg border border-border bg-muted/30 p-3">
                        <p className="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            {t('plantillas.preview')}
                        </p>
                        <pre className="whitespace-pre-wrap break-words font-sans text-sm leading-relaxed text-foreground">
                            {preview}
                        </pre>
                    </div>
                </div>
            ) : null}
        </FormModal>
    );
}
