import { TZDate } from '@date-fns/tz';
import { useForm, usePage } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, FormSection } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { InternamientoSignoRow } from '../types';

const ZONA_PERU = 'America/Lima';
const controlClass = 'h-10 w-full min-w-0';
const selectClass =
    'h-10 w-full min-w-0 cursor-pointer rounded-md border border-input bg-card/70 px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/25 disabled:cursor-not-allowed disabled:opacity-50';

const MUCOSAS = ['rosadas', 'rosadas_palidas', 'palidas', 'cianoticas', 'ictericas', 'congestionadas', 'hemorragicas', 'secas'] as const;
const VOMITO = ['no', 'si', 'alimentario', 'bilioso', 'hematico', 'espuma'] as const;
const DIARREA = ['no', 'si', 'moco', 'hemorragica', 'mixta'] as const;
const HECES = ['no', 'si', 'escasa', 'normal', 'abundante'] as const;
const ALIMENTO = ['no', 'poco', 'normal', 'sonda'] as const;
const AGUA = ['no', 'poco', 'normal'] as const;

type FormShape = {
    registrado_at: string;
    mucosas: string;
    mucosas_libre: string;
    glucemia_mg_dl: string;
    orina_ml: string;
    vomito: string;
    diarrea: string;
    heces: string;
    bristol: string;
    alimento: string;
    agua: string;
    notas: string;
};

function zonaPeru(timeZone: string | undefined): string {
    const zona = (timeZone ?? '').trim();

    if (zona === '' || zona.toUpperCase() === 'UTC' || zona === 'Etc/UTC') {
        return ZONA_PERU;
    }

    return zona;
}

function toDatetimeLocalValue(instant: Date | number, timeZone: string): string {
    const d = new TZDate(instant, timeZone);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function parseIsoToDatetimeLocal(iso: string, timeZone: string): string {
    const d = new TZDate(iso, timeZone);

    if (Number.isNaN(d.getTime())) {
        return toDatetimeLocalValue(Date.now(), timeZone);
    }

    return toDatetimeLocalValue(d, timeZone);
}

function soloDecimal(value: string): string {
    const normalizado = value.replace(',', '.');
    let salida = '';
    let tienePunto = false;

    for (const char of normalizado) {
        if (char >= '0' && char <= '9') {
            salida += char;
            continue;
        }

        if (char === '.' && !tienePunto) {
            tienePunto = true;
            salida += '.';
        }
    }

    return salida;
}

function esDigito(data: string | null, decimal: boolean): boolean {
    if (data == null || data === '') {
        return true;
    }

    return decimal ? /^[\d.,]+$/.test(data) : /^\d+$/.test(data);
}

function vacio(): FormShape {
    return {
        registrado_at: '',
        mucosas: '',
        mucosas_libre: '',
        glucemia_mg_dl: '',
        orina_ml: '',
        vomito: '',
        diarrea: '',
        heces: '',
        bristol: '',
        alimento: '',
        agua: '',
        notas: '',
    };
}

function desdeSigno(signo: InternamientoSignoRow, timeZone: string): FormShape {
    const mucosas = signo.mucosas ?? '';
    const conocida = (MUCOSAS as readonly string[]).includes(mucosas);

    return {
        registrado_at: parseIsoToDatetimeLocal(signo.registrado_at, timeZone),
        mucosas: conocida ? mucosas : '',
        mucosas_libre: conocida ? '' : mucosas,
        glucemia_mg_dl: signo.glucemia_mg_dl ?? '',
        orina_ml: signo.orina_ml ?? '',
        vomito: signo.vomito ?? '',
        diarrea: signo.diarrea ?? '',
        heces: signo.heces ?? '',
        bristol: signo.bristol != null ? String(signo.bristol) : '',
        alimento: signo.alimento ?? '',
        agua: signo.agua ?? '',
        notas: signo.notas ?? '',
    };
}

type Opcion = readonly string[];

function SelectCampo({
    id,
    value,
    opciones,
    grupo,
    disabled,
    onChange,
}: {
    id: string;
    value: string;
    opciones: Opcion;
    grupo: string;
    disabled: boolean;
    onChange: (value: string) => void;
}) {
    const { t } = useTranslation('hospitalizacion');

    return (
        <select id={id} className={selectClass} value={value} disabled={disabled} onChange={(e) => onChange(e.target.value)}>
            <option value="">{t('signos.opcion_vacia')}</option>
            {opciones.map((opcion) => (
                <option key={opcion} value={opcion}>
                    {t(`signos.${grupo}.${opcion}`)}
                </option>
            ))}
        </select>
    );
}

export type SignoFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    internamientoId: string;
    signo: InternamientoSignoRow | null;
};

export function SignoFormModal({ open, onOpenChange, internamientoId, signo }: SignoFormModalProps) {
    const { t } = useTranslation(['hospitalizacion', 'common']);
    const zona = zonaPeru(usePage().props.timezone);
    const isEdit = signo !== null;

    const { data, setData, post, put, processing, errors, clearErrors, transform, setDefaults, reset } = useForm<FormShape>(
        vacio(),
    );
    const initialRef = useRef<FormShape>(vacio());

    useEffect(() => {
        transform((raw) => {
            const libre = raw.mucosas_libre.trim();
            const glucemia = soloDecimal(raw.glucemia_mg_dl);
            const orina = soloDecimal(raw.orina_ml);

            return {
                registrado_at: raw.registrado_at,
                mucosas: libre !== '' ? libre : raw.mucosas || null,
                glucemia_mg_dl: glucemia === '' ? null : glucemia,
                orina_ml: orina === '' ? null : orina,
                vomito: raw.vomito || null,
                diarrea: raw.diarrea || null,
                heces: raw.heces || null,
                bristol: raw.bristol === '' ? null : Number.parseInt(raw.bristol, 10),
                alimento: raw.alimento || null,
                agua: raw.agua || null,
                notas: raw.notas.trim() === '' ? null : raw.notas.trim(),
            };
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (!open) {
            return;
        }

        clearErrors();
        const base = vacio();
        base.registrado_at = toDatetimeLocalValue(Date.now(), zona);
        const next = signo !== null ? desdeSigno(signo, zona) : base;
        initialRef.current = structuredClone(next);
        setData(next);
        setDefaults();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, signo?.id, signo, zona]);

    const handleClose = (next: boolean) => {
        if (!next) {
            reset();
            clearErrors();
        }

        onOpenChange(next);
    };

    const filtrarAntes = (event: FormEvent<HTMLInputElement>) => {
        const texto = (event.nativeEvent as InputEvent).data;

        if (!esDigito(texto, true)) {
            event.preventDefault();
        }
    };

    const err = (key: string): string | undefined => {
        const value = (errors as Record<string, string | undefined>)[key];

        return typeof value === 'string' ? value : undefined;
    };

    const hayAlguno = [
        data.mucosas,
        data.mucosas_libre,
        data.glucemia_mg_dl,
        data.orina_ml,
        data.vomito,
        data.diarrea,
        data.heces,
        data.bristol,
        data.alimento,
        data.agua,
        data.notas,
    ].some((value) => value.trim() !== '');

    const canSubmit = hayAlguno && data.registrado_at.trim() !== '' && !processing;

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const onSuccess = () => handleClose(false);

        if (isEdit && signo) {
            put(`/clinica/hospitalizacion/${internamientoId}/signos/${signo.id}`, {
                preserveScroll: true,
                onSuccess,
            });

            return;
        }

        post(`/clinica/hospitalizacion/${internamientoId}/signos`, {
            preserveScroll: true,
            onSuccess,
        });
    };

    return (
        <FormModal
            open={open}
            onOpenChange={handleClose}
            title={isEdit ? t('signos.title_edit') : t('signos.title_create')}
            size="lg"
            onSubmit={onSubmit}
            footer={
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={() => handleClose(false)} disabled={processing}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" disabled={!canSubmit} className="gap-2">
                        {processing && <Loader2 className="size-4 animate-spin" />}
                        {t('common:actions.save')}
                    </Button>
                </div>
            }
        >
            <FormSection title={t('signos.registrado_at')} columns={2}>
                <FormField id="signo-fecha" label={t('signos.registrado_at')} required error={err('registrado_at')}>
                    <Input
                        id="signo-fecha"
                        type="datetime-local"
                        className={controlClass}
                        value={data.registrado_at}
                        onChange={(e) => setData('registrado_at', e.target.value)}
                        disabled={processing}
                    />
                </FormField>
            </FormSection>
            <FormSection title={t('signos.grupo')} columns={2}>
                <FormField id="signo-mucosas" label={t('signos.mucosas')} hint={t('signos.mucosas_hint')} error={err('mucosas')}>
                    <SelectCampo
                        id="signo-mucosas"
                        value={data.mucosas}
                        opciones={MUCOSAS}
                        grupo="mucosas_opcion"
                        disabled={processing}
                        onChange={(value) => setData('mucosas', value)}
                    />
                </FormField>
                <FormField id="signo-mucosas-libre" label={t('signos.mucosas_libre')} error={err('mucosas')}>
                    <Input
                        id="signo-mucosas-libre"
                        className={controlClass}
                        value={data.mucosas_libre}
                        onChange={(e) => setData('mucosas_libre', e.target.value)}
                        placeholder={t('signos.mucosas_libre_placeholder')}
                        disabled={processing}
                        maxLength={80}
                    />
                </FormField>
                <FormField id="signo-glucemia" label={t('signos.glucemia')} error={err('glucemia_mg_dl')}>
                    <Input
                        id="signo-glucemia"
                        inputMode="decimal"
                        className={controlClass}
                        value={data.glucemia_mg_dl}
                        onBeforeInput={filtrarAntes}
                        onChange={(e) => setData('glucemia_mg_dl', soloDecimal(e.target.value))}
                        placeholder={t('signos.glucemia_placeholder')}
                        disabled={processing}
                    />
                </FormField>
                <FormField id="signo-orina" label={t('signos.orina')} error={err('orina_ml')}>
                    <Input
                        id="signo-orina"
                        inputMode="decimal"
                        className={controlClass}
                        value={data.orina_ml}
                        onBeforeInput={filtrarAntes}
                        onChange={(e) => setData('orina_ml', soloDecimal(e.target.value))}
                        placeholder={t('signos.orina_placeholder')}
                        disabled={processing}
                    />
                </FormField>
                <FormField id="signo-vomito" label={t('signos.vomito')} error={err('vomito')}>
                    <SelectCampo
                        id="signo-vomito"
                        value={data.vomito}
                        opciones={VOMITO}
                        grupo="vomito_opcion"
                        disabled={processing}
                        onChange={(value) => setData('vomito', value)}
                    />
                </FormField>
                <FormField id="signo-diarrea" label={t('signos.diarrea')} error={err('diarrea')}>
                    <SelectCampo
                        id="signo-diarrea"
                        value={data.diarrea}
                        opciones={DIARREA}
                        grupo="diarrea_opcion"
                        disabled={processing}
                        onChange={(value) => setData('diarrea', value)}
                    />
                </FormField>
                <FormField id="signo-heces" label={t('signos.heces')} error={err('heces')}>
                    <SelectCampo
                        id="signo-heces"
                        value={data.heces}
                        opciones={HECES}
                        grupo="heces_opcion"
                        disabled={processing}
                        onChange={(value) => setData('heces', value)}
                    />
                </FormField>
                <FormField id="signo-bristol" label={t('signos.bristol')} error={err('bristol')}>
                    <SelectCampo
                        id="signo-bristol"
                        value={data.bristol}
                        opciones={['1', '2', '3', '4', '5', '6', '7']}
                        grupo="bristol_opcion"
                        disabled={processing}
                        onChange={(value) => setData('bristol', value)}
                    />
                </FormField>
                <FormField id="signo-alimento" label={t('signos.alimento')} error={err('alimento')}>
                    <SelectCampo
                        id="signo-alimento"
                        value={data.alimento}
                        opciones={ALIMENTO}
                        grupo="alimento_opcion"
                        disabled={processing}
                        onChange={(value) => setData('alimento', value)}
                    />
                </FormField>
                <FormField id="signo-agua" label={t('signos.agua')} error={err('agua')}>
                    <SelectCampo
                        id="signo-agua"
                        value={data.agua}
                        opciones={AGUA}
                        grupo="agua_opcion"
                        disabled={processing}
                        onChange={(value) => setData('agua', value)}
                    />
                </FormField>
                <FormField id="signo-notas" label={t('signos.notas')} error={err('notas')} className="sm:col-span-2">
                    <Textarea
                        id="signo-notas"
                        className="min-h-16 w-full"
                        value={data.notas}
                        onChange={(e) => setData('notas', e.target.value)}
                        placeholder={t('signos.notas_placeholder')}
                        disabled={processing}
                    />
                </FormField>
            </FormSection>
        </FormModal>
    );
}
