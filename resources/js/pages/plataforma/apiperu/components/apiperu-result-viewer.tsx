import { Copy, Check } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { toastManager } from '@/lib/toast';

type Props = {
    data: unknown;
    timeMs?: number | null;
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

export function ApiPeruResultViewer({ data, timeMs, className }: Props) {
    const [copied, setCopied] = useState(false);
    const json = useMemo(() => JSON.stringify(data, null, 2), [data]);
    const rows = useMemo(() => flattenEntries(data), [data]);
    const showTable = rows.length > 0 && rows.length <= 40 && !Array.isArray(data);

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

    return (
        <div className={cn('flex min-w-0 flex-col gap-3', className)}>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-xs text-muted-foreground">
                    Resultado
                    {typeof timeMs === 'number' ? ` · ${timeMs}s` : null}
                </p>
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

            {showTable ? (
                <div className="overflow-x-auto rounded-lg border border-border/60">
                    <table className="w-full min-w-[16rem] text-left text-sm">
                        <tbody>
                            {rows.map((row) => (
                                <tr
                                    key={row.key}
                                    className="border-b border-border/50 last:border-0 odd:bg-muted/30"
                                >
                                    <th className="w-[40%] max-w-[12rem] px-3 py-2 align-top font-medium text-muted-foreground">
                                        {row.key}
                                    </th>
                                    <td className="px-3 py-2 break-all text-foreground">{row.value}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : null}

            <pre className="max-h-80 overflow-auto rounded-lg border border-border/60 bg-muted/40 p-3 text-xs leading-relaxed text-foreground sm:max-h-96 sm:text-[13px]">
                {json}
            </pre>
        </div>
    );
}
