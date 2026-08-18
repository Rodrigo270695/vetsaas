# TallerSaaS — Visión de producto (Perú)

> Documento de decisión y diseño para el **siguiente SaaS** después de VetSaaS.  
> Misma lógica de negocio: **plataforma multi-tenant** (un taller = un tenant), stack y patrón operativo similares a VetSaaS.  
> Fecha de referencia: ago. 2026.

---

## 1. Veredicto (qué construir)

**Construir: SaaS de gestión para talleres mecánicos (TallerSaaS).**

No es:

| Idea | Qué era | Por qué no es la apuesta principal |
|------|---------|-------------------------------------|
| Colegios / moras / pensiones | Cobranza a padres de familia | SAM chico (~1 500 colegios pagadores), ciclo escolar, venta institucional lenta |
| “Pagos por talleres” | Cobranza genérica para talleres | No es el producto: el taller necesita **operación** (OT, citas, caja, stock), no solo cobrar |
| SPLAFT (constructoras / inmobiliarias) | Cumplimiento UIF / compliance | Ticket alto, venta larga, otro DNA (legal), no reutiliza VetSaaS |

**Sí es:**

> Sistema de taller para Perú: **órdenes de trabajo + WhatsApp + caja + FEL**, multi-sede, listo en días, sin ERP caro.  
> Arquitectura: **tenants** (schemas aislados), panel central de plataforma, planes y suscripciones — el mismo modelo mental que VetSaaS.

---

## 2. Analogía VetSaaS → TallerSaaS

| VetSaaS | TallerSaaS |
|---------|------------|
| Clínica / tenant | Taller / tenant |
| Propietario (tutor) | Cliente (dueño del vehículo) |
| Paciente (mascota) | Vehículo (placa, marca, modelo, VIN) |
| Consulta / HC | Orden de trabajo (OT) / historial del vehículo |
| Cita | Cita / recepción / bay |
| Veterinario | Mecánico / técnico |
| Inventario / vacunas | Repuestos / insumos |
| Caja + FEL | Caja + FEL |
| WhatsApp recordatorios | WhatsApp (citas, OT lista, cobro) |
| Sedes | Sedes / locales |
| Superadmin plataforma | Superadmin plataforma |

La oportunidad es **reutilizar el esqueleto multi-tenant** (provisioning, planes, login, RBAC, caja, FEL, WhatsApp, sedes) y cambiar el dominio clínico por el de taller.

---

## 3. Mercado Perú — por qué talleres

### Universo

- AAP / referencias de industria: **~78 000** talleres en el país (~**38 000** solo Lima).
- Mercado fragmentado (igual que clínicas vet): muchos informales; el SAM es el subconjunto que **paga software**.

### SAM (clientes que podrían pagar SaaS)

Talleres con operación mínima formalizable: 2+ mecánicos, agenda, necesidad de OT, caja y/o FEL.

| Capa | Estimación | Criterio |
|------|------------|----------|
| Universo bruto | ~78 000 | Talleres reportados |
| **SAM** | **~8 000–12 000** (uso **10 000**) | Formalizan / quieren digitalizar operación |
| Competencia local | SmartTaller, TallERP, Bujia, etc. | Ticket típico ~S/40–100 o ~US$25–85/mes |

### Ticket y ARR teórico

Ticket de referencia: **S/149/mes** (rango realista S/99–199; upsell S/199–249).

| Capa | Clientes | Ticket/mes | ARR |
|------|----------|------------|-----|
| SAM 100% | 10 000 | S/149 | **~S/17.9 M** |
| SOM año 3 (~5%) | 500 | S/149 | **~S/0.89 M** |
| SOM año 5 (~10%) | 1 000 | S/149 | **~S/1.79 M** |
| Upside 10% × S/199 | 1 000 | S/199 | **~S/2.39 M** |

### Comparativo con otras ideas evaluadas

| Vertical | SAM clientes | SOM ~5 años ARR* | Parecido a VetSaaS | Dificultad venta |
|----------|-------------:|------------------:|--------------------|------------------|
| Colegios (moras) | ~1 500 | ~S/0.2–0.5 M | Bajo | Media-alta |
| SPLAFT | ~6 000 | ~S/1.1–2.5 M | Bajo | Alta (B2B legal) |
| **Talleres** | **~10 000** | **~S/0.9–1.8 M** | **Alto** | Media |

\*Con tickets base usados en el análisis (colegios ~S/249, SPLAFT ~S/499, talleres ~S/149).

**Conclusión de mercado:** talleres es la vertical con mejor equilibrio **volumen × ticket × reutilización de VetSaaS**.

---

## 4. Qué producto es (SaaS, no proyecto a medida)

### Principios

1. **Multi-tenant de verdad:** cada taller es un tenant (schema propio), no “una instalación por cliente”.
2. **Suscripción mensual/anual** con planes (Starter / Pro / etc.), facturación de la plataforma central.
3. **Self-serve + onboarding guiado:** crear tenant → configurar sede → primer cliente/vehículo → primera OT → primera venta.
4. **No bloquear** por configuración incompleta: guiar (banner/checklist), no redirigir en loop (lección ya aprendida en VetSaaS con sedes).
5. **Perú-first:** WhatsApp, SUNAT/FEL, soles, distritos/ubigeo, multi-sede Lima/provincias.

### Modelo de hosts (igual patrón VetSaaS)

```
app.tallersaas.pe          → panel central (superadmin, planes, tenants)
<slug>.tallersaas.pe       → app del taller (operación diaria)
```

Datos:

- `public`: tenants, planes, suscripciones, users (identidad), catálogos globales.
- `taller_<slug>` (o convención equivalente): datos operativos del taller (clientes, vehículos, OT, caja, inventario…).

---

## 5. Arquitectura multi-tenant (misma lógica que VetSaaS)

```
┌──────────────────────────────────────────────────────────┐
│ Identidad (compartida en public.users)                   │
│  superadmin          tenant_id=NULL                      │
│  admin@tallerX       tenant_id=A                         │
│  mecanico@tallerX    tenant_id=A                         │
└──────────────────────────────────────────────────────────┘
              │
              │ host / slug determina contexto
              ▼
┌──────────────────────────────────────────────────────────┐
│ Datos OPERATIVOS (aislados por schema tenant)            │
│  schema=public:           tenants, plans, subscriptions  │
│  schema=taller_x:         cfg, clientes, vehículos, OT…  │
│  schema=taller_y:         cfg, clientes, vehículos, OT…  │
└──────────────────────────────────────────────────────────┘
```

### Capas a reutilizar / clonar desde VetSaaS

| Capa | Qué traer |
|------|-----------|
| Provisioning | Crear tenant, schema, admin inicial, plan |
| Auth | Single-login, roles Spatie, cambio obligatorio de password |
| Plataforma | CRUD tenants, planes, suscripciones, cobros |
| Operación base | Sedes, usuarios del tenant, configuración general |
| Monetización clínica→taller | Caja, medios de pago, FEL (adaptar emisor) |
| Comunicación | WhatsApp outbound (citas, OT lista, cobro) |
| Frontend | Inertia + React + mismo design system (rebrand) |

### Capas nuevas (dominio taller)

| Módulo | Descripción |
|--------|-------------|
| Clientes | RUC/DNI, teléfono, WhatsApp, historial |
| Vehículos | Placa, marca, modelo, año, VIN, kilometraje, cliente dueño |
| Órdenes de trabajo (OT) | Recepción → diagnóstico → reparación → entrega |
| Bays / puestos | Cupos de taller (opcional MVP+) |
| Catálogo servicios | Mano de obra, paquetes (afinamiento, frenos…) |
| Repuestos | Inventario, descuento al usar en OT |
| Presupuestos | Aprobación del cliente (WhatsApp / link) |

---

## 6. Flujo core (equivalente a “consulta → cobro”)

```
Cliente + vehículo
    → Cita / recepción
    → Abrir OT
    → Diagnóstico + presupuesto
    → Aprobación cliente (WA)
    → Trabajos + repuestos
    → Cerrar OT
    → Cobro en caja (± FEL)
    → Entrega + encuesta / recordatorio
```

Ese flujo es el norte del MVP: si no cierra en caja, no es producto.

---

## 7. MVP sugerido (8–12 semanas de producto usable)

### Must-have (P0)

- [ ] Tenants + planes + login + roles (admin, recepcionista, mecánico)
- [ ] Sedes (guiar, no bloquear)
- [ ] Clientes + vehículos
- [ ] OT (estados: abierta / en proceso / lista / entregada / anulada)
- [ ] Catálogo servicios + ítems en OT
- [ ] Caja (pago total; parciales si el tiempo alcanza)
- [ ] WhatsApp: aviso “OT lista” + recordatorio de cita
- [ ] Onboarding checklist 7 días

### Should-have (P1)

- [ ] Inventario de repuestos ligado a OT
- [ ] Presupuesto con aprobación por link/WA
- [ ] FEL / boleta-factura
- [ ] Multi-sede
- [ ] Reportes: OT/mes, ticket promedio, mecánicos

### Later (P2)

- [ ] App / portal del cliente (historial por placa)
- [ ] Comisiones a mecánicos
- [ ] Integración aseguradoras
- [ ] Offline / tablet en piso

---

## 8. Pricing orientativo (plataforma)

| Plan | Precio/mes | Incluye (idea) |
|------|------------|----------------|
| Starter | S/99–129 | 1 sede, N usuarios básicos, OT + caja + WA básico |
| Pro | S/149–199 | Multi-sede, inventario, presupuestos, reportes |
| Business | S/249+ | FEL, más usuarios, soporte prioritario |

Regla: **CAC ≤ 1–1.5 meses** de plan; demo → pago medible (mismo embudo que VetSaaS).

---

## 9. Go-to-market (Perú)

1. **Lima primero** (~mitad del universo de talleres).
2. Público: dueños de taller / jefes de taller (no “conductores”).
3. Mensaje: “órdenes + WhatsApp + caja, sin Excel”.
4. Canal: Meta + visitas con cita + referidos (1 mes gratis o descuento).
5. Competir por **velocidad de onboarding** y WhatsApp, no por brochure ERP.

---

## 10. Riesgos y mitigación

| Riesgo | Mitigación |
|--------|------------|
| Competencia ya existe | Diferenciar con FEL + WA + onboarding rápido; foco Perú |
| Informalidad (no pagan software) | Filtrar SAM en ventas; no perseguir micro-informal |
| Scope creep (ERP total) | MVP = OT → cobro; el resto en fases |
| Diluir foco VetSaaS | Repo / producto separado; reutilizar código, no mezclar tenants vet+taller |
| Ciclo de caja del fundador | Mantener VetSaaS generando caja mientras se construye MVP taller |

---

## 11. Decisión explícita

| Pregunta | Respuesta |
|----------|-----------|
| ¿Colegios / pagos de pensiones? | No como producto principal |
| ¿“Pagos SaaS” genérico para talleres? | No |
| ¿SPLAFT? | No ahora (posible vertical futuro distinto) |
| ¿Qué sí? | **SaaS multi-tenant de talleres mecánicos** |
| ¿Cómo? | **Misma lógica de tenants que VetSaaS** |

---

## 12. Nombre de trabajo

- **Nombre interno:** TallerSaaS  
- Dominio / marca comercial: por definir (no bloquea el diseño técnico).

Cuando se abra el repo nuevo, este documento es el norte de producto; el detalle de tablas y migraciones tenant se documentará aparte (equivalente a `plataforma.md` / migraciones de VetSaaS).
