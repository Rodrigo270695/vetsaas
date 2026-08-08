import {
    Building2,
    CarFront,
    FileText,
    IdCard,
    Loader2,
    MapPinned,
    Search,
    WalletCards,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useMemo, useState, type FormEvent } from 'react';
import { FormField } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { toastManager } from '@/lib/toast';
import { SectionCard } from '@/pages/configuracion/general/components/section-card';
import { ApiPeruCountedField } from './apiperu-counted-field';
import type { ApiPeruField, ApiPeruPerfilPayload, ApiPeruProfile } from '../types';

const ICONS: Record<string, LucideIcon> = {
    id_card: IdCard,
    building: Building2,
    wallet: WalletCards,
    file: FileText,
    car: CarFront,
    map: MapPinned,
};

type Props = {
    profile: ApiPeruProfile;
    consultarUrl: string;
    disabled?: boolean;
    onResult: (payload: ApiPeruPerfilPayload) => void;
};

function readXsrfToken(): string {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return m ? decodeURIComponent(m[1]) : '';
}

function fieldsOf(profile: ApiPeruProfile): ApiPeruField[] {
    const list: ApiPeruField[] = [];
    if (profile.primary_field) {
        list.push(profile.primary_field);
    }
    list.push(...profile.extra_fields);

    return list;
}

function emptyValues(fields: ApiPeruField[]): Record<string, string> {
    const values: Record<string, string> = {};
    for (const field of fields) {
        values[field.name] = '';
    }

    return values;
}

export function ApiPeruHubCard({ profile, consultarUrl, disabled = false, onResult }: Props) {
    const fields = useMemo(() => fieldsOf(profile), [profile]);
    const [values, setValues] = useState<Record<string, string>>(() => emptyValues(fields));
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const Icon = ICONS[profile.icon] ?? Search;

    const setField = (name: string, value: string) => {
        setValues((prev) => ({ ...prev, [name]: value }));
        setError(null);
    };

    const onSubmit = async (e: FormEvent) => {
        e.preventDefault();
        if (disabled || loading) {
            return;
        }

        for (const field of fields) {
            const raw = (values[field.name] ?? '').trim();
            if (field.required && raw === '') {
                setError(`Completa «${field.label}».`);

                return;
            }

            if (field.pattern && raw !== '') {
                try {
                    if (!new RegExp(field.pattern).test(raw)) {
                        setError(`Formato inválido en «${field.label}».`);

                        return;
                    }
                } catch {
                    // ignore
                }
            }
        }

        setLoading(true);
        setError(null);

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
                    profile: profile.id,
                    payload: values,
                }),
            });

            const body = (await res.json()) as {
                success?: boolean;
                message?: string;
                code?: string;
                data?: ApiPeruPerfilPayload;
            };

            if (!res.ok || !body.success || !body.data) {
                toastManager.error({
                    title:
                        body.code === 'not_configured'
                            ? 'Token ApiPerú no configurado'
                            : body.code === 'rate_limit'
                              ? 'Límite de consultas alcanzado'
                              : (body.message ?? 'No se pudo consultar'),
                });

                return;
            }

            onResult(body.data);

            if (body.data.ok_count === 0) {
                toastManager.error({
                    title: 'Sin resultados útiles',
                    description: 'Ninguna fuente devolvió datos. Revisa el dato o tu plan ApiPerú.',
                });
            } else if (body.data.fail_count > 0) {
                toastManager.success({
                    title: `${body.data.ok_count} fuentes OK`,
                    description: `${body.data.fail_count} sin datos (puede ser cuota o endpoint no disponible).`,
                });
            } else {
                toastManager.success({ title: 'Ficha completa lista' });
            }
        } catch {
            toastManager.error({ title: 'Error de red al consultar ApiPerú' });
        } finally {
            setLoading(false);
        }
    };

    return (
        <SectionCard
            title={profile.label}
            description={profile.description}
            icon={Icon}
            badge={
                <span className="rounded-md bg-muted px-2 py-0.5 text-[11px] tabular-nums text-muted-foreground">
                    {profile.endpoint_keys.length} fuentes
                </span>
            }
        >
            <form onSubmit={(e) => void onSubmit(e)} className="flex flex-col gap-4">
                {fields.length > 0 ? (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        {fields.map((field) => {
                            const id = `hub-${profile.id}-${field.name}`;
                            const spanFull =
                                field.type === 'textarea' ||
                                fields.length === 1 ||
                                field.type === 'date';

                            return (
                                <FormField
                                    key={field.name}
                                    id={id}
                                    label={field.label}
                                    required={field.required}
                                    hint={field.hint ?? undefined}
                                    className={spanFull ? 'sm:col-span-2' : undefined}
                                >
                                    <ApiPeruCountedField
                                        id={id}
                                        as={field.type === 'textarea' ? 'textarea' : 'input'}
                                        type={field.type === 'date' ? 'date' : 'text'}
                                        value={values[field.name] ?? ''}
                                        onChange={(v) => setField(field.name, v)}
                                        placeholder={field.placeholder ?? undefined}
                                        maxLength={field.max_length}
                                        disabled={disabled || loading}
                                        inputMode={
                                            field.name.includes('dni') ||
                                            field.name.includes('ruc') ||
                                            field.name === 'numero' ||
                                            field.name === 'monto'
                                                ? 'numeric'
                                                : undefined
                                        }
                                    />
                                </FormField>
                            );
                        })}
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Sin parámetros. Pulsa consultar para obtener la respuesta.
                    </p>
                )}

                {error ? (
                    <p className="text-sm text-destructive" role="alert">
                        {error}
                    </p>
                ) : null}

                <div className="flex justify-end">
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
                        {loading
                            ? `Consultando ${profile.endpoint_keys.length}…`
                            : 'Consultar ficha completa'}
                    </Button>
                </div>
            </form>
        </SectionCard>
    );
}
