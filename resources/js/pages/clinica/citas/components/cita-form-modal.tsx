import { useForm, usePage } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect  } from 'react';
import type {FormEvent} from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal } from '@/components/forms';
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
import clinica from '@/routes/clinica';
import type { CitaRow, PacienteCitaOpcion, SedeCitaOpcion, UsuarioCitaOpcion } from '../types';

const controlClass = 'h-10 w-full min-w-0';

const CITA_ESTADOS = ['programada', 'confirmada', 'completada', 'cancelada', 'no_asistio'] as const;

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

function displayPropietario(p: PacienteCitaOpcion['propietario']): string {
    if (!p) {
        return '';
    }

    if (p.razon_social) {
        return p.razon_social;
    }

    return [p.nombres, p.apellidos].filter(Boolean).join(' ');
}

export type CitaFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    cita: CitaRow | null;
    pacientesOpciones: readonly PacienteCitaOpcion[];
    usuariosOpciones: readonly UsuarioCitaOpcion[];
    sedesOpciones: readonly SedeCitaOpcion[];
};

type FormShape = {
    paciente_id: string;
    inicio_at: string;
    duracion_minutos: string;
    estado: string;
    motivo: string;
    notas: string;
    veterinario_id: string | null;
    sede_id: string | null;
};

function emptyForm(defaultVetId: string | null): FormShape {
    return {
        paciente_id: '',
        inicio_at: toDatetimeLocalValue(new Date()),
        duracion_minutos: '30',
        estado: 'programada',
        motivo: '',
        notas: '',
        veterinario_id: defaultVetId,
        sede_id: null,
    };
}

function fromCita(c: CitaRow, defaultVetId: string | null): FormShape {
    return {
        paciente_id: c.paciente_id,
        inicio_at: parseIsoToDatetimeLocal(c.inicio_at),
        duracion_minutos: String(c.duracion_minutos),
        estado: c.estado,
        motivo: c.motivo ?? '',
        notas: c.notas ?? '',
        veterinario_id: c.veterinario_id ?? defaultVetId,
        sede_id: c.sede_id,
    };
}

export function CitaFormModal({
    open,
    onOpenChange,
    cita,
    pacientesOpciones,
    usuariosOpciones,
    sedesOpciones,
}: CitaFormModalProps) {
    const { t } = useTranslation(['citas', 'common']);
    const authUser = usePage().props.auth?.user as { id?: string } | undefined;
    const defaultVetId = authUser?.id ?? null;

    const { data, setData, post, put, processing, errors, clearErrors, transform, setDefaults } =
        useForm<FormShape>(emptyForm(defaultVetId));

    const isEdit = cita !== null;
    const lockPaciente = isEdit;

    useEffect(() => {
        transform((raw) => {
            const r = raw;
            const dm = r.duracion_minutos.trim();
            const dmVal = dm === '' ? NaN : Number.parseInt(dm, 10);

            return {
                paciente_id: r.paciente_id,
                inicio_at: r.inicio_at,
                duracion_minutos: Number.isNaN(dmVal) ? 30 : dmVal,
                estado: r.estado,
                motivo: r.motivo.trim() === '' ? null : r.motivo.trim(),
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

        if (cita !== null) {
            setData(fromCita(cita, defaultVetId));
        } else {
            setData(emptyForm(defaultVetId));
        }

        setDefaults();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, cita?.id, defaultVetId, cita]);

    const pacienteComboboxOptions: ComboboxOption[] = pacientesOpciones.map((p) => ({
        value: p.id,
        label: `${p.nombre} · ${displayPropietario(p.propietario) || '—'}`,
    }));

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();

        if (isEdit && cita) {
            put(clinica.citas.update({ cita: cita.id }).url, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });

            return;
        }

        post(clinica.citas.store().url, {
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
                    id="cf-paciente"
                    label={t('form.paciente')}
                    required
                    error={errors.paciente_id as string | undefined}
                >
                    <Combobox
                        id="cf-paciente"
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

                <FormField
                    id="cf-inicio"
                    label={t('form.inicio_at')}
                    required
                    error={errors.inicio_at as string | undefined}
                >
                    <Input
                        id="cf-inicio"
                        type="datetime-local"
                        className={controlClass}
                        value={data.inicio_at}
                        onChange={(e) => setData('inicio_at', e.target.value)}
                        aria-invalid={Boolean(errors.inicio_at)}
                        disabled={processing}
                    />
                </FormField>

                <FormField
                    id="cf-duracion"
                    label={t('form.duracion_minutos')}
                    required
                    error={errors.duracion_minutos as string | undefined}
                >
                    <Input
                        id="cf-duracion"
                        type="number"
                        min={5}
                        max={480}
                        className={controlClass}
                        value={data.duracion_minutos}
                        onChange={(e) => setData('duracion_minutos', e.target.value)}
                        aria-invalid={Boolean(errors.duracion_minutos)}
                        disabled={processing}
                    />
                </FormField>
                <p className="text-xs text-muted-foreground">{t('form.duracion_hint')}</p>

                {isEdit ? (
                    <FormField
                        id="cf-estado"
                        label={t('form.estado')}
                        required
                        error={errors.estado as string | undefined}
                    >
                        <Select
                            value={data.estado}
                            onValueChange={(v) => setData('estado', v)}
                            disabled={processing}
                        >
                            <SelectTrigger id="cf-estado" className={controlClass}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {CITA_ESTADOS.map((st) => (
                                    <SelectItem key={st} value={st}>
                                        {t(`estado.${st}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                ) : null}

                <FormField id="cf-motivo" label={t('form.motivo')} error={errors.motivo as string | undefined}>
                    <Textarea
                        id="cf-motivo"
                        rows={2}
                        className="resize-y text-sm"
                        value={data.motivo}
                        onChange={(e) => setData('motivo', e.target.value)}
                        aria-invalid={Boolean(errors.motivo)}
                        disabled={processing}
                    />
                </FormField>

                <FormField id="cf-notas" label={t('form.notas')} error={errors.notas as string | undefined}>
                    <Textarea
                        id="cf-notas"
                        rows={2}
                        className="resize-y text-sm"
                        value={data.notas}
                        onChange={(e) => setData('notas', e.target.value)}
                        aria-invalid={Boolean(errors.notas)}
                        disabled={processing}
                    />
                </FormField>

                <FormField
                    id="cf-vet"
                    label={t('form.veterinario')}
                    error={errors.veterinario_id as string | undefined}
                >
                    <Select
                        value={data.veterinario_id ?? '__none__'}
                        onValueChange={(v) => setData('veterinario_id', v === '__none__' ? null : v)}
                        disabled={processing}
                    >
                        <SelectTrigger id="cf-vet" className={controlClass}>
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

                <FormField id="cf-sede" label={t('form.sede')} error={errors.sede_id as string | undefined}>
                    <Select
                        value={data.sede_id ?? '__none__'}
                        onValueChange={(v) => setData('sede_id', v === '__none__' ? null : v)}
                        disabled={processing}
                    >
                        <SelectTrigger id="cf-sede" className={controlClass}>
                            <SelectValue placeholder={t('form.sede_placeholder')} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__none__">{t('form.sede_placeholder')}</SelectItem>
                            {sedesOpciones.map((s) => (
                                <SelectItem key={s.id} value={s.id}>
                                    {s.codigo ? `${s.nombre} (${s.codigo})` : s.nombre}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>
            </div>
        </FormModal>
    );
}
