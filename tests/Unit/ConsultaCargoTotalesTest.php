<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ConsultaCargo\ConsultaCargoTotales;
use PHPUnit\Framework\TestCase;

class ConsultaCargoTotalesTest extends TestCase
{
    public function test_excluye_igv_desde_precio_sin_igv(): void
    {
        $t = ConsultaCargoTotales::fromLineas(
            [
                ['cantidad' => 2, 'precio_unitario' => 50, 'descuento_importe' => 0],
            ],
            false,
            18.0,
        );

        $this->assertSame('100.00', $t['subtotal_sin_igv']);
        $this->assertSame('18.00', $t['igv_importe']);
        $this->assertSame('118.00', $t['total']);
    }

    public function test_precio_incluye_igv_extrae_base(): void
    {
        $t = ConsultaCargoTotales::fromLineas(
            [
                ['cantidad' => 1, 'precio_unitario' => 118, 'descuento_importe' => 0],
            ],
            true,
            18.0,
        );

        $this->assertSame('100.00', $t['subtotal_sin_igv']);
        $this->assertSame('18.00', $t['igv_importe']);
        $this->assertSame('118.00', $t['total']);
    }

    public function test_descuento_por_linea(): void
    {
        $t = ConsultaCargoTotales::fromLineas(
            [
                ['cantidad' => 1, 'precio_unitario' => 100, 'descuento_importe' => 10],
            ],
            false,
            18.0,
        );

        $this->assertSame('90.00', $t['subtotal_sin_igv']);
        $this->assertSame('16.20', $t['igv_importe']);
        $this->assertSame('106.20', $t['total']);
    }
}
