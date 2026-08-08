import { ExternalLink, Loader2, Search } from 'lucide-react';
import { useMemo, useState, type FormEvent } from 'react';
import { FormField } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { toastManager } from '@/lib/toast';
import { SectionCard } from '@/pages/configuracion/general/components/section-card';
import { ApiPeruResultViewer } from './apiperu-result-viewer';
import type { ApiPeruEndpoint } from '../types';

type Props = {
    endpoint: ApiPeruEndpoint;
    consultarUrl: string;
    disabled?: boolean;
};

function readXsrfToken(): string {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return m ? decodeURIComponent(m[1]) : '';
}

function initialValues(endpoint: ApiPeruEndpoint): Record<string, string> {
    const values: Record<string, string> = {};
    for (const field of endpoint.fields) {
        values[field.name] = '';
    }

    return values;
}

export function ApiPeruConsultaPanel({ endpoint, consultarUrl, disabled = false }: Props) {
    const [values, setValues] = useState<Record<string, string>>(() => initialValues(endpoint));
    const [loading, setLoading] = useState(false);
    const [result, setResult] = useState<unknown>(null);
    const [timeMs, setTimeMs] = useState<number | null>(null);
    const [fieldError, setFieldError] = useState<string | null>(null);

    const pathLabel = useMemo(() => `POST ${endpoint.path}`, [endpoint.path]);

    const setField = (name: string, value: string) => {
        setValues((prev) => ({ ...prev, [name]: value }));
        setFieldError(null);
    };

    const onSubmit = async (e: FormEvent) => {
        e.preventDefault();

        if (disabled || loading) {
            return;
        }

        for (const field of endpoint.fields) {
            const raw = (values[field.name] ?? '').trim();
            if (field.required && raw === '') {
                setFieldError(`Completa «${field.label}».`);

                return;
            }

            if (field.pattern && raw !== '') {
                try {
                    if (!new RegExp(field.pattern).test(raw)) {
                        setFieldError(`Formato inválido en «${field.label}».`);

                        return;
                    }
                } catch {
                    // ignore invalid regex from backend
                }
            }
        }

        setLoading(true);
        setResult(null);
        setTimeMs(null);
        setFieldError(null);

        try {
            const res = await fetch(consultarUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': readXsrfToken(),
                },
                body: JSON.stringify({
                    endpoint: endpoint.key,
                    payload: values,
                }),
            });

            const body = (await res.json()) as {
                success?: boolean;
                message?: string;
                code?: string;
                data?: {
                    data?: unknown;
                    time?: number | null;
                    raw?: unknown;
                };
            };

            if (!res.ok || !body.success) {
                toastManager.error({
                    title:
                        body.code === 'not_configured'
                            ? 'Token ApiPerú no configurado'
                            : body.code === 'rate_limit'
                              ? 'Límite de consultas alcanzado'
                              : (body.message ?? 'No se pudo consultar ApiPerú'),
                });

                return;
            }

            setResult(body.data?.raw ?? body.data?.data ?? body.data);
            setTimeMs(typeof body.data?.time === 'number' ? body.data.time : null);
            toastManager.success({ title: 'Consulta exitosa' });
        } catch {
            toastManager.error({ title: 'Error de red al consultar ApiPerú' });
        } finally {
            setLoading(false);
        }
    };

    return (
        <SectionCard
            title={endpoint.label}
            description={endpoint.description}
            icon={Search}
            badge={
                <div className="flex flex-wrap items-center gap-2">
                    <span className="rounded-md bg-muted px-2 py-0.5 font-mono text-[11px] text-muted-foreground">
                        {pathLabel}
                    </span>
                    {endpoint.docs_url ? (
                        <a
                            href={endpoint.docs_url}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                        >
                            Docs
                            <ExternalLink className="size-3" aria-hidden />
                        </a>
                    ) : null}
                </div>
            }
        >
            <form onSubmit={(e) => void onSubmit(e)} className="flex flex-col gap-4">
                {endpoint.fields.length > 0 ? (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        {endpoint.fields.map((field) => {
                            const id = `apiperu-${endpoint.key}-${field.name}`;
                            const spanFull = field.type === 'textarea' || endpoint.fields.length === 1;

                            return (
                                <FormField
                                    key={field.name}
                                    id={id}
                                    label={field.label}
                                    required={field.required}
                                    hint={field.hint ?? undefined}
                                    className={spanFull ? 'sm:col-span-2' : undefined}
                                >
                                    {field.type === 'textarea' ? (
                                        <Textarea
                                            id={id}
                                            value={values[field.name] ?? ''}
                                            onChange={(ev) => setField(field.name, ev.target.value)}
                                            placeholder={field.placeholder ?? undefined}
                                            rows={5}
                                            className="font-mono text-xs sm:text-sm"
                                            disabled={disabled || loading}
                                        />
                                    ) : (
                                        <Input
                                            id={id}
                                            type={field.type === 'date' ? 'date' : 'text'}
                                            value={values[field.name] ?? ''}
                                            onChange={(ev) => setField(field.name, ev.target.value)}
                                            placeholder={field.placeholder ?? undefined}
                                            maxLength={field.max_length ?? undefined}
                                            disabled={disabled || loading}
                                            inputMode={
                                                field.name.includes('dni') ||
                                                field.name.includes('ruc') ||
                                                field.name === 'numero' ||
                                                field.name === 'monto'
                                                    ? 'numeric'
                                                    : undefined
                                            }
                                            className="font-mono"
                                        />
                                    )}
                                </FormField>
                            );
                        })}
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Este endpoint no requiere parámetros. Pulsa consultar para obtener la respuesta.
                    </p>
                )}

                {fieldError ? (
                    <p className="text-sm text-destructive" role="alert">
                        {fieldError}
                    </p>
                ) : null}

                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button
                        type="submit"
                        disabled={disabled || loading}
                        className="gap-2 bg-linear-to-r from-primary to-emerald-600"
                    >
                        {loading ? (
                            <Loader2 className="size-4 animate-spin" aria-hidden />
                        ) : (
                            <Search className="size-4" aria-hidden />
                        )}
                        Consultar
                    </Button>
                </div>

                {result !== null ? <ApiPeruResultViewer data={result} timeMs={timeMs} /> : null}
            </form>
        </SectionCard>
    );
}
