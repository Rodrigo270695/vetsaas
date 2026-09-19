/**
 * Quita el papel blanco/gris claro de un sello o firma para dejar PNG transparente.
 */
export async function stripSignaturePaper(file: File): Promise<File> {
    const bitmap = await createImageBitmap(file);
    const canvas = document.createElement('canvas');
    canvas.width = bitmap.width;
    canvas.height = bitmap.height;
    const ctx = canvas.getContext('2d');
    if (!ctx) {
        bitmap.close();

        return file;
    }

    ctx.drawImage(bitmap, 0, 0);
    bitmap.close();

    const image = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const pixels = image.data;

    for (let i = 0; i < pixels.length; i += 4) {
        const r = pixels[i];
        const g = pixels[i + 1];
        const b = pixels[i + 2];
        const min = Math.min(r, g, b);
        const max = Math.max(r, g, b);
        const chroma = max - min;

        if (min >= 246 && chroma <= 16) {
            pixels[i + 3] = 0;

            continue;
        }

        if (min >= 222 && chroma <= 26) {
            const t = (min - 222) / 24;
            pixels[i + 3] = Math.round((1 - t) * 200);
        }
    }

    ctx.putImageData(image, 0, 0);

    const blob = await new Promise<Blob | null>((resolve) => {
        canvas.toBlob(resolve, 'image/png');
    });

    if (!blob) {
        return file;
    }

    const baseName = file.name.replace(/\.[^.]+$/, '') || 'firma';

    return new File([blob], `${baseName}.png`, { type: 'image/png' });
}
