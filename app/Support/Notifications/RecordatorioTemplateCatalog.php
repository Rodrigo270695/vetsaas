<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Models\RecordatorioTemplate;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de plantillas WhatsApp editables (citas, vacunas, cumpleaños, grooming, hotel).
 * Ventas / FEL / laboratorio quedan fuera a propósito.
 */
final class RecordatorioTemplateCatalog
{
    public const GRUPO_CITAS = 'citas';

    public const GRUPO_VACUNAS = 'vacunas';

    public const GRUPO_CUMPLE = 'cumple';

    public const GRUPO_GROOMING = 'grooming';

    public const GRUPO_HOTEL = 'hotel';

    /**
     * @return list<array{
     *     tipo: string,
     *     grupo: string,
     *     orden: int,
     *     variables: list<string>,
     *     cuerpo_default: string
     * }>
     */
    public static function definitions(): array
    {
        return [
            [
                'tipo' => 'cita_dias_antes',
                'grupo' => self::GRUPO_CITAS,
                'orden' => 10,
                'variables' => ['propietario', 'mascota', 'clinica', 'motivo_linea', 'fecha', 'hora'],
                'cuerpo_default' => "Hola {{propietario}} 👋\n\n⏰ Te recordamos la cita de *{{mascota}}* en *{{clinica}}*\n{{motivo_linea}}📅 *{{fecha}}* a las *{{hora}}*\n\nSi necesitas reprogramar, contáctanos.\n\nTe esperamos 🐾\n\n— {{clinica}}",
            ],
            [
                'tipo' => 'cita_2h',
                'grupo' => self::GRUPO_CITAS,
                'orden' => 20,
                'variables' => ['propietario', 'mascota', 'clinica', 'motivo_linea', 'hora'],
                'cuerpo_default' => "Hola {{propietario}} 👋\n\n⏳ En *2 horas* tienes cita de *{{mascota}}* en *{{clinica}}*\n{{motivo_linea}}🕒 *{{hora}}*\n\n¡Nos vemos pronto! 🐾\n\n— {{clinica}}",
            ],
            [
                'tipo' => 'cita_creada',
                'grupo' => self::GRUPO_CITAS,
                'orden' => 30,
                'variables' => ['propietario', 'mascota', 'clinica', 'motivo_linea', 'fecha', 'hora'],
                'cuerpo_default' => "Hola {{propietario}} 👋\n\n✅ Registramos la cita de *{{mascota}}* en *{{clinica}}*\n{{motivo_linea}}📅 *{{fecha}}* a las *{{hora}}*\n\nTe esperamos 🐾\n\n— {{clinica}}",
            ],
            [
                'tipo' => 'cita_reprogramada',
                'grupo' => self::GRUPO_CITAS,
                'orden' => 40,
                'variables' => ['propietario', 'mascota', 'clinica', 'motivo_linea', 'fecha', 'hora'],
                'cuerpo_default' => "Hola {{propietario}} 👋\n\n🔄 Reprogramamos la cita de *{{mascota}}* en *{{clinica}}*\n{{motivo_linea}}📅 Nueva fecha: *{{fecha}}* a las *{{hora}}*\n\nTe esperamos 🐾\n\n— {{clinica}}",
            ],
            [
                'tipo' => 'cita_actualizada',
                'grupo' => self::GRUPO_CITAS,
                'orden' => 50,
                'variables' => ['propietario', 'mascota', 'clinica', 'motivo_linea', 'fecha', 'hora'],
                'cuerpo_default' => "Hola {{propietario}} 👋\n\n✏️ Actualizamos la cita de *{{mascota}}* en *{{clinica}}*\n{{motivo_linea}}📅 *{{fecha}}* a las *{{hora}}*\n\nTe esperamos 🐾\n\n— {{clinica}}",
            ],
            [
                'tipo' => 'vacuna_proxima',
                'grupo' => self::GRUPO_VACUNAS,
                'orden' => 10,
                'variables' => ['propietario', 'mascota', 'clinica', 'vacuna', 'fecha'],
                'cuerpo_default' => "Hola {{propietario}} 👋\n\n💉 El refuerzo de *{{vacuna}}* para *{{mascota}}* vence el *{{fecha}}*\n📋 Agenda con *{{clinica}}* para mantenerlo al día.\n\nCuidamos de *{{mascota}}* 🐾\n\n— {{clinica}}",
            ],
            [
                'tipo' => 'cumple_mascota',
                'grupo' => self::GRUPO_CUMPLE,
                'orden' => 10,
                'variables' => ['propietario', 'mascota', 'clinica'],
                'cuerpo_default' => "Hola {{propietario}} 👋\n\n🎂 ¡Hoy es el cumpleaños de *{{mascota}}*! 🎉🥳\n\nDesde *{{clinica}}* le enviamos un cariñoso saludo 🐾💚\n\n— {{clinica}}",
            ],
            [
                'tipo' => 'grooming_programado',
                'grupo' => self::GRUPO_GROOMING,
                'orden' => 10,
                'variables' => ['propietario', 'mascota', 'clinica', 'servicio', 'fecha', 'hora', 'adelanto_linea'],
                'cuerpo_default' => "Hola {{propietario}} 👋\n\n✅ Agendamos el grooming de *{{mascota}}*\n🧴 Servicio: *{{servicio}}*\n📅 *{{fecha}}* a las *{{hora}}*{{adelanto_linea}}\n\nTe esperamos 🐾\n\n— {{clinica}}",
            ],
            [
                'tipo' => 'grooming_reprogramado',
                'grupo' => self::GRUPO_GROOMING,
                'orden' => 20,
                'variables' => ['propietario', 'mascota', 'clinica', 'servicio', 'fecha', 'hora'],
                'cuerpo_default' => "Hola {{propietario}} 👋\n\n🔄 Reprogramamos el grooming de *{{mascota}}*\n🧴 Servicio: *{{servicio}}*\n📅 Nueva fecha: *{{fecha}}* a las *{{hora}}*\n\nTe esperamos 🐾\n\n— {{clinica}}",
            ],
            [
                'tipo' => 'grooming_en_proceso',
                'grupo' => self::GRUPO_GROOMING,
                'orden' => 30,
                'variables' => ['propietario', 'mascota', 'clinica', 'servicio'],
                'cuerpo_default' => "Hola {{propietario}} 👋\n\n✂️ *{{mascota}}* ya está en grooming\n🧴 Servicio: *{{servicio}}*\n\nTe avisaremos cuando termine 🐾\n\n— {{clinica}}",
            ],
            [
                'tipo' => 'grooming_completada',
                'grupo' => self::GRUPO_GROOMING,
                'orden' => 40,
                'variables' => ['propietario', 'mascota', 'clinica', 'servicio'],
                'cuerpo_default' => "Hola {{propietario}} 👋\n\n✨ ¡*{{mascota}}* ya terminó su grooming!\n🧴 Servicio: *{{servicio}}*\n\nYa puede pasar a recogerlo 🐾\n\n— {{clinica}}",
            ],
            [
                'tipo' => 'grooming_cancelada',
                'grupo' => self::GRUPO_GROOMING,
                'orden' => 50,
                'variables' => ['propietario', 'mascota', 'clinica', 'servicio'],
                'cuerpo_default' => "Hola {{propietario}} 👋\n\nEl turno de grooming de *{{mascota}}* fue *cancelado*.\n🧴 Servicio: *{{servicio}}*\n\nSi deseas reagendar, escríbenos o llama a la clínica.\n\n— {{clinica}}",
            ],
            [
                'tipo' => 'grooming_no_asistio',
                'grupo' => self::GRUPO_GROOMING,
                'orden' => 60,
                'variables' => ['propietario', 'mascota', 'clinica', 'servicio'],
                'cuerpo_default' => "Hola {{propietario}} 👋\n\nRegistramos que *{{mascota}}* *no asistió* a su turno de grooming.\n🧴 Servicio: *{{servicio}}*\n\nSi fue un imprevisto, podemos ayudarte a reagendar.\n\n— {{clinica}}",
            ],
            [
                'tipo' => 'grooming_foto_proceso',
                'grupo' => self::GRUPO_GROOMING,
                'orden' => 70,
                'variables' => ['propietario', 'mascota', 'clinica', 'servicio'],
                'cuerpo_default' => "Hola {{propietario}} 👋\n\n✂️ *{{mascota}}* está en grooming\n🧴 Servicio: *{{servicio}}*\n\nTe compartimos una foto del proceso 📸\n\n— {{clinica}}",
            ],
            [
                'tipo' => 'grooming_foto_final',
                'grupo' => self::GRUPO_GROOMING,
                'orden' => 80,
                'variables' => ['propietario', 'mascota', 'clinica', 'servicio'],
                'cuerpo_default' => "Hola {{propietario}} 👋\n\n✨ ¡*{{mascota}}* ya terminó su grooming!\n🧴 Servicio: *{{servicio}}*\n\nTe compartimos la foto final 📸\n\n— {{clinica}}",
            ],
            [
                'tipo' => 'hotel_registrada',
                'grupo' => self::GRUPO_HOTEL,
                'orden' => 10,
                'variables' => ['propietario', 'mascota', 'clinica', 'fecha_ingreso', 'hora_ingreso', 'egreso_linea'],
                'cuerpo_default' => "Hola {{propietario}} 👋\n\n✅ Registramos la estancia de *{{mascota}}*\n📅 Ingreso: *{{fecha_ingreso}}* a las *{{hora_ingreso}}*{{egreso_linea}}\n\nTe esperamos 🐾\n\n— {{clinica}}",
            ],
            [
                'tipo' => 'hotel_confirmada',
                'grupo' => self::GRUPO_HOTEL,
                'orden' => 20,
                'variables' => ['propietario', 'mascota', 'clinica', 'fecha_ingreso', 'hora_ingreso', 'egreso_linea'],
                'cuerpo_default' => "Hola {{propietario}} 👋\n\n✅ Confirmamos la estancia de *{{mascota}}*\n📅 Ingreso: *{{fecha_ingreso}}* a las *{{hora_ingreso}}*{{egreso_linea}}\n\nTe esperamos 🐾\n\n— {{clinica}}",
            ],
            [
                'tipo' => 'hotel_reprogramada',
                'grupo' => self::GRUPO_HOTEL,
                'orden' => 30,
                'variables' => ['propietario', 'mascota', 'clinica', 'fecha_ingreso', 'hora_ingreso', 'egreso_linea'],
                'cuerpo_default' => "Hola {{propietario}} 👋\n\n🔄 Reprogramamos la estancia de *{{mascota}}*\n📅 Ingreso: *{{fecha_ingreso}}* a las *{{hora_ingreso}}*{{egreso_linea}}\n\nTe esperamos 🐾\n\n— {{clinica}}",
            ],
            [
                'tipo' => 'hotel_en_estancia',
                'grupo' => self::GRUPO_HOTEL,
                'orden' => 40,
                'variables' => ['propietario', 'mascota', 'clinica', 'fecha_ingreso', 'hora_ingreso', 'egreso_linea'],
                'cuerpo_default' => "Hola {{propietario}} 👋\n\n🏨 *{{mascota}}* ya ingresó al hotel\n📅 Ingreso: *{{fecha_ingreso}}* a las *{{hora_ingreso}}*{{egreso_linea}}\n\nTe mantendremos informado durante su estadía 🐾\n\n— {{clinica}}",
            ],
            [
                'tipo' => 'hotel_completada',
                'grupo' => self::GRUPO_HOTEL,
                'orden' => 50,
                'variables' => ['propietario', 'mascota', 'clinica', 'fecha_ingreso', 'hora_ingreso', 'egreso_linea'],
                'cuerpo_default' => "Hola {{propietario}} 👋\n\n🏡 La estancia de *{{mascota}}* fue completada\n📅 Ingreso: *{{fecha_ingreso}}* a las *{{hora_ingreso}}*{{egreso_linea}}\n\nGracias por confiar en nosotros 🐾\n\n— {{clinica}}",
            ],
            [
                'tipo' => 'hotel_cancelada',
                'grupo' => self::GRUPO_HOTEL,
                'orden' => 60,
                'variables' => ['propietario', 'mascota', 'clinica', 'fecha_ingreso', 'hora_ingreso', 'egreso_linea'],
                'cuerpo_default' => "Hola {{propietario}} 👋\n\nLa estancia de *{{mascota}}* fue *cancelada*.\n📅 Ingreso: *{{fecha_ingreso}}* a las *{{hora_ingreso}}*{{egreso_linea}}\n\nSi deseas reprogramar, comunícate con la clínica.\n\n— {{clinica}}",
            ],
            [
                'tipo' => 'hotel_no_presento',
                'grupo' => self::GRUPO_HOTEL,
                'orden' => 70,
                'variables' => ['propietario', 'mascota', 'clinica', 'fecha_ingreso', 'hora_ingreso', 'egreso_linea'],
                'cuerpo_default' => "Hola {{propietario}} 👋\n\nRegistramos que *{{mascota}}* *no se presentó* a su estancia.\n📅 Ingreso: *{{fecha_ingreso}}* a las *{{hora_ingreso}}*{{egreso_linea}}\n\nSi deseas reprogramar, comunícate con la clínica.\n\n— {{clinica}}",
            ],
            [
                'tipo' => 'hotel_bitacora',
                'grupo' => self::GRUPO_HOTEL,
                'orden' => 80,
                'variables' => ['propietario', 'mascota', 'clinica', 'fecha', 'notas'],
                'cuerpo_default' => "Hola {{propietario}} 👋\n\n📋 Nueva actualización de la estancia de *{{mascota}}*\n📅 *{{fecha}}*\n📝 {{notas}}\n\nSeguimos cuidándolo 🐾\n\n— {{clinica}}",
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function tipos(): array
    {
        return array_column(self::definitions(), 'tipo');
    }

    /**
     * @return array{tipo: string, grupo: string, orden: int, variables: list<string>, cuerpo_default: string}|null
     */
    public static function definition(string $tipo): ?array
    {
        foreach (self::definitions() as $definition) {
            if ($definition['tipo'] === $tipo) {
                return $definition;
            }
        }

        return null;
    }

    public static function defaultBody(string $tipo): ?string
    {
        return self::definition($tipo)['cuerpo_default'] ?? null;
    }

    /**
     * Inserta plantillas faltantes (tenants existentes / nuevos tipos).
     */
    public static function ensureSeeded(): void
    {
        if (! Schema::hasTable('cfg_recordatorio_templates')) {
            return;
        }

        $existing = RecordatorioTemplate::query()->pluck('tipo')->all();
        $existingLookup = array_fill_keys($existing, true);

        foreach (self::definitions() as $definition) {
            if (isset($existingLookup[$definition['tipo']])) {
                continue;
            }

            RecordatorioTemplate::query()->create([
                'tipo' => $definition['tipo'],
                'grupo' => $definition['grupo'],
                'canal' => 'whatsapp',
                'cuerpo' => $definition['cuerpo_default'],
                'activo' => true,
                'orden' => $definition['orden'],
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public static function sampleVariables(): array
    {
        return [
            'propietario' => 'María Pérez',
            'mascota' => 'Firulais',
            'clinica' => 'Clínica Demo',
            'motivo_linea' => "📋 Motivo: *Control*\n",
            'fecha' => '24/08/2026',
            'hora' => '10:30',
            'vacuna' => 'Antirrábica',
            'servicio' => 'Baño completo',
            'adelanto_linea' => "\n💵 Adelanto recibido: *PEN 30.00*",
            'fecha_ingreso' => '24/08/2026',
            'hora_ingreso' => '09:00',
            'egreso_linea' => "\n📅 Egreso previsto: *26/08/2026*",
            'notas' => 'Comió bien y descansó.',
        ];
    }

    public static function preview(string $cuerpo): string
    {
        $sample = self::sampleVariables();
        $replacements = [];
        foreach ($sample as $key => $value) {
            $replacements['{{'.$key.'}}'] = $value;
        }

        return strtr($cuerpo, $replacements);
    }
}
