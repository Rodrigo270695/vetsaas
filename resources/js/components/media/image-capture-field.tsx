import { Camera, Images, Loader2, Trash2, Upload } from 'lucide-react';
import {
    useEffect,
    useId,
    useMemo,
    useRef,
    useState,
    type ChangeEvent,
} from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useIsCameraFriendlyDevice } from '@/hooks/use-camera-friendly-device';
import { compressImageFile } from '@/lib/compress-image';
import { toastManager } from '@/lib/toast';
import { cn } from '@/lib/utils';

type Props = {
    id?: string;
    value: File | null;
    existingUrl?: string | null;
    onChange: (file: File | null) => void;
    disabled?: boolean;
    className?: string;
    /** Si true, limpia también el preview de URL existente (el form maneja clear_foto aparte). */
    clearExisting?: boolean;
};

/**
 * Selector de foto con:
 * - Cámara + galería en celular / tablet / PWA
 * - Compresión automática si la imagen supera ~2 MB (p. ej. iPhone)
 */
export function ImageCaptureField({
    id,
    value,
    existingUrl = null,
    onChange,
    disabled = false,
    className,
    clearExisting = false,
}: Props) {
    const { t } = useTranslation('pacientes');
    const autoId = useId();
    const fieldId = id ?? autoId;
    const cameraFriendly = useIsCameraFriendlyDevice();
    const galleryRef = useRef<HTMLInputElement>(null);
    const cameraRef = useRef<HTMLInputElement>(null);
    const [compressing, setCompressing] = useState(false);

    const objectUrl = useMemo(() => {
        if (value instanceof File) {
            return URL.createObjectURL(value);
        }
        return null;
    }, [value]);

    useEffect(() => {
        return () => {
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
            }
        };
    }, [objectUrl]);

    const previewSrc =
        objectUrl ?? (!clearExisting && existingUrl ? existingUrl : null);

    const applyFile = async (file: File | null | undefined) => {
        if (!file) {
            return;
        }

        if (!file.type.startsWith('image/') && file.type !== '') {
            toastManager.error({
                title: t('form.foto_invalid_type'),
            });
            return;
        }

        setCompressing(true);
        try {
            const compressed = await compressImageFile(file);
            if (compressed.size > 2_000_000) {
                toastManager.error({
                    title: t('form.foto_still_too_large'),
                });
                return;
            }
            onChange(compressed);
        } catch {
            toastManager.error({
                title: t('form.foto_compress_failed'),
            });
        } finally {
            setCompressing(false);
        }
    };

    const onInputChange = async (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        e.target.value = '';
        await applyFile(file);
    };

    const busy = disabled || compressing;

    return (
        <div className={cn('flex flex-col gap-3', className)}>
            <input
                ref={galleryRef}
                id={fieldId}
                type="file"
                accept="image/jpeg,image/png,image/webp,image/*"
                className="sr-only"
                disabled={busy}
                onChange={onInputChange}
            />
            <input
                ref={cameraRef}
                type="file"
                accept="image/*"
                capture="environment"
                className="sr-only"
                disabled={busy}
                onChange={onInputChange}
            />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-start">
                {previewSrc ? (
                    <div className="relative shrink-0">
                        <img
                            src={previewSrc}
                            alt=""
                            className="size-20 rounded-lg border border-border object-cover shadow-sm"
                        />
                        {value instanceof File ? (
                            <Button
                                type="button"
                                size="icon"
                                variant="destructive"
                                className="absolute -right-2 -top-2 size-7"
                                disabled={busy}
                                title={t('form.foto_remove')}
                                onClick={() => onChange(null)}
                            >
                                <Trash2 className="size-3.5" />
                            </Button>
                        ) : null}
                    </div>
                ) : (
                    <div className="flex size-20 shrink-0 items-center justify-center rounded-lg border border-dashed border-border bg-muted/30 text-muted-foreground">
                        {compressing ? (
                            <Loader2 className="size-5 animate-spin" />
                        ) : (
                            <Images className="size-5 opacity-60" />
                        )}
                    </div>
                )}

                <div className="flex min-w-0 flex-1 flex-col gap-2">
                    {cameraFriendly ? (
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                size="sm"
                                className="gap-1.5"
                                disabled={busy}
                                onClick={() => cameraRef.current?.click()}
                            >
                                {compressing ? (
                                    <Loader2 className="size-3.5 animate-spin" />
                                ) : (
                                    <Camera className="size-3.5" />
                                )}
                                {t('form.foto_take')}
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="gap-1.5"
                                disabled={busy}
                                onClick={() => galleryRef.current?.click()}
                            >
                                <Images className="size-3.5" />
                                {t('form.foto_gallery')}
                            </Button>
                        </div>
                    ) : (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="w-fit gap-1.5"
                            disabled={busy}
                            onClick={() => galleryRef.current?.click()}
                        >
                            {compressing ? (
                                <Loader2 className="size-3.5 animate-spin" />
                            ) : (
                                <Upload className="size-3.5" />
                            )}
                            {value || existingUrl
                                ? t('form.foto_replace')
                                : t('form.foto_choose')}
                        </Button>
                    )}

                    <p className="text-[11px] leading-snug text-muted-foreground">
                        {compressing
                            ? t('form.foto_compressing')
                            : t('form.foto_hint')}
                    </p>
                    {value instanceof File ? (
                        <p className="text-[11px] tabular-nums text-muted-foreground">
                            {t('form.foto_ready', {
                                size: formatKb(value.size),
                            })}
                        </p>
                    ) : null}
                </div>
            </div>
        </div>
    );
}

function formatKb(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    return `${(bytes / 1024).toFixed(0)} KB`;
}
