<?php

declare(strict_types=1);

namespace App\Support\Pdf;

use GdImage;
use RuntimeException;

/**
 * Convierte el sello/firma subido a PNG con fondo de papel transparente.
 */
final class SignatureImageProcessor
{
    public function toTransparentPng(string $binary): string
    {
        if ($binary === '') {
            throw new RuntimeException('La imagen de la firma está vacía.');
        }

        $source = @imagecreatefromstring($binary);
        if (! $source instanceof GdImage) {
            throw new RuntimeException('No se pudo leer la imagen de la firma.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);

        imagealphablending($source, true);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        imagedestroy($source);

        $this->knockOutPaper($canvas, $width, $height);

        ob_start();
        imagepng($canvas, null, 6);
        $png = ob_get_clean();
        imagedestroy($canvas);

        if (! is_string($png) || $png === '') {
            throw new RuntimeException('No se pudo generar el PNG de la firma.');
        }

        return $png;
    }

    private function knockOutPaper(GdImage $canvas, int $width, int $height): void
    {
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($canvas, $x, $y);
                $a = ($rgba >> 24) & 0x7F;
                if ($a >= 120) {
                    continue;
                }

                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $min = min($r, $g, $b);
                $max = max($r, $g, $b);
                $chroma = $max - $min;

                if ($min >= 246 && $chroma <= 16) {
                    imagesetpixel($canvas, $x, $y, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

                    continue;
                }

                if ($min >= 222 && $chroma <= 26) {
                    $t = ($min - 222) / 24;
                    $alpha = (int) round(20 + (107 * $t));
                    $alpha = min(127, max(0, $alpha));
                    imagesetpixel(
                        $canvas,
                        $x,
                        $y,
                        imagecolorallocatealpha($canvas, $r, $g, $b, $alpha),
                    );
                }
            }
        }
    }
}
