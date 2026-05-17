/**
 * Tipos de documento para titulares (propietarios).
 * Mantener alineado con `App\Support\PropietarioTipoDocumento::VALUES`.
 */
export const PROPIETARIO_DOCUMENT_TYPE_CODES = [
    'DNI',
    'RUC',
    'CE',
    'PAS',
    'OTR',
] as const;

export type PropietarioDocumentTypeCode =
    (typeof PROPIETARIO_DOCUMENT_TYPE_CODES)[number];

export function isPropietarioDocumentTypeCode(
    value: string,
): value is PropietarioDocumentTypeCode {
    return (PROPIETARIO_DOCUMENT_TYPE_CODES as readonly string[]).includes(value);
}
