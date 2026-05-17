<?php

namespace Tests\Unit;

use App\Grooming\GroomingCatalogoServicio;
use PHPUnit\Framework\TestCase;

class GroomingCatalogoServicioTest extends TestCase
{
    public function test_cada_slug_del_catalogo_tiene_duracion_sugerida(): void
    {
        $slugs = GroomingCatalogoServicio::slugs();
        $duraciones = GroomingCatalogoServicio::duracionesSugeridas();

        foreach ($slugs as $slug) {
            $this->assertArrayHasKey(
                $slug,
                $duraciones,
                "Falta duración sugerida para el slug «{$slug}».",
            );
            $this->assertGreaterThanOrEqual(5, $duraciones[$slug]);
            $this->assertLessThanOrEqual(480, $duraciones[$slug]);
        }

        $this->assertSame([], array_values(array_diff(array_keys($duraciones), $slugs)));
    }
}
