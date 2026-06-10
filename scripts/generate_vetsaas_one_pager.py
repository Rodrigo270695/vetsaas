#!/usr/bin/env python3
"""Genera el one-pager comercial de VetSaaS en PDF (A4, una página)."""

from __future__ import annotations

from pathlib import Path

from fpdf import FPDF

ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "docs" / "vetsaas-one-pager.pdf"
FONT_REGULAR = Path(r"C:\Windows\Fonts\arial.ttf")
FONT_BOLD = Path(r"C:\Windows\Fonts\arialbd.ttf")

BRAND = "#0F6E56"
BRAND_LIGHT = "#E8F5F1"
TEXT = "#1F2937"
MUTED = "#6B7280"

HTML = f"""
<style>
    * {{ margin: 0; padding: 0; box-sizing: border-box; }}
    body {{ font-family: Arial; color: {TEXT}; font-size: 8.5pt; line-height: 1.35; }}
    .header {{
        background: {BRAND};
        color: #ffffff;
        padding: 14px 16px 12px 16px;
        border-radius: 6px;
        margin-bottom: 10px;
    }}
    .title {{ font-size: 22pt; font-weight: bold; letter-spacing: -0.5px; }}
    .subtitle {{ font-size: 10pt; margin-top: 4px; opacity: 0.95; }}
    .tagline {{ font-size: 8.5pt; margin-top: 6px; line-height: 1.4; }}
    h2 {{
        color: {BRAND};
        font-size: 9.5pt;
        font-weight: bold;
        margin: 8px 0 4px 0;
        border-bottom: 1.5px solid {BRAND_LIGHT};
        padding-bottom: 2px;
    }}
    .cols {{ width: 100%; }}
    .col-left {{ width: 48%; float: left; padding-right: 8px; }}
    .col-right {{ width: 48%; float: right; padding-left: 8px; }}
    .clear {{ clear: both; }}
    ul {{ margin: 2px 0 4px 14px; padding: 0; }}
    li {{ margin-bottom: 1.5px; }}
    .muted {{ color: {MUTED}; font-size: 7.8pt; }}
    table.plans {{
        width: 100%;
        border-collapse: collapse;
        margin-top: 4px;
        font-size: 7.8pt;
    }}
    table.plans th {{
        background: {BRAND_LIGHT};
        color: {BRAND};
        padding: 4px 5px;
        text-align: left;
        font-weight: bold;
    }}
    table.plans td {{
        padding: 3px 5px;
        border-bottom: 1px solid #E5E7EB;
        vertical-align: top;
    }}
    .highlight {{
        background: {BRAND_LIGHT};
        border-left: 3px solid {BRAND};
        padding: 6px 8px;
        margin: 6px 0;
        border-radius: 0 4px 4px 0;
        font-size: 8pt;
    }}
    .cta {{
        background: {BRAND};
        color: #ffffff;
        text-align: center;
        padding: 10px 12px;
        border-radius: 6px;
        margin-top: 8px;
        font-size: 9pt;
        font-weight: bold;
    }}
    .cta-sub {{ font-size: 7.5pt; font-weight: normal; margin-top: 3px; opacity: 0.9; }}
    .checks {{ margin: 0; padding: 0; list-style: none; }}
    .checks li {{ margin-bottom: 2px; padding-left: 0; }}
    .badge {{ color: {BRAND}; font-weight: bold; }}
</style>

<div class="header">
    <div class="title">VetSaaS</div>
    <div class="subtitle">El software todo-en-uno para clínicas veterinarias en Perú</div>
    <div class="tagline">
        Gestiona pacientes, consultas, caja, inventario y facturacion SUNAT desde un solo lugar -
        sin Excel, sin papeles sueltos y con menos tiempo en tareas administrativas.
    </div>
</div>

<div class="cols">
    <div class="col-left">
        <h2>¿Para quién es?</h2>
        <p>Clínicas veterinarias, grooming y hotel para mascotas que quieren ordenar su operación,
        cobrar mejor, comunicarse por WhatsApp y cumplir con SUNAT.</p>

        <h2>El problema y la solucion</h2>
        <ul>
            <li><b>Excel y WhatsApp suelto</b> - todo centralizado y trazable</li>
            <li><b>Citas perdidas</b> - recordatorios automaticos (48 h y 2 h antes)</li>
            <li><b>Sin control de ventas/stock</b> - dashboard, caja e inventario en tiempo real</li>
            <li><b>Facturacion aparte</b> - emision FEL integrada en la venta (plan Clinica)</li>
        </ul>

        <h2>Módulos incluidos</h2>
        <ul>
            <li><b>Clínica:</b> propietarios, pacientes, citas, historias, vacunas, recetas, laboratorio, cirugías, hospitalización</li>
            <li><b>Operación:</b> caja, POS, ventas desde consulta/internamiento/grooming/hotel</li>
            <li><b>Inventario:</b> productos, stock, alertas, movimientos, compras</li>
            <li><b>Servicios:</b> grooming y hotel/guardería</li>
            <li><b>Comunicacion:</b> WhatsApp - citas, vacunas y cumpleanos</li>
            <li><b>Perú:</b> consulta RUC/DNI, multi-sede, roles y permisos</li>
        </ul>
    </div>

    <div class="col-right">
        <h2>Beneficios clave</h2>
        <ul class="checks">
            <li>- Menos tiempo en recepcion y menos llamadas repetitivas</li>
            <li>- Mas control del negocio en un solo panel</li>
            <li>- Mejor experiencia para el propietario de la mascota</li>
            <li>- Menos errores en caja - venta ligada a la atencion real</li>
            <li>- Escala de 1 sede a varias sucursales sin cambiar de sistema</li>
        </ul>

        <h2>Planes</h2>
        <table class="plans">
            <tr>
                <th>Plan</th>
                <th>Precio</th>
                <th>Ideal para</th>
            </tr>
            <tr>
                <td><b>Free</b></td>
                <td>S/ 0</td>
                <td>Probar el sistema</td>
            </tr>
            <tr>
                <td><b>Starter</b></td>
                <td><b>S/ 149/mes</b></td>
                <td>Clinica pequena | 1 sede | inventario</td>
            </tr>
            <tr>
                <td><b>Pro (* Popular)</b></td>
                <td><b>S/ 249/mes</b></td>
                <td>Crecimiento | lab | grooming | reportes</td>
            </tr>
            <tr>
                <td><b>Clínica</b></td>
                <td><b>S/ 399/mes</b></td>
                <td>Multi-sede | FEL SUNAT | API | soporte prioritario</td>
            </tr>
        </table>
        <p class="muted" style="margin-top:4px;">Prueba gratis: 14 dias (Starter/Pro) | 7 dias (Clinica)</p>

        <div class="highlight">
            <b>¿Por qué VetSaaS?</b><br/>
            Hecho para veterinarias peruanas | WhatsApp integrado | Clinica + caja + inventario en un solo proveedor | Datos aislados por clinica (multi-tenant seguro)
        </div>

        <h2>Cómo empezar</h2>
        <ol style="margin-left:14px;">
            <li>Solicita demo o activa prueba gratuita</li>
            <li>Configura clínica, sede y usuarios</li>
            <li>Carga propietarios y pacientes</li>
            <li>Opera: cita - consulta - venta - WhatsApp</li>
        </ol>
    </div>
    <div class="clear"></div>
</div>

<div class="cta">
    Agenda una demo gratuita de 20 minutos
    <div class="cta-sub">
        Web: vetsaas.orvae.pe | Email: notificaciones@orvae.pe<br/>
        Te mostramos: cita - consulta - venta - recordatorio WhatsApp
    </div>
</div>
<p class="muted" style="text-align:center; margin-top:5px;">
    VetSaaS | Producto Orvae | Software en la nube | Sin instalacion | Acceso desde navegador
</p>
"""


class OnePagerPDF(FPDF):
    def __init__(self) -> None:
        super().__init__(orientation="P", unit="mm", format="A4")
        self.set_auto_page_break(auto=False)
        self.set_margins(10, 10, 10)


def main() -> None:
    pdf = OnePagerPDF()
    if FONT_REGULAR.exists() and FONT_BOLD.exists():
        pdf.add_font("Arial", "", str(FONT_REGULAR))
        pdf.add_font("Arial", "B", str(FONT_BOLD))
        pdf.set_font("Arial", size=10)
    pdf.add_page()
    pdf.write_html(HTML)
    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    pdf.output(str(OUTPUT))
    print(f"PDF generado: {OUTPUT}")


if __name__ == "__main__":
    main()
