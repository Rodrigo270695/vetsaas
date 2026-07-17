import { useForm } from '@inertiajs/react';
import { CheckCircle2, ImagePlus, Loader2, MessageCircle, Play, XCircle } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { GroomingTurnoRow } from '../types';

export type GroomingEstadoTarget =
    | 'en_proceso'
    | 'completada'
    | 'cancelada'
    | 'no_asistio';

type Props = {
    turno: GroomingTurnoRow | null;
    target: GroomingEstadoTarget | null;
    onOpenChange: (open: boolean) => void;
};

function isPhotoFlow(target: GroomingEstadoTarget | null): boolean {
    return target === 'en_proceso' || target === 'completada';
}

function isAutoWhatsApp(target: GroomingEstadoTarget | null): boolean {
    return target === 'cancelada' || target === 'no_asistio';
}

export function GroomingEstadoModal({ turno, target, onOpenChange }: Props) {
    const { t } = useTranslation(['grooming', 'common']);
    const fileRef = useRef<HTMLInputElement>(null);
    const [previews, setPreviews] = useState<string[]>([]);
    const open = turno !== null && target !== null;
    const defaultPhone = turno?.paciente?.propietario?.telefono ?? '';

    const form = useForm<{
        estado: string;
        telefono: string;
        notificar_whatsapp: boolean;
        fotos: File[];
    }>({
        estado: target ?? '',
        telefono: defaultPhone,
        notificar_whatsapp: true,
        fotos: [],
    });

    useEffect(() => {
        if (!open || !target) {
            return;
        }

        form.setData({
            estado: target,
            telefono: defaultPhone,
            notificar_whatsapp: true,
            fotos: [],
        });
        form.clearErrors();
        setPreviews([]);
        if (fileRef.current) {
            fileRef.current.value = '';
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, target, turno?.id, defaultPhone]);

    useEffect(() => {
        return () => {
            for (const url of previews) {
                URL.revokeObjectURL(url);
            }
        };
    }, [previews]);

    const title = useMemo(() => {
        if (!target) {
            return '';
        }

        return t(`estado_flow.${target}.title`);
    }, [target, t]);

    const description = useMemo(() => {
        if (!target) {
            return '';
        }

        return t(`estado_flow.${target}.description`);
    }, [target, t]);

    const Icon = target === 'en_proceso'
        ? Play
        : target === 'completada'
          ? CheckCircle2
          : XCircle;

    const onFiles = (fileList: FileList | null) => {
        if (!fileList || fileList.length === 0) {
            return;
        }

        const next = [...form.data.fotos, ...Array.from(fileList)].slice(0, 8);
        for (const url of previews) {
            URL.revokeObjectURL(url);
        }
        setPreviews(next.map((f) => URL.createObjectURL(f)));
        form.setData('fotos', next);
    };

    const submit = () => {
        if (!turno || !target) {
            return;
        }

        form.transform((data) => ({
            estado: target,
            telefono: data.telefono,
            notificar_whatsapp: isAutoWhatsApp(target) ? true : data.notificar_whatsapp,
            fotos: data.fotos,
        }));

        form.post(`/servicios/grooming/${turno.id}/estado`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <div className="mb-1 flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <Icon className="size-5" />
                    </div>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-1">
                    {turno ? (
                        <p className="text-sm text-muted-foreground">
                            <span className="font-medium text-foreground">
                                {turno.paciente?.nombre ?? '—'}
                            </span>
                            {' · '}
                            {turno.servicio_label ?? turno.servicio}
                        </p>
                    ) : null}

                    {isPhotoFlow(target) ? (
                        <div className="space-y-2">
                            <Label htmlFor="grooming-estado-fotos">{t('estado_flow.fotos')}</Label>
                            <Input
                                id="grooming-estado-fotos"
                                ref={fileRef}
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                multiple
                                className="cursor-pointer text-xs"
                                disabled={form.processing}
                                onChange={(e) => onFiles(e.target.files)}
                            />
                            <p className="text-[11px] text-muted-foreground">{t('estado_flow.fotos_hint')}</p>
                            {previews.length > 0 ? (
                                <ul className="grid grid-cols-3 gap-2">
                                    {previews.map((src) => (
                                        <li key={src} className="overflow-hidden rounded-md border">
                                            <img src={src} alt="" className="aspect-square w-full object-cover" />
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <div className="flex items-center gap-2 rounded-md border border-dashed px-3 py-4 text-xs text-muted-foreground">
                                    <ImagePlus className="size-4 shrink-0" />
                                    {t('estado_flow.fotos_empty')}
                                </div>
                            )}
                            {form.errors.fotos ? (
                                <p className="text-xs text-destructive">{form.errors.fotos}</p>
                            ) : null}
                        </div>
                    ) : null}

                    <div className="space-y-2">
                        <Label htmlFor="grooming-estado-phone">{t('estado_flow.phone')}</Label>
                        <Input
                            id="grooming-estado-phone"
                            type="tel"
                            inputMode="tel"
                            placeholder="51999999999"
                            value={form.data.telefono}
                            disabled={form.processing}
                            onChange={(e) => form.setData('telefono', e.target.value)}
                        />
                        {form.errors.telefono ? (
                            <p className="text-xs text-destructive">{form.errors.telefono}</p>
                        ) : null}
                    </div>

                    {isAutoWhatsApp(target) ? (
                        <div className="flex gap-2 rounded-lg border border-emerald-500/20 bg-emerald-500/8 p-3 text-xs leading-relaxed text-emerald-900 dark:text-emerald-100">
                            <MessageCircle className="mt-0.5 size-4 shrink-0" />
                            <p>{t('estado_flow.auto_whatsapp')}</p>
                        </div>
                    ) : (
                        <label className="flex cursor-pointer items-start gap-2.5 text-sm">
                            <Checkbox
                                checked={form.data.notificar_whatsapp}
                                disabled={form.processing}
                                onCheckedChange={(v) => form.setData('notificar_whatsapp', v === true)}
                                className="mt-0.5"
                            />
                            <span>
                                <span className="font-medium">{t('estado_flow.notify_label')}</span>
                                <span className="mt-0.5 block text-xs text-muted-foreground">
                                    {t('estado_flow.notify_hint')}
                                </span>
                            </span>
                        </label>
                    )}
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={form.processing}
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="button" className="gap-2" disabled={form.processing} onClick={submit}>
                        {form.processing ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <CheckCircle2 className="size-4" />
                        )}
                        {t(`estado_flow.${target ?? 'en_proceso'}.confirm`)}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
