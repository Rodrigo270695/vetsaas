import { useForm } from '@inertiajs/react';
import { FileUp, Loader2, Sparkles } from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { cuerpoTieneTexto, PlantillaCuerpoEditor } from './plantilla-cuerpo-editor';

export type PlantillaAutorizacion = {
    id: string;
    nombre: string;
    descripcion: string | null;
    cuerpo: string;
    cuerpo_preview?: string;
    activo: boolean;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    plantilla: PlantillaAutorizacion | null;
    cuerpoDefault: string;
    clinicLogoUrl?: string | null;
    iaDisponible?: boolean;
};

const IA_STEPS = [
    'Leyendo el documento…',
    'Detectando datos del paciente y el titular…',
    'Insertando variables {{paciente}}, {{motivo}}…',
    'Armando el texto de la plantilla…',
] as const;

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export function DocumentoAutorizacionPlantillaFormModal({
    open,
    onOpenChange,
    plantilla,
    cuerpoDefault,
    clinicLogoUrl,
    iaDisponible = false,
}: Props) {
    const { t } = useTranslation(['documentos-autorizacion', 'common']);
    const isEdit = plantilla !== null;
    const fileRef = useRef<HTMLInputElement>(null);
    const [iaBusy, setIaBusy] = useState(false);
    const [iaStep, setIaStep] = useState(0);
    const [iaError, setIaError] = useState<string | null>(null);
    const [iaReset, setIaReset] = useState(0);

    const form = useForm({
        nombre: '',
        descripcion: '',
        cuerpo: cuerpoDefault,
        activo: true,
    });

    useEffect(() => {
        if (!open) {
            setIaBusy(false);
            setIaError(null);
            return;
        }
        form.setData({
            nombre: plantilla?.nombre ?? '',
            descripcion: plantilla?.descripcion ?? '',
            cuerpo: plantilla?.cuerpo ?? cuerpoDefault,
            activo: plantilla?.activo ?? true,
        });
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, plantilla?.id, cuerpoDefault]);

    useEffect(() => {
        if (!iaBusy) {
            setIaStep(0);
            return;
        }
        const id = window.setInterval(() => {
            setIaStep((n) => (n + 1) % IA_STEPS.length);
        }, 2200);
        return () => window.clearInterval(id);
    }, [iaBusy]);

    const canSubmit =
        form.data.nombre.trim().length > 0 && cuerpoTieneTexto(form.data.cuerpo) && !form.processing && !iaBusy;

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!canSubmit) {
            return;
        }
        const opts = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };
        if (isEdit && plantilla) {
            form.put(`/configuracion/documentos-autorizacion/${plantilla.id}`, opts);
            return;
        }
        form.post('/configuracion/documentos-autorizacion', opts);
    };

    const runIa = async (file: File) => {
        setIaError(null);
        setIaBusy(true);
        const body = new FormData();
        body.append('archivo', file);
        try {
            const res = await fetch('/configuracion/documentos-autorizacion/desde-ia', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body,
            });
            const json = (await res.json().catch(() => null)) as
                | { nombre?: string; descripcion?: string | null; cuerpo?: string; message?: string }
                | null;
            if (!res.ok) {
                throw new Error(json?.message || t('ia_error'));
            }
            form.setData({
                ...form.data,
                nombre: json?.nombre?.trim() || form.data.nombre,
                descripcion: json?.descripcion ?? form.data.descripcion,
                cuerpo: json?.cuerpo || form.data.cuerpo,
            });
            setIaReset((n) => n + 1);
        } catch (err) {
            setIaError(err instanceof Error ? err.message : t('ia_error'));
        } finally {
            setIaBusy(false);
            if (fileRef.current) {
                fileRef.current.value = '';
            }
        }
    };

    return (
        <FormModal
            open={open}
            onOpenChange={(next) => {
                if (iaBusy && !next) {
                    return;
                }
                onOpenChange(next);
            }}
            title={isEdit ? t('title_edit') : t('title_create')}
            size="xl"
            onSubmit={onSubmit}
            blockEscape={iaBusy}
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={iaBusy}
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" disabled={!canSubmit} className="gap-2">
                        {form.processing ? <Loader2 className="size-4 animate-spin" /> : null}
                        {isEdit ? t('submit_edit') : t('submit_create')}
                    </Button>
                </>
            }
        >
            <div className="relative flex flex-col gap-4">
                {iaDisponible ? (
                    <div className="rounded-xl border border-primary/20 bg-primary/6 px-3 py-2.5">
                        <input
                            ref={fileRef}
                            type="file"
                            accept="application/pdf,image/jpeg,image/png,image/webp,image/gif"
                            className="sr-only"
                            disabled={iaBusy}
                            onChange={(e) => {
                                const file = e.target.files?.[0];
                                if (file) {
                                    void runIa(file);
                                }
                            }}
                        />
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <p className="text-sm text-foreground">{t('ia_hint')}</p>
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                disabled={iaBusy}
                                className="cursor-pointer gap-1.5"
                                onClick={() => fileRef.current?.click()}
                            >
                                <Sparkles className="size-3.5" />
                                {t('ia_button')}
                            </Button>
                        </div>
                        {iaError ? <p className="mt-1.5 text-sm text-destructive">{iaError}</p> : null}
                    </div>
                ) : null}

                <FormField id="tpl-nombre" label={t('nombre')} required error={form.errors.nombre}>
                    <Input
                        id="tpl-nombre"
                        value={form.data.nombre}
                        disabled={iaBusy}
                        onChange={(e) => form.setData('nombre', e.target.value)}
                    />
                </FormField>
                <FormField id="tpl-desc" label={t('descripcion_field')} error={form.errors.descripcion}>
                    <Input
                        id="tpl-desc"
                        value={form.data.descripcion}
                        disabled={iaBusy}
                        onChange={(e) => form.setData('descripcion', e.target.value)}
                    />
                </FormField>
                <FormField
                    id="tpl-cuerpo"
                    label={t('cuerpo')}
                    required
                    error={form.errors.cuerpo}
                    hint={t('cuerpo_hint')}
                >
                    {open ? (
                        <PlantillaCuerpoEditor
                            value={form.data.cuerpo}
                            onChange={(html) => form.setData('cuerpo', html)}
                            resetKey={`${plantilla?.id ?? 'new'}:${open ? '1' : '0'}:${iaReset}`}
                            logoUrl={clinicLogoUrl}
                            disabled={iaBusy}
                        />
                    ) : null}
                </FormField>
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                        checked={form.data.activo}
                        disabled={iaBusy}
                        onCheckedChange={(c) => form.setData('activo', c === true)}
                    />
                    {t('activo')}
                </label>

                {iaBusy ? (
                    <div
                        className="absolute inset-0 z-20 flex items-center justify-center rounded-lg bg-background/80 backdrop-blur-[3px]"
                        role="status"
                        aria-live="polite"
                    >
                        <div className="mx-4 flex w-full max-w-sm flex-col items-center gap-4 rounded-2xl border border-primary/20 bg-card px-6 py-7 text-center shadow-lg">
                            <div className="relative flex size-16 items-center justify-center">
                                <span className="absolute inset-0 animate-ping rounded-full bg-primary/20" />
                                <span className="absolute inset-1 animate-spin rounded-full border-2 border-primary/20 border-t-primary" />
                                <Sparkles className="relative size-7 text-primary" />
                            </div>
                            <div>
                                <p className="font-semibold text-foreground">{t('ia_working')}</p>
                                <p className={cn('mt-1 text-sm text-muted-foreground transition-opacity')}>
                                    {IA_STEPS[iaStep]}
                                </p>
                            </div>
                            <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <FileUp className="size-3.5" />
                                {t('ia_wait')}
                            </p>
                        </div>
                    </div>
                ) : null}
            </div>
        </FormModal>
    );
}
