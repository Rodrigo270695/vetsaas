<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Sede;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pobla el tenant "demo" con datos clínicos realistas para prospectos.
 *
 * El prospecto entra con demo@vetsaas.pe / demo1234 y ve una clínica
 * que ya funciona: pacientes, historial, citas, caja y stock.
 *
 * Idempotente: trunca las tablas operativas del schema vet_demo antes
 * de reinsertar, por lo que se puede ejecutar múltiples veces sin
 * duplicar datos. Ideal para un job diario que resetea el demo.
 *
 * Requisitos:
 *   · DemoTenantsSeeder ya corrió (tenant "demo" existe en public.tenants)
 *   · InventarioCategoriasYProductosSeeder ya corrió para el schema vet_demo
 *   · SedesSeeder ya corrió (existe al menos 1 sede para el tenant demo)
 *
 * Uso:
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * O en cadena:
 *   php artisan db:seed --class=DemoTenantsSeeder
 *   php artisan db:seed --class=DemoDataSeeder
 */
final class DemoDataSeeder extends Seeder
{
    private const DEMO_SLUG = 'demo';

    public function run(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->command?->warn('DemoDataSeeder requiere PostgreSQL. Omitido.');

            return;
        }

        $tenant = Tenant::query()->where('slug', self::DEMO_SLUG)->first();

        if ($tenant === null) {
            $this->command?->error('Tenant "demo" no existe. Ejecuta DemoTenantsSeeder primero.');

            return;
        }

        $schemaName = (string) $tenant->schema_name;

        // Usuario admin del tenant demo (veterinario y creador de todo).
        $adminUser = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', 'demo@vetsaas.pe')
            ->first();

        if ($adminUser === null) {
            $this->command?->error('Usuario demo@vetsaas.pe no encontrado. Ejecuta DemoTenantsSeeder primero.');

            return;
        }

        // Sede principal del tenant demo (creada por SedesSeeder en public.sedes).
        $sede = Sede::query()->where('tenant_id', $tenant->id)->orderBy('codigo')->first();

        if ($sede === null) {
            $this->command?->error('No hay sedes para el tenant demo. Ejecuta SedesSeeder primero.');

            return;
        }

        DB::statement('SET search_path TO "'.$schemaName.'", public');

        try {
            $this->truncateOperationalTables();
            $this->command?->info('  → Tablas operativas limpiadas.');

            $propietarios = $this->seedPropietarios($adminUser->id);
            $this->command?->info('  → '.count($propietarios).' propietarios insertados.');

            $pacientes = $this->seedPacientes($propietarios, $adminUser->id);
            $this->command?->info('  → '.count($pacientes).' pacientes insertados.');

            $historias = $this->seedHistoriasClinicas($pacientes, $adminUser->id);
            $this->command?->info('  → '.count($historias).' historias clínicas creadas.');

            $this->seedConsultas($historias, $adminUser->id);
            $this->command?->info('  → Consultas y vitales sembrados.');

            $this->seedCitas($pacientes, $adminUser->id, $sede->id);
            $this->command?->info('  → Citas de la semana programadas.');

            $this->seedCajaYVentas($propietarios, $pacientes, $adminUser->id, $sede->id);
            $this->command?->info('  → Caja y ventas de los últimos 7 días sembradas.');

            $this->seedStock($sede->id, $adminUser->id);
            $this->command?->info('  → Stock sembrado.');

            $this->command?->info('✓ Demo tenant listo — demo@vetsaas.pe / demo1234');
        } finally {
            DB::statement('SET search_path TO public');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TRUNCATE
    // ─────────────────────────────────────────────────────────────────────────

    private function truncateOperationalTables(): void
    {
        // Orden: hijos antes que padres para evitar FK violations.
        $tables = [
            'movimientos_inventario',
            'existencias_sede',
            'venta_lineas',
            'ventas',
            'caja_sesiones',
            'citas',
            'vacunas_aplicadas',
            'consultas',
            'historias_clinicas',
            'pacientes',
            'propietarios',
        ];

        foreach ($tables as $table) {
            DB::statement('TRUNCATE TABLE "'.$table.'" CASCADE');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PROPIETARIOS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, string>  map nombre_corto → uuid
     */
    private function seedPropietarios(string $adminId): array
    {
        $now = now();

        $data = [
            ['key' => 'carlos',  'tipo' => 'DNI', 'num' => '43215678', 'nombres' => 'Carlos',    'apellidos' => 'Ríos Mendoza',    'email' => 'carlos.rios@gmail.com',     'tel' => '987654321', 'dir' => 'Av. Brasil 1240, Jesús María',   'dist' => 'Jesús María',  'prov' => 'Lima', 'dep' => 'Lima'],
            ['key' => 'maria',   'tipo' => 'DNI', 'num' => '52341890', 'nombres' => 'María',     'apellidos' => 'Torres Vega',     'email' => 'maria.torres@hotmail.com',   'tel' => '976543210', 'dir' => 'Calle Los Pinos 345, Surco',      'dist' => 'Surco',        'prov' => 'Lima', 'dep' => 'Lima'],
            ['key' => 'jose',    'tipo' => 'DNI', 'num' => '71234567', 'nombres' => 'José',      'apellidos' => 'Ramírez Castro',  'email' => 'jose.ramirez@outlook.com',   'tel' => '965432109', 'dir' => 'Jr. Huancavelica 890, Lima',      'dist' => 'Lima',         'prov' => 'Lima', 'dep' => 'Lima'],
            ['key' => 'ana',     'tipo' => 'DNI', 'num' => '48765432', 'nombres' => 'Ana',       'apellidos' => 'Flores Huamán',   'email' => 'ana.flores@gmail.com',       'tel' => '954321098', 'dir' => 'Av. Universitaria 567, SMP',      'dist' => 'San Martín de Porres', 'prov' => 'Lima', 'dep' => 'Lima'],
            ['key' => 'luis',    'tipo' => 'DNI', 'num' => '60987654', 'nombres' => 'Luis',      'apellidos' => 'Mendoza Paredes', 'email' => 'luis.mendoza@yahoo.com',     'tel' => '943210987', 'dir' => 'Calle Benavides 123, Miraflores', 'dist' => 'Miraflores',   'prov' => 'Lima', 'dep' => 'Lima'],
            ['key' => 'carmen',  'tipo' => 'DNI', 'num' => '55123456', 'nombres' => 'Carmen',    'apellidos' => 'Vega Salinas',    'email' => 'carmen.vega@gmail.com',      'tel' => '932109876', 'dir' => 'Av. La Marina 456, San Miguel',   'dist' => 'San Miguel',   'prov' => 'Lima', 'dep' => 'Lima'],
            ['key' => 'pedro',   'tipo' => 'DNI', 'num' => '41234567', 'nombres' => 'Pedro',     'apellidos' => 'Castillo Díaz',   'email' => 'pedro.castillo@gmail.com',   'tel' => '921098765', 'dir' => 'Jr. Cusco 78, Barranco',          'dist' => 'Barranco',     'prov' => 'Lima', 'dep' => 'Lima'],
            ['key' => 'sofia',   'tipo' => 'DNI', 'num' => '73456789', 'nombres' => 'Sofía',     'apellidos' => 'Paredes Núñez',   'email' => 'sofia.paredes@gmail.com',    'tel' => '910987654', 'dir' => 'Av. Arequipa 2234, Lince',        'dist' => 'Lince',        'prov' => 'Lima', 'dep' => 'Lima'],
            ['key' => 'juan',    'tipo' => 'DNI', 'num' => '46789012', 'nombres' => 'Juan',      'apellidos' => 'García López',    'email' => 'juan.garcia@hotmail.com',    'tel' => '999888777', 'dir' => 'Calle Larco 312, Miraflores',     'dist' => 'Miraflores',   'prov' => 'Lima', 'dep' => 'Lima'],
            ['key' => 'patricia','tipo' => 'DNI', 'num' => '62345678', 'nombres' => 'Patricia',  'apellidos' => 'Vargas Espinoza', 'email' => 'patricia.vargas@gmail.com',  'tel' => '988777666', 'dir' => 'Av. Petit Thouars 890, Lince',    'dist' => 'Lince',        'prov' => 'Lima', 'dep' => 'Lima'],
        ];

        $map = [];

        foreach ($data as $row) {
            $id = (string) Str::uuid();

            DB::table('propietarios')->insert([
                'id'               => $id,
                'tipo_documento'   => $row['tipo'],
                'numero_documento' => $row['num'],
                'nombres'          => $row['nombres'],
                'apellidos'        => $row['apellidos'],
                'email'            => $row['email'],
                'telefono'         => $row['tel'],
                'direccion'        => $row['dir'],
                'distrito'         => $row['dist'],
                'provincia'        => $row['prov'],
                'departamento'     => $row['dep'],
                'activo'           => true,
                'created_by_id'    => $adminId,
                'updated_by_id'    => $adminId,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            $map[$row['key']] = $id;
        }

        return $map;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PACIENTES
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, string>  $propietarios
     * @return array<string, string>  map nombre_mascota → uuid
     */
    private function seedPacientes(array $propietarios, string $adminId): array
    {
        $now = now();

        $data = [
            // clave          propietario   nombre      especie  raza                sexo  nacimiento    peso  color              esterilizado
            ['key'=>'max',    'prop'=>'carlos',  'nombre'=>'Max',    'esp'=>'Canino', 'raza'=>'Golden Retriever',   'sexo'=>'M', 'nac'=>'2021-03-15', 'peso'=>28.5, 'color'=>'Dorado',          'est'=>false],
            ['key'=>'luna',   'prop'=>'maria',   'nombre'=>'Luna',   'esp'=>'Felino', 'raza'=>'Persa',              'sexo'=>'F', 'nac'=>'2022-07-20', 'peso'=>4.2,  'color'=>'Blanco',          'est'=>true],
            ['key'=>'rocky',  'prop'=>'jose',    'nombre'=>'Rocky',  'esp'=>'Canino', 'raza'=>'Bulldog Francés',    'sexo'=>'M', 'nac'=>'2023-01-10', 'peso'=>12.0, 'color'=>'Atigrado',        'est'=>false],
            ['key'=>'toby',   'prop'=>'ana',     'nombre'=>'Toby',   'esp'=>'Canino', 'raza'=>'Beagle',             'sexo'=>'M', 'nac'=>'2019-06-05', 'peso'=>9.8,  'color'=>'Tricolor',        'est'=>true],
            ['key'=>'mia',    'prop'=>'luis',    'nombre'=>'Mia',    'esp'=>'Felino', 'raza'=>'Siamés',             'sexo'=>'F', 'nac'=>'2020-11-30', 'peso'=>3.8,  'color'=>'Crema y café',    'est'=>true],
            ['key'=>'pelusa', 'prop'=>'carmen',  'nombre'=>'Pelusa', 'esp'=>'Canino', 'raza'=>'Cocker Spaniel',     'sexo'=>'F', 'nac'=>'2022-04-12', 'peso'=>11.2, 'color'=>'Blanco y caramelo','est'=>false],
            ['key'=>'bruno',  'prop'=>'pedro',   'nombre'=>'Bruno',  'esp'=>'Canino', 'raza'=>'Labrador Retriever', 'sexo'=>'M', 'nac'=>'2018-09-22', 'peso'=>32.0, 'color'=>'Negro',           'est'=>true],
            ['key'=>'nala',   'prop'=>'sofia',   'nombre'=>'Nala',   'esp'=>'Canino', 'raza'=>'Shih Tzu',           'sexo'=>'F', 'nac'=>'2024-02-14', 'peso'=>5.5,  'color'=>'Blanco y gris',   'est'=>false],
            ['key'=>'simba',  'prop'=>'juan',    'nombre'=>'Simba',  'esp'=>'Felino', 'raza'=>'Maine Coon',         'sexo'=>'M', 'nac'=>'2021-08-01', 'peso'=>6.1,  'color'=>'Atigrado marrón', 'est'=>true],
            ['key'=>'kira',   'prop'=>'patricia','nombre'=>'Kira',   'esp'=>'Canino', 'raza'=>'Poodle Miniatura',   'sexo'=>'F', 'nac'=>'2022-12-25', 'peso'=>4.8,  'color'=>'Apricot',         'est'=>false],
            ['key'=>'titan',  'prop'=>'carlos',  'nombre'=>'Titán',  'esp'=>'Canino', 'raza'=>'Rottweiler',         'sexo'=>'M', 'nac'=>'2020-05-18', 'peso'=>42.0, 'color'=>'Negro y fuego',   'est'=>true],
            ['key'=>'cleo',   'prop'=>'maria',   'nombre'=>'Cleo',   'esp'=>'Felino', 'raza'=>'Bengalí',            'sexo'=>'F', 'nac'=>'2023-03-07', 'peso'=>3.5,  'color'=>'Moteado marrón',  'est'=>true],
        ];

        $map = [];

        foreach ($data as $row) {
            $id = (string) Str::uuid();

            DB::table('pacientes')->insert([
                'id'              => $id,
                'propietario_id'  => $propietarios[$row['prop']],
                'nombre'          => $row['nombre'],
                'especie'         => $row['esp'],
                'raza'            => $row['raza'],
                'sexo'            => $row['sexo'],
                'fecha_nacimiento'=> $row['nac'],
                'peso_kg'         => $row['peso'],
                'color'           => $row['color'],
                'esterilizado'    => $row['est'],
                'activo'          => true,
                'created_by_id'   => $adminId,
                'updated_by_id'   => $adminId,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            $map[$row['key']] = $id;
        }

        return $map;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HISTORIAS CLÍNICAS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, string>  $pacientes
     * @return array<string, string>  map nombre_mascota → historia_id
     */
    private function seedHistoriasClinicas(array $pacientes, string $adminId): array
    {
        $now = now();
        $map = [];

        foreach ($pacientes as $key => $pacienteId) {
            $id = (string) Str::uuid();

            DB::table('historias_clinicas')->insert([
                'id'            => $id,
                'paciente_id'   => $pacienteId,
                'created_by_id' => $adminId,
                'updated_by_id' => $adminId,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            $map[$key] = $id;
        }

        return $map;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONSULTAS (HISTORIAL CLÍNICO LLENO)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, string>  $historias
     */
    private function seedConsultas(array $historias, string $adminId): void
    {
        $consultas = [
            // Max — Control anual + vacuna
            [
                'historia' => 'max',
                'fecha'    => now()->subDays(45),
                'motivo'   => 'Control anual y aplicación de vacunas',
                'subjetivo'=> 'Propietario refiere que el paciente come bien, activo y sin síntomas. Última vacuna hace 12 meses.',
                'objetivo' => 'Paciente alerta, buen estado corporal. MC: 3/5. Mucosas rosadas, TRC < 2s. Temperatura 38.4°C. FC: 88 lpm. FR: 22 rpm.',
                'analisis' => 'Paciente en buen estado general. Sin evidencia de enfermedad activa. Dentición con acumulación leve de sarro.',
                'plan'     => 'Vacuna polivalente canina (octuple) aplicada. Vacuna antirrábica aplicada. Se recomienda limpieza dental en próxima visita. Control en 12 meses.',
                'peso'     => 28.5, 'temp' => 38.4, 'fc' => 88, 'fr' => 22,
            ],
            // Max — Segunda consulta (dermatológica)
            [
                'historia' => 'max',
                'fecha'    => now()->subDays(15),
                'motivo'   => 'Prurito e irritación en zona ventral',
                'subjetivo'=> 'Propietario reporta que Max se rasca constantemente el abdomen desde hace 10 días. Sin cambio de dieta ni ambiente.',
                'objetivo' => 'Eritema difuso en zona inguinal y ventral. Pápulas aisladas. Sin alopecia. Temperatura 38.6°C. FC: 92 lpm.',
                'analisis' => 'Cuadro compatible con dermatitis alérgica de contacto. Se descarta infección bacteriana primaria.',
                'plan'     => 'Shampoo hipoalergénico cada 48h. Meloxicam 0.1 mg/kg cada 24h por 5 días. Control en 7 días. Si no mejora, derivar a dermatología.',
                'peso'     => 28.2, 'temp' => 38.6, 'fc' => 92, 'fr' => 24,
            ],
            // Luna — Esterilización + postoperatorio
            [
                'historia' => 'luna',
                'fecha'    => now()->subDays(60),
                'motivo'   => 'Ovariohisterectomía electiva',
                'subjetivo'=> 'Paciente traída para esterilización programada. Sin patologías previas. Ayuno de 12 horas cumplido.',
                'objetivo' => 'Paciente estable pre-quirúrgico. Exámenes prequirúrgicos dentro de rangos normales. Peso 4.2 kg.',
                'analisis' => 'Candidata apta para anestesia general. ASA I.',
                'plan'     => 'Premedicación: Dexmedetomidina + Butorfanol IM. Inducción: Propofol IV. Mantenimiento: Isoflurano. Procedimiento sin incidencias. Alta a las 6h.',
                'peso'     => 4.2, 'temp' => 38.3, 'fc' => 140, 'fr' => 28,
            ],
            // Rocky — Consulta dermatológica
            [
                'historia' => 'rocky',
                'fecha'    => now()->subDays(20),
                'motivo'   => 'Lesiones en piel y pérdida de pelo en zona facial',
                'subjetivo'=> 'Dueño observa caída de pelo en hocico y región periocular desde hace 3 semanas. Sin prurito aparente.',
                'objetivo' => 'Alopecia focal en región facial. Pústulas aisladas. Raspado cutáneo tomado. Temperatura 38.8°C.',
                'analisis' => 'Hallazgos compatibles con demodicosis localizada. Confirmación pendiente de resultados de raspado.',
                'plan'     => 'Baño con benzoyl peroxide. Ivermectina 0.3 mg/kg SC semanal x4 semanas. Control en 15 días.',
                'peso'     => 12.0, 'temp' => 38.8, 'fc' => 110, 'fr' => 26,
            ],
            // Toby — Control geriátrico
            [
                'historia' => 'toby',
                'fecha'    => now()->subDays(30),
                'motivo'   => 'Control preventivo geriátrico (7 años)',
                'subjetivo'=> 'Sin quejas activas. Dueña nota que está más lento al subir escaleras.',
                'objetivo' => 'Paciente en buen estado. Leve aumento de sarro. Examen ortopédico: leve rigidez en cadera derecha. Temperatura 38.5°C. Peso estable.',
                'analisis' => 'Cambios articulares leves compatibles con edad. Enfermedad periodontal grado 1.',
                'plan'     => 'Perfil geriátrico completo solicitado. Meloxicam 0.1 mg/kg según necesidad. Dieta senior. Limpieza dental programada. Control en 6 meses.',
                'peso'     => 9.8, 'temp' => 38.5, 'fc' => 85, 'fr' => 20,
            ],
            // Bruno — Consulta digestiva
            [
                'historia' => 'bruno',
                'fecha'    => now()->subDays(10),
                'motivo'   => 'Vómitos y diarrea desde hace 2 días',
                'subjetivo'=> 'Dueño reporta 3-4 episodios de vómito al día y diarrea líquida. Paciente decaído, no come desde ayer. Sin acceso a basura ni tóxicos conocidos.',
                'objetivo' => 'Paciente levemente deshidratado (8%). Mucosas pálidas. Dolor a palpación abdominal. Temperatura 39.1°C. FC: 110 lpm.',
                'analisis' => 'Gastroenteritis aguda. Descartar causa infecciosa. Inicio de tratamiento sintomático.',
                'plan'     => 'Fluidos IV: Ringer Lactato 50 ml/kg/día. Metronidazol 25 mg/kg BID x5d. Dieta blanda 48h. Alta con tratamiento oral. Control en 3 días.',
                'peso'     => 31.5, 'temp' => 39.1, 'fc' => 110, 'fr' => 30,
            ],
        ];

        foreach ($consultas as $c) {
            $historiaId = $historias[$c['historia']] ?? null;
            if ($historiaId === null) {
                continue;
            }

            $id       = (string) Str::uuid();
            $fecha    = $c['fecha'] instanceof Carbon ? $c['fecha'] : Carbon::parse($c['fecha']);
            $cerradaAt = $fecha->copy()->addHours(1);

            DB::table('consultas')->insert([
                'id'               => $id,
                'historia_clinica_id' => $historiaId,
                'atendido_at'      => $fecha,
                'motivo'           => $c['motivo'],
                'subjetivo'        => $c['subjetivo'],
                'objetivo'         => $c['objetivo'],
                'analisis'         => $c['analisis'],
                'plan'             => $c['plan'],
                'peso_kg'          => $c['peso'],
                'temperatura_c'    => $c['temp'],
                'fc_lpm'           => $c['fc'],
                'fr_rpm'           => $c['fr'],
                'cerrada_at'       => $cerradaAt,
                'cerrada_por_id'   => $adminId,
                'veterinario_id'   => $adminId,
                'created_by_id'    => $adminId,
                'updated_by_id'    => $adminId,
                'created_at'       => $fecha,
                'updated_at'       => $cerradaAt,
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CITAS — esta semana + próximas
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, string>  $pacientes
     */
    private function seedCitas(array $pacientes, string $adminId, string $sedeId): void
    {
        // Lunes de esta semana como ancla para fechas relativas.
        $lunes = now()->startOfWeek(Carbon::MONDAY);

        $citas = [
            ['pac' => 'max',    'dias' => 1, 'hora' => '09:00', 'dur' => 30, 'motivo' => 'Control post-tratamiento dermatológico',        'estado' => 'programada'],
            ['pac' => 'nala',   'dias' => 1, 'hora' => '10:00', 'dur' => 30, 'motivo' => 'Primera consulta — revisión general',           'estado' => 'programada'],
            ['pac' => 'luna',   'dias' => 1, 'hora' => '11:30', 'dur' => 45, 'motivo' => 'Control postoperatorio esterilización',         'estado' => 'programada'],
            ['pac' => 'toby',   'dias' => 2, 'hora' => '09:30', 'dur' => 30, 'motivo' => 'Vacuna múltiple anual',                        'estado' => 'programada'],
            ['pac' => 'pelusa', 'dias' => 2, 'hora' => '11:00', 'dur' => 30, 'motivo' => 'Revisión piel y oídos',                        'estado' => 'programada'],
            ['pac' => 'bruno',  'dias' => 3, 'hora' => '09:00', 'dur' => 30, 'motivo' => 'Control gastroenteritis — revisión',            'estado' => 'programada'],
            ['pac' => 'simba',  'dias' => 3, 'hora' => '10:30', 'dur' => 30, 'motivo' => 'Desparasitación y control de peso',             'estado' => 'programada'],
            ['pac' => 'kira',   'dias' => 4, 'hora' => '09:00', 'dur' => 30, 'motivo' => 'Vacuna antirrábica + polivalente',              'estado' => 'programada'],
            ['pac' => 'rocky',  'dias' => 4, 'hora' => '10:30', 'dur' => 45, 'motivo' => 'Control demodicosis — segunda evaluación',      'estado' => 'programada'],
            ['pac' => 'titan',  'dias' => 5, 'hora' => '09:00', 'dur' => 30, 'motivo' => 'Control anual + perfil bioquímico',             'estado' => 'programada'],
            ['pac' => 'mia',    'dias' => 5, 'hora' => '10:00', 'dur' => 30, 'motivo' => 'Revisión dental preventiva',                   'estado' => 'programada'],
            ['pac' => 'cleo',   'dias' => 5, 'hora' => '11:30', 'dur' => 30, 'motivo' => 'Primera consulta — vacunas iniciales',          'estado' => 'programada'],
            // Citas de la semana pasada (atendidas)
            ['pac' => 'max',    'dias' => -6, 'hora' => '09:00', 'dur' => 30, 'motivo' => 'Consulta dermatológica urgente',               'estado' => 'atendida'],
            ['pac' => 'bruno',  'dias' => -5, 'hora' => '10:00', 'dur' => 45, 'motivo' => 'Gastroenteritis — primera atención',           'estado' => 'atendida'],
            ['pac' => 'toby',   'dias' => -4, 'hora' => '09:30', 'dur' => 30, 'motivo' => 'Control geriátrico',                          'estado' => 'atendida'],
        ];

        foreach ($citas as $c) {
            $pacienteId = $pacientes[$c['pac']] ?? null;
            if ($pacienteId === null) {
                continue;
            }

            $inicio = $lunes->copy()->addDays($c['dias'])->setTimeFromTimeString($c['hora']);

            DB::table('citas')->insert([
                'id'               => (string) Str::uuid(),
                'paciente_id'      => $pacienteId,
                'veterinario_id'   => $adminId,
                'sede_id'          => $sedeId,
                'inicio_at'        => $inicio,
                'duracion_minutos' => $c['dur'],
                'estado'           => $c['estado'],
                'motivo'           => $c['motivo'],
                'created_by_id'    => $adminId,
                'updated_by_id'    => $adminId,
                'created_at'       => now()->subDays(abs((int) $c['dias']) + 1),
                'updated_at'       => now()->subDays(abs((int) $c['dias']) + 1),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CAJA Y VENTAS — últimos 7 días
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, string>  $propietarios
     * @param  array<string, string>  $pacientes
     */
    private function seedCajaYVentas(
        array $propietarios,
        array $pacientes,
        string $adminId,
        string $sedeId,
    ): void {
        // Sesión de caja abierta (turno actual).
        $sesionId = (string) Str::uuid();
        $abiertaEn = now()->startOfDay()->addHours(8);

        DB::table('caja_sesiones')->insert([
            'id'              => $sesionId,
            'sede_id'         => $sedeId,
            'estado'          => 'abierta',
            'moneda'          => 'PEN',
            'saldo_apertura'  => 200.00,
            'opened_at'       => $abiertaEn,
            'opened_by_id'    => $adminId,
            'created_at'      => $abiertaEn,
            'updated_at'      => $abiertaEn,
        ]);

        // Obtener productos del schema para las líneas de venta.
        $productos = DB::table('productos')
            ->select('id', 'nombre', 'precio_venta')
            ->where('activo', true)
            ->get()
            ->keyBy('nombre');

        $ventasData = [
            [
                'prop' => 'carlos', 'pac' => 'max',
                'fecha' => now()->subDays(6)->setTime(10, 30),
                'metodo' => 'efectivo', 'recibido' => 200.00,
                'lineas' => [
                    ['prod' => 'Vacuna polivalente canina (octuple)', 'qty' => 1],
                    ['prod' => 'Vacuna antirrábica inactivada',       'qty' => 1],
                    ['prod' => 'Jeringa desechable 3 ml',             'qty' => 2],
                ],
            ],
            [
                'prop' => 'jose', 'pac' => 'rocky',
                'fecha' => now()->subDays(5)->setTime(11, 0),
                'metodo' => 'tarjeta', 'recibido' => null,
                'lineas' => [
                    ['prod' => 'Shampoo hipoalergénico 250 ml',     'qty' => 2],
                    ['prod' => 'Meloxicam 2 mg/ml inyectable',      'qty' => 1],
                ],
            ],
            [
                'prop' => 'ana', 'pac' => 'toby',
                'fecha' => now()->subDays(4)->setTime(9, 45),
                'metodo' => 'efectivo', 'recibido' => 100.00,
                'lineas' => [
                    ['prod' => 'Amoxicilina 250 mg (comp.)', 'qty' => 10],
                    ['prod' => 'Gasas estériles (paquete)',   'qty' => 2],
                ],
            ],
            [
                'prop' => 'pedro', 'pac' => 'bruno',
                'fecha' => now()->subDays(3)->setTime(14, 0),
                'metodo' => 'yape', 'recibido' => null,
                'lineas' => [
                    ['prod' => 'Amoxicilina 250 mg (comp.)', 'qty' => 20],
                    ['prod' => 'Jeringa desechable 3 ml',    'qty' => 5],
                    ['prod' => 'Gasas estériles (paquete)',   'qty' => 3],
                ],
            ],
            [
                'prop' => 'maria', 'pac' => 'luna',
                'fecha' => now()->subDays(2)->setTime(10, 15),
                'metodo' => 'efectivo', 'recibido' => 200.00,
                'lineas' => [
                    ['prod' => 'Meloxicam 2 mg/ml inyectable',  'qty' => 1],
                    ['prod' => 'Collar nylon talla M',           'qty' => 1],
                ],
            ],
            [
                'prop' => 'carmen', 'pac' => 'pelusa',
                'fecha' => now()->subDays(1)->setTime(11, 30),
                'metodo' => 'tarjeta', 'recibido' => null,
                'lineas' => [
                    ['prod' => 'Shampoo hipoalergénico 250 ml',  'qty' => 1],
                    ['prod' => 'Snack dental perro (bolsa)',      'qty' => 2],
                ],
            ],
            [
                'prop' => 'sofia', 'pac' => 'nala',
                'fecha' => now()->subHours(3),
                'metodo' => 'yape', 'recibido' => null,
                'lineas' => [
                    ['prod' => 'Vacuna polivalente canina (octuple)', 'qty' => 1],
                    ['prod' => 'Jeringa desechable 3 ml',             'qty' => 1],
                ],
            ],
        ];

        $correlativo = 1;
        $anio = (int) now()->format('Y');

        foreach ($ventasData as $v) {
            $ventaId   = (string) Str::uuid();
            $propId    = $propietarios[$v['prop']] ?? null;
            $pacId     = $pacientes[$v['pac']] ?? null;
            $fecha     = $v['fecha'] instanceof Carbon ? $v['fecha'] : Carbon::parse($v['fecha']);

            if ($propId === null) {
                continue;
            }

            // Calcular totales de líneas.
            $subtotal = 0.0;

            $lineasInsert = [];

            foreach ($v['lineas'] as $linea) {
                $prod = $productos->get($linea['prod']);
                if ($prod === null) {
                    continue;
                }

                $precioUnit = (float) $prod->precio_venta;
                $qty        = (float) $linea['qty'];
                $lineaSub   = round($precioUnit * $qty, 2);
                $subtotal  += $lineaSub;

                $lineasInsert[] = [
                    'id'                   => (string) Str::uuid(),
                    'venta_id'             => $ventaId,
                    'producto_id'          => $prod->id,
                    'descripcion_snapshot' => (string) $prod->nombre,
                    'igv_tipo_snapshot'    => 'gravado',
                    'cantidad'             => $qty,
                    'precio_unitario'      => $precioUnit,
                    'descuento_pct'        => 0,
                    'subtotal'             => $lineaSub,
                ];
            }

            if (empty($lineasInsert)) {
                continue;
            }

            $igv     = round($subtotal * 0.18, 2);
            $total   = round($subtotal + $igv, 2);
            $recibido = $v['recibido'] !== null ? (float) $v['recibido'] : $total;
            $vuelto  = max(0.0, $recibido - $total);

            DB::table('ventas')->insert([
                'id'             => $ventaId,
                'numero'         => sprintf('B001-%04d', $correlativo),
                'anio'           => $anio,
                'correlativo'    => $correlativo,
                'propietario_id' => $propId,
                'paciente_id'    => $pacId,
                'caja_sesion_id' => $sesionId,
                'sede_id'        => $sedeId,
                'moneda'         => 'PEN',
                'estado'         => 'pagada',
                'subtotal'       => $subtotal,
                'igv_monto'      => $igv,
                'descuento_monto'=> 0,
                'total'          => $total,
                'metodo_pago'    => $v['metodo'],
                'monto_recibido' => $recibido,
                'vuelto'         => $vuelto,
                'fecha_pago'     => $fecha,
                'fel_estado'     => 'sin_cpe',
                'created_by_id'  => $adminId,
                'created_at'     => $fecha,
                'updated_at'     => $fecha,
            ]);

            DB::table('venta_lineas')->insert($lineasInsert);

            $correlativo++;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STOCK
    // ─────────────────────────────────────────────────────────────────────────

    private function seedStock(string $sedeId, string $adminId): void
    {
        $now = now();

        $productos = DB::table('productos')
            ->select('id', 'nombre', 'precio_venta')
            ->where('activo', true)
            ->get();

        // Cantidades iniciales por nombre de producto.
        $cantidadesIniciales = [
            'Amoxicilina 250 mg (comp.)'            => 120,
            'Meloxicam 2 mg/ml inyectable'          => 24,
            'Vacuna polivalente canina (octuple)'    => 18,
            'Vacuna antirrábica inactivada'          => 30,
            'Concentrado adulto razas medianas 15 kg'=> 8,
            'Snack dental perro (bolsa)'             => 15,
            'Shampoo hipoalergénico 250 ml'          => 12,
            'Collar nylon talla M'                   => 10,
            'Gasas estériles (paquete)'              => 40,
            'Jeringa desechable 3 ml'                => 200,
            'Guantes nitrilo talla M (caja 100)'     => 5,
        ];

        foreach ($productos as $prod) {
            $cantidad = (float) ($cantidadesIniciales[$prod->nombre] ?? 10);

            // Existencia en sede.
            DB::table('existencias_sede')->insertOrIgnore([
                'id'         => (string) Str::uuid(),
                'producto_id'=> $prod->id,
                'sede_id'    => $sedeId,
                'cantidad'   => $cantidad,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Movimiento de entrada inicial.
            DB::table('movimientos_inventario')->insert([
                'id'             => (string) Str::uuid(),
                'producto_id'    => $prod->id,
                'sede_id'        => $sedeId,
                'tipo'           => 'entrada_manual',
                'delta'          => $cantidad,
                'stock_anterior' => 0,
                'stock_despues'  => $cantidad,
                'notas'          => 'Stock inicial demo',
                'created_by_id'  => $adminId,
                'created_at'     => now()->subDays(30),
            ]);
        }
    }
}
