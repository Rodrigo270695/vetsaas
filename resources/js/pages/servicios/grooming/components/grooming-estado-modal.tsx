import { useForm } from '@inertiajs/react';
import {
    Camera,
    CheckCircle2,
    ImagePlus,
    Images,
    Loader2,
    MessageCircle,
    Play,
    RefreshCw,
    Trash2,
    XCircle,
} from 'lucide-react';
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

type PhotoItem = {
    id: string;
    file: File;
    preview: string;
};

const MAX_FOTOS = 8;

function isPhotoFlow(target: GroomingEstadoTarget | null): boolean {
    return target === 'en_proceso' || target === 'completada';
}

function isAutoWhatsApp(target: GroomingEstadoTarget | null): boolean {
    return target === 'cancelada' || target === 'no_asistio';
}

function newPhotoId(): string {
    return `${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
}

function toPhotoItems(files: File[]): PhotoItem[] {
    return files.map((file) => ({
        id: newPhotoId(),
        file,
        preview: URL.createObjectURL(file),
    }));
}

export function GroomingEstadoModal({ turno, target, onOpenChange }: Props) {
    const { t } = useTranslation(['grooming', 'common']);
    const galleryRef = useRef<HTMLInputElement>(null);
    const cameraRef = useRef<HTMLInputElement>(null);
    const replaceGalleryRef = useRef<HTMLInputElement>(null);
    const replaceCameraRef = useRef<HTMLInputElement>(null);
    const replaceIndexRef = useRef<number | null>(null);
    const [photos, setPhotos] = useState<PhotoItem[]>([]);
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

    const syncFotos = (items: PhotoItem[]) => {
        setPhotos(items);
        form.setData(
            'fotos',
            items.map((p) => p.file),
        );
    };

    const revokeAll = (items: PhotoItem[]) => {
        for (const item of items) {
            URL.revokeObjectURL(item.preview);
        }
    };

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
        setPhotos((prev) => {
            revokeAll(prev);
            return [];
        });
        for (const ref of [galleryRef, cameraRef, replaceGalleryRef, replaceCameraRef]) {
            if (ref.current) {
                ref.current.value = '';
            }
        }
        replaceIndexRef.current = null;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, target, turno?.id, defaultPhone]);

    useEffect(() => {
        return () => {
            revokeAll(photos);
        };
        // Only on unmount
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

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

    const Icon =
        target === 'en_proceso' ? Play : target === 'completada' ? CheckCircle2 : XCircle;

    const canAddMore = photos.length < MAX_FOTOS;

    const appendFiles = (fileList: FileList | null) => {
        if (!fileList || fileList.length === 0) {
            return;
        }

        const incoming = Array.from(fileList).filter((f) => f.type.startsWith('image/'));
        if (incoming.length === 0) {
            return;
        }

        const room = MAX_FOTOS - photos.length;
        const next = [...photos, ...toPhotoItems(incoming.slice(0, room))];
        syncFotos(next);
    };

    const removeAt = (index: number) => {
        const next = photos.filter((_, i) => i !== index);
        URL.revokeObjectURL(photos[index]?.preview ?? '');
        syncFotos(next);
    };

    const replaceAt = (index: number, file: File | null) => {
        if (!file || !file.type.startsWith('image/')) {
            return;
        }

        const next = photos.map((item, i) => {
            if (i !== index) {
                return item;
            }

            URL.revokeObjectURL(item.preview);

            return {
                id: newPhotoId(),
                file,
                preview: URL.createObjectURL(file),
            };
        });
        syncFotos(next);
    };

    const openReplace = (index: number, mode: 'gallery' | 'camera') => {
        replaceIndexRef.current = index;
        const ref = mode === 'camera' ? replaceCameraRef : replaceGalleryRef;
        ref.current?.click();
    };

    const submit = () => {
        if (!turno || !target) {
            return;
        }

        form.transform((data) => ({
            estado: target,
            telefono: data.telefono,
            notificar_whatsapp: isAutoWhatsApp(target) ? true : data.notificar_whatsapp,
            fotos: photos.map((p) => p.file),
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
                        <div className="space-y-3">
                            <div className="flex items-center justify-between gap-2">
                                <Label>{t('estado_flow.fotos')}</Label>
                                <span className="text-[11px] text-muted-foreground">
                                    {photos.length}/{MAX_FOTOS}
                                </span>
                            </div>

                            <input
                                ref={galleryRef}
                                type="file"
                                accept="image/jpeg,image/png,image/webp,image/*"
                                multiple
                                className="hidden"
                                disabled={form.processing || !canAddMore}
                                onChange={(e) => {
                                    appendFiles(e.target.files);
                                    e.target.value = '';
                                }}
                            />
                            <input
                                ref={cameraRef}
                                type="file"
                                accept="image/*"
                                capture="environment"
                                className="hidden"
                                disabled={form.processing || !canAddMore}
                                onChange={(e) => {
                                    appendFiles(e.target.files);
                                    e.target.value = '';
                                }}
                            />
                            <input
                                ref={replaceGalleryRef}
                                type="file"
                                accept="image/jpeg,image/png,image/webp,image/*"
                                className="hidden"
                                disabled={form.processing}
                                onChange={(e) => {
                                    const idx = replaceIndexRef.current;
                                    if (idx !== null) {
                                        replaceAt(idx, e.target.files?.[0] ?? null);
                                    }
                                    replaceIndexRef.current = null;
                                    e.target.value = '';
                                }}
                            />
                            <input
                                ref={replaceCameraRef}
                                type="file"
                                accept="image/*"
                                capture="environment"
                                className="hidden"
                                disabled={form.processing}
                                onChange={(e) => {
                                    const idx = replaceIndexRef.current;
                                    if (idx !== null) {
                                        replaceAt(idx, e.target.files?.[0] ?? null);
                                    }
                                    replaceIndexRef.current = null;
                                    e.target.value = '';
                                }}
                            />

                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="default"
                                    size="sm"
                                    className="gap-1.5"
                                    disabled={form.processing || !canAddMore}
                                    onClick={() => cameraRef.current?.click()}
                                >
                                    <Camera className="size-3.5" />
                                    {t('estado_flow.take_photo')}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="gap-1.5"
                                    disabled={form.processing || !canAddMore}
                                    onClick={() => galleryRef.current?.click()}
                                >
                                    <Images className="size-3.5" />
                                    {t('estado_flow.from_gallery')}
                                </Button>
                            </div>

                            <p className="text-[11px] text-muted-foreground">{t('estado_flow.fotos_hint')}</p>

                            {photos.length > 0 ? (
                                <ul className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    {photos.map((photo, index) => (
                                        <li
                                            key={photo.id}
                                            className="group relative overflow-hidden rounded-md border bg-muted/20"
                                        >
                                            <img
                                                src={photo.preview}
                                                alt=""
                                                className="aspect-square w-full object-cover"
                                            />
                                            <div className="absolute inset-x-0 bottom-0 flex items-center justify-center gap-1 bg-black/55 p-1">
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="secondary"
                                                    className="size-7"
                                                    title={t('estado_flow.retake_photo')}
                                                    disabled={form.processing}
                                                    onClick={() => openReplace(index, 'camera')}
                                                >
                                                    <Camera className="size-3.5" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="secondary"
                                                    className="size-7"
                                                    title={t('estado_flow.replace_photo')}
                                                    disabled={form.processing}
                                                    onClick={() => openReplace(index, 'gallery')}
                                                >
                                                    <RefreshCw className="size-3.5" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="destructive"
                                                    className="size-7"
                                                    title={t('estado_flow.remove_photo')}
                                                    disabled={form.processing}
                                                    onClick={() => removeAt(index)}
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </Button>
                                            </div>
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
