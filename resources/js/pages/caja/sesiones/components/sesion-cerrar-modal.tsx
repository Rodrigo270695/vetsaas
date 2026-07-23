import { useForm } from '@inertiajs/react';
import { AlertTriangle, Loader2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import caja from '@/routes/caja';
import { arqueo as arqueoRoute } from '@/routes/caja/sesiones';
import type { QueryParams } from '@/wayfinder';
import { ArqueoResumen } from './arqueo-resumen';
import { arqueoCsrfToken, formatArqueoMoney, type ArqueoPayload } from './arqueo-types';
import type { CajaSesionRow } from '../types';

type SesionCerrarModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    sesion: CajaSesionRow | null;
    listQuery: QueryParams;
};

type FormData = {
    saldo_cierre_efectivo: string;
    notas: string;
};

const empty: FormData = {
    saldo_cierre_efectivo: '',
    notas: '',
};

export function SesionCerrarModal({ open, onOpenChange, sesion, listQuery }: SesionCerrarModalProps) {
    const { t, i18n } = useTranslation('caja');
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<FormData>(empty);
    const [arqueo, setArqueo] = useState<ArqueoPayload | null>(null);
    const [loadingArqueo, setLoadingArqueo] = useState(false);
    const [arqueoError, setArqueoError] = useState<string | null>(null);

    useEffect(() => {
        if (!open || !sesion) {
            return;
        }

        reset();
        clearErrors();
        setData(empty);
        setArqueo(null);
        setArqueoError(null);
        setLoadingArqueo(true);

        const url = arqueoRoute.url({ caja_sesion: sesion.id });
        const token = arqueoCsrfToken();

        void fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
            },
        })
            .then(async (res) => {
                if (!res.ok) {
                    throw new Error('arqueo_failed');
                }

                return res.json() as Promise<{ arqueo: ArqueoPayload }>;
            })
            .then((json) => {
                setArqueo(json.arqueo);
                if (json.arqueo.efectivo_esperado) {
                    setData('saldo_cierre_efectivo', json.arqueo.efectivo_esperado);
                }
            })
            .catch(() => {
                setArqueoError(t('sesiones.dialog_cerrar.arqueo_error'));
            })
            .finally(() => {
                setLoadingArqueo(false);
            });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, sesion?.id]);

    const moneda = arqueo?.moneda ?? sesion?.moneda ?? 'PEN';
    const locale = i18n.language?.startsWith('en') ? 'en-US' : 'es-PE';

    const diferenciaLive = useMemo(() => {
        if (!arqueo) {
            return null;
        }

        const esperado = Number(arqueo.efectivo_esperado);
        const contado = Number(data.saldo_cierre_efectivo);
        if (Number.isNaN(esperado) || Number.isNaN(contado) || data.saldo_cierre_efectivo.trim() === '') {
            return null;
        }

        return (contado - esperado).toFixed(2);
    }, [arqueo, data.saldo_cierre_efectivo]);

    const diffTone =
        diferenciaLive === null
            ? 'muted'
            : Number(diferenciaLive) === 0
              ? 'ok'
              : Number(diferenciaLive) > 0
                ? 'over'
                : 'short';

    const onSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        if (!sesion) {
            return;
        }

        const actionUrl = caja.sesiones.cerrar.url({ caja_sesion: sesion.id }, { query: listQuery });
        post(actionUrl, {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                reset();
                clearErrors();
                setArqueo(null);
            },
        });
    };

    return (
        <FormModal
            open={open && sesion !== null}
            onOpenChange={onOpenChange}
            size="lg"
            title={t('sesiones.dialog_cerrar.title')}
            description={t('sesiones.dialog_cerrar.description')}
            onSubmit={onSubmit}
            footer={
                <>
                    <Button type="button" variant="outline" className="cursor-pointer" onClick={() => onOpenChange(false)}>
                        {t('sesiones.dialog_cerrar.cancel')}
                    </Button>
                    <Button
                        type="submit"
                        disabled={processing || !sesion || loadingArqueo}
                        className="cursor-pointer gap-2"
                    >
                        {processing ? <Loader2 className="size-4 animate-spin" aria-hidden /> : null}
                        {t('sesiones.dialog_cerrar.submit')}
                    </Button>
                </>
            }
        >
            <div className="flex w-full min-w-0 flex-col gap-5">
                {loadingArqueo ? (
                    <div className="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground">
                        <Loader2 className="size-4 animate-spin" />
                        {t('sesiones.dialog_cerrar.loading_arqueo')}
                    </div>
                ) : null}

                {arqueoError ? (
                    <div className="flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive">
                        <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                        <span>{arqueoError}</span>
                    </div>
                ) : null}

                {arqueo ? (
                    <ArqueoResumen
                        arqueo={arqueo}
                        diferenciaOverride={diferenciaLive}
                        diffTone={diffTone}
                    />
                ) : null}

                <FormField
                    id="cerrar-saldo"
                    label={t('sesiones.fields.saldo_cierre')}
                    error={errors.saldo_cierre_efectivo}
                    hint={t('sesiones.dialog_cerrar.contado_hint')}
                >
                    <Input
                        type="number"
                        inputMode="decimal"
                        min={0}
                        step="0.01"
                        value={data.saldo_cierre_efectivo}
                        onChange={(ev) => setData('saldo_cierre_efectivo', ev.target.value)}
                        className="h-11 text-base font-semibold tabular-nums"
                        autoFocus
                    />
                </FormField>

                <FormField id="cerrar-notas" label={t('sesiones.fields.notas_cierre')} error={errors.notas}>
                    <Textarea
                        value={data.notas}
                        onChange={(ev) => setData('notas', ev.target.value)}
                        rows={2}
                        className="resize-y"
                        placeholder={t('sesiones.dialog_cerrar.notas_placeholder')}
                    />
                </FormField>

                {arqueo && data.saldo_cierre_efectivo.trim() !== '' ? (
                    <p className="sr-only">
                        {formatArqueoMoney(diferenciaLive, moneda, locale)}
                    </p>
                ) : null}
            </div>
        </FormModal>
    );
}
