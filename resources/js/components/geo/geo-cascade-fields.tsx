import { useEffect, useMemo, useRef, useState } from 'react';

import { Combobox, type ComboboxOption } from '@/components/ui/combobox';
import { Label } from '@/components/ui/label';

export type GeoOption = {
    id: number;
    name: string;
};

export type GeoCascadeValue = {
    departamento_id: number | null;
    provincia_id: number | null;
    distrito_id: number | null;
};

type GeoCascadeFieldsProps = {
    /** Catálogo de departamentos pre-cargado en page props. */
    departamentos: readonly GeoOption[];
    /** Valor controlado de los 3 niveles. */
    value: GeoCascadeValue;
    /** Llamado con el nuevo valor cada vez que cambia algún nivel. */
    onChange: (next: GeoCascadeValue) => void;
    /** Errores de validación por campo (vienen del FormRequest). */
    errors?: {
        departamento_id?: string;
        provincia_id?: string;
        distrito_id?: string;
    };
    /** Bloquea los 3 campos (ej. mientras se envía el form). */
    disabled?: boolean;
    /** Labels personalizables para i18n. */
    labels?: {
        departamento?: string;
        provincia?: string;
        distrito?: string;
    };
    /** Placeholders personalizables para i18n. */
    placeholders?: {
        departamento?: string;
        provincia?: string;
        distrito?: string;
    };
};

/**
 * Convierte una opción del API al formato del Combobox.
 *
 * El combobox trabaja con strings (más universal), pero internamente
 * usamos los IDs numéricos del catálogo. Esta función + `Number(value)`
 * en el onChange hacen la conversión idempotente.
 */
function toComboboxOptions(items: readonly GeoOption[]): ComboboxOption[] {
    return items.map((item) => ({
        value: String(item.id),
        label: item.name,
    }));
}

/**
 * Cascada **Departamento → Provincia → Distrito** con búsqueda en cada
 * nivel.
 *
 * Comportamiento:
 *   - Departamentos: vienen pre-cargados (catálogo pequeño).
 *   - Provincias: se piden al endpoint `/geo/provincias` cuando se
 *     elige un departamento. Se cachean por id para no re-fetchar al
 *     volver a abrir un modal con el mismo departamento.
 *   - Distritos: análogo, pero contra `/geo/distritos`.
 *   - Al cambiar el padre, el hijo (y nieto) se limpian
 *     automáticamente para mantener consistencia jerárquica.
 *   - En modo edición (los 3 IDs vienen pre-poblados), se disparan los
 *     fetches al montar.
 */
export function GeoCascadeFields({
    departamentos,
    value,
    onChange,
    errors,
    disabled = false,
    labels,
    placeholders,
}: GeoCascadeFieldsProps) {
    const [provincias, setProvincias] = useState<GeoOption[]>([]);
    const [distritos, setDistritos] = useState<GeoOption[]>([]);
    const [loadingProvincias, setLoadingProvincias] = useState(false);
    const [loadingDistritos, setLoadingDistritos] = useState(false);

    /*
     * Cache por id de padre. Evita re-fetchar al volver a abrir un
     * modal con el mismo departamento/provincia. La cache vive el
     * tiempo que viva el componente (suficiente para un único modal,
     * no contamina memoria entre sesiones).
     */
    const provinciasCache = useRef(new Map<number, GeoOption[]>());
    const distritosCache = useRef(new Map<number, GeoOption[]>());

    // ─── Cargar provincias cuando cambia el departamento ────────────
    useEffect(() => {
        const depId = value.departamento_id;

        if (depId === null) {
            setProvincias([]);
            return;
        }

        const cached = provinciasCache.current.get(depId);
        if (cached) {
            setProvincias(cached);
            return;
        }

        const controller = new AbortController();
        setLoadingProvincias(true);

        fetch(`/geo/provincias?departamento_id=${depId}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((res) => (res.ok ? res.json() : []))
            .then((data: GeoOption[]) => {
                provinciasCache.current.set(depId, data);
                setProvincias(data);
            })
            .catch((err) => {
                if (err.name !== 'AbortError') {
                    setProvincias([]);
                }
            })
            .finally(() => setLoadingProvincias(false));

        return () => controller.abort();
    }, [value.departamento_id]);

    // ─── Cargar distritos cuando cambia la provincia ────────────────
    useEffect(() => {
        const provId = value.provincia_id;

        if (provId === null) {
            setDistritos([]);
            return;
        }

        const cached = distritosCache.current.get(provId);
        if (cached) {
            setDistritos(cached);
            return;
        }

        const controller = new AbortController();
        setLoadingDistritos(true);

        fetch(`/geo/distritos?provincia_id=${provId}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((res) => (res.ok ? res.json() : []))
            .then((data: GeoOption[]) => {
                distritosCache.current.set(provId, data);
                setDistritos(data);
            })
            .catch((err) => {
                if (err.name !== 'AbortError') {
                    setDistritos([]);
                }
            })
            .finally(() => setLoadingDistritos(false));

        return () => controller.abort();
    }, [value.provincia_id]);

    // ─── Opciones memoizadas para el Combobox ───────────────────────
    const departamentoOptions = useMemo(
        () => toComboboxOptions(departamentos),
        [departamentos],
    );
    const provinciaOptions = useMemo(
        () => toComboboxOptions(provincias),
        [provincias],
    );
    const distritoOptions = useMemo(
        () => toComboboxOptions(distritos),
        [distritos],
    );

    // ─── Handlers de cascada ────────────────────────────────────────
    const handleDepartamentoChange = (next: string | null) => {
        onChange({
            departamento_id: next ? Number(next) : null,
            provincia_id: null,
            distrito_id: null,
        });
    };

    const handleProvinciaChange = (next: string | null) => {
        onChange({
            ...value,
            provincia_id: next ? Number(next) : null,
            distrito_id: null,
        });
    };

    const handleDistritoChange = (next: string | null) => {
        onChange({
            ...value,
            distrito_id: next ? Number(next) : null,
        });
    };

    // En mobile el ancho disponible es muy estrecho, así que comprimimos:
    //   - padding horizontal del botón
    //   - tamaño de texto
    //   - tamaño del label
    // El truncate del Combobox se encarga del resto del texto largo.
    const compactComboboxClass = 'px-2 text-xs sm:px-3 sm:text-sm';

    return (
        // En mobile mantenemos las 3 columnas (lado a lado) por requerimiento
        // del producto: una cascada vertical apilada rompe la metáfora de
        // "depende del anterior". En pantallas muy pequeñas reducimos gap y
        // padding interno del Combobox para que los 3 quepan sin overflow.
        <div className="grid min-w-0 grid-cols-3 gap-2 sm:gap-4">
            {/* ─── Departamento ─── */}
            <div className="flex min-w-0 flex-col gap-1.5">
                <Label
                    htmlFor="departamento_id"
                    className="truncate text-xs sm:text-sm"
                >
                    {labels?.departamento ?? 'Departamento'}
                </Label>
                <Combobox
                    id="departamento_id"
                    options={departamentoOptions}
                    value={
                        value.departamento_id !== null
                            ? String(value.departamento_id)
                            : null
                    }
                    onChange={handleDepartamentoChange}
                    placeholder={
                        placeholders?.departamento ?? 'Selecciona departamento'
                    }
                    searchPlaceholder="Buscar departamento..."
                    emptyMessage="Sin coincidencias."
                    disabled={disabled}
                    className={compactComboboxClass}
                    aria-invalid={Boolean(errors?.departamento_id)}
                    aria-describedby={
                        errors?.departamento_id
                            ? 'departamento_id-error'
                            : undefined
                    }
                />
                {errors?.departamento_id && (
                    <p
                        id="departamento_id-error"
                        className="text-xs text-destructive"
                    >
                        {errors.departamento_id}
                    </p>
                )}
            </div>

            {/* ─── Provincia ─── */}
            <div className="flex min-w-0 flex-col gap-1.5">
                <Label
                    htmlFor="provincia_id"
                    className="truncate text-xs sm:text-sm"
                >
                    {labels?.provincia ?? 'Provincia'}
                </Label>
                <Combobox
                    id="provincia_id"
                    options={provinciaOptions}
                    value={
                        value.provincia_id !== null
                            ? String(value.provincia_id)
                            : null
                    }
                    onChange={handleProvinciaChange}
                    placeholder={
                        value.departamento_id === null
                            ? 'Primero el departamento'
                            : placeholders?.provincia ?? 'Selecciona provincia'
                    }
                    searchPlaceholder="Buscar provincia..."
                    emptyMessage="Sin coincidencias."
                    disabled={disabled || value.departamento_id === null}
                    loading={loadingProvincias}
                    className={compactComboboxClass}
                    aria-invalid={Boolean(errors?.provincia_id)}
                    aria-describedby={
                        errors?.provincia_id
                            ? 'provincia_id-error'
                            : undefined
                    }
                />
                {errors?.provincia_id && (
                    <p
                        id="provincia_id-error"
                        className="text-xs text-destructive"
                    >
                        {errors.provincia_id}
                    </p>
                )}
            </div>

            {/* ─── Distrito ─── */}
            <div className="flex min-w-0 flex-col gap-1.5">
                <Label
                    htmlFor="distrito_id"
                    className="truncate text-xs sm:text-sm"
                >
                    {labels?.distrito ?? 'Distrito'}
                </Label>
                <Combobox
                    id="distrito_id"
                    options={distritoOptions}
                    value={
                        value.distrito_id !== null
                            ? String(value.distrito_id)
                            : null
                    }
                    onChange={handleDistritoChange}
                    placeholder={
                        value.provincia_id === null
                            ? 'Primero la provincia'
                            : placeholders?.distrito ?? 'Selecciona distrito'
                    }
                    searchPlaceholder="Buscar distrito..."
                    emptyMessage="Sin coincidencias."
                    disabled={disabled || value.provincia_id === null}
                    loading={loadingDistritos}
                    className={compactComboboxClass}
                    aria-invalid={Boolean(errors?.distrito_id)}
                    aria-describedby={
                        errors?.distrito_id ? 'distrito_id-error' : undefined
                    }
                />
                {errors?.distrito_id && (
                    <p
                        id="distrito_id-error"
                        className="text-xs text-destructive"
                    >
                        {errors.distrito_id}
                    </p>
                )}
            </div>
        </div>
    );
}
