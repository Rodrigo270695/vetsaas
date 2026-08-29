<?php

declare(strict_types=1);

/**
 * Configuración del scraper de prospectos veterinarios (clínicas/hospitales)
 * usado para prospección comercial de VetSaaS.
 *
 * `ubicaciones`: catálogo curado de páginas del directorio público
 * veterinariasperu.net a visitar. Cada entrada especifica el `slug` de la
 * URL (relativo a `base_url`) y la metadata geográfica correcta (el sitio
 * no siempre acierta el distrito/provincia en el texto de la dirección).
 *
 * El servicio recorre este catálogo en orden round-robin, avanzando cada
 * corrida a partir de las ubicaciones menos visitadas recientemente
 * (ver `VeterinariaProspectoScraperService::pickLocations()`), así que se
 * puede ampliar esta lista libremente sin romper nada.
 */
return [
    'base_url' => 'https://veterinariasperu.net',

    'max_por_corrida' => 40,

    'max_ubicaciones_por_corrida' => 6,

    'timeout_seg' => 15,

    'ubicaciones' => [
        // ── Lima Metropolitana (distritos) ──
        ['slug' => 'lima/barranco', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'Barranco'],
        ['slug' => 'lima/miraflores', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'Miraflores'],
        ['slug' => 'lima/san-isidro', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'San Isidro'],
        ['slug' => 'lima/santiago-de-surco', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'Santiago de Surco'],
        ['slug' => 'lima/san-borja', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'San Borja'],
        ['slug' => 'lima/la-molina', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'La Molina'],
        ['slug' => 'lima/jesus-maria', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'Jesús María'],
        ['slug' => 'lima/pueblo-libre', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'Pueblo Libre'],
        ['slug' => 'lima/magdalena-del-mar', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'Magdalena del Mar'],
        ['slug' => 'lima/san-miguel', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'San Miguel'],
        ['slug' => 'lima/los-olivos', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'Los Olivos'],
        ['slug' => 'lima/san-martin-de-porres', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'San Martín de Porres'],
        ['slug' => 'lima/comas', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'Comas'],
        ['slug' => 'lima/independencia', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'Independencia'],
        ['slug' => 'lima/santa-anita', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'Santa Anita'],
        ['slug' => 'lima/san-juan-de-lurigancho', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'San Juan de Lurigancho'],
        ['slug' => 'lima/san-juan-de-miraflores', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'San Juan de Miraflores'],
        ['slug' => 'lima/villa-el-salvador', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'Villa El Salvador'],
        ['slug' => 'lima/villa-maria-del-triunfo', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'Villa María del Triunfo'],
        ['slug' => 'lima/el-agustino', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'El Agustino'],
        ['slug' => 'lima/la-victoria', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'La Victoria'],
        ['slug' => 'lima/san-luis', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'San Luis'],
        ['slug' => 'lima/puente-piedra', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'Puente Piedra'],
        ['slug' => 'lima/surquillo', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'Surquillo'],
        ['slug' => 'lima/ate', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'Ate'],
        ['slug' => 'lima/chorrillos', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'Chorrillos'],
        ['slug' => 'lima/rimac', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'Rímac'],
        ['slug' => 'lima/lince', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'Lince'],
        ['slug' => 'lima/breña', 'departamento' => 'Lima', 'provincia' => 'Lima', 'distrito' => 'Breña'],
        ['slug' => 'callao', 'departamento' => 'Callao', 'provincia' => 'Callao', 'distrito' => null],

        // ── Otros departamentos (página principal de la ciudad/capital) ──
        ['slug' => 'arequipa', 'departamento' => 'Arequipa', 'provincia' => 'Arequipa', 'distrito' => null],
        ['slug' => 'trujillo', 'departamento' => 'La Libertad', 'provincia' => 'Trujillo', 'distrito' => null],
        ['slug' => 'chiclayo', 'departamento' => 'Lambayeque', 'provincia' => 'Chiclayo', 'distrito' => null],
        ['slug' => 'cusco', 'departamento' => 'Cusco', 'provincia' => 'Cusco', 'distrito' => null],
        ['slug' => 'piura', 'departamento' => 'Piura', 'provincia' => 'Piura', 'distrito' => null],
        ['slug' => 'iquitos', 'departamento' => 'Loreto', 'provincia' => 'Maynas', 'distrito' => null],
        ['slug' => 'huancayo', 'departamento' => 'Junín', 'provincia' => 'Huancayo', 'distrito' => null],
        ['slug' => 'tacna', 'departamento' => 'Tacna', 'provincia' => 'Tacna', 'distrito' => null],
        ['slug' => 'ica', 'departamento' => 'Ica', 'provincia' => 'Ica', 'distrito' => null],
        ['slug' => 'puno', 'departamento' => 'Puno', 'provincia' => 'Puno', 'distrito' => null],
        ['slug' => 'chimbote', 'departamento' => 'Áncash', 'provincia' => 'Santa', 'distrito' => null],
        ['slug' => 'cajamarca', 'departamento' => 'Cajamarca', 'provincia' => 'Cajamarca', 'distrito' => null],
        ['slug' => 'ayacucho', 'departamento' => 'Ayacucho', 'provincia' => 'Huamanga', 'distrito' => null],
        ['slug' => 'pucallpa', 'departamento' => 'Ucayali', 'provincia' => 'Coronel Portillo', 'distrito' => null],
        ['slug' => 'tarapoto', 'departamento' => 'San Martín', 'provincia' => 'San Martín', 'distrito' => null],
        ['slug' => 'huaraz', 'departamento' => 'Áncash', 'provincia' => 'Huaraz', 'distrito' => null],
        ['slug' => 'tumbes', 'departamento' => 'Tumbes', 'provincia' => 'Tumbes', 'distrito' => null],
        ['slug' => 'moquegua', 'departamento' => 'Moquegua', 'provincia' => 'Mariscal Nieto', 'distrito' => null],
        ['slug' => 'huanuco', 'departamento' => 'Huánuco', 'provincia' => 'Huánuco', 'distrito' => null],
        ['slug' => 'juliaca', 'departamento' => 'Puno', 'provincia' => 'San Román', 'distrito' => null],
        ['slug' => 'chincha', 'departamento' => 'Ica', 'provincia' => 'Chincha', 'distrito' => null],
        ['slug' => 'sullana', 'departamento' => 'Piura', 'provincia' => 'Sullana', 'distrito' => null],
        ['slug' => 'cañete', 'departamento' => 'Lima', 'provincia' => 'Cañete', 'distrito' => null],
        ['slug' => 'huacho', 'departamento' => 'Lima', 'provincia' => 'Huaura', 'distrito' => null],
        ['slug' => 'abancay', 'departamento' => 'Apurímac', 'provincia' => 'Abancay', 'distrito' => null],
        ['slug' => 'chachapoyas', 'departamento' => 'Amazonas', 'provincia' => 'Chachapoyas', 'distrito' => null],
        ['slug' => 'moyobamba', 'departamento' => 'San Martín', 'provincia' => 'Moyobamba', 'distrito' => null],
        ['slug' => 'pisco', 'departamento' => 'Ica', 'provincia' => 'Pisco', 'distrito' => null],
        ['slug' => 'tarma', 'departamento' => 'Junín', 'provincia' => 'Tarma', 'distrito' => null],
        ['slug' => 'jaen', 'departamento' => 'Cajamarca', 'provincia' => 'Jaén', 'distrito' => null],
    ],
];
