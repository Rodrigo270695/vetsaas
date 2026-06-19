import type { ComboboxOption } from '@/components/ui/combobox';

/**
 * Catálogos base de especie y raza para el formulario de mascotas.
 * Se combinan con valores ya usados en la clínica (desde el backend).
 */
export const PACIENTE_ESPECIES: readonly string[] = [
    'Perro',
    'Gato',
    'Conejo',
    'Hurón',
    'Ave',
    'Reptil',
    'Roedor',
];

export const PACIENTE_RAZAS: readonly string[] = [
    'Mestizo',
    'Cruce',
    'Labrador retriever',
    'Golden retriever',
    'Bulldog francés',
    'Yorkshire terrier',
    'Chihuahua',
    'Pastor alemán',
    'Beagle',
    'Caniche / Poodle',
    'Dálmata',
    'Rottweiler',
    'Boxer',
    'Persa',
    'Siamés',
    'Europeo común',
    'Maine coon',
    'British shorthair',
    'Scottish fold',
];

export type EspecieRazaCatalogo = {
    especies: readonly string[];
    razas: readonly string[];
};

export function mergeSortedCatalog(
    defaults: readonly string[],
    fromDb: readonly string[],
    current?: string | null,
): string[] {
    const unique = new Map<string, string>();

    for (const value of [...defaults, ...fromDb, current ?? '']) {
        const trimmed = value.trim();
        if (!trimmed) {
            continue;
        }
        const key = trimmed.toLocaleLowerCase();
        if (!unique.has(key)) {
            unique.set(key, trimmed);
        }
    }

    return [...unique.values()].sort((a, b) =>
        a.localeCompare(b, undefined, { sensitivity: 'base' }),
    );
}

export function toComboboxOptions(values: readonly string[]): ComboboxOption[] {
    return values.map((value) => ({
        value,
        label: value,
    }));
}

export function appendCatalogValue(
    values: readonly string[],
    value: string | null,
): string[] {
    if (!value?.trim()) {
        return [...values];
    }

    return mergeSortedCatalog([], values, value);
}
