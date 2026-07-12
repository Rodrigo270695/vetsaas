import { router } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    Download,
    FileSpreadsheet,
    LoaderCircle,
    Upload,
} from 'lucide-react';
import { useCallback, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

type ImportRowResult = {
    row: number;
    nombre: string;
    status: 'ok' | 'error' | 'skipped' | string;
    message: string;
};

type ImportResult = {
    ok: boolean;
    imported: number;
    failed: number;
    skipped: number;
    rows: ImportRowResult[];
    error?: string;
};

export type BulkImportModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Namespace i18n con claves `import.*`. */
    translationNs: string;
    templateUrl: string;
    importUrl: string;
    /** Props Inertia a recargar tras import exitoso. */
    reloadOnly: string[];
};

function xsrfToken(): string {
    return decodeURIComponent(
        document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
    );
}

export function BulkImportModal({
    open,
    onOpenChange,
    translationNs,
    templateUrl,
    importUrl,
    reloadOnly,
}: BulkImportModalProps) {
    const { t } = useTranslation(translationNs);
    const inputRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [uploading, setUploading] = useState(false);
    const [result, setResult] = useState<ImportResult | null>(null);

    const reset = useCallback(() => {
        setFile(null);
        setUploading(false);
        setResult(null);
        if (inputRef.current) {
            inputRef.current.value = '';
        }
    }, []);

    const handleOpenChange = (next: boolean) => {
        if (!next) {
            reset();
        }
        onOpenChange(next);
    };

    const handleUpload = async () => {
        if (!file || uploading) {
            return;
        }

        const form = new FormData();
        form.append('file', file);
        setUploading(true);
        setResult(null);

        try {
            const response = await fetch(importUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: form,
            });

            const data = (await response.json()) as ImportResult & {
                message?: string;
                errors?: Record<string, string[]> | string[];
            };

            if (!response.ok && !data.rows) {
                const validationErrors = data.errors;
                const errMsg = Array.isArray(validationErrors)
                    ? validationErrors.join(' | ')
                    : typeof validationErrors === 'object' && validationErrors !== null
                      ? Object.values(validationErrors).flat().join(' | ')
                      : (data.error ?? data.message ?? t('import.error_generic'));

                setResult({
                    ok: false,
                    imported: 0,
                    failed: 0,
                    skipped: 0,
                    rows: [],
                    error: errMsg,
                });
                return;
            }

            setResult({
                ok: Boolean(data.ok),
                imported: data.imported ?? 0,
                failed: data.failed ?? 0,
                skipped: data.skipped ?? 0,
                rows: data.rows ?? [],
                error: data.error,
            });

            if ((data.imported ?? 0) > 0) {
                router.reload({ only: reloadOnly });
            }
        } catch {
            setResult({
                ok: false,
                imported: 0,
                failed: 0,
                skipped: 0,
                rows: [],
                error: t('import.error_generic'),
            });
        } finally {
            setUploading(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="flex max-h-[90vh] max-w-[calc(100%-1rem)] flex-col gap-0 overflow-hidden p-0 sm:max-w-3xl">
                <DialogHeader className="border-b border-border/60 px-6 py-4 pr-12">
                    <DialogTitle>{t('import.title')}</DialogTitle>
                    <DialogDescription>{t('import.description')}</DialogDescription>
                </DialogHeader>

                <div className="grid min-h-0 flex-1 gap-0 overflow-hidden md:grid-cols-2">
                    <div className="flex flex-col gap-4 border-b border-border/60 p-6 md:border-r md:border-b-0">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-200">
                                <FileSpreadsheet className="size-5" aria-hidden />
                            </span>
                            <div className="min-w-0 space-y-1">
                                <p className="text-sm font-semibold text-foreground">{t('import.template_title')}</p>
                                <p className="text-xs leading-relaxed text-muted-foreground">
                                    {t('import.template_hint')}
                                </p>
                            </div>
                        </div>

                        <ul className="space-y-1.5 text-xs text-muted-foreground">
                            <li>• {t('import.sheet_data')}</li>
                            <li>• {t('import.sheet_catalogos')}</li>
                            <li>• {t('import.sheet_guide')}</li>
                            <li>• {t('import.required_hint')}</li>
                        </ul>

                        <Button asChild variant="outline" className="mt-auto w-full cursor-pointer gap-2">
                            <a href={templateUrl} download>
                                <Download className="size-4" strokeWidth={2.25} />
                                {t('import.download_template')}
                            </a>
                        </Button>
                    </div>

                    <div className="flex min-h-0 flex-col gap-4 p-6">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-foreground">
                                <Upload className="size-5" aria-hidden />
                            </span>
                            <div className="min-w-0 space-y-1">
                                <p className="text-sm font-semibold text-foreground">{t('import.upload_title')}</p>
                                <p className="text-xs leading-relaxed text-muted-foreground">
                                    {t('import.upload_hint')}
                                </p>
                            </div>
                        </div>

                        <div className="flex flex-col gap-2">
                            <input
                                ref={inputRef}
                                type="file"
                                accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                className="sr-only"
                                onChange={(e) => {
                                    setFile(e.target.files?.[0] ?? null);
                                    setResult(null);
                                }}
                            />
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full cursor-pointer justify-start gap-2 truncate font-normal"
                                onClick={() => inputRef.current?.click()}
                                disabled={uploading}
                            >
                                <FileSpreadsheet className="size-4 shrink-0" aria-hidden />
                                <span className="truncate">{file ? file.name : t('import.choose_file')}</span>
                            </Button>
                            <Button
                                type="button"
                                className="w-full cursor-pointer gap-2"
                                disabled={!file || uploading}
                                onClick={() => void handleUpload()}
                            >
                                {uploading ? (
                                    <LoaderCircle className="size-4 animate-spin" aria-hidden />
                                ) : (
                                    <Upload className="size-4" strokeWidth={2.25} aria-hidden />
                                )}
                                {uploading ? t('import.uploading') : t('import.upload')}
                            </Button>
                        </div>

                        {!result ? (
                            <div className="flex max-h-66 min-h-66 items-center justify-center rounded-lg border border-border/60 bg-muted/20 px-3">
                                <p className="text-center text-xs text-muted-foreground">{t('import.results_empty')}</p>
                            </div>
                        ) : (
                            <div className="flex min-h-0 flex-col gap-2">
                                {result.error ? (
                                    <p className="rounded-md border border-destructive/30 bg-destructive/10 px-2.5 py-2 text-xs text-destructive">
                                        {result.error}
                                    </p>
                                ) : null}

                                <div className="flex flex-wrap gap-2 text-[0.7rem]">
                                    <span className="rounded-full bg-emerald-50 px-2 py-0.5 font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
                                        {t('import.summary_ok', { count: result.imported })}
                                    </span>
                                    <span className="rounded-full bg-rose-50 px-2 py-0.5 font-medium text-rose-800 dark:bg-rose-950/40 dark:text-rose-200">
                                        {t('import.summary_error', { count: result.failed })}
                                    </span>
                                    {result.skipped > 0 ? (
                                        <span className="rounded-full bg-muted px-2 py-0.5 font-medium text-muted-foreground">
                                            {t('import.summary_skipped', { count: result.skipped })}
                                        </span>
                                    ) : null}
                                </div>

                                <ul className="max-h-66 space-y-1.5 overflow-y-auto overscroll-contain rounded-lg border border-border/60 bg-muted/20 p-2">
                                    {result.rows.length === 0 ? (
                                        <li className="px-2 py-4 text-center text-xs text-muted-foreground">
                                            {t('import.results_empty')}
                                        </li>
                                    ) : (
                                        result.rows.map((row, index) => (
                                            <li
                                                key={`${row.row}-${index}-${row.status}`}
                                                className={cn(
                                                    'flex min-h-[2.35rem] items-start gap-2 rounded-md border px-2 py-1.5 text-xs',
                                                    row.status === 'ok' &&
                                                        'border-emerald-200/80 bg-emerald-50/50 dark:border-emerald-900/50 dark:bg-emerald-950/20',
                                                    row.status === 'error' &&
                                                        'border-rose-200/80 bg-rose-50/50 dark:border-rose-900/50 dark:bg-rose-950/20',
                                                    row.status === 'skipped' && 'border-border/60 bg-background',
                                                )}
                                            >
                                                {row.status === 'ok' ? (
                                                    <CheckCircle2
                                                        className="mt-0.5 size-3.5 shrink-0 text-emerald-600"
                                                        aria-hidden
                                                    />
                                                ) : row.status === 'error' ? (
                                                    <AlertCircle
                                                        className="mt-0.5 size-3.5 shrink-0 text-rose-600"
                                                        aria-hidden
                                                    />
                                                ) : (
                                                    <span className="mt-0.5 size-3.5 shrink-0" />
                                                )}
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate font-medium text-foreground">
                                                        {t('import.row_label', {
                                                            row: row.row,
                                                            nombre: row.nombre,
                                                        })}
                                                    </p>
                                                    <p className="line-clamp-2 text-muted-foreground">{row.message}</p>
                                                </div>
                                            </li>
                                        ))
                                    )}
                                </ul>
                            </div>
                        )}
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
