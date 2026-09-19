<?php

use App\Support\Pdf\SignatureImageProcessor;

it('vuelve transparente el fondo blanco y conserva tinta oscura', function (): void {
    $img = imagecreatetruecolor(40, 20);
    $white = imagecolorallocate($img, 255, 255, 255);
    $blue = imagecolorallocate($img, 30, 70, 200);
    imagefilledrectangle($img, 0, 0, 39, 19, $white);
    imagefilledrectangle($img, 8, 4, 18, 15, $blue);

    ob_start();
    imagepng($img);
    $binary = (string) ob_get_clean();
    imagedestroy($img);

    $png = (new SignatureImageProcessor)->toTransparentPng($binary);
    $out = imagecreatefromstring($png);
    expect($out)->toBeInstanceOf(GdImage::class);

    $corner = imagecolorat($out, 0, 0);
    expect(($corner >> 24) & 0x7F)->toBeGreaterThan(100);

    $ink = imagecolorat($out, 12, 8);
    expect(($ink >> 24) & 0x7F)->toBeLessThan(20);
    expect(($ink >> 16) & 0xFF)->toBeLessThan(80);

    imagedestroy($out);
});
