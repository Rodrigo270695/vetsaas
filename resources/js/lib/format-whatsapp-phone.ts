/** Muestra un teléfono o ID de WhatsApp de forma legible (Perú). */
export function formatWhatsAppPhone(raw: string): string {
    if (raw.startsWith('lid:')) {
        return 'WhatsApp (ID privado)';
    }

    const digits = raw.replace('@c.us', '').replace(/\D/g, '');

    if (digits.startsWith('51') && digits.length === 11) {
        return `+51 ${digits.slice(2, 3)} ${digits.slice(3, 6)} ${digits.slice(6, 9)} ${digits.slice(9)}`;
    }

    if (digits.length >= 13) {
        return 'WhatsApp (ID privado)';
    }

    if (digits.length === 9 && digits.startsWith('9')) {
        return `+51 ${digits}`;
    }

    if (digits.length > 0) {
        return digits.startsWith('51') ? `+${digits}` : digits;
    }

    return raw;
}
