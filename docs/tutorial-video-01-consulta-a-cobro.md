# Tutorial redes — Video 01 (escalado)

## De la cita al cobro — click a click

Flujo completo en VetSaaS:

**Cita → Aperturar HC → Consulta → Precuenta → Confirmar → Cobrar en caja**

Duración grabación completa: ~2,5–4 min (luego recortas a 60–90 s para TikTok).

---

## Antes de grabar (checklist)

| # | Qué | Dónde |
|---|-----|--------|
| 1 | Usuario con permisos de citas, HC, cargos y ventas | Rol admin / recepción+caja |
| 2 | Paciente + propietario de prueba (nombres inventados) | Clínica › Pacientes / Propietarios |
| 3 | Sesión de caja **abierta** | Caja › Sesiones › Abrir |
| 4 | Zoom navegador ~110 %, tema claro, sin notificaciones | — |
| 5 | Datos demo: paciente `Max`, dueño `Ana Pérez`, motivo `Control` | — |

---

## Orden maestro de clicks (copia esto al grabar)

### BLOQUE 1 — Crear la cita

1. Menú izquierdo → **Clínica**
2. Click **Citas**
3. Click **Nueva cita** (o el botón de crear / “+” del calendario)
4. En el formulario:
   - **Paciente:** elige `Max`
   - **Fecha / hora:** hoy (o la hora que se vea clara)
   - **Motivo:** `Control general`
   - **Estado:** `Programada` (o `Confirmada`)
5. Click **Guardar** / **Crear**
6. Verifica que la cita aparezca en el calendario o listado

> **Pantalla que debe verse:** cita creada con paciente y horario.

---

### BLOQUE 2 — Abrir la atención (cita → historia clínica)

7. En **Citas**, click sobre la cita de `Max` (abre el detalle)
8. En el modal de detalle, click **Aperturar cita**
9. Se abre el diálogo de historia clínica:
   - Si ofrece **Nueva consulta** → selecciónala
   - Click **Crear y abrir** (o **Sí, abrir HC**)
10. Entras a la **consulta / historia clínica** del paciente

> **Pantalla que debe verse:** ficha de consulta abierta (SOAP / atención).

---

### BLOQUE 3 — (Opcional pero útil en video) Anotar algo clínico

11. Escribe algo corto en motivo o notas, ej. `Control de rutina`
12. Click **Guardar** (si aplica en esa pantalla)

> Si quieres video más corto, salta este bloque y ve directo a cargos.

---

### BLOQUE 4 — Armar la precuenta

13. En la consulta (o en la fila de acciones de la atención), click **Cargos / pre-cuenta**
14. En la pantalla de cargos:
    - Click **Agregar** línea / servicio (o busca un servicio)
    - Ejemplo línea 1: concepto `Consulta general`, precio `40.00`
    - (Opcional) línea 2: un producto o antiparasitario
15. Revisa el **total** a la derecha
16. Click **Guardar** si el UI lo pide aparte
17. Click **Confirmar pre-cuenta**
18. Debe aparecer aviso tipo **Listo para cobrar en caja**

> **Pantalla que debe verse:** precuenta en estado confirmado + total visible.

---

### BLOQUE 5 — Cobrar (elige UNA ruta para el video)

#### Ruta A — Desde la precuenta (más corta, recomendada para TikTok)

19. En la misma pantalla de cargos, click **Cobrar en caja**
20. Llegas a **Nueva venta** ya precargada (paciente + líneas + total)
21. En panel **Cobro** (derecha):
    - Método: click **Efectivo**
    - **Monto recibido:** escribe el total (o el que calce)
22. Click **Registrar venta**
23. Pausa 2 s en la venta / ticket / éxito

#### Ruta B — Desde Caja › Cobrar precuentas (muestra la función nueva)

19. Menú → **Caja**
20. Click **Ventas**
21. Click **Nueva** / **Nueva venta**
22. Click botón verde **Cobrar precuentas**
23. En el modal, busca la fila con badge **Historia clínica** + paciente `Max`
24. Click en esa fila (**Cargar al cobro**)
25. Método: **Efectivo** → monto → **Registrar venta**
26. Pausa 2 s en el resultado

> Para TikTok: usa **Ruta A** en el corte corto y **Ruta B** en un video 01b “función nueva”.

---

## Mapa visual del recorrido

```
Clínica › Citas
    → Nueva cita → Guardar
    → Abrir cita → Aperturar cita → Crear y abrir
        → Consulta abierta
            → Cargos / pre-cuenta
                → Agregar líneas → Confirmar pre-cuenta
                    → Cobrar en caja  ──┐
                                       ↓
                              Nueva venta (precargada)
                                       ↓
                         Efectivo → Registrar venta → Listo
```

Alternativa final:

```
… Confirmar pre-cuenta
    → Caja › Ventas › Nueva
        → Cobrar precuentas
            → Historia clínica (Max)
                → Registrar venta
```

---

## Guion voz IA (versión completa ~90 s)

> Una cita en VetSaaS no se queda en el calendario.  
> Creas la cita del paciente.  
> La aperturas y entras a la historia clínica.  
> Armas la precuenta: consulta y lo que usaste.  
> Confirmas.  
> Y cobras en caja sin volver a tipear nada.  
> Paciente, conceptos y total… ya están.  
> Confirmas el pago… y listo.  
> De la cita al cobro, en un solo flujo.  
> VetSaaS.

### Subtítulos cortos (en orden)

1. `Citas → Nueva cita`
2. `Aperturar cita → Historia clínica`
3. `Cargos / pre-cuenta`
4. `Confirmar precuenta`
5. `Cobrar en caja`
6. `Sin volver a tipear`
7. `VetSaaS`

---

## Guion voz IA (corte TikTok 60 s)

Usa solo: Bloque 2 (aperturar) + Bloque 4 (precuenta) + Ruta A (cobrar).

> ¿Cita, atención y cobro… en sistemas distintos?  
> En VetSaaS: aperturas la cita, armas la precuenta, confirmas…  
> y cobras en caja con un clic.  
> Sin volver a tipear.  
> VetSaaS — clínicas veterinarias.

---

## Storyboard por tiempo (grabación completa)

| Tiempo | Bloque | Qué se ve | Click principal |
|--------|--------|-----------|-----------------|
| 0:00–0:20 | 1 | Citas | Nueva cita → Guardar |
| 0:20–0:45 | 2 | Detalle cita | Aperturar cita → Crear y abrir |
| 0:45–0:55 | 3 | Consulta | (opcional) Guardar nota |
| 0:55–1:40 | 4 | Cargos | Agregar líneas → Confirmar pre-cuenta |
| 1:40–2:20 | 5A | POS | Cobrar en caja → Efectivo → Registrar venta |
| 2:20–2:30 | Cierre | Éxito / ticket | End card VetSaaS |

---

## Errores típicos al grabar (evítalos)

| Problema | Solución |
|----------|----------|
| No aparece **Cobrar en caja** | Falta confirmar precuenta o no hay sesión de caja abierta |
| Modal de precuentas vacío | La precuenta no está confirmada o ya tiene venta |
| No sale **Aperturar cita** | La cita está cancelada/completada; usa programada/confirmada |
| Carrito vacío en POS | No entraste por cobro vinculado; vuelve a **Cobrar en caja** |
| Datos reales de cliente | Usa siempre paciente demo |

---

## Textos para publicar

**Título:** `De la cita al cobro en VetSaaS (sin volver a tipear)`

**Caption:**
```
Cita → Historia clínica → Precuenta → Caja 💸
Todo en un solo flujo. VetSaaS.

#veterinaria #softwareveterinario #clinica #peru #vetsaas
```

**Gancho feed:** `La cita no se queda en el calendario.`

---

## Serie (orden sugerido)

| # | Video | Flujo |
|---|--------|--------|
| **01** | Este | Cita → HC → Precuenta → Cobro |
| 02 | Grooming | Turno → Cargos → Cobrar precuentas |
| 03 | Hotel | Estancia → Cargos → Cobro |
| 04 | FEL | Venta → Boleta/Factura |
| 05 | WhatsApp | Aviso al tutor tras el servicio |

Cuando termines el 01, el 02 reutiliza casi el mismo final (modal **Cobrar precuentas**, badge **Grooming**).
