/**
 * Catálogos de especie y raza para el formulario de mascotas.
 * Si el valor guardado no coincide con ninguna opción, se trata como «Otro» (texto libre).
 */
export const PACIENTE_OTRO_KEY = '__OTRO__';

/** Valores que se guardan tal cual en `pacientes.especie` (salvo «Otro» → texto personalizado). */
export const PACIENTE_ESPECIES: readonly string[] = [
    'Perro',
    'Gato',
    'Conejo',
    'Hurón',
    'Ave',
    'Reptil',
    'Roedor',
];

/** Razas frecuentes; el usuario puede elegir «Otro» y escribir la suya. */
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

export function splitStoredAgainstCatalog(
    stored: string | null | undefined,
    catalogOptions: readonly string[],
): { catalog: string; otro: string } {
    const s = (stored ?? '').trim();
    if (!s) {
        return { catalog: '', otro: '' };
    }
    if (catalogOptions.includes(s)) {
        return { catalog: s, otro: '' };
    }
    return { catalog: PACIENTE_OTRO_KEY, otro: s };
}

export function mergeCatalogAndOtro(
    catalog: string,
    otro: string,
): string | null {
    const c = catalog.trim();
    const o = otro.trim();
    if (c === PACIENTE_OTRO_KEY) {
        return o === '' ? null : o;
    }
    return c === '' ? null : c;
}
