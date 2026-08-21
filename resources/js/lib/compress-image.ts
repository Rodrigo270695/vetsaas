/**
 * Comprime una imagen en el navegador (canvas) para caber en el límite
 * de subida sin que el usuario tenga que redimensionarla a mano.
 *
 * - Escala el lado mayor a `maxEdge`.
 * - Re-encode a JPEG bajando calidad hasta `maxBytes`.
 * - Si ya es liviana y no es enorme, la deja igual.
 */
export type CompressImageOptions = {
    maxBytes?: number;
    maxEdge?: number;
    mimeType?: string;
    initialQuality?: number;
    minQuality?: number;
};

const DEFAULTS = {
    maxBytes: 1_800_000, // ~1.8 MB (margen bajo el tope Laravel de 2 MB)
    maxEdge: 1600,
    mimeType: 'image/jpeg',
    initialQuality: 0.86,
    minQuality: 0.45,
} as const;

export async function compressImageFile(
    file: File,
    options: CompressImageOptions = {},
): Promise<File> {
    if (!file.type.startsWith('image/')) {
        return file;
    }

    const maxBytes = options.maxBytes ?? DEFAULTS.maxBytes;
    const maxEdge = options.maxEdge ?? DEFAULTS.maxEdge;
    const mimeType = options.mimeType ?? DEFAULTS.mimeType;
    const initialQuality = options.initialQuality ?? DEFAULTS.initialQuality;
    const minQuality = options.minQuality ?? DEFAULTS.minQuality;

    const bitmap = await loadBitmap(file);
    try {
        const { width, height } = fitWithin(bitmap.width, bitmap.height, maxEdge);

        // Ya cabe y no hace falta reescalar: evita re-encode innecesario.
        if (
            file.size <= maxBytes &&
            width === bitmap.width &&
            height === bitmap.height &&
            (file.type === 'image/jpeg' ||
                file.type === 'image/png' ||
                file.type === 'image/webp')
        ) {
            return file;
        }

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d', { alpha: false });
        if (!ctx) {
            return file;
        }

        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, width, height);
        ctx.drawImage(bitmap, 0, 0, width, height);

        let quality = initialQuality;
        let blob = await canvasToBlob(canvas, mimeType, quality);

        while (blob && blob.size > maxBytes && quality > minQuality) {
            quality = Math.max(minQuality, quality - 0.08);
            blob = await canvasToBlob(canvas, mimeType, quality);
        }

        // Último recurso: bajar más el borde si aún pesa.
        if (blob && blob.size > maxBytes && maxEdge > 900) {
            const tighter = fitWithin(bitmap.width, bitmap.height, 900);
            canvas.width = tighter.width;
            canvas.height = tighter.height;
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, tighter.width, tighter.height);
            ctx.drawImage(bitmap, 0, 0, tighter.width, tighter.height);
            blob = await canvasToBlob(canvas, mimeType, minQuality);
        }

        if (!blob) {
            return file;
        }

        const baseName = file.name.replace(/\.[^.]+$/, '') || 'foto';
        return new File([blob], `${baseName}.jpg`, {
            type: mimeType,
            lastModified: Date.now(),
        });
    } finally {
        bitmap.close();
    }
}

function fitWithin(
    width: number,
    height: number,
    maxEdge: number,
): { width: number; height: number } {
    const longest = Math.max(width, height);
    if (longest <= maxEdge) {
        return { width, height };
    }

    const scale = maxEdge / longest;

    return {
        width: Math.max(1, Math.round(width * scale)),
        height: Math.max(1, Math.round(height * scale)),
    };
}

async function loadBitmap(file: File): Promise<ImageBitmap> {
    if (typeof createImageBitmap === 'function') {
        try {
            return await createImageBitmap(file);
        } catch {
            // HEIC u otros: caer al <img>.
        }
    }

    const url = URL.createObjectURL(file);
    try {
        const img = await loadHtmlImage(url);
        return await createImageBitmap(img);
    } finally {
        URL.revokeObjectURL(url);
    }
}

function loadHtmlImage(src: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error('No se pudo leer la imagen.'));
        img.src = src;
    });
}

function canvasToBlob(
    canvas: HTMLCanvasElement,
    mimeType: string,
    quality: number,
): Promise<Blob | null> {
    return new Promise((resolve) => {
        canvas.toBlob((blob) => resolve(blob), mimeType, quality);
    });
}
