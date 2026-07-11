import { useForm } from '@inertiajs/react';
import { Loader2, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { toastManager } from '@/lib/toast';
import { enqueueIfOffline } from '@/lib/offline/enqueue-if-offline';
import { useOfflineSync } from '@/hooks/use-offline-sync';
import inventario from '@/routes/inventario';
import type { ProveedorFila } from '../types';

type ProveedorFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    proveedor: ProveedorFila | null;
};

type FormData = {
    ruc: string;
    razon_social: string;
    direccion: string;
    ubigeo_sunat: string;
    estado_sunat: string;
    condicion_sunat: string;
    telefono: string;
    email: string;
    notas: string;
    activo: boolean;
};

const empty: FormData = {
    ruc: '',
    razon_social: '',
    direccion: '',
    ubigeo_sunat: '',
    estado_sunat: '',
    condicion_sunat: '',
    telefono: '',
    email: '',
    notas: '',
    activo: true,
};

function soloDigitosRuc(value: string): string {
    return value.replace(/\D/g, '').slice(0, 11);
}

export function ProveedorFormModal({ open, onOpenChange, proveedor }: ProveedorFormModalProps) {
    const { t } = useTranslation(['proveedores-inventario', 'common', 'offline']);
    const { refreshPending } = useOfflineSync();
    const isEdit = proveedor !== null;
    const [consultandoRuc, setConsultandoRuc] = useState(false);

    const { data, setData, post, put, processing, errors, reset, clearErrors, transform } = useForm<FormData>(empty);

    useEffect(() => {
        transform((form) => ({
            ...form,
            ruc: soloDigitosRuc(form.ruc),
        }));
    }, [transform]);

    useEffect(() => {
        if (!open) {
            return;
        }

        if (!proveedor) {
            reset();
            clearErrors();

            return;
        }

        setData({
            ruc: proveedor.ruc,
            razon_social: proveedor.razon_social,
            direccion: proveedor.direccion ?? '',
            ubigeo_sunat: proveedor.ubigeo_sunat ?? '',
            estado_sunat: proveedor.estado_sunat ?? '',
            condicion_sunat: proveedor.condicion_sunat ?? '',
            telefono: proveedor.telefono ?? '',
            email: proveedor.email ?? '',
            notas: proveedor.notas ?? '',
            activo: proveedor.activo,
        });
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, proveedor?.id]);

    const onConsultarRuc = async () => {
        const ruc = soloDigitosRuc(data.ruc);

        if (ruc.length !== 11) {
            toastManager.error({ title: t('form.consultar_invalid') });

            return;
        }

        setConsultandoRuc(true);

        try {
            const url = `${inventario.proveedores.consultaRuc.url()}?ruc=${encodeURIComponent(ruc)}`;
            const res = await fetch(url, {
                method: 'GET',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const body = (await res.json()) as {
                success?: boolean;
                message?: string;
                code?: string;
                data?: {
                    ruc: string;
                    razon_social: string;
                    direccion?: string | null;
                    ubigeo_sunat?: string | null;
                    estado_sunat?: string | null;
                    condicion_sunat?: string | null;
                };
            };

            if (!res.ok || !body.success || !body.data) {
                const title =
                    res.status === 429 || body.code === 'rate_limit'
                        ? t('form.consultar_rate_limit')
                        : (body.message ?? t('form.consultar_error'));
                toastManager.error({ title });

                return;
            }

            const d = body.data;
            setData((prev) => ({
                ...prev,
                ruc: d.ruc ?? ruc,
                razon_social: d.razon_social ?? prev.razon_social,
                direccion: typeof d.direccion === 'string' ? d.direccion : prev.direccion,
                ubigeo_sunat: typeof d.ubigeo_sunat === 'string' ? d.ubigeo_sunat : prev.ubigeo_sunat,
                estado_sunat: typeof d.estado_sunat === 'string' ? d.estado_sunat : prev.estado_sunat,
                condicion_sunat: typeof d.condicion_sunat === 'string' ? d.condicion_sunat : prev.condicion_sunat,
            }));
        } catch {
            toastManager.error({ title: t('form.consultar_error') });
        } finally {
            setConsultandoRuc(false);
        }
    };

    const onSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const onSuccess = () => {
            onOpenChange(false);
            reset();
            clearErrors();
        };

        if (isEdit && proveedor) {
            put(inventario.proveedores.update.url({ proveedor: proveedor.id }), {
                preserveScroll: true,
                onSuccess,
            });

            return;
        }

        void (async () => {
            const queued = await enqueueIfOffline(
                'inventario.proveedor.create',
                { ...data },
                {
                    refreshPending,
                    onSuccess,
                    title: t('offline:proveedor_inventario.queued_title'),
                    description: t('offline:proveedor_inventario.queued_body'),
                },
            );

            if (queued) {
                return;
            }

            post(inventario.proveedores.store.url(), { preserveScroll: true, onSuccess });
        })();
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('form.title_edit') : t('form.title_create')}
            description={t('description')}
            onSubmit={onSubmit}
            footer={
                <>
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={processing}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing && <Loader2 className="mr-2 size-4 animate-spin" />}
                        {isEdit ? t('common:actions.save') : t('common:actions.create')}
                    </Button>
                </>
            }
        >
            <div className="grid gap-4">
                <FormField id="prov-ruc" label={t('form.ruc')} error={errors.ruc}>
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-stretch">
                        <Input
                            id="prov-ruc"
                            className="min-w-0 sm:flex-1"
                            inputMode="numeric"
                            autoComplete="off"
                            maxLength={11}
                            value={data.ruc}
                            onChange={(e) => setData('ruc', soloDigitosRuc(e.target.value))}
                            aria-invalid={Boolean(errors.ruc)}
                        />
                        <Button
                            type="button"
                            variant="secondary"
                            className="shrink-0 gap-2 sm:w-auto"
                            disabled={consultandoRuc || processing}
                            onClick={() => void onConsultarRuc()}
                        >
                            {consultandoRuc ? (
                                <Loader2 className="size-4 animate-spin" aria-hidden />
                            ) : (
                                <Search className="size-4" aria-hidden />
                            )}
                            {consultandoRuc ? t('form.consultar_loading') : t('form.consultar_ruc')}
                        </Button>
                    </div>
                </FormField>

                <FormField id="prov-rs" label={t('form.razon_social')} error={errors.razon_social}>
                    <Input
                        id="prov-rs"
                        value={data.razon_social}
                        onChange={(e) => setData('razon_social', e.target.value)}
                        aria-invalid={Boolean(errors.razon_social)}
                    />
                </FormField>

                <FormField id="prov-dir" label={t('form.direccion')} error={errors.direccion}>
                    <Textarea
                        id="prov-dir"
                        rows={2}
                        value={data.direccion}
                        onChange={(e) => setData('direccion', e.target.value)}
                        aria-invalid={Boolean(errors.direccion)}
                    />
                </FormField>

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField id="prov-ubi" label={t('form.ubigeo')} error={errors.ubigeo_sunat}>
                        <Input
                            id="prov-ubi"
                            inputMode="numeric"
                            maxLength={6}
                            value={data.ubigeo_sunat}
                            onChange={(e) => setData('ubigeo_sunat', e.target.value.replace(/\D/g, '').slice(0, 6))}
                            aria-invalid={Boolean(errors.ubigeo_sunat)}
                        />
                    </FormField>
                    <div className="grid gap-2 text-sm text-muted-foreground">
                        <span className="font-medium text-foreground">{t('columns.sunat')}</span>
                        <span>
                            {data.estado_sunat || data.condicion_sunat
                                ? t('sunat_resumen', {
                                      estado: data.estado_sunat || '—',
                                      condicion: data.condicion_sunat || '—',
                                  })
                                : '—'}
                        </span>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField id="prov-tel" label={t('form.telefono')} error={errors.telefono}>
                        <Input
                            id="prov-tel"
                            value={data.telefono}
                            onChange={(e) => setData('telefono', e.target.value)}
                            aria-invalid={Boolean(errors.telefono)}
                        />
                    </FormField>
                    <FormField id="prov-mail" label={t('form.email')} error={errors.email}>
                        <Input
                            id="prov-mail"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            aria-invalid={Boolean(errors.email)}
                        />
                    </FormField>
                </div>

                <FormField id="prov-notas" label={t('form.notas')} error={errors.notas}>
                    <Textarea
                        id="prov-notas"
                        rows={2}
                        value={data.notas}
                        onChange={(e) => setData('notas', e.target.value)}
                        aria-invalid={Boolean(errors.notas)}
                    />
                </FormField>

                <FormField id="prov-activo" label={t('form.activo')} error={errors.activo}>
                    <label htmlFor="prov-activo" className="inline-flex cursor-pointer items-center gap-3">
                        <Checkbox
                            id="prov-activo"
                            checked={data.activo}
                            onCheckedChange={(checked) => setData('activo', Boolean(checked))}
                        />
                    </label>
                </FormField>
            </div>
        </FormModal>
    );
}
