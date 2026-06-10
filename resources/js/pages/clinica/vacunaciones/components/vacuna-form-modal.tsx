import { useForm, usePage } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useMemo, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, SedeFormField } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import type { ComboboxOption } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { resolveDefaultSedeId } from '@/lib/default-sede';
import clinica from '@/routes/clinica';
import { formatAtendidoInAppTimezone } from '../../historias-clinicas/format-atendido';
import type {
    PacienteVacunaOpcion,
    SedeVacunaOpcion,
    UsuarioVacunaOpcion,
    VacunaAplicadaRow,
    VacunaPrefillCreate,
} from '../types';
import { VacunaProductoPicker, type VacunaProductoOption } from './vacuna-producto-picker';

const controlClass = 'h-10 w-full min-w-0';

function toDatetimeLocalValue(d: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function parseIsoToDatetimeLocal(iso: string): string {
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) {
        return toDatetimeLocalValue(new Date());
    }

    return toDatetimeLocalValue(d);
}

function isoDateToInput(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const s = value.slice(0, 10);

    return /^\d{4}-\d{2}-\d{2}$/.test(s) ? s : '';
}

function dateInputToPayload(value: string): string | null {
    const t = value.trim();

    return t === '' ? null : t;
}

function displayPropietario(p: PacienteVacunaOpcion['propietario']): string {
    if (!p) {
        return '';
    }
    if (p.razon_social) {
        return p.razon_social;
    }

    return [p.nombres, p.apellidos].filter(Boolean).join(' ');
}

export type VacunaFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    vacuna: VacunaAplicadaRow | null;
    pacientesOpciones: readonly PacienteVacunaOpcion[];
    usuariosOpciones: readonly UsuarioVacunaOpcion[];
    sedesOpciones: readonly SedeVacunaOpcion[];
    /** Desde `vacuna_prefill` del servidor al crear (p. ej. URL con prefill). */
    prefillCreate?: VacunaPrefillCreate | null;
};

type FormShape = {
    paciente_id: string;
    consulta_id: string;
    producto_id: string | null;
    categoria_registro: string;
    nombre_vacuna: string;
    esquema_antigenos: string;
    fecha_proxima_sugerida: string;
    aplicada_at: string;
    numero_dosis: string;
    lote: string;
    notas: string;
    veterinario_id: string | null;
    sede_id: string | null;
};

function emptyForm(
    defaultVetId: string | null,
    sedes: readonly SedeVacunaOpcion[],
): FormShape {
    return {
        paciente_id: '',
        consulta_id: '',
        producto_id: null,
        categoria_registro: 'vacuna',
        nombre_vacuna: '',
        esquema_antigenos: '',
        fecha_proxima_sugerida: '',
        aplicada_at: toDatetimeLocalValue(new Date()),
        numero_dosis: '',
        lote: '',
        notas: '',
        veterinario_id: defaultVetId,
        sede_id: resolveDefaultSedeId(sedes),
    };
}

function fromVacuna(v: VacunaAplicadaRow, defaultVetId: string | null): FormShape {
    return {
        paciente_id: v.paciente_id,
        consulta_id: v.consulta_id ?? '',
        producto_id: v.producto_id,
        categoria_registro: v.categoria_registro ?? 'vacuna',
        nombre_vacuna: v.nombre_vacuna,
        esquema_antigenos: v.esquema_antigenos ?? '',
        fecha_proxima_sugerida: isoDateToInput(v.fecha_proxima_sugerida),
        aplicada_at: parseIsoToDatetimeLocal(v.aplicada_at),
        numero_dosis: v.numero_dosis != null ? String(v.numero_dosis) : '',
        lote: v.lote ?? '',
        notas: v.notas ?? '',
        veterinario_id: v.veterinario_id ?? defaultVetId,
        sede_id: v.sede_id,
    };
}

export function VacunaFormModal({
    open,
    onOpenChange,
    vacuna,
    pacientesOpciones,
    usuariosOpciones,
    sedesOpciones,
    prefillCreate = null,
}: VacunaFormModalProps) {
    const { t } = useTranslation(['vacunaciones', 'common']);
    const authUser = usePage().props.auth?.user as { id?: string } | undefined;
    const { locale: appLocale, timezone: appTz } = usePage().props;
    const defaultVetId = authUser?.id ?? null;

    const { data, setData, post, put, processing, errors, clearErrors, transform, setDefaults } =
        useForm<FormShape>(emptyForm(defaultVetId, sedesOpciones));

    const isEdit = vacuna !== null;
    const lockPaciente = isEdit || Boolean(prefillCreate?.paciente_id);

    useEffect(() => {
        transform((raw) => {
            const r = raw;
            const nd = r.numero_dosis.trim();
            const ndVal = nd === '' ? null : Number.parseInt(nd, 10);

            return {
                paciente_id: r.paciente_id,
                consulta_id: r.consulta_id.trim() === '' ? null : r.consulta_id.trim(),
                producto_id: r.producto_id && r.producto_id !== '' ? r.producto_id : null,
                categoria_registro: r.categoria_registro,
                nombre_vacuna: r.nombre_vacuna.trim(),
                esquema_antigenos: r.esquema_antigenos.trim() === '' ? null : r.esquema_antigenos.trim(),
                fecha_proxima_sugerida: dateInputToPayload(r.fecha_proxima_sugerida),
                aplicada_at: r.aplicada_at,
                numero_dosis: nd === '' || ndVal === null || Number.isNaN(ndVal) ? null : ndVal,
                lote: r.lote.trim() === '' ? null : r.lote.trim(),
                notas: r.notas.trim() === '' ? null : r.notas.trim(),
                veterinario_id:
                    r.veterinario_id != null && r.veterinario_id !== '' ? r.veterinario_id : null,
                sede_id: r.sede_id != null && r.sede_id !== '' ? r.sede_id : null,
            };
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (!open) {
            return;
        }
        clearErrors();
        if (vacuna !== null) {
            setData(fromVacuna(vacuna, defaultVetId));
        } else {
            const base = emptyForm(defaultVetId, sedesOpciones);
            if (prefillCreate) {
                base.paciente_id = prefillCreate.paciente_id;
                base.consulta_id = prefillCreate.consulta_id ?? '';
            }
            setData(base);
        }
        setDefaults();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, vacuna?.id, defaultVetId, vacuna, prefillCreate?.paciente_id, prefillCreate?.consulta_id]);

    const pacienteComboboxOptions = useMemo<ComboboxOption[]>(
        () =>
            pacientesOpciones.map((p) => ({
                value: p.id,
                label: `${p.nombre} · ${displayPropietario(p.propietario) || '—'}`,
            })),
        [pacientesOpciones],
    );

    const onProductSelect = (opt: VacunaProductoOption | null) => {
        if (opt === null) {
            setData('producto_id', null);

            return;
        }
        setData('producto_id', opt.id);
        if (data.nombre_vacuna.trim() === '') {
            setData('nombre_vacuna', opt.nombre);
        }
    };

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (isEdit && vacuna) {
            put(clinica.vacunaciones.update({ vacuna_aplicada: vacuna.id }).url, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });

            return;
        }

        post(clinica.vacunaciones.store().url, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('form.title_edit') : t('form.title_create')}
            description={isEdit ? undefined : t('description')}
            onSubmit={onSubmit}
            footer={
                <>
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={processing}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" disabled={processing} className="gap-2">
                        {processing && <Loader2 className="size-4 animate-spin" aria-hidden />}
                        {isEdit ? t('form.submit_edit') : t('form.submit_create')}
                    </Button>
                </>
            }
        >
            <div className="grid gap-4">
                <FormField
                    id="vf-paciente"
                    label={t('form.paciente')}
                    required
                    error={errors.paciente_id as string | undefined}
                >
                    <Combobox
                        id="vf-paciente"
                        options={pacienteComboboxOptions}
                        value={data.paciente_id === '' ? null : data.paciente_id}
                        onChange={(v) => setData('paciente_id', v ?? '')}
                        placeholder={t('form.paciente_placeholder')}
                        searchPlaceholder={t('form.paciente_search')}
                        emptyMessage={t('form.paciente_empty')}
                        disabled={lockPaciente || processing}
                        aria-invalid={Boolean(errors.paciente_id)}
                    />
                </FormField>

                {prefillCreate?.consulta_id && !isEdit ? (
                    <p className="rounded-md border border-border/60 bg-muted/25 px-3 py-2 text-sm text-muted-foreground">
                        {t('form.consulta_vinculada_abierta')}
                    </p>
                ) : null}
                {isEdit && vacuna?.consulta_id ? (
                    vacuna.consulta?.atendido_at ? (
                        <p className="rounded-md border border-border/60 bg-muted/25 px-3 py-2 text-sm text-muted-foreground">
                            {t('form.consulta_vinculada_visita', {
                                fecha: formatAtendidoInAppTimezone(
                                    vacuna.consulta.atendido_at,
                                    String(appLocale ?? 'es'),
                                    String(appTz ?? 'UTC'),
                                ),
                            })}
                        </p>
                    ) : null
                ) : null}

                <FormField
                    id="vf-producto"
                    label={t('form.producto_placeholder')}
                    error={errors.producto_id as string | undefined}
                >
                    <VacunaProductoPicker
                        id="vf-producto"
                        value={data.producto_id}
                        labelResolved={
                            data.producto_id != null && data.nombre_vacuna.trim() !== ''
                                ? data.nombre_vacuna
                                : null
                        }
                        onSelect={onProductSelect}
                        disabled={processing}
                        aria-invalid={Boolean(errors.producto_id)}
                    />
                </FormField>

                <FormField
                    id="vf-categoria"
                    label={t('form.categoria_registro')}
                    required
                    error={errors.categoria_registro as string | undefined}
                >
                    <Select
                        value={data.categoria_registro}
                        onValueChange={(v) => setData('categoria_registro', v)}
                    >
                        <SelectTrigger id="vf-categoria" className={controlClass}>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="vacuna">{t('form.categoria_vacuna')}</SelectItem>
                            <SelectItem value="desparasitacion">
                                {t('form.categoria_desparasitacion')}
                            </SelectItem>
                            <SelectItem value="otro">{t('form.categoria_otro')}</SelectItem>
                        </SelectContent>
                    </Select>
                </FormField>

                <FormField
                    id="vf-nombre"
                    label={t('form.nombre_vacuna')}
                    required
                    error={errors.nombre_vacuna as string | undefined}
                >
                    <Input
                        id="vf-nombre"
                        className={controlClass}
                        value={data.nombre_vacuna}
                        onChange={(e) => setData('nombre_vacuna', e.target.value)}
                        aria-invalid={Boolean(errors.nombre_vacuna)}
                    />
                </FormField>
                <p className="text-xs text-muted-foreground">{t('form.nombre_hint')}</p>

                <FormField
                    id="vf-esquema"
                    label={t('form.esquema_antigenos')}
                    error={errors.esquema_antigenos as string | undefined}
                >
                    <Textarea
                        id="vf-esquema"
                        rows={2}
                        className="resize-y text-sm"
                        value={data.esquema_antigenos}
                        onChange={(e) => setData('esquema_antigenos', e.target.value)}
                        aria-invalid={Boolean(errors.esquema_antigenos)}
                    />
                </FormField>
                <p className="text-xs text-muted-foreground">{t('form.esquema_hint')}</p>

                <FormField
                    id="vf-proxima"
                    label={t('form.fecha_proxima_sugerida')}
                    error={errors.fecha_proxima_sugerida as string | undefined}
                >
                    <Input
                        id="vf-proxima"
                        type="date"
                        className={controlClass}
                        value={data.fecha_proxima_sugerida}
                        onChange={(e) => setData('fecha_proxima_sugerida', e.target.value)}
                        aria-invalid={Boolean(errors.fecha_proxima_sugerida)}
                    />
                </FormField>
                <p className="text-xs text-muted-foreground">{t('form.fecha_proxima_hint')}</p>

                <FormField
                    id="vf-fecha"
                    label={t('form.aplicada_at')}
                    required
                    error={errors.aplicada_at as string | undefined}
                >
                    <Input
                        id="vf-fecha"
                        type="datetime-local"
                        className={controlClass}
                        value={data.aplicada_at}
                        onChange={(e) => setData('aplicada_at', e.target.value)}
                        aria-invalid={Boolean(errors.aplicada_at)}
                    />
                </FormField>

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="vf-dosis"
                        label={t('form.numero_dosis')}
                        error={errors.numero_dosis as string | undefined}
                    >
                        <Input
                            id="vf-dosis"
                            type="number"
                            min={1}
                            max={99}
                            className={controlClass}
                            value={data.numero_dosis}
                            onChange={(e) => setData('numero_dosis', e.target.value)}
                            aria-invalid={Boolean(errors.numero_dosis)}
                        />
                    </FormField>
                    <FormField id="vf-lote" label={t('form.lote')} error={errors.lote as string | undefined}>
                        <Input
                            id="vf-lote"
                            className={controlClass}
                            value={data.lote}
                            onChange={(e) => setData('lote', e.target.value)}
                            aria-invalid={Boolean(errors.lote)}
                        />
                    </FormField>
                </div>

                <FormField
                    id="vf-vet"
                    label={t('form.veterinario')}
                    error={errors.veterinario_id as string | undefined}
                >
                    <Select
                        value={data.veterinario_id ?? '__none__'}
                        onValueChange={(v) =>
                            setData('veterinario_id', v === '__none__' ? null : v)
                        }
                    >
                        <SelectTrigger id="vf-vet" className={controlClass}>
                            <SelectValue placeholder={t('form.veterinario_placeholder')} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__none__">{t('form.veterinario_placeholder')}</SelectItem>
                            {usuariosOpciones.map((u) => (
                                <SelectItem key={u.id} value={u.id}>
                                    {u.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>

                <SedeFormField
                    id="vf-sede"
                    label={t('form.sede')}
                    sedes={sedesOpciones}
                    value={data.sede_id}
                    onChange={(sedeId) => setData('sede_id', sedeId)}
                    required={Boolean(data.producto_id)}
                    hint={data.producto_id ? t('form.sede_required_hint') : undefined}
                    error={errors.sede_id as string | undefined}
                    disabled={processing}
                    noneLabel={t('form.sede_placeholder')}
                    controlClassName={controlClass}
                    formatLabel={(s) => `${s.nombre} (${s.codigo})`}
                />

                <FormField id="vf-notas" label={t('form.notas')} error={errors.notas as string | undefined}>
                    <Textarea
                        id="vf-notas"
                        rows={3}
                        className="resize-y text-sm"
                        value={data.notas}
                        onChange={(e) => setData('notas', e.target.value)}
                        aria-invalid={Boolean(errors.notas)}
                    />
                </FormField>
            </div>
        </FormModal>
    );
}
