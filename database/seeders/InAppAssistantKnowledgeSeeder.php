<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\InAppAssistantKnowledge;
use Illuminate\Database\Seeder;

final class InAppAssistantKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->entries() as $entry) {
            InAppAssistantKnowledge::query()->updateOrCreate(
                ['slug' => $entry['slug']],
                $entry,
            );
        }

        // Módulos todavía no operativos no deben aparecer como sugerencias.
        InAppAssistantKnowledge::query()
            ->where('slug', 'module-bloqueos')
            ->update(['is_active' => false]);

        InAppAssistantKnowledge::flushCache();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entries(): array
    {
        $entries = [];
        $order = 10;

        foreach ($this->modules() as $slug => $module) {
            $permission = $module['permission'];
            $url = $module['url'];
            $actions = [[
                'type' => 'navigate',
                'url' => $url,
                'label' => 'Ir a '.$module['title'],
                'required_permissions' => $permission !== null ? [$permission] : [],
                'allowed_roles' => $module['roles'] ?? [],
            ]];
            if (isset($module['tour_id']) && $permission !== null) {
                $actions[] = $this->startTour(
                    $module['tour_id'],
                    'Ver tour de '.$module['title'],
                    [$permission],
                    $module['roles'] ?? [],
                );
            }

            $entries[] = $this->entry([
                'slug' => 'module-'.$slug,
                'section' => InAppAssistantKnowledge::SECTION_MODULE,
                'title' => $module['title'],
                'content' => $module['content'],
                'keywords' => array_values(array_unique(array_merge(
                    [$module['title'], $slug],
                    $module['keywords'],
                ))),
                'url_patterns' => [$url, rtrim($url, '/').'/*'],
                'component_patterns' => [$module['component']],
                'required_permissions' => $permission !== null ? [$permission] : [],
                'allowed_roles' => $module['roles'] ?? [],
                'actions' => $actions,
                'priority' => $module['priority'] ?? 20,
                'sort_order' => $order,
            ]);
            $order += 10;
        }

        return array_merge($entries, $this->roleEntries(), $this->workflowEntries(), $this->faqEntries());
    }

    /**
     * @return array<string, array{
     *     title: string,
     *     permission: string|null,
     *     url: string,
     *     component: string,
     *     keywords: list<string>,
     *     content: string,
     *     tour_id?: string,
     *     roles?: list<string>,
     *     priority?: int
     * }>
     */
    private function modules(): array
    {
        return [
            'dashboard' => [
                'title' => 'Dashboard',
                'permission' => 'dashboard.view',
                'url' => '/dashboard',
                'component' => 'dashboard*',
                'keywords' => ['inicio', 'indicadores', 'resumen', 'rentabilidad', 'alertas'],
                'content' => 'Panel inicial con indicadores, citas próximas, alertas y accesos rápidos. Los datos visibles dependen de la sede, plan y permisos del usuario.',
                'priority' => 25,
            ],
            'pacientes' => [
                'title' => 'Clínica · Pacientes',
                'permission' => 'pacientes.view',
                'url' => '/clinica/pacientes',
                'component' => 'clinica/pacientes/*',
                'keywords' => ['mascotas', 'petpass', 'microchip', 'carnet', 'historial'],
                'content' => 'Administra mascotas, datos clínicos básicos, PetPass, documentos y acceso al historial. Para registrar una mascota debe existir o crearse su propietario.',
                'tour_id' => 'pacientes',
            ],
            'propietarios' => [
                'title' => 'Clínica · Propietarios',
                'permission' => 'propietarios.view',
                'url' => '/clinica/propietarios',
                'component' => 'clinica/propietarios/*',
                'keywords' => ['dueños', 'clientes', 'titulares', 'dni', 'ruc', 'contacto'],
                'content' => 'Gestiona titulares y sus datos de contacto, documento y mascotas asociadas. Desde el detalle se pueden registrar pacientes del propietario.',
            ],
            'citas' => [
                'title' => 'Clínica · Citas',
                'permission' => 'citas.view',
                'url' => '/clinica/citas',
                'component' => 'clinica/citas/*',
                'keywords' => ['agenda', 'calendario', 'reservas', 'reprogramar', 'cancelar', 'aperturar'],
                'content' => 'Agenda, reprograma y cancela citas por paciente, veterinario y sede. La acción Aperturar inicia la atención y vincula la cita con una consulta de historia clínica.',
                'tour_id' => 'citas',
                'priority' => 30,
            ],
            'historias-clinicas' => [
                'title' => 'Clínica · Historias clínicas',
                'permission' => 'historias-clinicas.view',
                'url' => '/clinica/historias-clinicas',
                'component' => 'clinica/historias-clinicas/*',
                'keywords' => ['hc', 'consulta', 'anamnesis', 'diagnóstico', 'tratamiento', 'evolución'],
                'content' => 'Expediente clínico por paciente. Permite registrar consultas, examen, diagnóstico, plan de tratamiento, seguimiento, documentos y cierre de atención según permisos.',
                'tour_id' => 'historias-clinicas',
                'priority' => 30,
            ],
            'vacunaciones' => [
                'title' => 'Clínica · Vacunaciones',
                'permission' => 'vacunaciones.view',
                'url' => '/clinica/vacunaciones',
                'component' => 'clinica/vacunaciones/*',
                'keywords' => ['vacunas', 'desparasitación', 'aplicaciones', 'refuerzo', 'carnet'],
                'content' => 'Registra vacunas y otras aplicaciones, lote/producto, fecha y próximo refuerzo. Genera constancia y alimenta las alertas de próximas vacunaciones.',
            ],
            'recetas' => [
                'title' => 'Clínica · Recetas',
                'permission' => 'recetas.view',
                'url' => '/clinica/recetas',
                'component' => 'clinica/recetas/*',
                'keywords' => ['prescripción', 'medicamentos', 'dosis', 'pdf'],
                'content' => 'Crea y consulta prescripciones asociadas al paciente o consulta, con medicamentos, dosis, frecuencia, duración e indicaciones. Permite generar PDF.',
            ],
            'laboratorio' => [
                'title' => 'Clínica · Laboratorio',
                'permission' => 'laboratorio.view',
                'url' => '/clinica/laboratorio',
                'component' => 'clinica/laboratorio/*',
                'keywords' => ['análisis', 'exámenes', 'pedido', 'muestra', 'resultado'],
                'content' => 'Gestiona pedidos de exámenes, líneas solicitadas, estado y archivos de resultados. Los pedidos pueden vincularse al paciente y a su atención.',
            ],
            'cirugias' => [
                'title' => 'Clínica · Cirugías',
                'permission' => 'cirugias.view',
                'url' => '/clinica/cirugias',
                'component' => 'clinica/cirugias/*',
                'keywords' => ['cirugía', 'quirófano', 'procedimiento', 'operación'],
                'content' => 'Registra y da seguimiento a procedimientos quirúrgicos del paciente, fechas, responsables, estado y observaciones clínicas.',
            ],
            'hospitalizacion' => [
                'title' => 'Clínica · Hospitalización',
                'permission' => 'hospitalizacion.view',
                'url' => '/clinica/hospitalizacion',
                'component' => 'clinica/hospitalizacion/*',
                'keywords' => ['internamiento', 'hospital', 'evoluciones', 'alta'],
                'content' => 'Administra internamientos, evolución, indicaciones, estado y alta. Los consumos clínicos pueden incorporarse a los cargos de la atención.',
            ],
            'grooming' => [
                'title' => 'Servicios · Grooming',
                'permission' => 'grooming.view',
                'url' => '/servicios/grooming',
                'component' => 'servicios/grooming/*',
                'keywords' => ['peluquería', 'baño', 'turnos', 'fotos', 'insumos'],
                'content' => 'Gestiona turnos de grooming, servicios, tarifas, estado, fotos e insumos consumidos. El servicio terminado puede cobrarse desde caja.',
            ],
            'hotel' => [
                'title' => 'Servicios · Hotel',
                'permission' => 'hotel.view',
                'url' => '/servicios/hotel',
                'component' => 'servicios/hotel/*',
                'keywords' => ['guardería', 'hospedaje', 'estancia', 'diario', 'tarifa'],
                'content' => 'Registra estancias de hotel o guardería, tipo, ingreso/salida, tarifa, notas diarias y servicios adicionales para su posterior cobro.',
            ],
            'productos' => [
                'title' => 'Inventario · Productos',
                'permission' => 'productos.view',
                'url' => '/inventario/productos',
                'component' => 'inventario/productos/*',
                'keywords' => ['catálogo', 'sku', 'medicamentos', 'lotes', 'precio'],
                'content' => 'Catálogo de productos e insumos con SKU, unidad, categoría, precios y control por lote cuando corresponda.',
            ],
            'categorias-inventario' => [
                'title' => 'Inventario · Categorías',
                'permission' => 'categorias-inventario.view',
                'url' => '/inventario/categorias',
                'component' => 'inventario/categorias/*',
                'keywords' => ['clasificación', 'familias', 'categorías de producto'],
                'content' => 'Organiza el catálogo de productos en categorías para facilitar búsquedas, reportes y operación de caja.',
            ],
            'stock' => [
                'title' => 'Inventario · Stock',
                'permission' => 'stock.view',
                'url' => '/inventario/stock',
                'component' => 'inventario/stock/*',
                'keywords' => ['existencias', 'almacén', 'ajuste', 'sede', 'cantidad'],
                'content' => 'Consulta existencias por producto y sede. Los usuarios autorizados pueden realizar ajustes con motivo y trazabilidad.',
            ],
            'movimientos-stock' => [
                'title' => 'Inventario · Movimientos',
                'permission' => 'movimientos-stock.view',
                'url' => '/inventario/movimientos',
                'component' => 'inventario/movimientos/*',
                'keywords' => ['kardex', 'entrada', 'salida', 'traslado', 'ajuste'],
                'content' => 'Kardex de entradas, salidas, ajustes y traslados entre sedes. Cada movimiento conserva producto, cantidad, motivo y responsable.',
            ],
            'alertas-stock' => [
                'title' => 'Inventario · Alertas',
                'permission' => 'alertas-stock.view',
                'url' => '/inventario/alertas',
                'component' => 'inventario/alertas/*',
                'keywords' => ['stock bajo', 'agotado', 'caducidad', 'vencimiento', 'lotes'],
                'content' => 'Centraliza productos con stock bajo o agotado y lotes vencidos o próximos a vencer para priorizar reposición y rotación FEFO.',
            ],
            'proveedores' => [
                'title' => 'Inventario · Proveedores',
                'permission' => 'proveedores.view',
                'url' => '/inventario/proveedores',
                'component' => 'inventario/proveedores/*',
                'keywords' => ['proveedor', 'contacto', 'ruc', 'abastecimiento'],
                'content' => 'Directorio de proveedores utilizado en compras, con identificación fiscal, contacto y datos comerciales.',
            ],
            'compras' => [
                'title' => 'Inventario · Compras',
                'permission' => 'compras.view',
                'url' => '/inventario/compras',
                'component' => 'inventario/compras/*',
                'keywords' => ['compra', 'recepción', 'proveedor', 'costo', 'ingreso stock'],
                'content' => 'Registra compras a proveedores y sus líneas. Confirmar la recepción genera entradas de inventario, costos y lotes según el producto.',
            ],
            'caja-sesiones' => [
                'title' => 'Caja · Sesiones',
                'permission' => 'caja-sesiones.view',
                'url' => '/caja/sesiones',
                'component' => 'caja/sesiones/*',
                'keywords' => ['abrir caja', 'cerrar caja', 'turno', 'arqueo', 'saldo'],
                'content' => 'Abre y cierra turnos de caja por usuario y sede. Antes de cobrar debe existir una sesión abierta; al cierre se registran totales y diferencias.',
                'priority' => 25,
            ],
            'ventas' => [
                'title' => 'Caja · Ventas',
                'permission' => 'ventas.view',
                'url' => '/caja/ventas',
                'component' => 'caja/ventas/*',
                'keywords' => ['pos', 'punto de venta', 'cobrar', 'ticket', 'precuenta'],
                'content' => 'Punto de venta para cobrar productos, servicios y cargos clínicos. Requiere sesión de caja abierta y permite asociar propietario, paciente y comprobante.',
                'priority' => 30,
            ],
            'pagos' => [
                'title' => 'Caja · Pagos',
                'permission' => 'pagos.view',
                'url' => '/caja/pagos',
                'component' => 'caja/pagos/*',
                'keywords' => ['medio de pago', 'efectivo', 'tarjeta', 'reembolso'],
                'content' => 'Consulta los pagos registrados y sus medios. Los reembolsos y operaciones especiales dependen de permisos específicos.',
            ],
            'descuentos' => [
                'title' => 'Caja · Descuentos',
                'permission' => 'descuentos.view',
                'url' => '/caja/descuentos',
                'component' => 'caja/descuentos/*',
                'keywords' => ['promociones', 'descuento', 'segunda mascota', 'campaña'],
                'content' => 'Configura y consulta promociones o descuentos aplicables en ventas, incluyendo vigencia, condiciones y productos o servicios alcanzados.',
            ],
            'documentos' => [
                'title' => 'Facturación · Documentos',
                'permission' => 'documentos.view',
                'url' => '/facturacion/documentos',
                'component' => 'facturacion/documentos/*',
                'keywords' => ['boleta', 'factura', 'comprobante', 'sunat', 'fel'],
                'content' => 'Consulta y emite comprobantes electrónicos originados en ventas. Permite revisar estado de envío y anulación según permisos y proveedor FEL configurado.',
            ],
            'series' => [
                'title' => 'Facturación · Series',
                'permission' => 'series.view',
                'url' => '/facturacion/series',
                'component' => 'facturacion/series/*',
                'keywords' => ['serie', 'correlativo', 'boleta', 'factura'],
                'content' => 'Administra series y correlativos de comprobantes por tipo y sede. Deben concordar con la configuración del proveedor de facturación electrónica.',
            ],
            'notas-baja' => [
                'title' => 'Facturación · Notas de baja',
                'permission' => 'notas-baja.view',
                'url' => '/facturacion/notas-baja',
                'component' => 'facturacion/notas-baja/*',
                'keywords' => ['baja', 'anulación', 'sunat', 'comprobante'],
                'content' => 'Consulta y genera comunicaciones de baja de comprobantes cuando la normativa y el estado del documento lo permiten.',
            ],
            'resumenes' => [
                'title' => 'Facturación · Resúmenes',
                'permission' => 'resumenes.view',
                'url' => '/facturacion/resumenes',
                'component' => 'facturacion/resumenes/*',
                'keywords' => ['resumen diario', 'sunat', 'boletas', 'envío'],
                'content' => 'Gestiona resúmenes diarios de boletas y su envío al proveedor de facturación electrónica.',
            ],
            'comunicaciones-cola' => [
                'title' => 'Comunicaciones · Cola',
                'permission' => 'comunicaciones-cola.view',
                'url' => '/comunicaciones/cola',
                'component' => 'comunicaciones/cola/*',
                'keywords' => ['mensajes', 'pendientes', 'whatsapp', 'reintentar'],
                'content' => 'Muestra mensajes pendientes, procesados o fallidos. Los usuarios con permiso de gestión pueden reintentar o administrar la cola.',
            ],
            'comunicaciones-historico' => [
                'title' => 'Comunicaciones · Histórico',
                'permission' => 'comunicaciones-historico.view',
                'url' => '/comunicaciones/historico',
                'component' => 'comunicaciones/historico/*',
                'keywords' => ['historial', 'mensajes enviados', 'whatsapp', 'estado'],
                'content' => 'Consulta el historial de comunicaciones enviadas, destinatario, canal, fecha y resultado.',
            ],
            'comunicaciones-bot-ia' => [
                'title' => 'Comunicaciones · Bot IA',
                'permission' => 'comunicaciones-bot-ia.view',
                'url' => '/comunicaciones/bot-ia',
                'component' => 'comunicaciones/bot-ia/*',
                'keywords' => ['asistente whatsapp', 'bot', 'conocimiento clínica', 'conversaciones'],
                'content' => 'Administra el Bot IA de WhatsApp de la clínica, su estado y conocimiento clínico propio. Esta información es distinta de la ayuda interna de VetSaaS.',
            ],
            'plantillas' => [
                'title' => 'Comunicaciones · Plantillas',
                'permission' => 'plantillas.view',
                'url' => '/comunicaciones/plantillas',
                'component' => 'comunicaciones/plantillas/*',
                'keywords' => ['mensajes', 'plantilla', 'recordatorio', 'whatsapp'],
                'content' => 'Gestiona textos reutilizables para recordatorios y comunicaciones de la clínica.',
            ],
            'ayuda' => [
                'title' => 'Configuración · Centro de ayuda',
                'permission' => null,
                'url' => '/configuracion/ayuda',
                'component' => 'configuracion/ayuda/*',
                'keywords' => ['manual', 'guía', 'primeros pasos', 'soporte'],
                'content' => 'Centro de ayuda con guías operativas y accesos a los módulos disponibles para el rol actual.',
                'roles' => ['admin_clinica', 'veterinario', 'asistente_vet', 'recepcionista', 'groomer'],
            ],
            'config-general' => [
                'title' => 'Configuración · General',
                'permission' => 'config-general.view',
                'url' => '/configuracion/general',
                'component' => 'configuracion/general/*',
                'keywords' => ['clínica', 'datos fiscales', 'logo', 'facturación electrónica'],
                'content' => 'Configura identidad, datos fiscales, contacto, preferencias y credenciales operativas de la clínica.',
            ],
            'suscripcion' => [
                'title' => 'Configuración · Suscripción',
                'permission' => null,
                'url' => '/configuracion/suscripcion',
                'component' => 'configuracion/suscripcion/*',
                'keywords' => ['plan', 'límites', 'renovación', 'pago'],
                'content' => 'Muestra el plan, estado de suscripción, límites y próxima renovación de la clínica.',
                'roles' => ['admin_clinica'],
            ],
            'sedes' => [
                'title' => 'Configuración · Sedes',
                'permission' => 'sedes.view',
                'url' => '/configuracion/sedes',
                'component' => 'configuracion/sedes/*',
                'keywords' => ['sucursales', 'locales', 'dirección'],
                'content' => 'Gestiona sucursales de la clínica. Sedes se usan en agenda, usuarios, inventario, caja y facturación.',
            ],
            'roles' => [
                'title' => 'Configuración · Roles',
                'permission' => 'roles.view',
                'url' => '/configuracion/roles',
                'component' => 'configuracion/roles/*',
                'keywords' => ['perfiles', 'permisos', 'acceso'],
                'content' => 'Consulta roles base y crea roles personalizados. Los permisos determinan módulos y operaciones visibles para cada usuario.',
            ],
            'horarios' => [
                'title' => 'Configuración · Horarios',
                'permission' => 'horarios.view',
                'url' => '/configuracion/horarios',
                'component' => 'configuracion/horarios/*',
                'keywords' => ['agenda', 'disponibilidad', 'atención'],
                'content' => 'Define horarios de atención utilizados por agenda y disponibilidad operativa.',
            ],
            'tarifas' => [
                'title' => 'Configuración · Tarifas',
                'permission' => 'tarifas.view',
                'url' => '/configuracion/tarifas',
                'component' => 'configuracion/tarifas/*',
                'keywords' => ['precios', 'servicios clínicos', 'grooming', 'hotel'],
                'content' => 'Administra catálogos y precios de servicios clínicos, grooming y hotel usados al generar cargos y ventas.',
            ],
            'usuarios' => [
                'title' => 'Configuración · Usuarios',
                'permission' => 'usuarios.view',
                'url' => '/configuracion/usuarios',
                'component' => 'configuracion/usuarios/*',
                'keywords' => ['personal', 'empleados', 'acceso', 'contraseña', 'rol'],
                'content' => 'Gestiona personal de la clínica, sede, estado, roles y restablecimiento de contraseña según permisos.',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function roleEntries(): array
    {
        $roles = [
            'admin-clinica' => [
                'title' => 'Rol base · Administrador de clínica',
                'role' => 'admin_clinica',
                'keywords' => ['dueño', 'administrador', 'admin'],
                'content' => 'Acceso operativo amplio al tenant: clínica, servicios, inventario, caja, facturación, comunicaciones, reportes y configuración. No concede acceso al panel central de plataforma.',
            ],
            'veterinario' => [
                'title' => 'Rol base · Veterinario',
                'role' => 'veterinario',
                'keywords' => ['médico', 'doctor', 'vet'],
                'content' => 'Atiende citas y trabaja con historia clínica, vacunas, recetas, laboratorio, cirugías y hospitalización. Su acceso financiero y administrativo es limitado.',
            ],
            'asistente-vet' => [
                'title' => 'Rol base · Asistente veterinario',
                'role' => 'asistente_vet',
                'keywords' => ['asistente clínico', 'apoyo'],
                'content' => 'Apoya agenda y vacunaciones y consulta información clínica permitida. No sustituye las operaciones clínicas reservadas al veterinario.',
            ],
            'recepcionista' => [
                'title' => 'Rol base · Recepcionista',
                'role' => 'recepcionista',
                'keywords' => ['recepción', 'front desk', 'cobros'],
                'content' => 'Registra propietarios y pacientes, administra agenda, caja, ventas y comprobantes. Puede gestionar cargos para cobro sin editar el contenido médico de la atención.',
            ],
            'groomer' => [
                'title' => 'Rol base · Groomer',
                'role' => 'groomer',
                'keywords' => ['peluquero', 'bañador'],
                'content' => 'Opera grooming y consulta los datos mínimos de pacientes, propietarios, citas y hotel necesarios para prestar el servicio.',
            ],
        ];

        $entries = [];
        $order = 1000;
        foreach ($roles as $slug => $role) {
            $entries[] = $this->entry([
                'slug' => 'role-'.$slug,
                'section' => InAppAssistantKnowledge::SECTION_ROLE,
                'title' => $role['title'],
                'content' => $role['content'],
                'keywords' => array_merge([$role['role']], $role['keywords']),
                'allowed_roles' => [$role['role']],
                'priority' => 15,
                'sort_order' => $order,
            ]);
            $order += 10;
        }

        return $entries;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function workflowEntries(): array
    {
        return [
            $this->entry([
                'slug' => 'workflow-cita-atencion-cobro-cierre',
                'section' => InAppAssistantKnowledge::SECTION_WORKFLOW,
                'title' => 'Flujo clave · Cita → HC → cargos → caja → cierre',
                'content' => "1. Recepción registra o confirma la cita.\n2. El veterinario usa Aperturar para crear o vincular la consulta en la historia clínica.\n3. Durante la atención registra hallazgos, plan, receta, laboratorio u otros actos clínicos.\n4. En Cargos agrega productos y servicios consumidos y confirma la pre-cuenta.\n5. Caja, con sesión abierta, cobra la pre-cuenta en una venta y emite el comprobante si corresponde.\n6. Al cobrarse la pre-cuenta, el sistema cierra automáticamente la consulta de HC y marca la cita como completada. No se requiere un cierre clínico manual posterior. Caja cierra su sesión al terminar el turno.",
                'keywords' => ['aperturar cita', 'atender cita', 'historia clínica', 'consulta', 'cargos', 'cobrar', 'cerrar consulta', 'cerrar caja'],
                'url_patterns' => ['/clinica/citas*', '/clinica/historias-clinicas*', '/caja/*'],
                'required_permissions' => ['citas.aperturar', 'historias-clinicas.view', 'consulta-cargos.view', 'ventas.create'],
                'permission_mode' => InAppAssistantKnowledge::PERMISSION_ANY,
                'allowed_roles' => ['admin_clinica', 'veterinario', 'recepcionista'],
                'actions' => [
                    $this->navigate('/clinica/citas', 'Abrir Citas', ['citas.view']),
                    $this->navigate('/clinica/historias-clinicas', 'Abrir Historias clínicas', ['historias-clinicas.view']),
                    $this->navigate('/caja/sesiones', 'Revisar sesión de caja', ['caja-sesiones.view']),
                    $this->navigate('/caja/ventas', 'Abrir Ventas', ['ventas.view']),
                ],
                'priority' => 50,
                'sort_order' => 2000,
            ]),
            $this->entry([
                'slug' => 'workflow-alta-propietario-paciente-cita',
                'section' => InAppAssistantKnowledge::SECTION_WORKFLOW,
                'title' => 'Flujo · Nuevo cliente, paciente y cita',
                'content' => 'Busca primero al propietario por documento o contacto para evitar duplicados. Si no existe, créalo; luego registra su paciente y finalmente agenda la cita con sede, fecha, hora y veterinario.',
                'keywords' => ['nuevo cliente', 'nueva mascota', 'registrar paciente', 'agendar'],
                'url_patterns' => ['/clinica/propietarios*', '/clinica/pacientes*', '/clinica/citas*'],
                'required_permissions' => ['propietarios.create', 'pacientes.create', 'citas.create'],
                'permission_mode' => InAppAssistantKnowledge::PERMISSION_ALL,
                'actions' => [
                    $this->navigate('/clinica/propietarios', 'Abrir Propietarios', ['propietarios.create']),
                    $this->navigate('/clinica/citas', 'Abrir Citas', ['citas.create']),
                ],
                'priority' => 35,
                'sort_order' => 2010,
            ]),
            $this->entry([
                'slug' => 'workflow-compra-ingreso-stock',
                'section' => InAppAssistantKnowledge::SECTION_WORKFLOW,
                'title' => 'Flujo · Compra e ingreso de stock',
                'content' => 'Verifica proveedor y productos, registra la compra con cantidades, costos y lotes, y confirma la recepción. El sistema genera los movimientos de entrada; valida luego las existencias por sede.',
                'keywords' => ['abastecer', 'recepcionar compra', 'aumentar stock', 'lote'],
                'url_patterns' => ['/inventario/compras*', '/inventario/stock*', '/inventario/movimientos*'],
                'required_permissions' => ['compras.create', 'stock.view'],
                'permission_mode' => InAppAssistantKnowledge::PERMISSION_ALL,
                'actions' => [
                    $this->navigate('/inventario/compras', 'Abrir Compras', ['compras.create']),
                    $this->navigate('/inventario/stock', 'Ver Stock', ['stock.view']),
                ],
                'priority' => 30,
                'sort_order' => 2020,
            ]),
            $this->entry([
                'slug' => 'workflow-vacunacion-refuerzo',
                'section' => InAppAssistantKnowledge::SECTION_WORKFLOW,
                'title' => 'Flujo · Vacunación y próximo refuerzo',
                'content' => 'Selecciona paciente y producto de vacuna, registra aplicación y lote, define la fecha del próximo refuerzo y guarda. Verifica la constancia y las alertas futuras.',
                'keywords' => ['aplicar vacuna', 'próxima vacuna', 'refuerzo', 'carnet'],
                'url_patterns' => ['/clinica/vacunaciones*'],
                'required_permissions' => ['vacunaciones.create'],
                'actions' => [$this->navigate('/clinica/vacunaciones', 'Abrir Vacunaciones', ['vacunaciones.create'])],
                'priority' => 30,
                'sort_order' => 2030,
            ]),
            $this->entry([
                'slug' => 'workflow-abrir-vender-cerrar-caja',
                'section' => InAppAssistantKnowledge::SECTION_WORKFLOW,
                'title' => 'Flujo · Abrir caja → vender → cerrar caja',
                'content' => 'Abre una sesión en la sede y registra el monto inicial. Durante el turno cobra ventas y cargos con sus medios de pago. Al finalizar revisa totales, registra efectivo contado y cierra la sesión explicando diferencias.',
                'keywords' => ['turno de caja', 'apertura', 'cobro', 'arqueo', 'cierre'],
                'url_patterns' => ['/caja/*'],
                'required_permissions' => ['caja-sesiones.open', 'ventas.create', 'caja-sesiones.close'],
                'permission_mode' => InAppAssistantKnowledge::PERMISSION_ALL,
                'actions' => [
                    $this->navigate('/caja/sesiones', 'Abrir Sesiones de caja', ['caja-sesiones.view']),
                    $this->navigate('/caja/ventas', 'Abrir Ventas', ['ventas.create']),
                ],
                'priority' => 40,
                'sort_order' => 2040,
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function faqEntries(): array
    {
        return [
            $this->entry([
                'slug' => 'faq-permisos-modulos',
                'section' => InAppAssistantKnowledge::SECTION_FAQ,
                'title' => '¿Por qué no veo un módulo o botón?',
                'content' => 'La visibilidad depende de los permisos del rol, módulos habilitados por el plan y, en algunos casos, la sede. Solicita al administrador de la clínica revisar tu usuario y rol; el asistente no debe ofrecer navegación hacia módulos sin permiso.',
                'keywords' => ['no veo', 'no aparece', 'sin acceso', 'permiso', 'módulo oculto'],
                'allowed_roles' => ['admin_clinica', 'veterinario', 'asistente_vet', 'recepcionista', 'groomer'],
                'priority' => 35,
                'sort_order' => 3000,
            ]),
            $this->entry([
                'slug' => 'faq-diferencia-cierre-clinico-caja',
                'section' => InAppAssistantKnowledge::SECTION_FAQ,
                'title' => 'Cierre automático de consulta y cierre de caja',
                'content' => 'Al cobrar la pre-cuenta, VetSaaS cierra automáticamente la consulta de historia clínica y completa la cita. El cierre de caja es otra operación: finaliza el turno financiero y concilia cobros.',
                'keywords' => ['cerrar atención', 'cerrar hc', 'cerrar turno', 'cierre'],
                'required_permissions' => ['historias-clinicas.view', 'caja-sesiones.view'],
                'permission_mode' => InAppAssistantKnowledge::PERMISSION_ANY,
                'priority' => 30,
                'sort_order' => 3010,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function entry(array $overrides): array
    {
        return array_merge([
            'scope' => InAppAssistantKnowledge::SCOPE_CLINIC,
            'section' => InAppAssistantKnowledge::SECTION_MODULE,
            'keywords' => [],
            'url_patterns' => [],
            'component_patterns' => [],
            'required_permissions' => [],
            'permission_mode' => InAppAssistantKnowledge::PERMISSION_ANY,
            'allowed_roles' => [],
            'actions' => [],
            'priority' => 0,
            'sort_order' => 0,
            'is_active' => true,
        ], $overrides);
    }

    /**
     * @param  list<string>  $permissions
     * @return array<string, mixed>
     */
    private function navigate(string $url, string $label, array $permissions): array
    {
        return [
            'type' => 'navigate',
            'url' => $url,
            'label' => $label,
            'required_permissions' => $permissions,
        ];
    }

    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $roles
     * @return array<string, mixed>
     */
    private function startTour(string $tourId, string $label, array $permissions, array $roles = []): array
    {
        return [
            'type' => 'start_tour',
            'tour_id' => $tourId,
            'label' => $label,
            'required_permissions' => $permissions,
            'allowed_roles' => $roles,
        ];
    }
}
