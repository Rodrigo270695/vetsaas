# MVP — PetPass ID

**Producto:** red de identidad animal (microchip + pasaporte digital).  
**Stack:** Laravel + React (Inertia), proyecto **aparte** de VetSaaS.  
**Puente:** desde VetSaaS → “¿Registrar en PetPass ID?” → handoff automático.

| Documento | Fecha | Estado |
|-----------|-------|--------|
| PetPass ID — MVP | 2026-07-17 | Actualizado (marca + lost + competitivos) |

**Tagline sugerido:** *El pasaporte de tu mascota. Identidad, reencuentro y viaje.*

---

## 1. Visión

> VetSaaS opera la clínica. **PetPass ID** es la red de identidad.  
> La clínica implanta y cobra el chip; PetPass ID registra, certifica, declara **perdido/encontrado** y hace buscable el animal a nivel nacional / Latam.

No es GPS. El microchip ISO **identifica**; PetPass ID **conecta** dueño, clínica y quien encuentra.

---

## 2. Marca y posicionamiento

| | |
|--|--|
| **Nombre** | **PetPass ID** |
| **Inspira** | Pasaporte + identidad (viaje, DNI animal, certificado) |
| **Tono de página** | Institucional-claro, confiable, humano (reencuentro), no “crypto” ni jerga Web3 |
| **Dominios ideales** | `petpassid.com` / `petpass.id` / `petpass.pe` (validar disponibilidad) |
| **Repo / carpeta** | `petpass-id` |

### Cómo nos distinguimos (mensaje clave)

| Ellos | PetPass ID |
|-------|------------|
| W.A.R. / blockchain / criptomoneda (FIRU/CUSD) | **Pago simple** (Yape, tarjeta, efectivo vía clínica). Cero fricción crypto. |
| Registro “global” abstracto | **Red operativa** conectada a clínicas reales (VetSaaS) en un clic |
| Carnet estático | **Estados vivos**: activo, **perdido**, encontrado, transferido |
| Dueño pelea solo el registro | Clínica **empuja** el alta desde la ficha del paciente |
| Enfoque ONG / campaña | Producto SaaS + red: clínica gana, dueño recupera, municipio después |

---

## 3. Mapa competitivo (referencia)

### W.A.R. — World Animal Registry ([worldanimalregistry.org](https://worldanimalregistry.org/es))

- Registro internacional / “tenencia responsable”.
- Énfasis en **blockchain** y token (FIRU / CUSD).
- Entidades registradoras (vets, albergues, municipios).
- Certificado / carné / identificador.
- Relacionado en Perú con iniciativas tipo **RENIAN** (registro nacional + microchip).

**Gap para adelantarnos:** crypto no ayuda al dueño promedio ni a la recepción de una vet. Nosotros: WhatsApp, handoff desde VetSaaS, **perdido/encontrado** accionable, UX clínica.

### RENIAN / PETid (Perú)

- Microchip ISO + registro formal + carnet.
- Precio de campaña típico ~S/ 50 (chip + registro).
- Dejan claro: **el chip no es GPS**.

**Gap:** muchas vets implantan y **no registran**. PetPass ID cierra ese hueco con un clic desde VetSaaS.

### Animal ID / Pet-ID ( Latam / Ecuador y similares )

- Kit: microchip + placa/QR + cédula + perfil digital + certificado.
- Red de clínicas afiliadas.
- Registro web + búsqueda / recuperación.

**Gap:** PetPass ID nace **dentro del SaaS clínico** (agenda, caja, WhatsApp, historial). Ellos venden kit; nosotros vendemos **sistema + red + operación diaria**.

### Aprendizajes a copiar (sin copiar marca)

- Carnet / certificado con **QR**.
- Perfil digital público acotado.
- Placa/collar con código corto (fase plus).
- Red de entidades registradoras.
- Flujo de tenencia responsable (transferencia, fallecimiento).

---

## 4. Principios de diseño

1. Fuente de verdad del registro = **PetPass ID** (BD central).
2. VetSaaS solo guarda: chip + estado red + `petpass_id` + URL certificado / perdido.
3. Un microchip ISO = un registro activo.
4. Búsqueda pública **no** expone DNI/dirección completa.
5. **Declarar perdido** es feature de primera clase (no un afterthought).
6. Sin blockchain en el MVP (ni como promesa de marketing).
7. Pago en moneda local; la clínica puede incluir el fee en el pack.

---

## 5. Alcance

### MVP (sí)

- Registro desde VetSaaS (handoff) + alta manual.
- Pago fee registro (manual + 1 pasarela después).
- Certificado PDF + QR + WhatsApp.
- Búsqueda pública por chip / código PetPass.
- **Declarar perdido** / **marcar encontrado** / **reportar hallazgo**.
- Alertas WhatsApp/email al dueño y clínica.
- Transferencia de titularidad (OTP).
- Panel clínica (registros, perdidos activos).
- API + webhooks VetSaaS ↔ PetPass ID.

### Plus (fase 1.5 — adelantarnos)

- Muro / mapa de **mascotas perdidas** (ciudad, foto, fecha; sin datos sensibles).
- QR de collar + página pública `/p/{code}`.
- “Pack viaje”: export PDF pasaporte (vacunas snapshot vía VetSaaS API opcional).
- Multi-país (`country_code`) desde día 1 en datos.
- App PWA “encontré una mascota” (móvil).
- Directorio de clínicas PetPass.
- Import CSV de chips ya implantados (migración desde Excel/RENIAN parcial).

### Después (fase 2+)

- Municipios / B2G.
- Federación con otros registros (si hay API).
- GPS **aparte** (collar IoT; no es el microchip).
- Marketplace de chips físicos.
- App nativa.

---

## 6. Arquitectura

```
┌─────────────────────┐     handoff token      ┌────────────────────────────┐
│      VetSaaS        │ ─────────────────────► │       PetPass ID           │
│  (multi-tenant)     │◄── webhooks:           │  (Laravel + React, BD      │
│                     │    registered / lost / │   central, un solo país o  │
│ pacientes.microchip │    found / paid        │   Latam)                   │
└─────────────────────┘                        └────────────────────────────┘
         │                                                │
         ▼                                                ▼
   Caja: chip + implante                         Público: buscar / perdido
```

Env sugerido:

```env
# VetSaaS
PETPASS_ENABLED=true
PETPASS_BASE_URL=https://petpassid.com
PETPASS_HANDOFF_SECRET=...
PETPASS_WEBHOOK_SECRET=...
```

---

## 7. Flujos

### 7.1 Registrar desde VetSaaS

1. Paciente con `microchip` (15 dígitos ISO preferido).
2. Botón **Registrar en PetPass ID**.
3. VetSaaS emite token one-time (TTL 15–30 min) con clínica, paciente, dueño, chip.
4. Redirect `https://petpassid.com/handoff?token=...`
5. Confirmación + términos → `pending_payment` o `active`.
6. Pago → certificado + WhatsApp.
7. Webhook `petpass.registered` → VetSaaS actualiza estado.

### 7.2 Declarar **perdido** (plus crítico)

**Quién puede:** dueño verificado o clínica registradora (staff).

1. En PetPass ID (o deep link desde VetSaaS): **Declarar perdido**.
2. Formulario:
   - fecha/hora aproximada de pérdida
   - zona / distrito / ciudad (texto + opcional lat/lng aproximado)
   - foto reciente (opcional)
   - notas públicas (collar, señas)
   - contacto preferido (tel/WhatsApp; visible solo tras intermediación o parcialmente)
3. Estado del registro: `lost`.
4. Aparece en:
   - búsqueda por chip → banner rojo **PERDIDO**
   - listado público `/perdidos` (filtros ciudad/especie)
5. Webhook `petpass.lost` → VetSaaS badge en ficha paciente.
6. Notificación a clínicas de la zona (fase plus: push a tenants cercanos).

**Al encontrar / recuperar:**

- Dueño o clínica: **Marcar como encontrada / recuperada**.
- Estado vuelve a `active`.
- Evento `recovered` + cierra reportes abiertos.
- Webhook `petpass.recovered`.

### 7.3 Reportar hallazgo (público)

1. Alguien escanea/lee chip o busca en PetPass.
2. Ve perfil: si `lost` → CTA fuerte **“La encontré”**.
3. Formulario hallazgo (nombre, tel, zona, mensaje) + captcha + rate limit.
4. PetPass notifica dueño + clínica **sin publicar el teléfono del dueño** al reportero (MVP: mensaje intermediado; plus: chat/relay).
5. Tabla `found_reports` + `audit_events`.

### 7.4 Búsqueda pública

- Input: nº microchip o código certificado / QR.
- Resultado:
  - Activo: nombre, especie, foto, clínica, ciudad, país.
  - Perdido: lo mismo + alerta + “Reportar hallazgo”.
  - No encontrado: mensaje educativo (“implante sin registro no sirve”).

### 7.5 Transferencia / fallecimiento

- Transferencia OTP dueño actual → nuevo dueño (historial append-only).
- Baja por fallecimiento (`deceased`) — deja de salir en perdidos.

### 7.6 Solo chip local en VetSaaS

- Badge **Local** vs **PetPass** vs **Perdido**.
- CTA hasta registrar.

---

## 8. Base de datos — PetPass ID (central)

PostgreSQL. UUIDs. Timestamps TZ.

### 8.1 `organizations`

| Columna | Tipo | Notas |
|---------|------|--------|
| id | uuid PK | |
| type | enum | `clinic`, `municipality`, `shelter`, `partner`, `platform` |
| name | varchar(200) | |
| country_code | char(2) | |
| region / city | varchar nullable | |
| vetsaas_tenant_id | uuid nullable | |
| vetsaas_slug | varchar(80) nullable | |
| contact_email / contact_phone | nullable | |
| active | bool | |
| created_at / updated_at | timestamptz | |

### 8.2 `users` + Spatie Permission

Autenticación con Laravel Fortify. **Roles y permisos viven en Spatie** (`spatie/laravel-permission`), no en una columna `role` enum.

| Rol Spatie | Quién |
|------------|--------|
| `owner` | Dueño / tutor (asignado al registrarse) |
| `clinic_staff` | Personal de clínica afiliada |
| `org_admin` | Admin de organización / clínica |
| `platform_admin` | Admin de la red AlmaPet ID (bypass de permisos) |

Permisos gruesos (módulo.acción): `dashboard.*`, `animals.*`, `registrations.*`, `lost.*`, `found.*`, `organizations.*`, `platform.*`, `search.*`.

Tabla `users` (starter): id, name, email, password, 2FA, timestamps. Más adelante: `organization_id`, teléfono, etc.

Seeders: `PermissionsSeeder` → `RolesSeeder` → `PlatformAdminSeeder`.

| Columna (futuro) | Tipo | Notas |
|---------|------|--------|
| organization_id | uuid nullable FK | Clínica / org del staff |

### 8.3 `animals`

| Columna | Tipo |
|---------|------|
| id | uuid PK |
| name | varchar(120) |
| species | varchar(40) |
| breed / sex / color | nullable |
| birth_date | date nullable |
| photo_path | varchar nullable |
| notes_public | text nullable |
| distinctive_marks | text nullable |

### 8.4 `chip_registrations` (canónica)

| Columna | Tipo | Notas |
|---------|------|--------|
| id | uuid PK | |
| microchip | varchar(20) **unique** | solo dígitos |
| public_code | varchar(16) **unique** | corto para QR/collar |
| animal_id | uuid FK | |
| status | enum | ver §8.4.1 |
| registered_at / expires_at | timestamptz | |
| organization_id | uuid FK | |
| registered_by_user_id | uuid nullable | |
| vetsaas_tenant_id / vetsaas_paciente_id | uuid nullable | |
| country_code | char(2) | |
| implant_date / implant_site | nullable | |
| certificate_code | varchar(40) unique | |
| created_at / updated_at | timestamptz | |

#### 8.4.1 Estados `status`

`draft` · `pending_payment` · `active` · **`lost`** · `suspended` · `transferred` · `deceased` · `void`

### 8.5 `lost_reports` (**nuevo — declarar perdido**)

| Columna | Tipo | Notas |
|---------|------|--------|
| id | uuid PK | |
| registration_id | uuid FK | |
| status | enum | `open`, `recovered`, `cancelled` |
| lost_at | timestamptz | |
| last_seen_zone | varchar(200) | |
| last_seen_city | varchar(120) | |
| last_seen_lat / lng | decimal nullable | aproximado |
| public_notes | text nullable | |
| photo_path | varchar nullable | |
| declared_by_user_id | uuid nullable | |
| recovered_at | timestamptz nullable | |
| created_at / updated_at | timestamptz | |

Índice: `(status, last_seen_city)`, `registration_id`.

### 8.6 `owners` + `chip_ownerships`

Igual que antes: dueño actual + historial de titularidad (`is_current`).

### 8.7 `registration_payments`

`amount`, `currency` (PEN/USD), `status` (`pending|paid|failed|refunded`), `provider` (**culqi / niubiz / stripe / manual** — **no crypto**).

### 8.8 `found_reports`

| Columna | Tipo | Notas |
|---------|------|--------|
| id | uuid PK | |
| registration_id | uuid FK | |
| lost_report_id | uuid nullable FK | si había alerta lost |
| reporter_name | varchar | |
| reporter_phone / email | nullable | |
| message | text | |
| city / zone | nullable | |
| notified_owner_at | timestamptz nullable | |
| created_at | timestamptz | |

### 8.9 `handoff_tokens`

token_hash, payload_json, expires_at, used_at.

### 8.10 `audit_events`

Eventos: `created`, `paid`, `activated`, `lost_declared`, `found_reported`, `recovered`, `transfer_*`, `search_hit`, `webhook_sent`.

---

## 9. Cambios en VetSaaS (tenant)

`pacientes` ya tiene `microchip`. Agregar:

| Columna | Tipo |
|---------|------|
| petpass_status | varchar(32) nullable | `null`, `pending`, `registered`, `lost`, `error` |
| petpass_registration_id | uuid nullable |
| petpass_public_code | varchar(16) nullable |
| petpass_certificate_url | varchar(500) nullable |
| petpass_registered_at | timestamptz nullable |
| petpass_lost_at | timestamptz nullable |

**UI:** badge Local / PetPass / **Perdido** + botones Registrar / Ver certificado / Declarar perdido (abre PetPass).

**Permisos:** `petpass.view`, `petpass.register`, `petpass.lost` (opcional).

---

## 10. API / webhooks

### Públicos PetPass ID

| Método | Ruta | Uso |
|--------|------|-----|
| GET | `/` | Landing + buscar |
| GET | `/buscar` | Resultado chip/código |
| GET | `/perdidos` | Listado perdidos |
| GET | `/p/{public_code}` | Perfil QR collar |
| POST | `/hallazgos` | Reportar encontrado |
| GET | `/certificado/{code}` | Certificado |

### Clínicas / handoff

| Método | Ruta |
|--------|------|
| GET | `/handoff?token=` |
| POST | `/api/v1/registrations` |
| POST | `/api/v1/registrations/{id}/lost` |
| POST | `/api/v1/registrations/{id}/recover` |
| POST | `/api/v1/registrations/{id}/pay` |

### Webhooks → VetSaaS

`petpass.registered` · `petpass.lost` · `petpass.recovered` · `petpass.paid`

Firmados HMAC (`X-PetPass-Signature`).

---

## 11. Plus de producto (checklist de diferenciación)

| Plus | Por qué gana vs W.A.R. / Animal ID |
|------|-------------------------------------|
| Handoff 1 clic desde VetSaaS | Ellos no viven dentro del HIS/SaaS clínico |
| Estado **PERDIDO** visible + muro | Registro vivo, no solo carnet PDF |
| WhatsApp nativo (dueño + clínica) | Canal real en Perú/Latam |
| Pago Yape/tarjeta/efectivo clínica | Sin wallet crypto |
| QR collar → perfil | Como Pet-ID, pero ligado a lost/found |
| Badge en ficha paciente VetSaaS | Operación diaria, no portal aparte olvidado |
| Certificado tipo pasaporte | Marca PetPass = viaje + identidad |
| Multi-país en datos | Listo para Latam sin reescritura |
| Intermediación de contacto | Privacidad > dump de teléfono |
| Import de chips ya puestos | Convierte “chip muerto” en registro útil |

**Explícito: no usamos criptomoneda ni blockchain en el MVP.** Si algún día hay ancla de integridad, será transparente y opcional — nunca requisito de pago ni de registro.

---

## 12. Modelo de ingreso

| Concepto | Quién paga | Quién cobra |
|----------|------------|-------------|
| Chip físico + implantación | Dueño | Clínica (VetSaaS caja) |
| Fee registro PetPass ID | Dueño (o pack clínica) | PetPass ID |
| Búsqueda / muro perdidos | Gratis | Adquisición |
| Certificado / QR | Incluido en fee | PetPass ID |
| Plus viaje / municipio | Después | PetPass ID |

Piloto Perú: fee configurable (ej. S/ 15–40) aparte del chip físico.

---

## 13. Validación microchip

- Solo dígitos; preferir **15** (ISO 11784/11785).
- Rechazar duplicado `active` / `lost` / `pending_payment`.
- Educar en UI: *“El microchip no es GPS; PetPass ID es la red que lo hace útil.”*

---

## 14. Privacidad y seguridad

- Rate limit + captcha en búsqueda y hallazgos.
- Perdidos: zona amplia, no dirección exacta del hogar.
- Handoff one-time; webhooks firmados.
- Consentimiento al publicar en red y en muro de perdidos.

---

## 15. Sprints

| Sprint | Entrega |
|--------|---------|
| **A** | Skeleton PetPass ID + tablas + búsqueda + alta manual |
| **B** | Bridge VetSaaS (handoff + webhook + badges) |
| **C** | **Lost / found / recover** + WhatsApp + muro `/perdidos` |
| **D** | Certificado QR + pago (manual → pasarela) |
| **E** | Plus: QR collar, import CSV, PWA hallazgo |

---

## 16. Criterios de éxito MVP

- Clínica piloto registra desde VetSaaS en ≤ 3 clics.
- Dueño declara perdido y aparece en `/perdidos` + búsqueda.
- Hallazgo notifica al dueño en minutos.
- 0 datos sensibles en pantalla pública.
- Cero dependencia de crypto para usar el producto.

---

## 17. Checklist de arranque

- [ ] Repo `petpass-id` (Laravel + React/Inertia)
- [ ] Dominio + SSL
- [ ] BD central + migraciones §8
- [ ] Feature flag `PETPASS_ENABLED` en VetSaaS
- [ ] Secrets handoff/webhook
- [ ] Landing: buscar + perdido + “soy clínica”
- [ ] Términos + privacidad
- [ ] Clínica piloto + precio fee
- [ ] Marca visual pasaporte (color, sello, QR)

---

## 18. Relación con VetSaaS hoy

| Existente | Uso |
|-----------|-----|
| `pacientes.microchip` | Semilla del registro |
| Caja / productos | Cobrar chip + implante |
| WhatsApp clínica / plataforma | Certificado y alertas perdido |
| Multi-tenant | Envía `tenant_id` + `paciente_id` |

---

## 19. Nombre comercial (cerrado)

| Campo | Valor |
|-------|--------|
| Producto | **PetPass ID** |
| Código interno | `petpass` |
| No usar en docs | ChipNet (obsoleto en este doc) |

---

*Siguiente paso de build: Sprint A (skeleton PetPass ID) o bridge mínimo en VetSaaS según prioridad comercial.*
