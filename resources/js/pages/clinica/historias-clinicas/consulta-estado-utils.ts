/** Horas sin cerrar para considerar una consulta «antigua». */
export const CONSULTA_ABIERTA_ANTIGUA_HORAS = 24;

export function isConsultaAbiertaAntigua(
    atendidoAt: string,
    cerradaAt: string | null,
): boolean {
    if (cerradaAt) {
        return false;
    }

    const at = new Date(atendidoAt).getTime();

    if (Number.isNaN(at)) {
        return false;
    }

    return Date.now() - at > CONSULTA_ABIERTA_ANTIGUA_HORAS * 60 * 60 * 1000;
}
