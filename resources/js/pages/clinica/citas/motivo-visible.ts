type MotivoCitaLike = {
    motivo?: string | null;
    sala_espera_motivo?: string | null;
};

/** Motivo clínico (no el marcador «Sala de espera» del walk-in). */
export function motivoCitaVisible(cita: MotivoCitaLike): string | null {
    const sala = cita.sala_espera_motivo?.trim();
    if (sala) {
        return sala;
    }
    const motivo = cita.motivo?.trim();
    if (motivo && motivo !== 'Sala de espera') {
        return motivo;
    }

    return null;
}
