export type LegalDocId =
    | 'terminos'
    | 'privacidad'
    | 'datos'
    | 'cookies'
    | 'seguridad'
    | 'soporte';

export type LegalBlock =
    | { type: 'p'; text: string }
    | { type: 'ul'; items: string[] };

export type LegalDoc = {
    id: LegalDocId;
    label: string;
    title: string;
    updated: string;
    blocks: LegalBlock[];
};

export const LEGAL_DOC_ORDER: LegalDocId[] = [
    'terminos',
    'privacidad',
    'datos',
    'cookies',
    'seguridad',
    'soporte',
];

export const LEGAL_DOCS: Record<LegalDocId, LegalDoc> = {
    terminos: {
        id: 'terminos',
        label: 'Términos',
        title: 'Términos de uso',
        updated: '12 de septiembre de 2026',
        blocks: [
            {
                type: 'p',
                text: 'Estos términos regulan el uso de VetSaaS, software de gestión clínica veterinaria operado desde el Perú. Al crear una cuenta o iniciar sesión, la clínica (el “Cliente”) acepta estas condiciones.',
            },
            {
                type: 'p',
                text: 'VetSaaS es una herramienta tecnológica. No sustituye el criterio profesional veterinario ni constituye consejo médico, legal o tributario. El Cliente es responsable de la veracidad de la información que registra y del uso que haga del sistema.',
            },
            {
                type: 'p',
                text: 'La cuenta es de uso interno de la clínica y de su personal autorizado. Queda prohibido compartir credenciales, intentar acceder a datos de otras clínicas o usar el servicio para fines ilícitos.',
            },
            {
                type: 'p',
                text: 'Los datos clínicos, de propietarios y de operaciones que se cargan en el tenant pertenecen a la clínica. VetSaaS no los comercializa ni los usa como producto de reventa. El acceso de soporte, cuando exista, se limita a lo necesario para resolver un incidente y queda registrado.',
            },
            {
                type: 'p',
                text: 'Podemos suspender el acceso ante incumplimiento, riesgo de seguridad o falta de pago, con el aviso razonable que permita el caso. El Cliente puede solicitar la baja y la exportación de su información según el plan contratado.',
            },
        ],
    },
    privacidad: {
        id: 'privacidad',
        label: 'Privacidad',
        title: 'Política de privacidad',
        updated: '12 de septiembre de 2026',
        blocks: [
            {
                type: 'p',
                text: 'Esta política describe cómo se tratan los datos personales en VetSaaS, en línea con la Ley N.° 29733, Ley de Protección de Datos Personales, su Reglamento vigente y las directrices de la Autoridad Nacional de Protección de Datos Personales (ANPDP) del Ministerio de Justicia y Derechos Humanos del Perú.',
            },
            {
                type: 'p',
                text: 'Hay dos ámbitos distintos. (1) Datos de la cuenta de la clínica y de sus usuarios (correo, nombre, rol): el responsable del tratamiento es el operador de VetSaaS. (2) Datos de propietarios, pacientes y atención clínica: el titular del banco de datos y responsable es la clínica. VetSaaS actúa como encargado del tratamiento, solo para prestar el servicio y siguiendo las instrucciones del Cliente.',
            },
            {
                type: 'p',
                text: 'Los datos de historias clínicas, identificadores de mascotas, datos de contacto de tutores, facturación interna y comunicaciones (p. ej. recordatorios) se almacenan de forma segregada por clínica (tenant). No se mezclan con otras clínicas ni se publican. Son de uso interno de ese establecimiento, salvo obligación legal o requerimiento de autoridad competente.',
            },
            {
                type: 'p',
                text: 'No vendemos bases de datos. No usamos la historia clínica para publicidad de terceros. El tratamiento se limita a operar el software, seguridad, soporte técnico, facturación del servicio SaaS y cumplimiento normativo.',
            },
            {
                type: 'p',
                text: 'Si un propietario o usuario ejerce sus derechos, puede dirigirse primero a la clínica (respecto de su ficha) o a soporte@vetsaas.pe (respecto de la cuenta de acceso a la plataforma). Atenderemos o reenviaremos la solicitud según corresponda al rol de responsable o encargado.',
            },
        ],
    },
    datos: {
        id: 'datos',
        label: 'Datos personales',
        title: 'Protección de datos personales (Perú)',
        updated: '12 de septiembre de 2026',
        blocks: [
            {
                type: 'p',
                text: 'En el Perú, el tratamiento de datos personales se rige principalmente por la Ley N.° 29733 y su reglamento. Los datos de salud y los vinculados a la atención veterinaria de un tutor se tratan con reserva y finalidad determinada: la gestión de la clínica que usted autorizó.',
            },
            {
                type: 'p',
                text: 'Principios que aplicamos: legalidad, consentimiento cuando corresponda, finalidad, proporcionalidad, calidad, seguridad y disposición de recurso. No se recaban datos ajenos al servicio. El consentimiento para el banco de datos de la clínica lo recaba el establecimiento en su relación con el propietario; VetSaaS no sustituye ese deber.',
            },
            {
                type: 'p',
                text: 'Derechos ARCO y complementarios (acceso, rectificación, cancelación, oposición, y los que reconozca la normativa vigente, como revocación e información): el titular puede ejercerlos ante el responsable. La clínica debe habilitar un canal; VetSaaS colaborará técnicamente (corrección, bloqueo o supresión en el sistema, cuando proceda y no exista deber de conservación).',
            },
            {
                type: 'ul',
                items: [
                    'Finalidad interna: agenda, historia clínica, inventario, caja y comunicaciones operativas de esa clínica.',
                    'No hay cesión a terceros con fines comerciales.',
                    'Encargados técnicos (hosting, correo, mensajería) solo procesan lo necesario, con medidas de confidencialidad.',
                    'Conservación: mientras la clínica mantenga la cuenta y los plazos legales o profesionales de archivo clínico que ella deba cumplir.',
                    'Transferencia: los servidores se eligen con criterios de seguridad; si hubiera flujo transfronterizo, se aplicarán las salvaguardas que exija la Ley 29733.',
                ],
            },
            {
                type: 'p',
                text: 'La ANPDP es la autoridad administrativa en materia de protección de datos personales en el Perú. El titular puede interponer reclamo ante dicha autoridad si considera vulnerados sus derechos, sin perjuicio de la vía judicial.',
            },
        ],
    },
    cookies: {
        id: 'cookies',
        label: 'Cookies',
        title: 'Cookies y sesión',
        updated: '12 de septiembre de 2026',
        blocks: [
            {
                type: 'p',
                text: 'Usamos cookies y almacenamiento local estrictamente necesarios para iniciar sesión, mantener la sesión, recordar preferencias (idioma, tema) y proteger el servicio (por ejemplo, tokens CSRF y detección de abuso).',
            },
            {
                type: 'p',
                text: 'No instalamos cookies de publicidad de terceros ni perfiles de marketing sobre historias clínicas. Si en el futuro se añadiera una cookie no esencial, se solicitará el consentimiento cuando la norma lo exija.',
            },
            {
                type: 'p',
                text: 'Puede bloquear cookies en el navegador; algunas funciones de login o sesión dejarían de operar. El “mantener sesión iniciada” solo prolonga su acceso a la clínica, no comparte datos con redes publicitarias.',
            },
        ],
    },
    seguridad: {
        id: 'seguridad',
        label: 'Seguridad',
        title: 'Seguridad y confidencialidad',
        updated: '12 de septiembre de 2026',
        blocks: [
            {
                type: 'p',
                text: 'Los datos se tratan como información interna y confidencial de cada clínica. Aplicamos medidas organizativas y técnicas razonables: separación por tenant, cifrado en tránsito (HTTPS), control de accesos por rol, registro de operaciones sensibles y copias de respaldo.',
            },
            {
                type: 'p',
                text: 'Ningún sistema es invulnerable. El Cliente debe usar contraseñas robustas, no compartir usuarios y cerrar sesión en equipos compartidos. El personal de la clínica solo debe acceder a lo que su rol permita.',
            },
            {
                type: 'p',
                text: 'Los incidentes de seguridad que afecten datos personales se gestionarán conforme a la normativa peruana aplicable, incluyendo la comunicación a la autoridad y a los titulares cuando corresponda.',
            },
            {
                type: 'p',
                text: 'El soporte de VetSaaS no usa los datos clínicos para entrenar modelos de IA de terceros ni para fines ajenos a la incidencia. Cualquier asistencia humana o automatizada sobre el tenant queda sujeta a este deber de reserva.',
            },
        ],
    },
    soporte: {
        id: 'soporte',
        label: 'Soporte',
        title: 'Soporte',
        updated: '12 de septiembre de 2026',
        blocks: [
            {
                type: 'p',
                text: 'Para ayuda de la plataforma, escriba a soporte@vetsaas.pe indicando el nombre de la clínica y, si es posible, el usuario afectado. No envíe por correo innecesario números de historia clínica, recetas completas ni datos de salud que no hagan falta para el ticket.',
            },
            {
                type: 'p',
                text: 'El canal de soporte existe para operar el software (accesos, planes, fallos técnicos). No es un consultorio veterinario ni un buzón para datos de pacientes de otras clínicas.',
            },
            {
                type: 'p',
                text: 'Las solicitudes ARCO u otras sobre datos personales pueden enviarse al mismo correo, con asunto “Protección de datos”, para derivarlas al responsable correcto (clínica u operador de la plataforma).',
            },
        ],
    },
};
