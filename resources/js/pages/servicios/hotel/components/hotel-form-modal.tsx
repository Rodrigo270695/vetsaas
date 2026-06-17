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
    HotelEstanciaRow,
    HotelTipoGrupo,
    HotelTipoRow,
    PacienteHotelOpcion,
    SedeHotelOpcion,
    UsuarioHotelOpcion,
} from '../types';

const controlClass = 'h-10 w-full min-w-0';

const OTRO_PERSONALIZADO = 'otro_personalizado';

const HOTEL_ESTADOS = [
    'programada',
    'confirmada',
    'en_estancia',
    'completada',
    'cancelada',
    'no_presento',
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

function displayPropietario(p: PacienteHotelOpcion['propietario']): string {
    if (!p) {
        return '';
    }

    if (p.razon_social) {
        return p.razon_social;
    }

    return [p.nombres, p.apellidos].filter(Boolean).join(' ');
}

export type HotelFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    estancia: HotelEstanciaRow | null;
    catalogoPersonalizado: boolean;
    hotelTipos: readonly HotelTipoRow[];
    tipoGrupos: readonly HotelTipoGrupo[];
    pacientesOpciones: readonly PacienteHotelOpcion[];
    usuariosOpciones: readonly UsuarioHotelOpcion[];
    sedesOpciones: readonly SedeHotelOpcion[];
};

type FormShape = {
    paciente_id: string;
    ingreso_at: string;
    egreso_at: string;
    estado?: string;
    tipo_estancia: string;
    hotel_tipo_id: string;
    tipo_detalle: string;
    notas: string;
    responsable_id: string | null;
    sede_id: string | null;
};

function emptyForm(
    defaultResponsableId: string | null,
    sedes: readonly SedeHotelOpcion[],
    catalogoPersonalizado: boolean,
    hotelTipos: readonly HotelTipoRow[],
): FormShape {
    const firstTipo = hotelTipos.find((t) => t.activo) ?? hotelTipos[0];

    return {
        paciente_id: '',
        ingreso_at: toDatetimeLocalValue(new Date()),
        egreso_at: '',
        tipo_estancia: catalogoPersonalizado ? '' : 'habitacion_estandar',
        hotel_tipo_id: firstTipo?.id ?? '',
        tipo_detalle: '',
        notas: '',
        responsable_id: defaultResponsableId,
        sede_id: resolveDefaultSedeId(sedes),
    };
}

function fromEstancia(e: HotelEstanciaRow, defaultResponsableId: string | null): FormShape {
    return {
        paciente_id: e.paciente_id,
        ingreso_at: parseIsoToDatetimeLocal(e.ingreso_at),
        egreso_at: e.egreso_at ? parseIsoToDatetimeLocal(e.egreso_at) : '',
        estado: e.estado,
        tipo_estancia: e.tipo_estancia,
        hotel_tipo_id: e.hotel_tipo_id ?? e.tipo_estancia,
        tipo_detalle: e.tipo_detalle ?? '',
        notas: e.notas ?? '',
        responsable_id: e.responsable_id ?? defaultResponsableId,
        sede_id: e.sede_id,
    };
}

export function HotelFormModal({
    open,
    onOpenChange,
    estancia,
    catalogoPersonalizado,
    hotelTipos,
    tipoGrupos,
    pacientesOpciones,
    usuariosOpciones,
    sedesOpciones,
}: HotelFormModalProps) {
    const { t } = useTranslation(['hotel', 'common']);
    const authUser = usePage().props.auth?.user as { id?: string } | undefined;
    const defaultResponsableId = authUser?.id ?? null;

    const { data, setData, post, put, processing, errors, clearErrors, transform, setDefaults } =
        useForm<FormShape>(
            emptyForm(defaultResponsableId, sedesOpciones, catalogoPersonalizado, hotelTipos),
        );

    const tiposActivos = useMemo(() => hotelTipos.filter((row) => row.activo), [hotelTipos]);

    const tiposComboboxOptions = useMemo<ComboboxOption[]>(
        () =>
            tiposActivos.map((row) => ({
                value: row.id,
                label: row.categoria ? `${row.categoria} · ${row.nombre}` : row.nombre,
            })),
        [tiposActivos],
    );

    const isEdit = estancia !== null;
    const lockPaciente = isEdit;

    const tipoHint = useMemo(() => {
        if (!data.tipo_estancia) {
            return null;
        }

        const key = `tipos_estancia.items.${data.tipo_estancia}.hint` as const;

        return t(key);
    }, [data.tipo_estancia, t]);

    useEffect(() => {
        transform((raw) => {
            const r = raw;
            const base: Record<string, unknown> = {
                paciente_id: r.paciente_id,
                ingreso_at: r.ingreso_at,
                egreso_at: r.egreso_at.trim() === '' ? null : r.egreso_at.trim(),
                notas: r.notas.trim() === '' ? null : r.notas.trim(),
                responsable_id:
                    r.responsable_id != null && r.responsable_id !== '' ? r.responsable_id : null,
                sede_id: r.sede_id != null && r.sede_id !== '' ? r.sede_id : null,
            };

            if (catalogoPersonalizado) {
                base.hotel_tipo_id = r.hotel_tipo_id;
            } else {
                const det =
                    r.tipo_estancia === OTRO_PERSONALIZADO
                        ? r.tipo_detalle.trim() === ''
                            ? null
                            : r.tipo_detalle.trim()
                        : null;
                base.tipo_estancia = r.tipo_estancia;
                base.tipo_detalle = det;
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

        if (estancia !== null) {
            setData(fromEstancia(estancia, defaultResponsableId));
        } else {
            setData(emptyForm(defaultResponsableId, sedesOpciones, catalogoPersonalizado, hotelTipos));
        }

        setDefaults();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, estancia?.id, defaultResponsableId, estancia, sedesOpciones, catalogoPersonalizado, hotelTipos]);

    const pacienteComboboxOptions: ComboboxOption[] = pacientesOpciones.map((p) => ({
        value: p.id,
        label: `${p.nombre} · ${displayPropietario(p.propietario) || '—'}`,
    }));

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();

        if (isEdit && estancia) {
            put(servicios.hotel.update({ hotel_estancia: estancia.id }).url, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });

            return;
        }

        post(servicios.hotel.store().url, {
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
                    <Button
                        type="submit"
                        disabled={
                            processing ||
                            (catalogoPersonalizado && tiposActivos.length === 0 && !isEdit)
                        }
                        className="gap-2"
                    >
                        {processing && <Loader2 className="size-4 animate-spin" aria-hidden />}
                        {isEdit ? t('form.submit_edit') : t('form.submit_create')}
                    </Button>
                </>
            }
        >
            <div className="grid gap-4">
                <FormField
                    id="hf-paciente"
                    label={t('form.paciente')}
                    required
                    error={errors.paciente_id as string | undefined}
                >
                    <Combobox
                        id="hf-paciente"
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
                    id="hf-ingreso"
                    label={t('form.ingreso_at')}
                    required
                    error={errors.ingreso_at as string | undefined}
                >
                    <Input
                        id="hf-ingreso"
                        type="datetime-local"
                        className={controlClass}
                        value={data.ingreso_at}
                        onChange={(e) => setData('ingreso_at', e.target.value)}
                        aria-invalid={Boolean(errors.ingreso_at)}
                        disabled={processing}
                    />
                </FormField>

                <FormField
                    id="hf-egreso"
                    label={t('form.egreso_at')}
                    error={errors.egreso_at as string | undefined}
                >
                    <Input
                        id="hf-egreso"
                        type="datetime-local"
                        className={controlClass}
                        value={data.egreso_at}
                        onChange={(e) => setData('egreso_at', e.target.value)}
                        aria-invalid={Boolean(errors.egreso_at)}
                        disabled={processing}
                    />
                    <p className="text-xs text-muted-foreground">{t('form.egreso_hint')}</p>
                </FormField>

                <FormField
                    id="hf-tipo"
                    label={t('form.tipo_estancia')}
                    required
                    error={
                        (errors.hotel_tipo_id ??
                            errors.tipo_estancia ??
                            errors.tipo_detalle) as string | undefined
                    }
                >
                    {catalogoPersonalizado ? (
                        tiposActivos.length === 0 ? (
                            <p className="text-sm text-muted-foreground">{t('tipos.empty_turno')}</p>
                        ) : (
                            <Combobox
                                id="hf-tipo"
                                options={tiposComboboxOptions}
                                value={data.hotel_tipo_id || null}
                                onChange={(value) => setData('hotel_tipo_id', value ?? '')}
                                placeholder={t('form.tipo_estancia_placeholder')}
                                searchPlaceholder={t('form.tipo_estancia_search')}
                                emptyMessage={t('form.tipo_estancia_empty')}
                                disabled={processing}
                                clearable={false}
                                aria-invalid={Boolean(errors.hotel_tipo_id)}
                            />
                        )
                    ) : (
                        <>
                            <Select
                                value={data.tipo_estancia}
                                onValueChange={(v) => {
                                    setData('tipo_estancia', v);
                                    if (v !== OTRO_PERSONALIZADO) {
                                        setData('tipo_detalle', '');
                                    }
                                }}
                                disabled={processing}
                            >
                                <SelectTrigger
                                    id="hf-tipo"
                                    className={controlClass}
                                    aria-invalid={Boolean(errors.tipo_estancia)}
                                >
                                    <SelectValue placeholder={t('form.tipo_estancia_placeholder')} />
                                </SelectTrigger>
                                <SelectContent className="max-h-72">
                                    {tipoGrupos.map((bloque) => (
                                        <SelectGroup key={bloque.grupo}>
                                            <SelectLabel className="text-xs font-semibold">
                                                {t(`tipos_estancia.grupos.${bloque.grupo}`)}
                                            </SelectLabel>
                                            {bloque.items.map((slug) => (
                                                <SelectItem key={slug} value={slug} className="text-sm">
                                                    {t(`tipos_estancia.items.${slug}.label`)}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">{t('form.tipo_estancia_hint')}</p>
                            {tipoHint ? <p className="text-xs text-foreground/80">{tipoHint}</p> : null}
                        </>
                    )}
                </FormField>

                {!catalogoPersonalizado && data.tipo_estancia === OTRO_PERSONALIZADO ? (
                    <FormField
                        id="hf-tipo-detalle"
                        label={t('form.tipo_detalle')}
                        required
                        error={errors.tipo_detalle as string | undefined}
                    >
                        <Textarea
                            id="hf-tipo-detalle"
                            rows={3}
                            className="resize-y text-sm"
                            value={data.tipo_detalle}
                            onChange={(e) => setData('tipo_detalle', e.target.value)}
                            placeholder={t('form.tipo_detalle_placeholder')}
                            aria-invalid={Boolean(errors.tipo_detalle)}
                            disabled={processing}
                        />
                        <p className="text-xs text-muted-foreground">{t('form.tipo_detalle_hint')}</p>
                    </FormField>
                ) : null}

                {isEdit ? (
                    <FormField id="hf-estado" label={t('form.estado')} required error={errors.estado as string | undefined}>
                        <Select
                            value={data.estado ?? 'programada'}
                            onValueChange={(v) => setData('estado', v)}
                            disabled={processing}
                        >
                            <SelectTrigger id="hf-estado" className={controlClass}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {HOTEL_ESTADOS.map((st) => (
                                    <SelectItem key={st} value={st}>
                                        {t(`estado.${st}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                ) : null}

                <FormField
                    id="hf-responsable"
                    label={t('form.responsable')}
                    error={errors.responsable_id as string | undefined}
                >
                    <Select
                        value={data.responsable_id ?? '__none__'}
                        onValueChange={(v) => setData('responsable_id', v === '__none__' ? null : v)}
                        disabled={processing}
                    >
                        <SelectTrigger id="hf-responsable" className={controlClass}>
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
                    id="hf-sede"
                    label={t('form.sede')}
                    sedes={sedesOpciones}
                    value={data.sede_id}
                    onChange={(sedeId) => setData('sede_id', sedeId)}
                    error={errors.sede_id as string | undefined}
                    disabled={processing}
                    noneLabel={t('form.sede_placeholder')}
                    controlClassName={controlClass}
                    formatLabel={(s) => `${s.nombre} (${s.codigo})`}
                />

                <FormField id="hf-notas" label={t('form.notas')} error={errors.notas as string | undefined}>
                    <Textarea
                        id="hf-notas"
                        rows={3}
                        className="resize-y text-sm"
                        value={data.notas}
                        onChange={(e) => setData('notas', e.target.value)}
                        disabled={processing}
                    />
                </FormField>
            </div>
        </FormModal>
    );
}
