import { ImagePlus, RotateCcw, Sparkles, Upload, X } from 'lucide-react';
import { useEffect, useRef, useState, type DragEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const ACCEPTED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
const MAX_BYTES = 2 * 1024 * 1024;

export type LogoUploaderProps = {
    /** URL pública del logo ya guardado (null si todavía no hay). */
    currentUrl: string | null;
    /** Archivo seleccionado en la sesión actual (aún no enviado). */
    file: File | null;
    /** Marca de "borrar al guardar" — toggle controlado por el padre. */
    pendingRemoval: boolean;
    /** Mensaje de error del backend si la validación falló. */
    error?: string;
    /** Si false, deshabilita todas las interacciones. */
    canUpdate: boolean;

    /** El usuario eligió un archivo nuevo (file picker o drag&drop). */
    onSelect: (file: File) => void;
    /** El usuario quitó el archivo en la sesión actual. */
    onClearSelection: () => void;
    /** Toggle del flag `clear_logo` (marca/desmarca borrado al guardar). */
    onTogglePendingRemoval: () => void;
};

/**
 * Componente de subida del logo de la clínica.
 *
 * Estados visuales (en orden de prioridad):
 *
 *   1) Archivo recién seleccionado en esta sesión (`file !== null`)
 *      → muestra preview local (URL.createObjectURL) + nombre + tamaño.
 *   2) `pendingRemoval = true` con logo previo
 *      → muestra el logo en gris con badge "Se quitará al guardar".
 *   3) Logo guardado en backend (`currentUrl !== null`)
 *      → muestra el logo + botones "Reemplazar" / "Quitar".
 *   4) Sin logo
 *      → muestra el dropzone con icono y CTA.
 *
 * Drag & drop:
 *   Acepta archivos arrastrados directamente sobre el dropzone. Valida
 *   tipo MIME y tamaño en el cliente para feedback inmediato; el backend
 *   re-valida (defensa en profundidad).
 */
export function LogoUploader({
    currentUrl,
    file,
    pendingRemoval,
    error,
    canUpdate,
    onSelect,
    onClearSelection,
    onTogglePendingRemoval,
}: LogoUploaderProps) {
    const { t } = useTranslation(['general', 'common']);
    const inputRef = useRef<HTMLInputElement>(null);
    const [isDragging, setIsDragging] = useState(false);
    const [localError, setLocalError] = useState<string | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);

    // URL temporal del archivo seleccionado: la creamos cuando cambia el
    // archivo y la liberamos en el cleanup para no filtrar memoria.
    useEffect(() => {
        if (!file) {
            setPreviewUrl(null);

            return;
        }

        const url = URL.createObjectURL(file);
        setPreviewUrl(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    const handleFiles = (files: FileList | null) => {
        setLocalError(null);
        if (!files || files.length === 0) {
            return;
        }

        const candidate = files[0];

        if (!ACCEPTED_MIME.includes(candidate.type)) {
            setLocalError(t('general:fields.logo_drop_supported'));

            return;
        }

        if (candidate.size > MAX_BYTES) {
            setLocalError(t('general:fields.logo_drop_supported'));

            return;
        }

        onSelect(candidate);
    };

    const handleDrop = (event: DragEvent<HTMLLabelElement>) => {
        event.preventDefault();
        setIsDragging(false);
        if (!canUpdate) {
            return;
        }
        handleFiles(event.dataTransfer.files);
    };

    const handleDragOver = (event: DragEvent<HTMLLabelElement>) => {
        event.preventDefault();
        if (canUpdate) {
            setIsDragging(true);
        }
    };

    const handleDragLeave = () => setIsDragging(false);

    const openPicker = () => {
        if (canUpdate) {
            inputRef.current?.click();
        }
    };

    const hasNewFile = file !== null;
    const hasExistingLogo = currentUrl !== null;
    const showPreview = hasNewFile || hasExistingLogo;
    const previewSrc = previewUrl ?? currentUrl;
    const effectiveError = error ?? localError;

    /*
     * Cuando el padre nos manda un nuevo `file`, sincronizamos el input
     * nativo. Cuando lo limpia, vaciamos el input para que el usuario
     * pueda volver a elegir el mismo archivo si quiere.
     */
    useEffect(() => {
        if (!file && inputRef.current) {
            inputRef.current.value = '';
        }
    }, [file]);

    return (
        <div className="flex flex-col gap-2">
            <input
                ref={inputRef}
                type="file"
                accept={ACCEPTED_MIME.join(',')}
                className="sr-only"
                onChange={(event) => handleFiles(event.target.files)}
                disabled={!canUpdate}
            />

            {showPreview ? (
                <div className="flex flex-col gap-3 rounded-lg border border-border/60 bg-card/40 p-4 sm:flex-row sm:items-center sm:gap-5">
                    <div
                        className={cn(
                            'flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border/60 bg-muted/40 p-2',
                            pendingRemoval && !hasNewFile && 'opacity-40 grayscale',
                        )}
                    >
                        {previewSrc && (
                            <img
                                src={previewSrc}
                                alt={t('general:fields.logo_preview_alt')}
                                className="size-full object-contain"
                            />
                        )}
                    </div>

                    <div className="flex min-w-0 flex-1 flex-col gap-2">
                        <div className="flex min-w-0 flex-col gap-0.5">
                            {hasNewFile && file && (
                                <>
                                    <span className="truncate text-sm font-medium">
                                        {t('general:fields.logo_pending', {
                                            name: file.name,
                                        })}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {(file.size / 1024).toFixed(1)} KB
                                    </span>
                                </>
                            )}
                            {!hasNewFile && pendingRemoval && (
                                <span className="text-sm font-medium text-destructive">
                                    {t('general:fields.logo_remove_pending')}
                                </span>
                            )}
                            {!hasNewFile && !pendingRemoval && (
                                <span className="text-sm text-muted-foreground">
                                    {t('general:fields.logo_hint_uploaded')}
                                </span>
                            )}
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={openPicker}
                                disabled={!canUpdate}
                                className="h-8 cursor-pointer gap-1.5 text-xs"
                            >
                                <Upload className="size-3.5" strokeWidth={2.25} />
                                {hasNewFile
                                    ? t('general:fields.logo_select')
                                    : t('general:fields.logo_replace')}
                            </Button>

                            {hasNewFile ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={onClearSelection}
                                    disabled={!canUpdate}
                                    className="h-8 cursor-pointer gap-1.5 text-xs"
                                >
                                    <X className="size-3.5" strokeWidth={2.25} />
                                    {t('common:actions.cancel')}
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={pendingRemoval ? 'outline' : 'ghost'}
                                    onClick={onTogglePendingRemoval}
                                    disabled={!canUpdate}
                                    className={cn(
                                        'h-8 cursor-pointer gap-1.5 text-xs',
                                        !pendingRemoval &&
                                            'text-destructive hover:text-destructive',
                                    )}
                                >
                                    {pendingRemoval ? (
                                        <>
                                            <RotateCcw
                                                className="size-3.5"
                                                strokeWidth={2.25}
                                            />
                                            {t('general:fields.logo_undo_remove')}
                                        </>
                                    ) : (
                                        <>
                                            <X
                                                className="size-3.5"
                                                strokeWidth={2.25}
                                            />
                                            {t('general:fields.logo_remove')}
                                        </>
                                    )}
                                </Button>
                            )}
                        </div>
                    </div>
                </div>
            ) : (
                /*
                  Aunque visualmente es un dropzone interactivo, NO usamos
                  `htmlFor` porque el click ya está gestionado por
                  `openPicker` y duplicarlo (label nativo + handler) abría
                  el file picker dos veces en algunos navegadores.
                */
                <label
                    onClick={openPicker}
                    onDrop={handleDrop}
                    onDragOver={handleDragOver}
                    onDragLeave={handleDragLeave}
                    className={cn(
                        'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-border/60 bg-muted/20 p-8 text-center transition-colors hover:bg-muted/40',
                        isDragging && 'border-primary/60 bg-primary/5',
                        !canUpdate && 'cursor-not-allowed opacity-60',
                    )}
                >
                    <span className="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary ring-1 ring-primary/20">
                        {isDragging ? (
                            <Sparkles className="size-5" strokeWidth={2.25} />
                        ) : (
                            <ImagePlus className="size-5" strokeWidth={2.25} />
                        )}
                    </span>
                    <span className="text-sm font-medium">
                        {t('general:fields.logo_drop')}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {t('general:fields.logo_drop_supported')}
                    </span>
                </label>
            )}

            {effectiveError && (
                <p className="text-xs text-destructive" role="alert">
                    {effectiveError}
                </p>
            )}
        </div>
    );
}
