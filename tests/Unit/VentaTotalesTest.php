<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Venta\VentaTotales;
use PHPUnit\Framework\TestCase;

class VentaTotalesTest extends TestCase
{
    public function test_precio_incluye_igv_no_pierde_centimos_en_total(): void
    {
        $lineas = [
            ['cantidad' => 1, 'precio_lista' => 209.00, 'subtotal' => 177.12, 'descuento_pct' => 0],
            ['cantidad' => 1, 'precio_lista' => 215.90, 'subtotal' => 182.97, 'descuento_pct' => 0],
            ['cantidad' => 1, 'precio_lista' => 0.10, 'subtotal' => 0.08, 'descuento_pct' => 0],
        ];

        $totales = VentaTotales::fromLineas($lineas, 18.0, true);

        $this->assertSame(360.17, $totales['subtotal']);
        $this->assertSame(425.00, $totales['total']);
        $this->assertSame(64.83, $totales['igv']);
    }

    public function test_precio_sin_igv_suma_base_mas_impuesto(): void
    {
        $lineas = [
            ['cantidad' => 2, 'precio_lista' => 50.00, 'subtotal' => 100.00, 'descuento_pct' => 0],
        ];

        $totales = VentaTotales::fromLineas($lineas, 18.0, false);

        $this->assertSame(100.00, $totales['subtotal']);
        $this->assertSame(18.00, $totales['igv']);
        $this->assertSame(118.00, $totales['total']);
    }

    public function test_descuento_porcentual_sobre_bruto(): void
    {
        $lineas = [
            ['cantidad' => 1, 'precio_lista' => 118.00, 'subtotal' => 90.00, 'descuento_pct' => 10.0],
        ];

        $totales = VentaTotales::fromLineas($lineas, 18.0, true);

        $this->assertSame(90.00, $totales['subtotal']);
        $this->assertSame(106.20, $totales['total']);
        $this->assertSame(16.20, $totales['igv']);
    }
}
