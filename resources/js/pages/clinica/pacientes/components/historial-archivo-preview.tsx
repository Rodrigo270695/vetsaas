import { router } from '@inertiajs/react';
import { FileText, ImageIcon, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

export type HistorialArchivoKind = 'pdf' | 'image' | 'other';

export type HistorialArchivoItem = {
    id: string;
    nombre_examen: string;
    resultado_archivo_url: string | null;
    resultado_archivo_original_name?: string | null;
    archivo_kind?: HistorialArchivoKind;
};

type Props = {
    archivo: HistorialArchivoItem;
    className?: string;
    /** Si true, muestra botón para eliminar el archivo. */
    canDelete?: boolean;
};

function resolveKind(archivo: HistorialArchivoItem): HistorialArchivoKind {
    if (archivo.archivo_kind) {
        return archivo.archivo_kind;
    }

    const name = (
        archivo.resultado_archivo_original_name ??
        archivo.resultado_archivo_url ??
        ''
    ).toLowerCase();

    if (name.includes('.pdf')) {
        return 'pdf';
    }

    if (/\.(jpe?g|png|webp|gif)(\?|$)/.test(name)) {
        return 'image';
    }

    return 'other';
}

/**
 * Chip compacto de PDF/imagen en el historial (clic abre el archivo).
 * Opcionalmente permite eliminar con confirmación.
 */
export function HistorialArchivoPreview({ archivo, className, canDelete = false }: Props) {
    const { t } = useTranslation(['pacientes', 'common']);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [previewOpen, setPreviewOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const url = archivo.resultado_archivo_url;
    if (!url) {
        return null;
    }

    const kind = resolveKind(archivo);

    const onConfirmDelete = () => {
        setDeleting(true);
        router.delete(`/clinica/laboratorio/lineas/${archivo.id}`, {
            preserveScroll: true,
            onFinish: () => {
                setDeleting(false);
                setConfirmOpen(false);
            },
        });
    };

    return (
        <>
            <div
                className={cn(
                    'group relative inline-flex max-w-[12.5rem] shrink-0 items-stretch',
                    className,
                )}
            >
                <a
                    href={url}
                    target="_blank"
                    rel="noopener noreferrer"
                    title={archivo.nombre_examen}
                    onClick={(e) => {
                        if (kind !== 'pdf') {
                            return;
                        }
                        e.preventDefault();
                        setPreviewOpen(true);
                    }}
                    className={cn(
                        'inline-flex min-w-0 flex-1 items-center gap-1.5 rounded-md border border-border/70 bg-background px-1.5 py-1',
                        'text-left shadow-sm transition hover:border-primary/40 hover:bg-muted/40',
                        canDelete && 'rounded-r-none border-r-0',
                    )}
                >
                    <span
                        className={cn(
                            'flex size-7 shrink-0 items-center justify-center overflow-hidden rounded',
                            kind === 'pdf' && 'bg-rose-500/12 text-rose-700 dark:text-rose-200',
                            kind === 'image' && 'bg-muted',
                            kind === 'other' && 'bg-muted text-muted-foreground',
                        )}
                    >
                        {kind === 'image' ? (
                            <img
                                src={url}
                                alt=""
                                className="size-full object-cover"
                                loading="lazy"
                            />
                        ) : kind === 'pdf' ? (
                            <FileText className="size-3.5" strokeWidth={2.25} />
                        ) : (
                            <ImageIcon className="size-3.5" strokeWidth={2.25} />
                        )}
                    </span>
                    <span className="min-w-0">
                        <span className="block truncate text-[0.7rem] font-medium leading-tight text-foreground">
                            {archivo.nombre_examen}
                        </span>
                        <span className="block text-[0.6rem] font-semibold uppercase tracking-wide text-muted-foreground">
                            {kind === 'pdf' ? 'PDF' : kind === 'image' ? 'IMG' : 'Archivo'}
                        </span>
                    </span>
                </a>
                {canDelete ? (
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="h-auto w-8 shrink-0 rounded-l-none border-border/70 text-muted-foreground hover:border-destructive/40 hover:bg-destructive/10 hover:text-destructive"
                        title={t('historial.archivo_eliminar')}
                        aria-label={t('historial.archivo_eliminar')}
                        onClick={(e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            setConfirmOpen(true);
                        }}
                    >
                        <Trash2 className="size-3.5" strokeWidth={2.25} />
                    </Button>
                ) : null}
            </div>

            <Dialog open={previewOpen} onOpenChange={setPreviewOpen}>
                <DialogContent className="flex max-h-[92vh] max-w-4xl flex-col gap-3 sm:max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>{archivo.nombre_examen}</DialogTitle>
                        <DialogDescription>Vista del documento. Puedes descargarlo si lo necesitas.</DialogDescription>
                    </DialogHeader>
                    <iframe
                        title={archivo.nombre_examen}
                        src={url}
                        className="h-[min(72vh,680px)] w-full rounded-md border border-border bg-white"
                    />
                    <DialogFooter>
                        <Button type="button" variant="outline" asChild>
                            <a href={url} target="_blank" rel="noopener noreferrer">
                                Abrir / descargar
                            </a>
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <DialogContent className="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>{t('historial.archivo_eliminar_title')}</DialogTitle>
                        <DialogDescription>
                            {t('historial.archivo_eliminar_description', {
                                name: archivo.nombre_examen,
                            })}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={deleting}
                            onClick={() => setConfirmOpen(false)}
                        >
                            {t('common:actions.cancel')}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={deleting}
                            onClick={onConfirmDelete}
                        >
                            {t('common:actions.delete')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
