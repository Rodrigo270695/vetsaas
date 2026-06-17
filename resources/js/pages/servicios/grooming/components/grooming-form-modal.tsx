import { useForm, usePage } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, SedeFormField } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import type { ComboboxOption } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { resolveDefaultSedeId } from '@/lib/default-sede';
import servicios from '@/routes/servicios';
import type {
    GroomingServicioGrupo,
    GroomingServicioRow,
    GroomingTurnoRow,
    PacienteGroomingOpcion,
    SedeGroomingOpcion,
    UsuarioGroomingOpcion,
} from '../types';

const controlClass = 'h-10 w-full min-w-0';

const OTRO_PERSONALIZADO = 'otro_personalizado';

const GROOMING_ESTADOS = [
    'programada',
    'confirmada',
    'en_proceso',
    'completada',
    'cancelada',
    'no_asistio',
] as const;

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

function displayPropietario(p: PacienteGroomingOpcion['propietario']): string {
    if (!p) {
        return '';
    }

    if (p.razon_social) {
        return p.razon_social;
    }

    return [p.nombres, p.apellidos].filter(Boolean).join(' ');
}

export type GroomingFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    turno: GroomingTurnoRow | null;
    catalogoPersonalizado: boolean;
    serviciosOpciones: readonly GroomingServicioRow[];
    servicioGrupos: readonly GroomingServicioGrupo[];
    servicioDuraciones: Readonly<Record<string, number>>;
    pacientesOpciones: readonly PacienteGroomingOpcion[];
    usuariosOpciones: readonly UsuarioGroomingOpcion[];
    sedesOpciones: readonly SedeGroomingOpcion[];
};

type FormShape = {
    paciente_id: string;
    inicio_at: string;
    duracion_minutos: string;
    estado?: string;
    servicio: string;
    grooming_servicio_id: string;
    servicio_detalle: string;
    notas: string;
    responsable_id: string | null;
    sede_id: string | null;
};

function emptyForm(
    defaultResponsableId: string | null,
    duraciones: Readonly<Record<string, number>>,
    sedes: readonly SedeGroomingOpcion[],
    catalogoPersonalizado: boolean,
    servicios: readonly GroomingServicioRow[],
): FormShape {
    const slugDefault = 'bano_higienico';
    const firstServicio = servicios.find((s) => s.activo) ?? servicios[0];

    return {
        paciente_id: '',
        inicio_at: toDatetimeLocalValue(new Date()),
        duracion_minutos: catalogoPersonalizado
            ? String(firstServicio?.duracion_minutos ?? 60)
            : String(duraciones[slugDefault] ?? 60),
        servicio: catalogoPersonalizado ? '' : slugDefault,
        grooming_servicio_id: firstServicio?.id ?? '',
        servicio_detalle: '',
        notas: '',
        responsable_id: defaultResponsableId,
        sede_id: resolveDefaultSedeId(sedes),
    };
}

function fromTurno(t: GroomingTurnoRow, defaultResponsableId: string | null): FormShape {
    return {
        paciente_id: t.paciente_id,
        inicio_at: parseIsoToDatetimeLocal(t.inicio_at),
        duracion_minutos: String(t.duracion_minutos),
        estado: t.estado,
        servicio: t.servicio,
        grooming_servicio_id: t.grooming_servicio_id ?? t.servicio,
        servicio_detalle: t.servicio_detalle ?? '',
        notas: t.notas ?? '',
        responsable_id: t.responsable_id ?? defaultResponsableId,
        sede_id: t.sede_id,
    };
}

export function GroomingFormModal({
    open,
    onOpenChange,
    turno,
    catalogoPersonalizado,
    serviciosOpciones,
    servicioGrupos,
    servicioDuraciones,
    pacientesOpciones,
    usuariosOpciones,
    sedesOpciones,
}: GroomingFormModalProps) {
    const { t } = useTranslation(['grooming', 'common']);
    const authUser = usePage().props.auth?.user as { id?: string } | undefined;
    const defaultResponsableId = authUser?.id ?? null;

    const { data, setData, post, put, processing, errors, clearErrors, transform, setDefaults } =
        useForm<FormShape>(
            emptyForm(defaultResponsableId, servicioDuraciones, sedesOpciones, catalogoPersonalizado, serviciosOpciones),
        );

    const serviciosActivos = useMemo(
        () => serviciosOpciones.filter((s) => s.activo),
        [serviciosOpciones],
    );

    const serviciosPorCategoria = useMemo(() => {
        const map = new Map<string, GroomingServicioRow[]>();

        for (const s of serviciosActivos) {
            const key = s.categoria?.trim() || 'general';
            const list = map.get(key) ?? [];
            list.push(s);
            map.set(key, list);
        }

        return [...map.entries()];
    }, [serviciosActivos]);

    const isEdit = turno !== null;
    const lockPaciente = isEdit;

    const servicioHint = useMemo(() => {
        if (!data.servicio) {
            return null;
        }

        const key = `tipos_servicio.items.${data.servicio}.hint` as const;

        return t(key);
    }, [data.servicio, t]);

    useEffect(() => {
        transform((raw) => {
            const r = raw;
            const dm = r.duracion_minutos.trim();
            const dmVal = dm === '' ? NaN : Number.parseInt(dm, 10);

            const det =
                r.servicio === OTRO_PERSONALIZADO
                    ? r.servicio_detalle.trim() === ''
                        ? null
                        : r.servicio_detalle.trim()
                    : null;

            const base: Record<string, unknown> = {
                paciente_id: r.paciente_id,
                inicio_at: r.inicio_at,
                duracion_minutos: Number.isNaN(dmVal) ? 60 : dmVal,
                notas: r.notas.trim() === '' ? null : r.notas.trim(),
                responsable_id:
                    r.responsable_id != null && r.responsable_id !== '' ? r.responsable_id : null,
                sede_id: r.sede_id != null && r.sede_id !== '' ? r.sede_id : null,
            };

            if (catalogoPersonalizado) {
                base.grooming_servicio_id = r.grooming_servicio_id;
            } else {
                base.servicio = r.servicio;
                base.servicio_detalle = det;
            }

            if (r.estado !== undefined) {
                base.estado = r.estado;
            }

            return base;
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (!open) {
            return;
        }

        clearErrors();

        if (turno !== null) {
            setData(fromTurno(turno, defaultResponsableId));
        } else {
            setData(
                emptyForm(
                    defaultResponsableId,
                    servicioDuraciones,
                    sedesOpciones,
                    catalogoPersonalizado,
                    serviciosOpciones,
                ),
            );
        }

        setDefaults();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, turno?.id, defaultResponsableId, turno, sedesOpciones, catalogoPersonalizado, serviciosOpciones]);

    const pacienteComboboxOptions: ComboboxOption[] = pacientesOpciones.map((p) => ({
        value: p.id,
        label: `${p.nombre} · ${displayPropietario(p.propietario) || '—'}`,
    }));

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();

        if (isEdit && turno) {
            put(servicios.grooming.update({ grooming_turno: turno.id }).url, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });

            return;
        }

        post(servicios.grooming.store().url, {
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
                    <Button type="submit" disabled={processing || (catalogoPersonalizado && serviciosActivos.length === 0 && !isEdit)} className="gap-2">
                        {processing && <Loader2 className="size-4 animate-spin" aria-hidden />}
                        {isEdit ? t('form.submit_edit') : t('form.submit_create')}
                    </Button>
                </>
            }
        >
            <div className="grid gap-4">
                <FormField
                    id="gf-paciente"
                    label={t('form.paciente')}
                    required
                    error={errors.paciente_id as string | undefined}
                >
                    <Combobox
                        id="gf-paciente"
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
                    id="gf-inicio"
                    label={t('form.inicio_at')}
                    required
                    error={errors.inicio_at as string | undefined}
                >
                    <Input
                        id="gf-inicio"
                        type="datetime-local"
                        className={controlClass}
                        value={data.inicio_at}
                        onChange={(e) => setData('inicio_at', e.target.value)}
                        aria-invalid={Boolean(errors.inicio_at)}
                        disabled={processing}
                    />
                </FormField>

                <FormField
                    id="gf-servicio"
                    label={t('form.servicio')}
                    required
                    error={
                        (errors.grooming_servicio_id ??
                            errors.servicio ??
                            errors.servicio_detalle) as string | undefined
                    }
                >
                    {catalogoPersonalizado ? (
                        serviciosActivos.length === 0 ? (
                            <p className="text-sm text-muted-foreground">{t('servicios.empty_turno')}</p>
                        ) : (
                            <Select
                                value={data.grooming_servicio_id}
                                onValueChange={(v) => {
                                    setData('grooming_servicio_id', v);
                                    const row = serviciosActivos.find((s) => s.id === v);
                                    if (row) {
                                        setData('duracion_minutos', String(row.duracion_minutos));
                                    }
                                }}
                                disabled={processing}
                            >
                                <SelectTrigger
                                    id="gf-servicio"
                                    className={controlClass}
                                    aria-invalid={Boolean(errors.grooming_servicio_id)}
                                >
                                    <SelectValue placeholder={t('form.servicio_placeholder')} />
                                </SelectTrigger>
                                <SelectContent className="max-h-72">
                                    {serviciosPorCategoria.map(([categoria, items]) => (
                                        <SelectGroup key={categoria}>
                                            <SelectLabel className="text-xs font-semibold">
                                                {categoria === 'general'
                                                    ? t('servicios.categoria_general')
                                                    : categoria}
                                            </SelectLabel>
                                            {items.map((s) => (
                                                <SelectItem key={s.id} value={s.id} className="text-sm">
                                                    {s.nombre}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    ))}
                                </SelectContent>
                            </Select>
                        )
                    ) : (
                        <>
                            <Select
                                value={data.servicio}
                                onValueChange={(v) => {
                                    setData('servicio', v);
                                    setData('duracion_minutos', String(servicioDuraciones[v] ?? 60));
                                    if (v !== OTRO_PERSONALIZADO) {
                                        setData('servicio_detalle', '');
                                    }
                                }}
                                disabled={processing}
                            >
                                <SelectTrigger
                                    id="gf-servicio"
                                    className={controlClass}
                                    aria-invalid={Boolean(errors.servicio)}
                                >
                                    <SelectValue placeholder={t('form.servicio_placeholder')} />
                                </SelectTrigger>
                                <SelectContent className="max-h-72">
                                    {servicioGrupos.map((bloque) => (
                                        <SelectGroup key={bloque.grupo}>
                                            <SelectLabel className="text-xs font-semibold">
                                                {t(`tipos_servicio.grupos.${bloque.grupo}`)}
                                            </SelectLabel>
                                            {bloque.items.map((slug) => (
                                                <SelectItem key={slug} value={slug} className="text-sm">
                                                    {t(`tipos_servicio.items.${slug}.label`)}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">{t('form.servicio_hint')}</p>
                            {servicioHint ? (
                                <p className="text-xs text-foreground/80">{servicioHint}</p>
                            ) : null}
                        </>
                    )}
                </FormField>

                {!catalogoPersonalizado && data.servicio === OTRO_PERSONALIZADO ? (
                    <FormField
                        id="gf-servicio-detalle"
                        label={t('form.servicio_detalle')}
                        required
                        error={errors.servicio_detalle as string | undefined}
                    >
                        <Textarea
                            id="gf-servicio-detalle"
                            rows={3}
                            className="resize-y text-sm"
                            value={data.servicio_detalle}
                            onChange={(e) => setData('servicio_detalle', e.target.value)}
                            placeholder={t('form.servicio_detalle_placeholder')}
                            aria-invalid={Boolean(errors.servicio_detalle)}
                            disabled={processing}
                        />
                        <p className="text-xs text-muted-foreground">{t('form.servicio_detalle_hint')}</p>
                    </FormField>
                ) : null}

                <FormField
                    id="gf-duracion"
                    label={t('form.duracion_minutos')}
                    required
                    error={errors.duracion_minutos as string | undefined}
                >
                    <Input
                        id="gf-duracion"
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
                <p className="text-xs text-muted-foreground">
                    {t('form.duracion_sugerida', {
                        minutes: String(servicioDuraciones[data.servicio] ?? 60),
                    })}
                </p>

                {isEdit ? (
                    <FormField
                        id="gf-estado"
                        label={t('form.estado')}
                        required
                        error={errors.estado as string | undefined}
                    >
                        <Select
                            value={data.estado ?? 'programada'}
                            onValueChange={(v) => setData('estado', v)}
                            disabled={processing}
                        >
                            <SelectTrigger id="gf-estado" className={controlClass}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {GROOMING_ESTADOS.map((st) => (
                                    <SelectItem key={st} value={st}>
                                        {t(`estado.${st}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                ) : null}

                <FormField id="gf-notas" label={t('form.notas')} error={errors.notas as string | undefined}>
                    <Textarea
                        id="gf-notas"
                        rows={2}
                        className="resize-y text-sm"
                        value={data.notas}
                        onChange={(e) => setData('notas', e.target.value)}
                        aria-invalid={Boolean(errors.notas)}
                        disabled={processing}
                    />
                </FormField>

                <FormField
                    id="gf-responsable"
                    label={t('form.responsable')}
                    error={errors.responsable_id as string | undefined}
                >
                    <Select
                        value={data.responsable_id ?? '__none__'}
                        onValueChange={(v) => setData('responsable_id', v === '__none__' ? null : v)}
                        disabled={processing}
                    >
                        <SelectTrigger id="gf-responsable" className={controlClass}>
                            <SelectValue placeholder={t('form.responsable_placeholder')} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__none__">{t('form.responsable_placeholder')}</SelectItem>
                            {usuariosOpciones.map((u) => (
                                <SelectItem key={u.id} value={u.id}>
                                    {u.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>

                <SedeFormField
                    id="gf-sede"
                    label={t('form.sede')}
                    sedes={sedesOpciones}
                    value={data.sede_id}
                    onChange={(sedeId) => setData('sede_id', sedeId)}
                    error={errors.sede_id as string | undefined}
                    disabled={processing}
                    noneLabel={t('form.sede_placeholder')}
                    controlClassName={controlClass}
                />
            </div>
        </FormModal>
    );
}
