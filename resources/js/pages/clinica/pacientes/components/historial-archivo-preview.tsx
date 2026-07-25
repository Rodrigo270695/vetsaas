import { FileText, ImageIcon } from 'lucide-react';
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
 */
export function HistorialArchivoPreview({ archivo, className }: Props) {
    const url = archivo.resultado_archivo_url;
    if (!url) {
        return null;
    }

    const kind = resolveKind(archivo);

    return (
        <a
            href={url}
            target="_blank"
            rel="noopener noreferrer"
            title={archivo.nombre_examen}
            className={cn(
                'group inline-flex max-w-[11rem] shrink-0 items-center gap-1.5 rounded-md border border-border/70 bg-background px-1.5 py-1',
                'text-left shadow-sm transition hover:border-primary/40 hover:bg-muted/40',
                className,
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
    );
}
