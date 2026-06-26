#!/usr/bin/env python3
"""Brochure VetSaaS — módulos tenant, diseño editorial profesional."""

from __future__ import annotations

from pathlib import Path

from fpdf import FPDF

ROOT = Path(__file__).resolve().parents[1]
LOGO = ROOT / "public" / "logo.png"
OUTPUT = ROOT / "docs" / "vetsaas-brochure-modulos.pdf"
FONT_REGULAR = Path(r"C:\Windows\Fonts\arial.ttf")
FONT_BOLD = Path(r"C:\Windows\Fonts\arialbd.ttf")
FONT_ITALIC = Path(r"C:\Windows\Fonts\ariali.ttf")

# Paleta VetSaaS
BRAND = (0, 128, 100)          # #008064
BRAND_DARK = (12, 78, 62)      # header portada
BRAND_SOFT = (232, 247, 243)
BRAND_MID = (180, 220, 208)
WHITE = (255, 255, 255)
INK = (28, 36, 44)
MUTED = (100, 110, 120)
CARD_BG = (248, 250, 249)
LINE = (226, 232, 228)

MARGIN = 14
PAGE_BOTTOM = 272
COL_GAP = 5
CARD_PAD = 4

MODULES: list[dict] = [
    {
        "title": "Dashboard",
        "tagline": "Tu clínica de un vistazo",
        "intro": "El punto de partida cada mañana: métricas del día, citas próximas y accesos directos a lo que más usas.",
        "views": [
            (
                "Dashboard",
                "Resumen ejecutivo con indicadores de actividad, citas del día y atajos a pacientes, agenda y caja. "
                "Ideal para recepción y dueños de clínica que necesitan ver el pulso del negocio en segundos.",
            ),
        ],
    },
    {
        "title": "Clínica",
        "tagline": "El corazón asistencial",
        "intro": "Gestiona todo el ciclo de atención veterinaria: desde el primer contacto con el propietario hasta cirugías e internamiento.",
        "views": [
            (
                "Pacientes",
                "Ficha digital de cada mascota con foto, especie, raza, edad, microchip y propietario vinculado. "
                "Historial clínico completo accesible en un clic desde cualquier dispositivo.",
            ),
            (
                "Propietarios",
                "Directorio de dueños con DNI/RUC, teléfono, email y todas sus mascotas. "
                "Búsqueda rápida y comunicación centralizada.",
            ),
            (
                "Citas",
                "Agenda visual por día, semana o mes. Asigna veterinario, tipo de servicio y sede. "
                "Reduce ausencias con recordatorios automáticos por WhatsApp.",
            ),
            (
                "Historias clínicas",
                "Consultas estructuradas: motivo, examen físico, diagnóstico, plan terapéutico y archivos adjuntos "
                "(radiografías, informes, fotos). Sin cuadernos ni hojas sueltas.",
            ),
            (
                "Vacunaciones",
                "Control de esquemas de vacunación, fechas aplicadas y próximas dosis. "
                "Alertas para no perder campañas ni ingresos por vacunas vencidas.",
            ),
            (
                "Recetas",
                "Prescripciones médicas ligadas a cada consulta, con medicamentos, dosis y duración. "
                "Listas para imprimir o entregar al propietario.",
            ),
            (
                "Laboratorio",
                "Solicitud y registro de análisis clínicos con resultados vinculados al paciente y a la consulta. "
                "Trazabilidad total sin papeles perdidos.",
            ),
            (
                "Cirugías",
                "Protocolo quirúrgico completo: preanestesia, técnica, hallazgos, materiales y evolución postoperatoria. "
                "Documentación profesional para auditorías y seguimiento.",
            ),
            (
                "Hospitalización",
                "Control de pacientes internados: jaula, evolución diaria, medicación, fluidoterapia y alta médica. "
                "El equipo siempre sabe el estado de cada interno.",
            ),
        ],
    },
    {
        "title": "Servicios",
        "tagline": "Más allá de la consulta",
        "intro": "Áreas comerciales que muchas clínicas ofrecen y que VetSaaS integra con la misma agenda y caja.",
        "views": [
            (
                "Grooming",
                "Agenda de peluquería canina, catálogo de servicios, historial de cortes por mascota y cobro directo en caja. "
                "Separa grooming de consultas sin perder el control.",
            ),
            (
                "Hotel",
                "Guardería y hospedaje: check-in, estancia por días, cargos automáticos y checkout con venta integrada. "
                "Perfecto para clínicas con servicio de hotel para mascotas.",
            ),
        ],
    },
    {
        "title": "Inventario",
        "tagline": "Stock bajo control",
        "intro": "Medicamentos, vacunas e insumos siempre disponibles, con trazabilidad por sede y alertas antes de quedarte sin stock.",
        "views": [
            (
                "Productos",
                "Catálogo con SKU, código de barras, precio de compra y venta, categoría y stock mínimo configurable.",
            ),
            (
                "Categorías",
                "Organiza medicamentos, alimentos, accesorios y consumibles para encontrar todo rápido en ventas y compras.",
            ),
            (
                "Stock",
                "Existencias en tiempo real por sede. Sabes qué hay en almacén y qué está en piso de venta.",
            ),
            (
                "Movimientos",
                "Kardex detallado: cada entrada, salida, ajuste y consumo en consulta queda registrado con fecha y usuario.",
            ),
            (
                "Alertas",
                "Notificaciones de stock bajo y productos próximos a vencer. Repón antes de perder ventas en temporada alta.",
            ),
            (
                "Proveedores",
                "Base de proveedores con RUC validado, contacto y condiciones. Vinculado a tus órdenes de compra.",
            ),
            (
                "Compras",
                "Registra ingresos de mercadería con líneas, factura del proveedor y actualización automática de inventario.",
            ),
        ],
    },
    {
        "title": "Caja",
        "tagline": "Cobra y controla",
        "intro": "Punto de venta pensado para clínicas: cobra consultas, productos y servicios con arqueo diario confiable.",
        "views": [
            (
                "Sesiones",
                "Apertura y cierre de caja por usuario y sede. Arqueo con totales por método de pago y diferencias detectadas.",
            ),
            (
                "Ventas",
                "POS completo: productos de inventario, servicios, cargos de consulta, grooming u hospitalización. "
                "Efectivo, Yape, Plin, tarjeta y transferencia.",
            ),
            (
                "Pagos",
                "Historial de cobros con trazabilidad a cada venta. Conciliación clara para el cierre del día.",
            ),
            (
                "Descuentos",
                "Promociones configurables (ej. segunda mascota en grooming). Aplicación automática en el checkout.",
            ),
        ],
    },
    {
        "title": "Facturación",
        "tagline": "SUNAT sin salir del sistema",
        "intro": "Emite boletas y facturas electrónicas integradas con Nubefact al momento de cobrar (planes Pro y Clínica).",
        "views": [
            (
                "Comprobantes emitidos",
                "Listado de boletas y facturas con PDF, XML, estado SUNAT y reenvío al cliente por correo o WhatsApp.",
            ),
            (
                "Series",
                "Configura series FEL (B001, F001, etc.) por sede según tu certificado digital y numeración SUNAT.",
            ),
            (
                "Notas de baja",
                "Anulaciones, notas de crédito y débito electrónicas cuando el negocio lo requiera.",
            ),
            (
                "Resúmenes",
                "Resúmenes de boletas y reportes tributarios para tu contador y cumplimiento mensual.",
            ),
        ],
    },
    {
        "title": "Comunicaciones",
        "tagline": "WhatsApp que trabaja por ti",
        "intro": "Automatiza recordatorios y avisos a propietarios desde el número de tu clínica, sin apps extra.",
        "views": [
            (
                "Cola saliente",
                "Mensajes programados: recordatorio de cita 24 h antes, vacunas vencidas, cumpleaños de mascotas y más.",
            ),
            (
                "Histórico",
                "Registro de cada mensaje enviado con estado y fecha. Sabes qué se comunicó y a quién.",
            ),
            (
                "Plantillas",
                "Textos reutilizables con variables dinámicas: nombre de la mascota, hora de cita, nombre de la clínica.",
            ),
        ],
    },
    {
        "title": "Reportes",
        "tagline": "Decisiones con datos",
        "intro": "Convierte la operación diaria en información útil para crecer y detectar oportunidades.",
        "views": [
            (
                "Snapshots",
                "Instantáneas periódicas de indicadores clave para comparar semanas o meses.",
            ),
            (
                "Financiero mensual",
                "Ingresos, ventas por categoría y tendencias del mes. Por sede o consolidado multi-sucursal.",
            ),
            (
                "Top pacientes",
                "Ranking de mascotas más atendidas o que más facturan. Identifica clientes fieles y VIP.",
            ),
        ],
    },
    {
        "title": "Configuración",
        "tagline": "Tu clínica, a tu medida",
        "intro": "Identidad, equipo, sedes, horarios y tarifas en un solo lugar. Sin depender de soporte para cada cambio.",
        "views": [
            (
                "General",
                "RUC, razón social, logo, colores, token Nubefact, recordatorios WhatsApp y parámetros de la clínica.",
            ),
            (
                "Mi suscripción",
                "Plan VetSaaS activo, límites de usuarios/pacientes y enlace de renovación.",
            ),
            (
                "Sedes",
                "Gestiona sucursales con datos operativos independientes: caja, stock y agenda por local.",
            ),
            (
                "Roles",
                "Admin, veterinario, recepción y más. Cada rol ve solo lo que necesita.",
            ),
            (
                "Horarios",
                "Define bloques de atención por día de la semana para la agenda y citas en línea.",
            ),
            (
                "Bloqueos",
                "Marca feriados, capacitaciones o cierres parciales para que nadie agende en esas fechas.",
            ),
            (
                "Tarifas",
                "Precios base de consultas, procedimientos, grooming y hotel. Base para cotizar en caja.",
            ),
            (
                "Usuarios",
                "Invita a tu equipo, asigna sede y rol. Control de quién accede y qué puede hacer.",
            ),
        ],
    },
    {
        "title": "Auditoría",
        "tagline": "Seguridad y trazabilidad",
        "intro": "Registro de actividad para cumplimiento, resolución de incidencias y tranquilidad operativa.",
        "views": [
            (
                "Logs",
                "Acciones relevantes en el sistema: quién hizo qué y cuándo.",
            ),
            (
                "Intentos de acceso",
                "Historial de inicios de sesión exitosos y fallidos. Detecta accesos sospechosos.",
            ),
            (
                "Logs API",
                "Peticiones a integraciones externas para depuración y soporte.",
            ),
            (
                "Tokens",
                "Gestión de tokens de API para conectar sistemas externos de forma segura.",
            ),
        ],
    },
]


class BrochurePDF(FPDF):
    def __init__(self) -> None:
        super().__init__(orientation="P", unit="mm", format="A4")
        self.set_auto_page_break(auto=False)
        self.set_margins(MARGIN, MARGIN, MARGIN)
        self._font = "Helvetica"
        self._module_idx = 0
        self._setup_fonts()

    def _setup_fonts(self) -> None:
        if FONT_REGULAR.exists() and FONT_BOLD.exists():
            self.add_font("VF", "", str(FONT_REGULAR))
            self.add_font("VF", "B", str(FONT_BOLD))
            if FONT_ITALIC.exists():
                self.add_font("VF", "I", str(FONT_ITALIC))
            self._font = "VF"

    def _f(self, style: str = "", size: float = 10) -> None:
        self.set_font(self._font, style, size)

    def _footer_line(self) -> None:
        self.set_draw_color(*LINE)
        self.line(MARGIN, 283, 210 - MARGIN, 283)
        self.set_y(285)
        self._f("", 7)
        self.set_text_color(*MUTED)
        self.cell(95, 4, "VetSaaS by Orvae", align="L")
        self.cell(0, 4, "vetsaas.orvae.pe", align="R")

    def _page_shell(self, module_num: int, title: str) -> None:
        """Banda superior sutil en páginas internas."""
        self.set_fill_color(*BRAND_DARK)
        self.rect(0, 0, 210, 22, style="F")
        self.set_fill_color(*BRAND)
        self.rect(0, 22, 210, 1.2, style="F")

        if LOGO.exists():
            self.image(str(LOGO), x=MARGIN, y=4.5, w=12)

        self.set_xy(MARGIN + 16, 7)
        self._f("B", 9)
        self.set_text_color(*WHITE)
        self.cell(80, 5, "VetSaaS")

        self.set_xy(-MARGIN - 45, 8)
        self._f("", 7.5)
        self.set_text_color(200, 230, 218)
        self.cell(45, 4, f"Módulo {module_num:02d}", align="R")

        self.set_y(30)

    def cover(self) -> None:
        self.add_page()
        # Bloque superior oscuro
        self.set_fill_color(*BRAND_DARK)
        self.rect(0, 0, 210, 155, style="F")
        # Acento diagonal suave
        self.set_fill_color(8, 95, 75)
        self.rect(0, 140, 210, 18, style="F")

        if LOGO.exists():
            # Marco blanco detrás del logo
            self.set_fill_color(*WHITE)
            self.rect(72, 22, 66, 66, style="F")
            self.image(str(LOGO), x=78, y=28, w=54)

        self.set_y(98)
        self._f("B", 32)
        self.set_text_color(*WHITE)
        self.cell(0, 12, "VetSaaS", align="C", new_x="LMARGIN", new_y="NEXT")

        self._f("", 12)
        self.set_text_color(210, 235, 225)
        self.cell(0, 7, "Software integral para clínicas veterinarias", align="C", new_x="LMARGIN", new_y="NEXT")

        self.ln(3)
        self._f("I", 9.5)
        self.cell(0, 5, "Perú  ·  Multi-sede  ·  SUNAT  ·  WhatsApp  ·  PWA", align="C", new_x="LMARGIN", new_y="NEXT")

        # Cuerpo inferior blanco
        self.set_y(168)
        self._f("B", 11)
        self.set_text_color(*BRAND)
        self.cell(0, 7, "Guía de módulos operativos", align="C", new_x="LMARGIN", new_y="NEXT")

        self.ln(2)
        self._f("", 9.5)
        self.set_text_color(*MUTED)
        self.multi_cell(
            0,
            5.5,
            "Todo lo que tu equipo usa cada día dentro de la clínica: atención médica, "
            "servicios, inventario, caja, facturación electrónica, comunicaciones y reportes. "
            "Sin incluir el panel de superadministración de la plataforma.",
            align="C",
        )

        # Chips de valor
        chips = ["40+ pantallas", "9 módulos", "App en celular", "Datos aislados"]
        chip_w = 42
        start_x = (210 - (chip_w * 4 + 6 * 3)) / 2
        y_chip = 210
        for i, chip in enumerate(chips):
            x = start_x + i * (chip_w + 6)
            self.set_xy(x, y_chip)
            self.set_fill_color(*BRAND_SOFT)
            self.set_draw_color(*BRAND_MID)
            self._f("B", 8)
            self.set_text_color(*BRAND)
            self.cell(chip_w, 8, chip, align="C", fill=True, border=1)

        self.set_y(248)
        self._f("", 8.5)
        self.set_text_color(*INK)
        self.cell(0, 5, "orvae.pe/software/VETSAAS", align="C", new_x="LMARGIN", new_y="NEXT")
        self._f("", 7.5)
        self.set_text_color(*MUTED)
        self.cell(0, 4, "Producto Orvae  ·  Demo: demo.vetsaas.orvae.pe", align="C")

        self._footer_line()

    def index_page(self) -> None:
        self.add_page()
        self._page_shell(0, "Índice")
        self.set_y(32)

        self._f("B", 20)
        self.set_text_color(*INK)
        self.cell(0, 10, "Contenido", new_x="LMARGIN", new_y="NEXT")
        self.ln(4)

        usable = 210 - 2 * MARGIN
        col_w = usable / 2 - 3

        for i, mod in enumerate(MODULES, start=1):
            col = (i - 1) % 2
            row = (i - 1) // 2
            x = MARGIN + col * (col_w + 6)
            y = 48 + row * 22

            self.set_xy(x, y)
            self.set_fill_color(*BRAND_SOFT if i % 2 else CARD_BG)
            self.rect(x, y, col_w, 18, style="F")
            self.set_fill_color(*BRAND)
            self.rect(x, y, 2.5, 18, style="F")

            self.set_xy(x + 6, y + 3)
            self._f("B", 7)
            self.set_text_color(*BRAND)
            self.cell(10, 4, f"{i:02d}")

            self._f("B", 10)
            self.set_text_color(*INK)
            self.cell(col_w - 14, 5, mod["title"])

            self.set_xy(x + 6, y + 10)
            self._f("I", 7.5)
            self.set_text_color(*MUTED)
            self.cell(col_w - 10, 4, mod["tagline"])

        self._footer_line()

    def _measure_text(self, text: str, width: float, line_h: float) -> float:
        self._f("", 8.2)
        lines = self.multi_cell(width, line_h, text, dry_run=True, output="LINES")
        return len(lines) * line_h

    def _draw_card(self, x: float, y: float, w: float, title: str, desc: str) -> float:
        pad = CARD_PAD
        inner_w = w - 2 * pad - 3
        title_h = 5.5
        desc_h = self._measure_text(desc, inner_w, 4.2)
        card_h = pad + title_h + 2 + desc_h + pad

        # Fondo
        self.set_fill_color(*CARD_BG)
        self.set_draw_color(*LINE)
        self.rect(x, y, w, card_h, style="FD")

        # Acento izquierdo
        self.set_fill_color(*BRAND)
        self.rect(x, y, 2.2, card_h, style="F")

        # Título
        self.set_xy(x + pad + 2, y + pad)
        self._f("B", 9)
        self.set_text_color(*INK)
        self.cell(inner_w, title_h, title, new_x="LMARGIN", new_y="NEXT")

        # Descripción
        self.set_x(x + pad + 2)
        self._f("", 8.2)
        self.set_text_color(*MUTED)
        self.multi_cell(inner_w, 4.2, desc)

        return card_h

    def module_pages(self, module_num: int, module: dict) -> None:
        self.add_page()
        self._page_shell(module_num, module["title"])

        # Título del módulo
        self.set_y(34)
        self._f("B", 22)
        self.set_text_color(*INK)
        self.cell(0, 10, module["title"], new_x="LMARGIN", new_y="NEXT")

        self._f("I", 10)
        self.set_text_color(*BRAND)
        self.cell(0, 5, module["tagline"], new_x="LMARGIN", new_y="NEXT")
        self.ln(3)

        self._f("", 9.5)
        self.set_text_color(60, 70, 78)
        self.multi_cell(0, 5, module["intro"])
        self.ln(5)

        # Línea separadora
        self.set_draw_color(*BRAND_MID)
        self.line(MARGIN, self.get_y(), 210 - MARGIN, self.get_y())
        self.ln(6)

        usable_w = 210 - 2 * MARGIN
        col_w = (usable_w - COL_GAP) / 2
        y_cols = [self.get_y(), self.get_y()]

        for title, desc in module["views"]:
            col = 0 if y_cols[0] <= y_cols[1] else 1
            x = MARGIN + col * (col_w + COL_GAP)
            y = y_cols[col]
            inner_w = col_w - 2 * CARD_PAD - 3
            est_h = CARD_PAD * 2 + 5.5 + 2 + self._measure_text(desc, inner_w, 4.2) + 4

            if y + est_h > PAGE_BOTTOM:
                self._footer_line()
                self.add_page()
                self._page_shell(module_num, module["title"])
                self.set_y(34)
                self._f("B", 14)
                self.set_text_color(*INK)
                self.cell(0, 8, f"{module['title']} (cont.)", new_x="LMARGIN", new_y="NEXT")
                self.ln(4)
                y_cols = [self.get_y(), self.get_y()]
                col = 0
                x = MARGIN
                y = y_cols[0]

            h = self._draw_card(x, y, col_w, title, desc)
            y_cols[col] = y + h + COL_GAP

        self._footer_line()


def main() -> None:
    pdf = BrochurePDF()
    pdf.cover()
    pdf.index_page()
    for idx, module in enumerate(MODULES, start=1):
        pdf.module_pages(idx, module)

    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    pdf.output(str(OUTPUT))
    print(f"PDF generado: {OUTPUT}")


if __name__ == "__main__":
    main()
