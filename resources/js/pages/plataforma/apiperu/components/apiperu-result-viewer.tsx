import { Check, ChevronDown, ChevronUp, Copy } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { toastManager } from '@/lib/toast';
import { ApiPeruDataTable, isObjectArray } from './apiperu-data-table';

type Props = {
    data: unknown;
    timeMs?: number | null;
    endpointKey?: string;
    className?: string;
};

function isPlainObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function flattenEntries(value: unknown, prefix = ''): Array<{ key: string; value: string }> {
    if (value === null || value === undefined) {
        return [{ key: prefix || 'valor', value: '—' }];
    }

    if (Array.isArray(value)) {
        if (value.length === 0) {
            return [{ key: prefix || 'lista', value: '[]' }];
        }

        return value.flatMap((item, index) =>
            flattenEntries(item, prefix ? `${prefix}[${index}]` : `[${index}]`),
        );
    }

    if (isPlainObject(value)) {
        const keys = Object.keys(value);
        if (keys.length === 0) {
            return [{ key: prefix || 'objeto', value: '{}' }];
        }

        return keys.flatMap((key) =>
            flattenEntries(value[key], prefix ? `${prefix}.${key}` : key),
        );
    }

    return [{ key: prefix || 'valor', value: String(value) }];
}

function humanizeKey(key: string): string {
    return key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export function ApiPeruResultViewer({ data, timeMs, endpointKey, className }: Props) {
    const [copied, setCopied] = useState(false);
    const [showJson, setShowJson] = useState(false);
    const json = useMemo(() => JSON.stringify(data, null, 2), [data]);

    const objectRows = useMemo(() => (isObjectArray(data) ? data : null), [data]);
    const kvRows = useMemo(() => {
        if (objectRows) {
            return [];
        }

        if (isPlainObject(data)) {
            return Object.entries(data).map(([key, value]) => ({
                key: humanizeKey(key),
                value:
                    value === null || value === undefined
                        ? '—'
                        : typeof value === 'string' ||
                            typeof value === 'number' ||
                            typeof value === 'boolean'
                          ? String(value)
                          : JSON.stringify(value),
            }));
        }

        return flattenEntries(data);
    }, [data, objectRows]);

    const copyJson = async () => {
        try {
            await navigator.clipboard.writeText(json);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
            toastManager.success({ title: 'JSON copiado' });
        } catch {
            toastManager.error({ title: 'No se pudo copiar' });
        }
    };

    const timeLabel =
        typeof timeMs === 'number' ? ` · ${Number(timeMs).toFixed(2)}s` : null;

    return (
        <div className={cn('flex min-w-0 flex-col gap-3', className)}>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-xs text-muted-foreground">
                    Resultado
                    {timeLabel}
                    {objectRows ? ` · ${objectRows.length} filas` : null}
                </p>
                <div className="flex flex-wrap gap-1.5">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="h-8 gap-1.5"
                        onClick={() => setShowJson((v) => !v)}
                    >
                        {showJson ? (
                            <ChevronUp className="size-3.5" aria-hidden />
                        ) : (
                            <ChevronDown className="size-3.5" aria-hidden />
                        )}
                        {showJson ? 'Ocultar JSON' : 'Ver JSON'}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-8 gap-1.5"
                        onClick={() => void copyJson()}
                    >
                        {copied ? (
                            <Check className="size-3.5 text-emerald-600" aria-hidden />
                        ) : (
                            <Copy className="size-3.5" aria-hidden />
                        )}
                        Copiar JSON
                    </Button>
                </div>
            </div>

            {objectRows ? (
                <ApiPeruDataTable rows={objectRows} endpointKey={endpointKey} />
            ) : kvRows.length > 0 ? (
                <div className="overflow-x-auto rounded-lg border border-border/60">
                    <table className="w-full min-w-[16rem] text-left text-sm">
                        <tbody>
                            {kvRows.map((row) => (
                                <tr
                                    key={row.key}
                                    className="border-b border-border/50 last:border-0 odd:bg-muted/30"
                                >
                                    <th className="w-[38%] max-w-[12rem] px-3 py-2 align-top font-medium text-muted-foreground">
                                        {row.key}
                                    </th>
                                    <td className="px-3 py-2 break-all text-foreground">{row.value}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">Sin datos.</p>
            )}

            {showJson ? (
                <pre className="max-h-64 overflow-auto rounded-lg border border-border/60 bg-muted/40 p-3 text-xs leading-relaxed text-foreground sm:max-h-80 sm:text-[13px]">
                    {json}
                </pre>
            ) : null}
        </div>
    );
}
