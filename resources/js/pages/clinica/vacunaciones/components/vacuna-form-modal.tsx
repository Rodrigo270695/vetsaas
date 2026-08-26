import { useForm, usePage } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useMemo, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, SedeFormField } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import type { ComboboxOption } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { resolveDefaultSedeId } from '@/lib/default-sede';
import { enqueueIfOffline } from '@/lib/offline/enqueue-if-offline';
import { useOfflineSync } from '@/hooks/use-offline-sync';
import clinica from '@/routes/clinica';
import { formatAtendidoInAppTimezone } from '../../historias-clinicas/format-atendido';
import type {
    PacienteVacunaOpcion,
    SedeVacunaOpcion,
    ServicioVacunaOpcion,
    VacunaAplicadaRow,
    VacunaPrefillCreate,
} from '../types';
import { VacunaProductoPicker, type VacunaProductoOption } from './vacuna-producto-picker';

const controlClass = 'h-10 w-full min-w-0';

function formatPrecioLista(amount: string): string {
    const n = Number(amount);

    return Number.isFinite(n)
        ? new Intl.NumberFormat(undefined, { style: 'currency', currency: 'PEN' }).format(n)
        : amount;
}

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

/** Alinea con VacunaAplicada::categoriaRegistroDesdeNombreCategoriaServicio. */
function categoriaDesdePaquete(categoriaNombre: string | null | undefined): string {
    const n = (categoriaNombre ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    if (n === '') {
        return 'vacuna';
    }
    if (n.includes('desparasit') || n.includes('antiparasit')) {
        return 'desparasitacion';
    }
    if (n.includes('vacun') || n.includes('inmun')) {
        return 'vacuna';
    }

    return 'otro';
}

export type VacunaFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    vacuna: VacunaAplicadaRow | null;
    pacientesOpciones: readonly PacienteVacunaOpcion[];
    sedesOpciones: readonly SedeVacunaOpcion[];
    serviciosVacunaOpciones?: readonly ServicioVacunaOpcion[];
    /** Desde `vacuna_prefill` del servidor al crear (p. ej. URL con prefill). */
    prefillCreate?: VacunaPrefillCreate | null;
};

type FormShape = {
    paciente_id: string;
    consulta_id: string;
    producto_id: string | null;
    servicio_clinico_id: string | null;
    categoria_registro: string;
    nombre_vacuna: string;
    esquema_antigenos: string;
    fecha_proxima_sugerida: string;
    proxima_servicio_clinico_id: string | null;
    proxima_inicio_at: string;
    proxima_duracion_minutos: string;
    aplicada_at: string;
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
        servicio_clinico_id: null,
        categoria_registro: 'vacuna',
        nombre_vacuna: '',
        esquema_antigenos: '',
        fecha_proxima_sugerida: '',
        proxima_servicio_clinico_id: null,
        proxima_inicio_at: '',
        proxima_duracion_minutos: '30',
        aplicada_at: toDatetimeLocalValue(new Date()),
        notas: '',
        veterinario_id: defaultVetId,
        sede_id: resolveDefaultSedeId(sedes),
    };
}

function resolveProximaPaqueteId(
    v: VacunaAplicadaRow,
    servicios: readonly ServicioVacunaOpcion[],
): string | null {
    const motivo = (v.cita_proxima?.motivo ?? '').toLowerCase();
    if (motivo !== '') {
        const hit = servicios.find((s) => motivo.includes(s.nombre.toLowerCase()));
        if (hit) {
            return hit.id;
        }
    }

    return v.servicio_clinico_id ?? null;
}

function fromVacuna(
    v: VacunaAplicadaRow,
    defaultVetId: string | null,
    servicios: readonly ServicioVacunaOpcion[],
): FormShape {
    const citaInicio = v.cita_proxima?.inicio_at
        ? parseIsoToDatetimeLocal(v.cita_proxima.inicio_at)
        : '';

    return {
        paciente_id: v.paciente_id,
        consulta_id: v.consulta_id ?? '',
        producto_id: v.producto_id,
        servicio_clinico_id: v.servicio_clinico_id ?? null,
        categoria_registro: v.categoria_registro ?? 'vacuna',
        nombre_vacuna: v.nombre_vacuna,
        esquema_antigenos: v.esquema_antigenos ?? '',
        fecha_proxima_sugerida: isoDateToInput(v.fecha_proxima_sugerida),
        proxima_servicio_clinico_id:
            v.cita_proxima != null ? resolveProximaPaqueteId(v, servicios) : null,
        proxima_inicio_at: citaInicio,
        proxima_duracion_minutos: String(v.cita_proxima?.duracion_minutos ?? 30),
        aplicada_at: parseIsoToDatetimeLocal(v.aplicada_at),
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
    sedesOpciones,
    serviciosVacunaOpciones = [],
    prefillCreate = null,
}: VacunaFormModalProps) {
    const { t } = useTranslation(['vacunaciones', 'common', 'offline']);
    const { refreshPending } = useOfflineSync();
    const authUser = usePage().props.auth?.user as { id?: string } | undefined;
    const { locale: appLocale, timezone: appTz } = usePage().props;
    const defaultVetId = authUser?.id ?? null;

    const { data, setData, post, put, processing, errors, clearErrors, transform, setDefaults } =
        useForm<FormShape>(emptyForm(defaultVetId, sedesOpciones));

    const isEdit = vacuna !== null;
    const lockPaciente = isEdit || Boolean(prefillCreate?.paciente_id);

    const paqueteSeleccionado = useMemo(
        () =>
            serviciosVacunaOpciones.find((s) => s.id === data.servicio_clinico_id) ?? null,
        [serviciosVacunaOpciones, data.servicio_clinico_id],
    );
    const paqueteConProductos = (paqueteSeleccionado?.productos_count ?? 0) > 0;

    const canSubmit = useMemo(() => {
        return (
            data.paciente_id.trim() !== '' &&
            data.servicio_clinico_id != null &&
            data.servicio_clinico_id !== '' &&
            data.nombre_vacuna.trim() !== '' &&
            data.aplicada_at.trim() !== '' &&
            data.sede_id != null &&
            data.sede_id !== ''
        );
    }, [
        data.paciente_id,
        data.servicio_clinico_id,
        data.nombre_vacuna,
        data.aplicada_at,
        data.sede_id,
    ]);

    useEffect(() => {
        transform((raw) => {
            const r = raw;

            return {
                paciente_id: r.paciente_id,
                consulta_id: r.consulta_id.trim() === '' ? null : r.consulta_id.trim(),
                producto_id: r.producto_id && r.producto_id !== '' ? r.producto_id : null,
                servicio_clinico_id:
                    r.servicio_clinico_id != null && r.servicio_clinico_id !== ''
                        ? r.servicio_clinico_id
                        : null,
                categoria_registro: r.categoria_registro || 'vacuna',
                nombre_vacuna: r.nombre_vacuna.trim(),
                esquema_antigenos: r.esquema_antigenos.trim() === '' ? null : r.esquema_antigenos.trim(),
                fecha_proxima_sugerida: dateInputToPayload(
                    r.proxima_inicio_at.trim() !== ''
                        ? r.proxima_inicio_at.slice(0, 10)
                        : r.fecha_proxima_sugerida,
                ),
                proxima_servicio_clinico_id:
                    r.proxima_servicio_clinico_id != null && r.proxima_servicio_clinico_id !== ''
                        ? r.proxima_servicio_clinico_id
                        : null,
                proxima_inicio_at: r.proxima_inicio_at.trim() === '' ? null : r.proxima_inicio_at,
                proxima_duracion_minutos: (() => {
                    const n = Number.parseInt(r.proxima_duracion_minutos, 10);

                    return Number.isFinite(n) ? n : 30;
                })(),
                aplicada_at: r.aplicada_at,
                numero_dosis: vacuna?.numero_dosis ?? null,
                lote: vacuna?.lote?.trim() ? vacuna.lote.trim() : null,
                notas: r.notas.trim() === '' ? null : r.notas.trim(),
                veterinario_id:
                    r.veterinario_id != null && r.veterinario_id !== '' ? r.veterinario_id : null,
                sede_id: r.sede_id != null && r.sede_id !== '' ? r.sede_id : null,
            };
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [vacuna?.id, vacuna?.numero_dosis, vacuna?.lote]);

    useEffect(() => {
        if (!open) {
            return;
        }
        clearErrors();
        if (vacuna !== null) {
            setData(fromVacuna(vacuna, defaultVetId, serviciosVacunaOpciones));
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
    }, [open, vacuna?.id, defaultVetId, vacuna, prefillCreate?.paciente_id, prefillCreate?.consulta_id, sedesOpciones]);

    const pacienteComboboxOptions = useMemo<ComboboxOption[]>(
        () =>
            pacientesOpciones.map((p) => ({
                value: p.id,
                label: `${p.nombre} · ${displayPropietario(p.propietario) || '—'}`,
            })),
        [pacientesOpciones],
    );

    const servicioComboboxOptions = useMemo<ComboboxOption[]>(
        () =>
            serviciosVacunaOpciones.map((s) => ({
                value: s.id,
                label: `${s.nombre} · ${formatPrecioLista(s.precio_lista)}${
                    s.productos_count > 0 ? ` · ${t('form.paquete_productos_count', { count: s.productos_count })}` : ''
                }`,
            })),
        [serviciosVacunaOpciones, t],
    );

    const onServicioSelect = (value: string | null) => {
        if (value == null || value === '') {
            setData((prev) => ({
                ...prev,
                servicio_clinico_id: null,
                categoria_registro: 'vacuna',
            }));

            return;
        }

        const servicio = serviciosVacunaOpciones.find((s) => s.id === value);
        setData((prev) => ({
            ...prev,
            servicio_clinico_id: value,
            categoria_registro: categoriaDesdePaquete(servicio?.categoria),
            nombre_vacuna: servicio?.nombre ?? prev.nombre_vacuna,
            producto_id: null,
        }));
    };

    const onProductSelect = (opt: VacunaProductoOption | null) => {
        if (opt === null) {
            setData('producto_id', null);

            return;
        }
        setData((prev) => ({
            ...prev,
            producto_id: opt.id,
            nombre_vacuna: prev.nombre_vacuna.trim() === '' ? opt.nombre : prev.nombre_vacuna,
        }));
    };

    const buildCreatePayload = (raw: FormShape): Record<string, unknown> => {
        return {
            paciente_id: raw.paciente_id,
            consulta_id: raw.consulta_id.trim() === '' ? null : raw.consulta_id.trim(),
            producto_id: raw.producto_id && raw.producto_id !== '' ? raw.producto_id : null,
            servicio_clinico_id:
                raw.servicio_clinico_id != null && raw.servicio_clinico_id !== ''
                    ? raw.servicio_clinico_id
                    : null,
            categoria_registro: raw.categoria_registro || 'vacuna',
            nombre_vacuna: raw.nombre_vacuna.trim(),
            esquema_antigenos: raw.esquema_antigenos.trim() === '' ? null : raw.esquema_antigenos.trim(),
            fecha_proxima_sugerida: dateInputToPayload(
                raw.proxima_inicio_at.trim() !== ''
                    ? raw.proxima_inicio_at.slice(0, 10)
                    : raw.fecha_proxima_sugerida,
            ),
            proxima_servicio_clinico_id:
                raw.proxima_servicio_clinico_id != null && raw.proxima_servicio_clinico_id !== ''
                    ? raw.proxima_servicio_clinico_id
                    : null,
            proxima_inicio_at: raw.proxima_inicio_at.trim() === '' ? null : raw.proxima_inicio_at,
            proxima_duracion_minutos: (() => {
                const n = Number.parseInt(raw.proxima_duracion_minutos, 10);

                return Number.isFinite(n) ? n : 30;
            })(),
            aplicada_at: raw.aplicada_at,
            numero_dosis: null,
            lote: null,
            notas: raw.notas.trim() === '' ? null : raw.notas.trim(),
            veterinario_id:
                raw.veterinario_id != null && raw.veterinario_id !== '' ? raw.veterinario_id : null,
            sede_id: raw.sede_id != null && raw.sede_id !== '' ? raw.sede_id : null,
        };
    };

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!canSubmit || processing) {
            return;
        }
        if (isEdit && vacuna) {
            put(clinica.vacunaciones.update({ vacuna_aplicada: vacuna.id }).url, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });

            return;
        }

        void (async () => {
            const queued = await enqueueIfOffline(
                'clinica.vacuna.create',
                buildCreatePayload(data),
                {
                    refreshPending,
                    onSuccess: () => onOpenChange(false),
                    title: t('offline:vacuna.queued_title'),
                    description: t('offline:vacuna.queued_body'),
                },
            );

            if (queued) {
                return;
            }

            post(clinica.vacunaciones.store().url, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        })();
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
                    <Button type="submit" disabled={processing || !canSubmit} className="gap-2">
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
                    id="vf-paquete"
                    label={t('form.paquete')}
                    required
                    error={errors.servicio_clinico_id as string | undefined}
                >
                    <Combobox
                        id="vf-paquete"
                        options={servicioComboboxOptions}
                        value={data.servicio_clinico_id}
                        onChange={onServicioSelect}
                        placeholder={t('form.paquete_placeholder')}
                        searchPlaceholder={t('form.paquete_search')}
                        emptyMessage={t('form.paquete_empty')}
                        disabled={processing}
                        aria-invalid={Boolean(errors.servicio_clinico_id)}
                    />
                </FormField>

                {paqueteConProductos ? (
                    <p className="text-xs text-muted-foreground">{t('form.paquete_stock_hint')}</p>
                ) : null}

                {!data.servicio_clinico_id ? (
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
                ) : null}

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

                <div className="space-y-3 rounded-lg border border-border/70 bg-muted/15 p-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="text-sm font-medium text-foreground">
                            {t('form.fecha_proxima_sugerida')}
                        </p>
                        <a
                            href="/clinica/citas"
                            target="_blank"
                            rel="noreferrer"
                            className="text-xs font-medium text-violet-700 underline-offset-2 hover:underline dark:text-violet-300"
                        >
                            {t('form.proxima_citas_link')}
                        </a>
                    </div>
                    <p className="text-xs text-muted-foreground">{t('form.fecha_proxima_hint')}</p>

                    <FormField
                        id="vf-proxima-paquete"
                        label={t('form.proxima_paquete')}
                        error={errors.proxima_servicio_clinico_id as string | undefined}
                    >
                        <Combobox
                            id="vf-proxima-paquete"
                            options={servicioComboboxOptions}
                            value={data.proxima_servicio_clinico_id}
                            onChange={(value) =>
                                setData(
                                    'proxima_servicio_clinico_id',
                                    value != null && value !== '' ? value : null,
                                )
                            }
                            placeholder={t('form.proxima_paquete_placeholder')}
                            searchPlaceholder={t('form.proxima_paquete_search')}
                            emptyMessage={t('form.proxima_paquete_empty')}
                            disabled={processing}
                            aria-invalid={Boolean(errors.proxima_servicio_clinico_id)}
                        />
                    </FormField>

                    <div className="grid gap-3 sm:grid-cols-[1fr_7rem]">
                        <FormField
                            id="vf-proxima-inicio"
                            label={t('form.proxima_inicio_at')}
                            error={errors.proxima_inicio_at as string | undefined}
                        >
                            <Input
                                id="vf-proxima-inicio"
                                type="datetime-local"
                                className={controlClass}
                                value={data.proxima_inicio_at}
                                onChange={(e) => setData('proxima_inicio_at', e.target.value)}
                                aria-invalid={Boolean(errors.proxima_inicio_at)}
                            />
                        </FormField>
                        <FormField
                            id="vf-proxima-duracion"
                            label={t('form.proxima_duracion_minutos')}
                            error={errors.proxima_duracion_minutos as string | undefined}
                        >
                            <Input
                                id="vf-proxima-duracion"
                                type="number"
                                min={5}
                                max={480}
                                step={5}
                                className={controlClass}
                                value={data.proxima_duracion_minutos}
                                onChange={(e) => setData('proxima_duracion_minutos', e.target.value)}
                                aria-invalid={Boolean(errors.proxima_duracion_minutos)}
                            />
                        </FormField>
                    </div>

                    {data.proxima_servicio_clinico_id || data.proxima_inicio_at ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-8 px-2 text-xs text-muted-foreground"
                            disabled={processing}
                            onClick={() =>
                                setData((prev) => ({
                                    ...prev,
                                    proxima_servicio_clinico_id: null,
                                    proxima_inicio_at: '',
                                    fecha_proxima_sugerida: '',
                                    proxima_duracion_minutos: '30',
                                }))
                            }
                        >
                            {t('form.proxima_clear')}
                        </Button>
                    ) : null}
                </div>

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

                <SedeFormField
                    id="vf-sede"
                    label={t('form.sede')}
                    sedes={sedesOpciones}
                    value={data.sede_id}
                    onChange={(sedeId) => setData('sede_id', sedeId)}
                    required
                    hint={
                        paqueteConProductos
                            ? t('form.paquete_stock_hint')
                            : data.producto_id
                              ? t('form.sede_required_hint')
                              : undefined
                    }
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
