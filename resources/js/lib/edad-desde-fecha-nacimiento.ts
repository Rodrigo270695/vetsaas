/**
 * Edad en años y meses completos desde la fecha de nacimiento (solo fecha, sin hora).
 * `fechaNacimiento` en formato ISO `YYYY-MM-DD` (como envía Laravel/Inertia).
 */
export type EdadMascota = {
    years: number;
    months: number;
    /** Nacido hace menos de ~1 mes (meses calendario 0). */
    menosDeUnMes: boolean;
};

export function calcularEdadMascota(
    fechaNacimiento: string | null | undefined,
): EdadMascota | null {
    if (!fechaNacimiento?.trim()) {
        return null;
    }
    const d = fechaNacimiento.slice(0, 10);
    if (d.length < 10) {
        return null;
    }
    const birth = new Date(`${d}T12:00:00`);
    if (Number.isNaN(birth.getTime())) {
        return null;
    }
    const today = new Date();
    if (birth.getTime() > today.getTime()) {
        return null;
    }

    const tY = today.getFullYear();
    const tM = today.getMonth();
    const tD = today.getDate();
    const bY = birth.getFullYear();
    const bM = birth.getMonth();
    const bD = birth.getDate();

    let totalMonths = (tY - bY) * 12 + (tM - bM);
    if (tD < bD) {
        totalMonths -= 1;
    }
    if (totalMonths < 0) {
        return null;
    }

    if (totalMonths === 0) {
        return { years: 0, months: 0, menosDeUnMes: true };
    }

    const years = Math.floor(totalMonths / 12);
    const months = totalMonths % 12;

    return { years, months, menosDeUnMes: false };
}
