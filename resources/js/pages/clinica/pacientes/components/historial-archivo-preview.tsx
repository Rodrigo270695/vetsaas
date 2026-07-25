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
 * Vista compacta de PDF/imagen en el historial (clic abre el archivo).
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
            className={cn(
                'group flex w-[7.5rem] shrink-0 flex-col gap-1.5 sm:w-32',
                className,
            )}
        >
            <div
                className={cn(
                    'relative aspect-[3/4] overflow-hidden rounded-lg border border-border/70 bg-muted/40 shadow-sm transition',
                    'ring-1 ring-black/[0.03] group-hover:border-primary/40 group-hover:shadow-md dark:ring-white/5',
                )}
            >
                {kind === 'image' ? (
                    <img
                        src={url}
                        alt={archivo.nombre_examen}
                        className="size-full object-cover"
                        loading="lazy"
                    />
                ) : kind === 'pdf' ? (
                    <div className="flex size-full flex-col items-center justify-center gap-2 bg-gradient-to-b from-rose-500/15 to-rose-500/5 p-2 text-rose-800 dark:text-rose-200">
                        <FileText className="size-8 opacity-90" strokeWidth={1.75} />
                        <span className="rounded bg-rose-600/90 px-1.5 py-0.5 text-[0.6rem] font-bold tracking-wide text-white">
                            PDF
                        </span>
                    </div>
                ) : (
                    <div className="flex size-full flex-col items-center justify-center gap-2 p-2 text-muted-foreground">
                        <ImageIcon className="size-8 opacity-70" strokeWidth={1.75} />
                        <span className="text-[0.6rem] font-semibold uppercase">Archivo</span>
                    </div>
                )}
            </div>
            <p className="line-clamp-2 text-center text-[0.7rem] font-medium leading-snug text-foreground">
                {archivo.nombre_examen}
            </p>
        </a>
    );
}
