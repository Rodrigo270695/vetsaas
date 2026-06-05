<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Seeder;

class PlansAndFeaturesSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            'free' => [
                'nombre' => 'Free',
                'descripcion' => 'Para conocer el sistema sin compromiso',
                'badge' => null,
                'color_hex' => '#6B7280',
                'precio_mensual' => 0,
                'trial_days' => 0,
                'orden' => 1,
                'features' => [
                    ['feature' => 'max_sedes', 'valor_int' => 1, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_pacientes', 'valor_int' => 50, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_propietarios', 'valor_int' => 50, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_productos', 'valor_int' => 50, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_usuarios', 'valor_int' => 1, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_citas_mes', 'valor_int' => 100, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_cpe_mes', 'valor_int' => 0, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_wa_mes', 'valor_int' => 0, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'historia_clinica', 'valor_int' => null, 'valor_bool' => true, 'valor_str' => null],
                    ['feature' => 'factura_electronica', 'valor_int' => null, 'valor_bool' => false, 'valor_str' => null],
                    ['feature' => 'modulo_stock', 'valor_int' => null, 'valor_bool' => false, 'valor_str' => null],
                    ['feature' => 'modulo_grooming', 'valor_int' => null, 'valor_bool' => false, 'valor_str' => null],
                    ['feature' => 'modulo_guarderia', 'valor_int' => null, 'valor_bool' => false, 'valor_str' => null],
                    ['feature' => 'modulo_laboratorio', 'valor_int' => null, 'valor_bool' => false, 'valor_str' => null],
                    ['feature' => 'multi_sede', 'valor_int' => null, 'valor_bool' => false, 'valor_str' => null],
                    ['feature' => 'api_acceso', 'valor_int' => null, 'valor_bool' => false, 'valor_str' => null],
                    ['feature' => 'reportes_avanzados', 'valor_int' => null, 'valor_bool' => false, 'valor_str' => null],
                    ['feature' => 'soporte_tipo', 'valor_int' => null, 'valor_bool' => null, 'valor_str' => 'docs'],
                ],
            ],
            'starter' => [
                'nombre' => 'Starter',
                'descripcion' => 'Para clínicas pequeñas que inician su digitalización',
                'badge' => null,
                'color_hex' => '#0F6E56',
                'precio_mensual' => 149,
                'trial_days' => 14,
                'orden' => 2,
                'features' => [
                    ['feature' => 'max_sedes', 'valor_int' => 1, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_pacientes', 'valor_int' => 300, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_propietarios', 'valor_int' => 300, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_productos', 'valor_int' => 200, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_usuarios', 'valor_int' => 2, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_citas_mes', 'valor_int' => 500, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_cpe_mes', 'valor_int' => 100, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_wa_mes', 'valor_int' => 50, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'historia_clinica', 'valor_int' => null, 'valor_bool' => true, 'valor_str' => null],
                    ['feature' => 'factura_electronica', 'valor_int' => null, 'valor_bool' => false, 'valor_str' => null],
                    ['feature' => 'modulo_stock', 'valor_int' => null, 'valor_bool' => true, 'valor_str' => null],
                    ['feature' => 'modulo_grooming', 'valor_int' => null, 'valor_bool' => false, 'valor_str' => null],
                    ['feature' => 'modulo_guarderia', 'valor_int' => null, 'valor_bool' => false, 'valor_str' => null],
                    ['feature' => 'modulo_laboratorio', 'valor_int' => null, 'valor_bool' => false, 'valor_str' => null],
                    ['feature' => 'multi_sede', 'valor_int' => null, 'valor_bool' => false, 'valor_str' => null],
                    ['feature' => 'api_acceso', 'valor_int' => null, 'valor_bool' => false, 'valor_str' => null],
                    ['feature' => 'reportes_avanzados', 'valor_int' => null, 'valor_bool' => false, 'valor_str' => null],
                    ['feature' => 'soporte_tipo', 'valor_int' => null, 'valor_bool' => null, 'valor_str' => 'email'],
                ],
            ],
            'pro' => [
                'nombre' => 'Pro',
                'descripcion' => 'Para clínicas en crecimiento con facturación activa',
                'badge' => 'Más popular',
                'color_hex' => '#1D4ED8',
                'precio_mensual' => 249,
                'trial_days' => 14,
                'orden' => 3,
                'features' => [
                    ['feature' => 'max_sedes', 'valor_int' => 3, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_pacientes', 'valor_int' => -1, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_propietarios', 'valor_int' => -1, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_productos', 'valor_int' => 500, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_usuarios', 'valor_int' => 5, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_citas_mes', 'valor_int' => -1, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_cpe_mes', 'valor_int' => 300, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_wa_mes', 'valor_int' => -1, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'historia_clinica', 'valor_int' => null, 'valor_bool' => true, 'valor_str' => null],
                    ['feature' => 'factura_electronica', 'valor_int' => null, 'valor_bool' => false, 'valor_str' => null],
                    ['feature' => 'modulo_stock', 'valor_int' => null, 'valor_bool' => true, 'valor_str' => null],
                    ['feature' => 'modulo_grooming', 'valor_int' => null, 'valor_bool' => true, 'valor_str' => null],
                    ['feature' => 'modulo_guarderia', 'valor_int' => null, 'valor_bool' => false, 'valor_str' => null],
                    ['feature' => 'modulo_laboratorio', 'valor_int' => null, 'valor_bool' => true, 'valor_str' => null],
                    ['feature' => 'multi_sede', 'valor_int' => null, 'valor_bool' => false, 'valor_str' => null],
                    ['feature' => 'api_acceso', 'valor_int' => null, 'valor_bool' => false, 'valor_str' => null],
                    ['feature' => 'reportes_avanzados', 'valor_int' => null, 'valor_bool' => true, 'valor_str' => null],
                    ['feature' => 'soporte_tipo', 'valor_int' => null, 'valor_bool' => null, 'valor_str' => 'whatsapp'],
                ],
            ],
            'clinica' => [
                'nombre' => 'Clínica',
                'descripcion' => 'Para clínicas grandes con múltiples sedes y equipo',
                'badge' => 'Mejor valor',
                'color_hex' => '#7C3AED',
                'precio_mensual' => 399,
                'trial_days' => 7,
                'orden' => 4,
                'features' => [
                    ['feature' => 'max_sedes', 'valor_int' => -1, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_pacientes', 'valor_int' => -1, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_propietarios', 'valor_int' => -1, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_productos', 'valor_int' => -1, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_usuarios', 'valor_int' => -1, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_citas_mes', 'valor_int' => -1, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_cpe_mes', 'valor_int' => -1, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'max_wa_mes', 'valor_int' => -1, 'valor_bool' => null, 'valor_str' => null],
                    ['feature' => 'historia_clinica', 'valor_int' => null, 'valor_bool' => true, 'valor_str' => null],
                    ['feature' => 'factura_electronica', 'valor_int' => null, 'valor_bool' => true, 'valor_str' => null],
                    ['feature' => 'modulo_stock', 'valor_int' => null, 'valor_bool' => true, 'valor_str' => null],
                    ['feature' => 'modulo_grooming', 'valor_int' => null, 'valor_bool' => true, 'valor_str' => null],
                    ['feature' => 'modulo_guarderia', 'valor_int' => null, 'valor_bool' => true, 'valor_str' => null],
                    ['feature' => 'modulo_laboratorio', 'valor_int' => null, 'valor_bool' => true, 'valor_str' => null],
                    ['feature' => 'multi_sede', 'valor_int' => null, 'valor_bool' => true, 'valor_str' => null],
                    ['feature' => 'api_acceso', 'valor_int' => null, 'valor_bool' => true, 'valor_str' => null],
                    ['feature' => 'reportes_avanzados', 'valor_int' => null, 'valor_bool' => true, 'valor_str' => null],
                    ['feature' => 'soporte_tipo', 'valor_int' => null, 'valor_bool' => null, 'valor_str' => 'whatsapp_prioritario'],
                ],
            ],
        ];

        foreach ($definitions as $codigo => $meta) {
            $plan = Plan::query()->updateOrCreate(
                ['codigo' => $codigo],
                [
                    'nombre' => $meta['nombre'],
                    'descripcion' => $meta['descripcion'],
                    'badge' => $meta['badge'],
                    'color_hex' => $meta['color_hex'],
                    'precio_mensual' => $meta['precio_mensual'],
                    'precio_anual' => null,
                    'trial_days' => $meta['trial_days'],
                    'orden' => $meta['orden'],
                    'es_publico' => true,
                    'activo' => true,
                ],
            );

            $plan->features()->delete();

            foreach ($meta['features'] as $row) {
                PlanFeature::query()->create([
                    'plan_id' => $plan->id,
                    'feature' => $row['feature'],
                    'valor_int' => $row['valor_int'],
                    'valor_bool' => $row['valor_bool'],
                    'valor_str' => $row['valor_str'],
                ]);
            }
        }
    }
}
