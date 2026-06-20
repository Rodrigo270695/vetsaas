import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import {
    useEffect,
    useMemo,
    useRef,
    useState,
    type FormEvent,
} from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, FormSection } from '@/components/forms';
import {
    appendCatalogValue,
    mergeSortedCatalog,
    PACIENTE_ESPECIES,
    PACIENTE_RAZAS,
    toComboboxOptions,
    type EspecieRazaCatalogo,
} from '@/lib/paciente-especie-raza-options';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { enqueueIfOffline, isOfflineMode } from '@/lib/offline/enqueue-if-offline';
import { useOfflineSync } from '@/hooks/use-offline-sync';
import { toastManager } from '@/lib/toast';
import pacientes from '@/routes/clinica/pacientes';
import propPacientes from '@/routes/clinica/propietarios/pacientes';
import type { Paciente, PropietarioOpcion } from '../../propietarios/types';

export type PacienteFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    paciente: Paciente | null;
    /** Creación desde ficha de propietario: no se muestra selector ni se envía `propietario_id`. */
    propietarioFijoId: string | null;
    propietariosOpciones: readonly PropietarioOpcion[];
    especieRazaCatalogo?: EspecieRazaCatalogo;
};

type InternalForm = {
    propietario_id: string;
    nombre: string;
    especie: string;
    raza: string;
    sexo: '' | 'M' | 'H' | 'U';
    fecha_nacimiento: string;
    peso_kg: string;
    microchip: string;
    color: string;
    esterilizado: '' | 'yes' | 'no';
    notas: string;
    activo: boolean;
};

type PacienteFormData = InternalForm & {
    foto: File | null;
    clear_foto: boolean;
};

const emptyInternal: InternalForm = {
    propietario_id: '',
    nombre: '',
    especie: '',
    raza: '',
    sexo: '',
    fecha_nacimiento: '',
    peso_kg: '',
    microchip: '',
    color: '',
    esterilizado: '',
    notas: '',
    activo: true,
};

const emptyForm: PacienteFormData = {
    ...emptyInternal,
    foto: null,
    clear_foto: false,
};

const controlClass = 'h-10 w-full min-w-0';

function labelPropietario(o: PropietarioOpcion): string {
    if (o.razon_social) {
        return o.razon_social;
    }
    return [o.nombres, o.apellidos].filter(Boolean).join(' ');
}

const fromModel = (
    p: Paciente | null,
    fijoId: string | null,
): InternalForm => ({
        propietario_id: fijoId ?? p?.propietario_id ?? '',
        nombre: p?.nombre ?? '',
        especie: p?.especie?.trim() ?? '',
        raza: p?.raza?.trim() ?? '',
        sexo: (p?.sexo as InternalForm['sexo']) || '',
        fecha_nacimiento: p?.fecha_nacimiento
            ? p.fecha_nacimiento.slice(0, 10)
            : '',
        peso_kg: p?.peso_kg != null && p.peso_kg !== '' ? String(p.peso_kg) : '',
        microchip: p?.microchip ?? '',
        color: p?.color ?? '',
        esterilizado:
            p?.esterilizado === true
                ? 'yes'
                : p?.esterilizado === false
                  ? 'no'
                  : '',
        notas: p?.notas ?? '',
        activo: p?.activo ?? true,
    });

export function PacienteFormModal({
    open,
    onOpenChange,
    paciente,
    propietarioFijoId,
    propietariosOpciones,
    especieRazaCatalogo = { especies: [], razas: [] },
}: PacienteFormModalProps) {
    const { t } = useTranslation(['pacientes', 'common', 'offline']);
    const { refreshPending } = useOfflineSync();
    const isEdit = paciente !== null;
    const fijoRef = useRef(propietarioFijoId);
    fijoRef.current = propietarioFijoId;
    const isEditRef = useRef(isEdit);
    isEditRef.current = isEdit;

    const { data, setData, post, processing, errors, reset, clearErrors, transform } =
        useForm<PacienteFormData>(emptyForm);

    type FormSnapshot = {
        internal: InternalForm;
        hadFotoUrl: boolean;
    };

    const snapshotRef = useRef<FormSnapshot>({
        internal: emptyInternal,
        hadFotoUrl: false,
    });
    const [ownerTouched, setOwnerTouched] = useState(false);
    const [especiesLista, setEspeciesLista] = useState<string[]>([]);
    const [razasLista, setRazasLista] = useState<string[]>([]);

    useEffect(() => {
        transform((raw) => {
            const next: Record<string, unknown> = {
                nombre: raw.nombre.trim(),
                especie: raw.especie.trim() || null,
                raza: raw.raza.trim() || null,
                fecha_nacimiento: raw.fecha_nacimiento || null,
                microchip: raw.microchip.trim() || null,
                color: raw.color.trim() || null,
                notas: raw.notas.trim() || null,
                activo: raw.activo,
            };
            const peso = raw.peso_kg.trim();
            next.peso_kg = peso === '' ? null : Number.parseFloat(peso);
            if (raw.sexo) {
                next.sexo = raw.sexo;
            }
            if (raw.esterilizado === 'yes') {
                next.esterilizado = true;
            } else if (raw.esterilizado === 'no') {
                next.esterilizado = false;
            }
            if (!isEditRef.current && !fijoRef.current && raw.propietario_id) {
                next.propietario_id = raw.propietario_id;
            }
            if (raw.foto instanceof File) {
                next.foto = raw.foto;
            }
            if (raw.clear_foto === true) {
                next.clear_foto = true;
            }
            if (isEditRef.current) {
                next._method = 'put';
            }
            return next;
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (open) {
            const internal = fromModel(paciente, propietarioFijoId);
            snapshotRef.current = {
                internal,
                hadFotoUrl: Boolean(paciente?.foto_url),
            };
            setEspeciesLista(
                mergeSortedCatalog(
                    PACIENTE_ESPECIES,
                    especieRazaCatalogo.especies,
                    paciente?.especie,
                ),
            );
            setRazasLista(
                mergeSortedCatalog(
                    PACIENTE_RAZAS,
                    especieRazaCatalogo.razas,
                    paciente?.raza,
                ),
            );
            (Object.keys(internal) as Array<keyof InternalForm>).forEach((key) => {
                setData(key, internal[key]);
            });
            setData('foto', null);
            setData('clear_foto', false);
            setOwnerTouched(false);
            clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, paciente?.id, propietarioFijoId, especieRazaCatalogo]);

    const especieComboboxOptions = useMemo(
        () => toComboboxOptions(especiesLista),
        [especiesLista],
    );
    const razaComboboxOptions = useMemo(
        () => toComboboxOptions(razasLista),
        [razasLista],
    );

    const previewUrl = useMemo(() => {
        if (data.foto instanceof File) {
            return URL.createObjectURL(data.foto);
        }
        return null;
    }, [data.foto]);

    useEffect(() => {
        return () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    const isDirty = useMemo(() => {
        const snap = snapshotRef.current;
        const formDirty = (Object.keys(snap.internal) as Array<keyof InternalForm>).some(
            (key) => snap.internal[key] !== data[key],
        );
        const fotoDirty =
            data.foto instanceof File || (Boolean(data.clear_foto) && snap.hadFotoUrl);
        return formDirty || fotoDirty;
    }, [data]);

    const handleClose = (next: boolean) => {
        if (!next) {
            if (
                isDirty &&
                !window.confirm(t('common:form.unsaved_changes'))
            ) {
                return;
            }
            reset();
            clearErrors();
        }
        onOpenChange(next);
    };

    const needsOwnerSelect = !propietarioFijoId && !isEdit;
    const propietarioComboboxOptions = useMemo<readonly ComboboxOption[]>(
        () =>
            propietariosOpciones.map((o) => ({
                value: o.id,
                label: labelPropietario(o),
            })),
        [propietariosOpciones],
    );
    const canSubmit =
        data.nombre.trim().length > 0 &&
        !processing &&
        (!needsOwnerSelect || data.propietario_id.length > 0);

    const buildCreatePayload = (raw: PacienteFormData): Record<string, unknown> => {
        const next: Record<string, unknown> = {
            nombre: raw.nombre.trim(),
            especie: raw.especie.trim() || null,
            raza: raw.raza.trim() || null,
            fecha_nacimiento: raw.fecha_nacimiento || null,
            microchip: raw.microchip.trim() || null,
            color: raw.color.trim() || null,
            notas: raw.notas.trim() || null,
            activo: raw.activo,
        };
        const peso = raw.peso_kg.trim();
        next.peso_kg = peso === '' ? null : Number.parseFloat(peso);
        if (raw.sexo) {
            next.sexo = raw.sexo;
        }
        if (raw.esterilizado === 'yes') {
            next.esterilizado = true;
        } else if (raw.esterilizado === 'no') {
            next.esterilizado = false;
        }

        const ownerId = propietarioFijoId ?? raw.propietario_id;
        if (ownerId) {
            next.propietario_id = ownerId;
        }

        return next;
    };

    const onSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const onSuccess = () => {
            reset();
            clearErrors();
            onOpenChange(false);
        };

        const hasNewFoto = data.foto instanceof File;
        const submitOptions = {
            preserveScroll: true,
            onSuccess,
        } as const;

        if (isEdit && paciente) {
            post(pacientes.update(paciente.id).url, {
                ...submitOptions,
                forceFormData: true,
            });

            return;
        }

        if (isOfflineMode()) {
            if (hasNewFoto) {
                toastManager.warning({
                    title: t('offline:paciente.foto_requires_online'),
                });

                return;
            }

            void (async () => {
                const queued = await enqueueIfOffline(
                    'clinica.paciente.create',
                    buildCreatePayload(data),
                    {
                        refreshPending,
                        onSuccess,
                        title: t('offline:paciente.queued_title'),
                        description: t('offline:paciente.queued_body'),
                    },
                );

                if (queued) {
                    return;
                }

                if (propietarioFijoId) {
                    post(propPacientes.store(propietarioFijoId).url, submitOptions);
                } else {
                    post(pacientes.store().url, submitOptions);
                }
            })();

            return;
        }

        if (propietarioFijoId) {
            post(propPacientes.store(propietarioFijoId).url, {
                ...submitOptions,
                forceFormData: hasNewFoto,
            });
        } else {
            post(pacientes.store().url, {
                ...submitOptions,
                forceFormData: hasNewFoto,
            });
        }
    };

    const handleEspecieChange = (value: string | null) => {
        const next = value?.trim() ?? '';
        setData('especie', next);
        if (next) {
            setEspeciesLista((prev) => appendCatalogValue(prev, next));
        }
    };

    const handleRazaChange = (value: string | null) => {
        const next = value?.trim() ?? '';
        setData('raza', next);
        if (next) {
            setRazasLista((prev) => appendCatalogValue(prev, next));
        }
    };

    const fotoPreviewSrc =
        previewUrl ??
        (isEdit && paciente?.foto_url && !data.clear_foto ? paciente.foto_url : null);

    return (
        <FormModal
            open={open}
            onOpenChange={handleClose}
            title={isEdit ? t('form.title_edit') : t('form.title_create')}
            description={t('description')}
            size="lg"
            onSubmit={onSubmit}
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => handleClose(false)}
                        disabled={processing}
                        className="cursor-pointer"
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="submit"
                        disabled={!canSubmit}
                        className="cursor-pointer gap-2"
                    >
                        {processing && (
                            <Loader2 className="size-4 animate-spin" aria-hidden />
                        )}
                        {isEdit ? t('form.submit_edit') : t('form.submit_create')}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-5">
                {errors.plan_limit ? (
                    <p
                        className="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                        role="alert"
                    >
                        {errors.plan_limit}
                    </p>
                ) : null}
                <FormSection
                    index={0}
                    title={t('form.section_main')}
                    columns={2}
                    className="gap-4"
                >
                    {needsOwnerSelect && (
                        <FormField
                            id="pac-prop"
                            label={t('form.propietario')}
                            required
                            error={errors.propietario_id}
                            className="min-w-0 sm:col-span-2"
                        >
                            <Combobox
                                id="pac-prop"
                                options={propietarioComboboxOptions}
                                value={data.propietario_id || null}
                                onChange={(v) => {
                                    setOwnerTouched(true);
                                    setData('propietario_id', v ?? '');
                                }}
                                placeholder={t('form.propietario_placeholder')}
                                searchPlaceholder={t('form.propietario_search')}
                                emptyMessage={t('form.propietario_empty')}
                                clearable={false}
                                className={`${controlClass} cursor-pointer`}
                                aria-invalid={ownerTouched && !data.propietario_id}
                            />
                        </FormField>
                    )}
                    <FormField
                        id="pac-nombre"
                        label={t('form.nombre')}
                        required
                        error={errors.nombre}
                        className="min-w-0 sm:col-span-2"
                    >
                        <Input
                            id="pac-nombre"
                            value={data.nombre}
                            onChange={(e) => setData('nombre', e.target.value)}
                            autoFocus
                            className={controlClass}
                        />
                    </FormField>
                    <FormField
                        id="pac-foto"
                        label={t('form.foto')}
                        error={errors.foto}
                        hint={t('form.foto_hint')}
                        className="min-w-0 sm:col-span-2"
                    >
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start">
                            <Input
                                id="pac-foto"
                                key={`${open ? 'o' : 'c'}-${paciente?.id ?? 'n'}`}
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                className={`${controlClass} cursor-pointer file:cursor-pointer`}
                                onChange={(e) => {
                                    const f = e.target.files?.[0];
                                    setData('foto', f ?? null);
                                    if (f) {
                                        setData('clear_foto', false);
                                    }
                                }}
                            />
                            {fotoPreviewSrc ? (
                                <div className="flex shrink-0 items-center gap-2">
                                    <img
                                        src={fotoPreviewSrc}
                                        alt=""
                                        className="size-16 rounded-md border border-border object-cover"
                                    />
                                </div>
                            ) : null}
                        </div>
                        {isEdit && Boolean(paciente?.foto_url) && (
                            <div className="mt-3 flex items-center gap-3">
                                <Checkbox
                                    id="pac-clear-foto"
                                    checked={data.clear_foto}
                                    disabled={data.foto instanceof File}
                                    onCheckedChange={(c) => {
                                        const on = c === true;
                                        setData('clear_foto', on);
                                        if (on) {
                                            setData('foto', null);
                                        }
                                    }}
                                />
                                <label
                                    htmlFor="pac-clear-foto"
                                    className={`text-sm text-muted-foreground leading-none ${data.foto instanceof File ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'}`}
                                >
                                    {t('form.clear_foto')}
                                </label>
                            </div>
                        )}
                    </FormField>
                    <FormField
                        id="pac-esp"
                        label={t('form.especie')}
                        error={errors.especie}
                        hint={t('form.especie_hint')}
                        className="min-w-0"
                    >
                        <Combobox
                            id="pac-esp"
                            options={especieComboboxOptions}
                            value={data.especie || null}
                            onChange={handleEspecieChange}
                            placeholder={t('form.especie_placeholder')}
                            searchPlaceholder={t('form.especie_search')}
                            emptyMessage={t('form.especie_empty')}
                            createOptionLabel={(value) =>
                                t('form.especie_create', { value })
                            }
                            creatable
                            className={`${controlClass} cursor-pointer`}
                            aria-invalid={!!errors.especie}
                        />
                    </FormField>
                    <FormField
                        id="pac-raza"
                        label={t('form.raza')}
                        error={errors.raza}
                        hint={t('form.raza_hint')}
                        className="min-w-0"
                    >
                        <Combobox
                            id="pac-raza"
                            options={razaComboboxOptions}
                            value={data.raza || null}
                            onChange={handleRazaChange}
                            placeholder={t('form.raza_placeholder')}
                            searchPlaceholder={t('form.raza_search')}
                            emptyMessage={t('form.raza_empty')}
                            createOptionLabel={(value) =>
                                t('form.raza_create', { value })
                            }
                            creatable
                            className={`${controlClass} cursor-pointer`}
                            aria-invalid={!!errors.raza}
                        />
                    </FormField>
                    <FormField
                        id="pac-sexo"
                        label={t('form.sexo')}
                        error={errors.sexo}
                        className="min-w-0"
                    >
                        <Select
                            value={data.sexo || '__none__'}
                            onValueChange={(v) =>
                                setData('sexo', v === '__none__' ? '' : (v as InternalForm['sexo']))
                            }
                        >
                            <SelectTrigger
                                id="pac-sexo"
                                className={`${controlClass} cursor-pointer`}
                            >
                                <SelectValue placeholder={t('form.sexo_placeholder')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none__" className="cursor-pointer">
                                    {t('form.sexo_placeholder')}
                                </SelectItem>
                                <SelectItem value="M" className="cursor-pointer">
                                    {t('form.sexo_m')}
                                </SelectItem>
                                <SelectItem value="H" className="cursor-pointer">
                                    {t('form.sexo_h')}
                                </SelectItem>
                                <SelectItem value="U" className="cursor-pointer">
                                    {t('form.sexo_u')}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        id="pac-fn"
                        label={t('form.fecha_nacimiento')}
                        error={errors.fecha_nacimiento}
                        className="min-w-0"
                    >
                        <Input
                            id="pac-fn"
                            type="date"
                            value={data.fecha_nacimiento}
                            onChange={(e) =>
                                setData('fecha_nacimiento', e.target.value)
                            }
                            className={controlClass}
                        />
                    </FormField>
                    <FormField
                        id="pac-peso"
                        label={t('form.peso_kg')}
                        error={errors.peso_kg}
                        className="min-w-0"
                    >
                        <Input
                            id="pac-peso"
                            type="number"
                            step="0.01"
                            min={0}
                            value={data.peso_kg}
                            onChange={(e) => setData('peso_kg', e.target.value)}
                            className={controlClass}
                        />
                    </FormField>
                    <FormField
                        id="pac-chip"
                        label={t('form.microchip')}
                        error={errors.microchip}
                        className="min-w-0"
                    >
                        <Input
                            id="pac-chip"
                            value={data.microchip}
                            onChange={(e) => setData('microchip', e.target.value)}
                            className={controlClass}
                        />
                    </FormField>
                    <FormField
                        id="pac-color"
                        label={t('form.color')}
                        error={errors.color}
                        className="min-w-0"
                    >
                        <Input
                            id="pac-color"
                            value={data.color}
                            onChange={(e) => setData('color', e.target.value)}
                            className={controlClass}
                        />
                    </FormField>
                    <FormField
                        id="pac-ester"
                        label={t('form.esterilizado')}
                        error={errors.esterilizado}
                        className="min-w-0"
                    >
                        <Select
                            value={data.esterilizado || '__unk__'}
                            onValueChange={(v) =>
                                setData(
                                    'esterilizado',
                                    v === '__unk__' ? '' : (v as InternalForm['esterilizado']),
                                )
                            }
                        >
                            <SelectTrigger
                                id="pac-ester"
                                className={`${controlClass} cursor-pointer`}
                            >
                                <SelectValue placeholder={t('form.esterilizado_unknown')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__unk__" className="cursor-pointer">
                                    {t('form.esterilizado_unknown')}
                                </SelectItem>
                                <SelectItem value="yes" className="cursor-pointer">
                                    {t('form.esterilizado_yes')}
                                </SelectItem>
                                <SelectItem value="no" className="cursor-pointer">
                                    {t('form.esterilizado_no')}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        id="pac-notas"
                        label={t('form.notas')}
                        error={errors.notas}
                        className="min-w-0 sm:col-span-2"
                    >
                        <Textarea
                            id="pac-notas"
                            value={data.notas}
                            onChange={(e) => setData('notas', e.target.value)}
                            rows={3}
                            className="min-h-22 w-full min-w-0 resize-y"
                        />
                    </FormField>
                    <FormField
                        id="pac-activo"
                        label={t('form.activo')}
                        error={errors.activo}
                        className="min-w-0 sm:col-span-2"
                    >
                        <div className="flex h-10 items-center gap-3">
                            <Checkbox
                                id="pac-activo"
                                checked={data.activo}
                                onCheckedChange={(c) => setData('activo', c === true)}
                            />
                            <span className="text-sm text-muted-foreground">
                                {t('form.activo_help')}
                            </span>
                        </div>
                    </FormField>
                </FormSection>
            </div>
        </FormModal>
    );
}
