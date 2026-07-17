<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Venta\DescuentoManualLinea;
use PHPUnit\Framework\TestCase;

class DescuentoManualLineaTest extends TestCase
{
    public function test_aplica_descuento_manual_sobre_precio_con_igv(): void
    {
        $result = DescuentoManualLinea::apply(
            [[
                'cantidad' => 2,
                'precio_lista' => 59.00,
                'precio_unitario' => 50.00,
                'subtotal' => 100.00,
                'descuento_pct' => 0,
                'promotion_id' => null,
            ]],
            [['descuento_pct' => 10]],
            18.0,
            true,
        );

        $this->assertSame(90.00, $result['lineas'][0]['subtotal']);
        $this->assertSame(45.00, $result['lineas'][0]['precio_unitario']);
        $this->assertSame(10.00, $result['lineas'][0]['descuento_pct']);
        $this->assertSame(11.80, $result['discount_amount']);
    }

    public function test_compone_promocion_y_descuento_manual_sin_perder_promocion(): void
    {
        $result = DescuentoManualLinea::apply(
            [[
                'cantidad' => 1,
                'precio_lista' => 100.00,
                'precio_unitario' => 76.2712,
                'subtotal' => 76.27,
                'descuento_pct' => 10,
                'promotion_id' => 'promo-1',
            ]],
            [['descuento_pct' => 20]],
            18.0,
            true,
        );

        $this->assertSame(61.02, $result['lineas'][0]['subtotal']);
        $this->assertSame(28.00, $result['lineas'][0]['descuento_pct']);
        $this->assertSame('promo-1', $result['lineas'][0]['promotion_id']);
        $this->assertSame(18.00, $result['discount_amount']);
    }

    public function test_aplica_descuento_manual_a_precio_sin_igv(): void
    {
        $result = DescuentoManualLinea::apply(
            [[
                'cantidad' => 2,
                'precio_lista' => 50.00,
                'precio_unitario' => 50.00,
                'subtotal' => 100.00,
                'descuento_pct' => 0,
            ]],
            [['descuento_pct' => 25]],
            18.0,
            false,
        );

        $this->assertSame(75.00, $result['lineas'][0]['subtotal']);
        $this->assertSame(37.50, $result['lineas'][0]['precio_unitario']);
        $this->assertSame(25.00, $result['lineas'][0]['descuento_pct']);
        $this->assertSame(29.50, $result['discount_amount']);
    }
}
