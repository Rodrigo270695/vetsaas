# VetSaaS — Estructura de base de datos y orden de migraciones (Laravel)

**PostgreSQL 16 · Multi-tenant por schema · UUID v4**  
Este documento es **solo composición de datos**: tablas en el **orden en que deben ejecutarse las migraciones** (de arriba hacia abajo), con **tipos**, **índices** y **comentarios de propósito**.  
La especificación completa (negocio, triggers, particionamiento) sigue en `vetsaas_db_completo.md`.

---

## Orvae PE — Fuente de verdad comercial (no en VetSaaS)

El **checkout, cobro (p. ej. Culqi) y la suscripción comercial** viven en la web **Orvae PE** (`D:\Programacion\Laravel\LaraReact\orvaepe`), igual que hoy con **Aula Virtual**: allí se registran **pedidos**, **suscripciones** del catálogo y, tras el pago, un **provisioner** llama por HTTP al producto SaaS para crear el entorno del cliente.

| Responsabilidad | Dónde |
|-----------------|--------|
| Catálogo, carrito, pago, facturación del **cliente hacia ORVAE** | **Orvae PE** |
| Crear **tenant** (fila `public.tenants`), schema PostgreSQL, usuario admin, semillas tenant | **VetSaaS** (endpoint interno protegido) |
| Límites de plan / estado “¿puede operar?” en la app veterinaria | VetSaaS (`public.subscriptions` + `plan_features` como **espejo operativo** o sincronizado desde Orvae) |
| Email con **enlace de ingreso** al subdominio del cliente | Orvae PE (notificación) y/o VetSaaS (tras provision OK) — definir un solo emisor para evitar duplicados |

**Patrón técnico ya probado en Orvae** (ver `app/Services/Checkout/AulaVirtualPlanProvisioner.php`): `POST` a `config('services.*.provision_url')` con cuerpo JSON firmado (`X-Orvae-Timestamp`, `X-Orvae-Signature` HMAC-SHA256) e `X-Idempotency-Key` por pedido. Para VetSaaS se replica el mismo contrato (p. ej. `VETSAAS_PROVISION_URL` + `VETSAAS_PROVISION_HMAC_SECRET` en Orvae).

**Payload conceptual** (alineado al de Aula Virtual): identificador de pedido Orvae, datos del comprador (`external_user_id`, email, nombre, documento), `tenant.name` / `tenant.slug`, `subscription.plan_slug` (mapeado al SKU de Orvae → `public.plans.codigo`), importe y moneda del pago. VetSaaS responde con `login_url` o `tenant_url` para que Orvae lo incluya en el correo al cliente.

**Implicación en este documento:** las migraciones **`007`–`009`** (`tenants`, `subscriptions`, `subscription_payments`) siguen siendo necesarias en la BD de VetSaaS para **multi-tenant y límites**, pero **no** implican que el usuario pague dentro de VetSaaS; el alta nace del **provision** desde Orvae PE.

---

## Cómo usar esto con Laravel

| Ubicación | Contenido | Cuándo se ejecuta |
|------------|------------|-------------------|
| `database/migrations/` (archivos `2026_05_12_070xxx_*.php`) | Tablas del schema **`public`** (SaaS global) | `php artisan migrate` en cada deploy (Laravel solo carga migraciones en esta raíz por defecto) |
| `database/migrations/tenant/` | Tablas del schema **por clínica** (`vet_xxxxxx`) | `TENANT_MIGRATION_SCHEMA=vet_xxx php artisan migrate --path=database/migrations/tenant` (ver `database/migrations/tenant/README.md`) |

**Regla:** el nombre del archivo de migración debe ordenarse alfabéticamente en el orden deseado (`001_…`, `002_…` y `t001_…`, `t010_…`, etc.). Laravel ejecuta las migraciones **en orden lexicográfico del path**; un FK a una tabla que aún no existe falla.

**Dependencias circulares** (resueltas con migraciones `alter` posteriores):

- `vet_appointments.historia_clinica_id` → `vet_clinical_records` (t030 sin FK; t041 añade FK).
- `vet_clinical_records.venta_id` → `vet_sales` (t040 sin FK o nullable; t065 añade FK).
- `vet_appointments.grooming_id` → `vet_grooming_services` (t082 tras paquetes y servicios).

---

## Convenciones de tipos (resumen)

| Uso | Tipo habitual | Notas |
|-----|---------------|--------|
| ID de negocio expuesto al API | `UUID` + `gen_random_uuid()` | No BIGINT en API |
| Logs / alto volumen interno | `BIGSERIAL` | `audit_logs`, `stock_movements`, etc. |
| Dinero | `DECIMAL(10,2)` o `(10,6)` en líneas FEL | Nunca `FLOAT` |
| Fecha-hora | `TIMESTAMPTZ` | Siempre con zona |
| Solo día | `DATE` | Nacimientos, vencimientos |
| Flexible estructurado | `JSONB` | Protocolos, ítems, payloads |
| Soft delete | `deleted_at TIMESTAMPTZ NULL` | Tablas de negocio clínico |
| Enums de negocio | `VARCHAR` + `CHECK (...)` | Validación en BD |

### Índices (cómo leerlos)

- **Parcial** (`WHERE …`): más pequeños y rápidos; típico `deleted_at IS NULL` o estados activos.
- **Único**: integridad (`UNIQUE`, `uq_…`).
- **GIN** (`to_tsvector`): búsqueda de texto; ver también índices extra en doc maestro §71.

---

# PARTE A — Schema `public` (orden de migración)

> Prefijo de archivo: `001_`, `002_`, … (o `YYYY_MM_DD_000001_` si usas fechas; **mantén el orden relativo**).

---

## 001 — `001_create_ubigeos_table.php` → `public.ubigeos`

**Propósito:** Catálogo INEI Perú (departamento / provincia / distrito); FK desde tenants, owners, suppliers, sedes.

| Columna | Tipo | Null | Restricción / default | Comentario |
|---------|------|------|-------------------------|------------|
| `id` | `SERIAL` | NO | PK | Identificador interno |
| `ubigeo` | `VARCHAR(6)` | NO | `UNIQUE`, `CHECK LENGTH=6` | Código INEI |
| `departamento` | `VARCHAR(50)` | NO | — | Nombre departamento |
| `provincia` | `VARCHAR(50)` | NO | — | Nombre provincia |
| `distrito` | `VARCHAR(50)` | NO | — | Nombre distrito |

**Índices**

| Nombre | Columnas | Tipo | Comentario |
|--------|----------|------|------------|
| `idx_ubigeos_dep` | `departamento` | B-tree | Filtros por departamento |
| `idx_ubigeos_pro` | `provincia` | B-tree | Filtros por provincia |
| (PK) | `id` | — | — |
| (único) | `ubigeo` | — | — |

---

## 002 — `002_seed_ubigeos_peru.php`

**Propósito:** Semilla ~1 874 distritos (sin nueva tabla).

---

## 003 — `003_create_plans_table.php` → `public.plans`

**Propósito:** Planes de suscripción SaaS (Free, Starter, Pro, Clínica).

| Columna | Tipo | Null | Comentario |
|---------|------|------|------------|
| `id` | `UUID` | NO | PK |
| `codigo` | `VARCHAR(30)` | NO | `UNIQUE`; ej. `free`, `starter` |
| `nombre` | `VARCHAR(80)` | NO | Etiqueta comercial |
| `descripcion` | `TEXT` | Sí | Detalle del plan |
| `badge` | `VARCHAR(50)` | Sí | Texto marketing (“Más popular”) |
| `color_hex` | `VARCHAR(7)` | Sí | Color UI |
| `precio_mensual` | `DECIMAL(10,2)` | NO | Soles sin IGV |
| `precio_anual` | `DECIMAL(10,2)` | Sí | Descuento anual opcional |
| `trial_days` | `SMALLINT` | NO | Días de prueba |
| `orden` | `SMALLINT` | NO | Orden en landing |
| `es_publico` | `BOOLEAN` | NO | Plan oculto vs público |
| `activo` | `BOOLEAN` | NO | — |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | NO | Auditoría |

**Índices:** ninguno explícito salvo PK/`UNIQUE(codigo)` (definidos en columnas).

---

## 004 — `004_create_plan_features_table.php` → `public.plan_features`

**Propósito:** Features por plan (límites y flags); joinable y auditable frente a solo JSON.

| Columna | Tipo | Null | Comentario |
|---------|------|------|------------|
| `id` | `UUID` | NO | PK |
| `plan_id` | `UUID` | NO | FK → `plans`, `ON DELETE CASCADE` |
| `feature` | `VARCHAR(60)` | NO | Nombre canónico (`max_pacientes`, `modulo_stock`, …) |
| `valor_int` | `INTEGER` | Sí | Límites numéricos; `-1` = ilimitado |
| `valor_bool` | `BOOLEAN` | Sí | Flags |
| `valor_str` | `VARCHAR(50)` | Sí | Valores texto (`soporte_tipo`, etc.) |

**Índices / restricciones**

| Nombre | Definición | Comentario |
|--------|--------------|------------|
| `uq_plan_feature` | `UNIQUE (plan_id, feature)` | Un valor por feature y plan |

---

## 005 — `005_seed_plans_and_features.php`

**Propósito:** Inserta los 4 planes y sus `plan_features` (semilla).

---

## 006 — `006_create_promo_codes_table.php` → `public.promo_codes`

**Propósito:** Códigos promocionales para suscripción.

| Columna | Tipo | Null | Comentario |
|---------|------|------|------------|
| `id` | `UUID` | NO | PK |
| `codigo` | `VARCHAR(30)` | NO | `UNIQUE` |
| `descripcion` | `VARCHAR(200)` | Sí | — |
| `tipo_descuento` | `VARCHAR(15)` | NO | `CHECK`: porcentaje, monto_fijo, meses_gratis |
| `valor` | `DECIMAL(10,2)` | NO | Interpretación según tipo |
| `plan_id_restriccion` | `UUID` | Sí | FK `plans`; NULL = cualquier plan |
| `max_usos`, `usos_actuales` | `INTEGER` | — | Cupos |
| `un_uso_por_tenant` | `BOOLEAN` | NO | — |
| `activo` | `BOOLEAN` | NO | — |
| `valido_desde`, `valido_hasta` | `TIMESTAMPTZ` | Sí | Ventana |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | NO | — |

**Índices**

| Nombre | Columnas | Parcial | Comentario |
|--------|----------|---------|------------|
| `idx_promo_activo` | `codigo` | `WHERE activo = TRUE` | Resolución rápida en checkout |

---

## 007 — `007_create_tenants_table.php` → `public.tenants`

**Propósito:** Registro de cada clínica (tenant); enlaza slug, schema PostgreSQL y estado SaaS.

| Columna | Tipo | Null | Comentario |
|---------|------|------|------------|
| `id` | `UUID` | NO | PK |
| `slug` | `VARCHAR(60)` | NO | Subdominio; `CHECK` regex minúsculas |
| `schema_name` | `VARCHAR(60)` | NO | Schema físico; inmutable |
| `razon_social`, `nombre_comercial` | `VARCHAR` | — | Datos fiscales / marca |
| `ruc` | `VARCHAR(11)` | Sí | `UNIQUE`; `CHECK` 11 dígitos o NULL |
| `email_admin` | `VARCHAR(150)` | NO | `UNIQUE` |
| `telefono`, `direccion`, `logo_url` | varios | Sí | Contacto / branding |
| `ubigeo_id` | `INTEGER` | Sí | FK → `ubigeos` |
| `nubefact_token_enc`, `nubefact_ruc` | `TEXT` / `VARCHAR` | Sí | Credenciales encriptadas en app |
| `sunat_configurado` | `BOOLEAN` | NO | — |
| `estado` | `VARCHAR(20)` | NO | trial, active, suspended, cancelled |
| `trial_ends_at`, `suspended_at`, `cancelled_at` | `TIMESTAMPTZ` | Sí | Ciclo de vida |
| `onboarding_*` | `BOOLEAN` / `SMALLINT` | NO | Wizard 0–5 |
| `timezone`, `locale` | `VARCHAR` | NO | Default Lima / `es_PE` |
| `canal_adquisicion`, `referido_por_tenant_id` | … | Sí | Marketing |
| `created_at`, `updated_at`, `deleted_at` | `TIMESTAMPTZ` | — | Soft delete |

**Índices**

| Nombre | Columnas | Parcial | Uso |
|--------|----------|---------|-----|
| `idx_tenants_slug` | `slug` | `deleted_at IS NULL` | Resolución por host |
| `idx_tenants_estado` | `estado` | `deleted_at IS NULL` | Paneles operación |
| `idx_tenants_trial` | `trial_ends_at` | `estado = 'trial'` | Jobs vencimiento trial |

---

## 008 — `008_create_subscriptions_table.php` → `public.subscriptions`

**Propósito:** Suscripción activa por tenant (un registro “vivo” por tenant).

| Columna | Tipo | Null | Comentario |
|---------|------|------|------------|
| `id` | `UUID` | NO | PK |
| `tenant_id` | `UUID` | NO | FK tenants `CASCADE` |
| `plan_id` | `UUID` | NO | FK plans |
| `estado` | `VARCHAR(20)` | NO | trial, active, grace, suspended, cancelled |
| `ciclo` | `VARCHAR(10)` | NO | mensual / anual |
| `trial_ends_at`, `current_period_*`, `grace_ends_at` | `TIMESTAMPTZ` | Sí | Facturación |
| `precio_pactado`, `descuento_pct` | `DECIMAL` | NO | Precio real |
| `promo_code_id` | `UUID` | Sí | FK `promo_codes` |
| `proximo_cobro_at`, `metodo_pago_token` | … | Sí | Cobro recurrente |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | NO | — |

**Índices**

| Nombre | Definición | Comentario |
|--------|------------|------------|
| `idx_subscriptions_tenant_active` | `UNIQUE (tenant_id) WHERE estado != 'cancelled'` | Solo una suscripción no cancelada |
| `idx_subscriptions_cobro` | `proximo_cobro_at` WHERE `active` | Job cobros |
| `idx_subscriptions_grace` | `grace_ends_at` WHERE `grace` | Fin de gracia |

---

## 009 — `009_create_subscription_payments_table.php` → `public.subscription_payments`

**Propósito:** Historial de pagos de suscripción (auditoría de ingresos SaaS).

| Columna | Tipo | Comentario breve |
|---------|------|------------------|
| `id` | `UUID` | PK |
| `subscription_id`, `tenant_id`, `plan_id` | `UUID` | FKs |
| `monto`, `igv_monto`, `descuento_monto`, `total` | `DECIMAL(10,2)` | Montos |
| `moneda` | `CHAR(3)` | PEN |
| `estado` | `VARCHAR(20)` | pendiente, procesado, fallido, reembolsado |
| `pasarela`, `pasarela_transaction_id`, `pasarela_response` | … | Integración pago |
| `periodo_inicio`, `periodo_fin` | `TIMESTAMPTZ` | Período cubierto |
| `fel_emitido`, `fel_numero` | … | CPE ORVAE al cliente |
| `error_mensaje`, `pagado_at`, `created_at` | … | Traza |

**Índices**

| Nombre | Columnas | Parcial | Uso |
|--------|----------|---------|-----|
| `idx_sub_payments_tenant` | `tenant_id`, `created_at DESC` | — | Historial por clínica |
| `idx_sub_payments_estado` | `estado` | `estado = 'pendiente'` | Cola cobros |

---

## 010 — `010_create_global_notifications_table.php` → `public.global_notifications`

**Propósito:** Avisos del SaaS a tenants (mantenimiento, novedades).

| Columna | Tipo | Comentario |
|---------|------|------------|
| `id` | `UUID` | PK |
| `titulo` | `VARCHAR(200)` | — |
| `mensaje` | `TEXT` | Cuerpo |
| `tipo` | `VARCHAR(20)` | info, warning, error, success, mantenimiento |
| `plan_id_target`, `tenant_id_target` | `UUID` NULL | Segmentación; NULL = todos |
| `activo` | `BOOLEAN` | — |
| `publicado_at`, `expira_at` | `TIMESTAMPTZ` | Vigencia |
| `created_at` | `TIMESTAMPTZ` | — |

**Índices:** añadir según consultas (p. ej. `activo`, `expira_at`).

---

# PARTE B — Schema tenant (orden de migración)

> Prefijo sugerido: `t001_`, `t010_`, … **Misma regla:** orden lexicográfico = orden de ejecución.  
> Todas las tablas viven en el schema del tenant (`SET search_path`); **no** llevan `tenant_id`.

---

## t001 — `t001_create_users_table.php` → `users`

**Propósito:** Personal de la clínica; autenticación Fortify/Laravel (alinear nombre columna `password` vs `password_hash` con el modelo si aplica).

| Grupo | Columnas principales | Tipos / notas |
|-------|----------------------|---------------|
| Identidad | `id` UUID PK; `nombres`, `apellidos` VARCHAR(100); `email` VARCHAR(150) UNIQUE; `password_hash` VARCHAR(255) | Credenciales |
| Perfil | `telefono`, `avatar_url`; `rol` VARCHAR(30) + CHECK roles clínica | RBAC app + Spatie |
| Vet | `especialidad`, `colegio_vet_num`, `firma_url` | Solo veterinarios |
| Operación | `sede_id` UUID NULL (FK sedes en migración posterior si aplica); `color_agenda` VARCHAR(7) | Agenda |
| Estado | `activo`, `email_verificado` BOOLEAN | — |
| Notificaciones | varios `notif_*` BOOLEAN | Preferencias |
| Seguridad | `last_login_at`, `last_login_ip`, `must_change_password` | — |
| Auditoría | `created_at`, `updated_at`, `deleted_at`, `created_by_id` | Soft delete |

**Índices**

| Nombre | Columnas | Parcial |
|--------|----------|---------|
| `idx_users_email` | `email` | `deleted_at IS NULL` |
| `idx_users_rol` | `rol` | `deleted_at IS NULL` |
| `idx_users_activo` | `activo` | `deleted_at IS NULL` |

---

## t002 — `t002_create_password_reset_tokens_table.php` → `password_reset_tokens`

| Columna | Tipo | Comentario |
|---------|------|------------|
| `id` | `UUID` | PK |
| `user_id` | `UUID` | FK users `CASCADE` |
| `token_hash` | `VARCHAR(255)` | `UNIQUE`; nunca token plano |
| `expira_at` | `TIMESTAMPTZ` | TTL típico 1 h |
| `usado`, `usado_at` | `BOOLEAN` / `TIMESTAMPTZ` | One-time |
| `ip_solicitud` | `VARCHAR(45)` | Traza |
| `created_at` | `TIMESTAMPTZ` | — |

**Índices:** `idx_pwreset_user` (`user_id`); `idx_pwreset_token` (`token_hash`) `WHERE usado = FALSE`.

---

## t003 — `t003_create_sessions_table.php` → `sessions`

| Columna | Tipo | Comentario |
|---------|------|------------|
| `id` | `VARCHAR(255)` | PK; ID sesión Laravel |
| `user_id` | `UUID` NULL | FK users |
| `ip_address` | `VARCHAR(45)` | — |
| `user_agent` | `TEXT` | — |
| `payload` | `TEXT` | Serializado |
| `last_activity` | `INTEGER` | Unix time |

**Índices:** `idx_sessions_user`, `idx_sessions_activity`.

---

## t004 — `t004_create_personal_access_tokens_table.php` → `personal_access_tokens`

**Propósito:** API tokens plan Clínica (`api_acceso`).

| Columna | Tipo | Comentario |
|---------|------|------------|
| `id` | `UUID` | PK |
| `user_id` | `UUID` | FK |
| `nombre` | `VARCHAR(100)` | Etiqueta token |
| `token_hash` | `VARCHAR(255)` | `UNIQUE` |
| `permisos` | `JSONB` | Scopes |
| `ultimo_uso_at`, `expira_at` | `TIMESTAMPTZ` | — |
| `activo` | `BOOLEAN` | Revocación |
| `created_at` | `TIMESTAMPTZ` | — |

**Índices:** `idx_pat_user`, `idx_pat_token` (parciales `activo = TRUE`).

---

## t010 — `t010_create_cfg_clinic_settings_table.php` → `cfg_clinic_settings`

**Propósito:** **Una fila** por tenant: fiscal, agenda, integraciones, marca.

| Grupo | Contenido |
|-------|------------|
| Fiscal | `ruc`, `razon_social`, `nombre_comercial`, `direccion_fiscal`, `ubigeo_id` → `public.ubigeos`, URLs, contacto |
| Agenda | `duracion_cita_default_min`, `intervalo_agenda_min`, `horario_atencion` JSONB, `dias_anticipacion_cita` |
| Recordatorios | flags + `recordatorio_vacuna_dias_antes` |
| Integraciones | NubeFact, Twilio, Brevo (campos `*_enc` encriptados en app) |
| Finanzas | `moneda`, `igv_porcentaje`, `precio_incluye_igv` |
| Políticas | `horas_min_cancelacion` |
| UI | `color_primario`, `color_secundario` |
| Auditoría | `created_at`, `updated_at`, `updated_by_id` |

**Índices**

| Nombre | Definición | Comentario |
|--------|------------|------------|
| `uq_cfg_clinic_settings_single_row` | `UNIQUE ((TRUE))` | Garantiza una sola fila |

---

## t011 — `t011_create_cfg_sedes_table.php` → `cfg_sedes`

**Propósito:** Sucursales (plan multi-sede).

| Columna | Tipo | Comentario |
|---------|------|------------|
| `id` | `UUID` | PK |
| `nombre` | `VARCHAR(150)` | — |
| `codigo` | `VARCHAR(10)` | `UNIQUE` |
| `direccion`, `ubigeo_id`, `telefono`, `email` | … | Ubicación |
| `responsable_id` | `UUID` NULL | FK users |
| `nubefact_serie_factura`, `nubefact_serie_boleta` | `VARCHAR(4)` | Series por sede |
| `activa` | `BOOLEAN` | — |
| `created_at`, `updated_at`, `deleted_at` | `TIMESTAMPTZ` | — |

---

## t012 — `t012_create_cfg_horarios_table.php` → `cfg_horarios`

**Propósito:** Disponibilidad por veterinario y día.

| Columna | Tipo | Comentario |
|---------|------|------------|
| `veterinario_id` | `UUID` | FK users |
| `sede_id` | `UUID` NULL | FK sedes |
| `dia_semana` | `SMALLINT` | 0 dom … 6 sáb |
| `hora_inicio`, `hora_fin` | `TIME` | `CHECK hora_fin > hora_inicio` |
| `activo` | `BOOLEAN` | — |

**Restricción:** `UNIQUE (veterinario_id, dia_semana, sede_id)`.

---

## t013 — `t013_create_cfg_bloqueos_agenda_table.php` → `cfg_bloqueos_agenda`

| Columna | Comentario |
|---------|------------|
| `veterinario_id` NULL | Bloqueo clínica entera si NULL |
| `fecha_inicio`, `fecha_fin` | Rango |
| `todo_el_dia`, `recurrente`, `patron_recurrencia` | Reglas |

**Índice:** `idx_bloqueos_fecha` (`fecha_inicio`, `fecha_fin`).

---

## t014 — `t014_create_cfg_tarifas_table.php` → `cfg_tarifas`

| Columna | Comentario |
|---------|------------|
| `tipo_consulta`, `especie` | Opcional; amarra a tipos de cita |
| `precio`, `duracion_min` | Base caja/agenda |

---

## t015 — `t015_create_cfg_recordatorio_templates_table.php` → `cfg_recordatorio_templates`

| Columna | Comentario |
|---------|------------|
| `tipo` | `UNIQUE`; cita_48h, vacuna_proxima, etc. |
| `canal` | whatsapp, email, sms |
| `cuerpo`, `asunto` | Plantillas con variables `{{...}}` |

---

## t016 — `t016_seed_cfg_recordatorio_templates.php`

Semilla de plantillas por defecto.

---

## t020 — `t020_create_vet_owners_table.php` → `vet_owners`

**Propósito:** Titulares / clientes.

| Grupo | Campos clave |
|-------|----------------|
| Persona | `nombres`, `apellidos` |
| Documento | `tipo_documento` + `numero_documento` + `UNIQUE (tipo, numero)` |
| Contacto | `telefono`, `email`, `canal_contacto`, `ubigeo_id` |
| CRM | `referido_por_owner_id`, `puntos_fidelidad`, `como_nos_conocio` |
| Control | `es_empresa`, `activo`, `notas`, auditoría + soft delete |

**Índices**

| Nombre | Uso |
|--------|-----|
| `idx_owners_apellidos` | `LOWER(apellidos)` parcial `deleted_at IS NULL` |
| `idx_owners_telefono`, `idx_owners_email` | Búsqueda caja |
| `idx_owners_busqueda` | `LOWER(nombres \|\| apellidos)` |

*(Doc maestro §71 añade GIN `to_tsvector` para búsqueda avanzada.)*

---

## t021 — `t021_create_vet_patients_table.php` → `vet_patients`

**Propósito:** Mascotas / pacientes.

| Grupo | Campos |
|-------|--------|
| Relación | `owner_id` FK owners |
| Identidad | `nombre`, `especie` CHECK, `raza`, `sexo`, fechas, `microchip`, etc. |
| Clínico | `alergias_conocidas`, `condiciones_cronicas`, `medicacion_permanente`, `notas_internas` |
| Cache | `peso_ultimo_kg`, `ultima_consulta_at`, `ultimo_veterinario_id` | Mantenido por trigger HC |
| Estado | `fallecido`, fechas fallecimiento | — |

**Índices:** `idx_patients_owner`, `nombre`, `especie`, `microchip` (parciales según doc).

---

## t022 — `t022_create_vet_patient_owners_table.php` → `vet_patient_owners`

Co-propietarios; `UNIQUE (patient_id, owner_id)`; índices por `patient_id` / `owner_id`.

---

## t023 — `t023_create_vet_patient_documents_table.php` → `vet_patient_documents`

Adjuntos R2; `idx_pat_docs_patient`.

---

## t024 — `t024_create_vet_owner_consents_table.php` → `vet_owner_consents`

Ley 29733; `idx_consents_owner`.

---

## t030 — `t030_create_vet_appointments_table.php` → `vet_appointments`

**Propósito:** Citas; **sin FK** aún a `vet_clinical_records` / `vet_sales` / grooming (columnas UUID NULL).

| Grupo | Campos |
|-------|--------|
| Quién | `patient_id`, `owner_id`, `veterinario_id`, `sede_id`, `tarifa_id` |
| Qué | `tipo_consulta` CHECK, `estado` CHECK |
| Cuándo | `fecha_hora_inicio`, `fecha_hora_fin`, `duracion_min` |
| Texto | `motivo_consulta`, `notas_previas`, `notas_internas` |
| Recordatorios | `recordatorio_*`, `confirmado_propietario` |
| Cancel / reagenda | `cancelado_por`, `cita_original_id` |
| Post-atención | `historia_clinica_id`, `venta_id`, `grooming_id` (FKs después) |
| Origen | `origen_cita` CHECK |

**Índices**

| Nombre | Uso |
|--------|-----|
| `idx_appt_vet_fecha` | Agenda semanal; excluye canceladas/no_asistio |
| `idx_appt_recordatorio_48h` / `2h` | Jobs recordatorios |
| `idx_appt_fecha` | `DATE(fecha_hora_inicio)` vista día |

---

## t031 — `t031_create_vet_appointment_history_table.php` → `vet_appointment_history`

| Columna | Tipo | Comentario |
|---------|------|------------|
| `id` | `BIGSERIAL` | Alto volumen |
| `appointment_id` | `UUID` | FK CASCADE |
| `estado_anterior`, `estado_nuevo` | `VARCHAR` | Trazabilidad |
| `usuario_id` | `UUID` NULL | Quién cambió |

**Índice:** `idx_appt_history` (`appointment_id`, `created_at DESC`).

---

## t032 — `t032_create_vet_waiting_list_table.php` → `vet_waiting_list`

**Índice:** `idx_waitlist_estado` WHERE `estado = 'espera'`.

---

## t040 — `t040_create_vet_clinical_records_table.php` → `vet_clinical_records`

**Propósito:** Historia clínica SOAP (tabla central).

| Bloque | Columnas (resumen) |
|--------|---------------------|
| Cabecera | `patient_id`, `appointment_id`, `veterinario_id`, `sede_id`, `fecha_atencion`, `numero_hc` |
| Signos vitales | `peso_kg`, `temperatura_c`, FC/FR, SpO2, mucosas, BCS, etc. |
| SOAP | `motivo_consulta`, `historia_enfermedad`, `exploracion_fisica`, `hallazgos_por_sistemas` JSONB |
| Diagnóstico / plan | `diagnostico_*`, `cie_codigos`, `tratamiento`, `proxima_visita_*` |
| Adjuntos | `adjuntos_url` JSONB |
| Control | `confidencial`, `estado_facturacion`, `venta_id` (FK en t065) |
| Auditoría | `created_at`, `updated_at`, `deleted_at`, `created_by_id`, `updated_by_id` |

**Índices:** `idx_hc_patient_fecha`, `idx_hc_vet_fecha`, `idx_hc_facturacion`, `idx_hc_numero`.

---

## t041 — `t041_alter_vet_appointments_add_hc_fk.php`

**Acción:** Añadir FK `vet_appointments.historia_clinica_id` → `vet_clinical_records(id)`.

---

## t042 — `t042_create_vet_vaccinations_table.php` → `vet_vaccinations`

Índices: `idx_vacc_patient`, `idx_vacc_recordatorio` (próximas vacunas).

---

## t043 — `t043_create_vet_vaccination_protocols_table.php` → `vet_vaccination_protocols`

`pasos` JSONB NOT NULL — definición por especie.

---

## t044 — `t044_create_vet_prescriptions_table.php` → `vet_prescriptions`

`items` JSONB; índices `idx_rx_patient`, `idx_rx_pendientes` (`dispensado = FALSE`).

---

## t045 — `t045_create_vet_lab_orders_table.php` → `vet_lab_orders`

`examenes` JSONB; índices por paciente y estado activo.

---

## t046 — `t046_create_vet_lab_results_table.php` → `vet_lab_results`

`resultados` JSONB; `idx_lab_results_criticos` para valores críticos sin notificar.

---

## t047 — `t047_create_vet_surgeries_table.php` → `vet_surgeries`

Tiempos, anestesia, monitoreo JSONB, `idx_surgeries_patient`, `idx_surgeries_cirujano`.

---

## t048 — `t048_create_vet_hospitalizations_table.php` → `vet_hospitalizations`

`dias_internado` SMALLINT mantenido por trigger (t126); `idx_hosp_activas`.

---

## t049 — `t049_create_vet_vital_signs_log_table.php` → `vet_vital_signs_log`

`BIGSERIAL` PK; `idx_vitals_hosp`.

---

## t050 — `t050_create_vet_suppliers_table.php` → `vet_suppliers`

`idx_suppliers_nombre` en `LOWER(razon_social)` parcial no borrados.

---

## t051 — `t051_create_vet_product_categories_table.php` → `vet_product_categories`

Jerarquía `parent_id` self-FK.

---

## t052 — `t052_create_vet_products_table.php` → `vet_products`

Muchos índices: nombre, tipo, código, barras, supplier (ver DDL maestro).

---

## t053 — `t053_create_vet_stock_items_table.php` → `vet_stock_items`

Stock por lote/sede; índices vencimiento, bajo mínimo, agotado.

---

## t054 — `t054_create_vet_stock_movements_table.php` → `vet_stock_movements`

**Solo INSERT** (inmutable kardex); `BIGSERIAL`; índices por item/producto/fecha.

---

## t055 — `t055_create_vet_stock_alerts_table.php` → `vet_stock_alerts`

`BIGSERIAL`; alertas enviadas (sin índices en DDL base del doc — recomendados por `stock_item_id`, `created_at`).

---

## t056 — `t056_create_vet_purchases_table.php` → `vet_purchases`

OC proveedor; índices supplier/estado.

---

## t057 — `t057_create_vet_purchase_items_table.php` → `vet_purchase_items`

Detalle OC; FK `purchase_id` CASCADE.

---

## t060 — `t060_create_vet_cash_sessions_table.php` → `vet_cash_sessions`

Turnos de caja. **Nota diseño:** el campo `diferencia` como `GENERATED` con subconsulta **no es válido en PostgreSQL**; calcular cierre en **aplicación** o columna simple (ver comentario en `vetsaas_db_completo.md`).

**Índices:** `idx_cash_sessions_cajero`, `idx_cash_sessions_abierta` (`estado = 'abierta'`).

---

## t061 — `t061_create_vet_discounts_table.php` → `vet_discounts`

Catálogo descuentos; sin índices en snippet — añadir por `codigo`, `activo`.

---

## t062 — `t062_create_vet_sales_table.php` → `vet_sales`

Tickets; `saldo_pendiente` puede ser `GENERATED` simple `(total - total_pagado)` si se valida en PG; índices owner, estado, fecha, fel, session.

---

## t063 — `t063_create_vet_sale_items_table.php` → `vet_sale_items`

Snapshots inmutables de producto en venta.

---

## t064 — `t064_create_vet_payments_table.php` → `vet_payments`

Pagos parciales por venta; índices `sale_id`, `pagado_at`.

---

## t065 — `t065_alter_vet_clinical_records_add_venta_fk.php`

**Acción:** FK `vet_clinical_records.venta_id` → `vet_sales(id)`.

---

## t070 — `t070_create_fel_series_table.php` → `fel_series`

Series SUNAT; `UNIQUE (tipo_cpe, serie, sede_id)`; función `next_correlativo` en migración t125 o aquí según organices.

---

## t071 — `t071_seed_fel_series_default.php`

Semilla F001/B001 sede principal.

---

## t072 — `t072_create_fel_documents_table.php` → `fel_documents`

CPE inmutable; muchos índices estado/fecha/venta/reintentos.

---

## t073 — `t073_create_fel_document_items_table.php` → `fel_document_items`

Líneas SUNAT; `valor_unitario`/`precio_unitario` 6 decimales.

---

## t074 — `t074_create_fel_void_requests_table.php` → `fel_void_requests`

Comunicaciones de baja.

---

## t075 — `t075_create_fel_summary_documents_table.php` → `fel_summary_documents`

Resumen diario boletas; `UNIQUE (fecha_referencia)` parcial excluyendo rechazados.

---

## t080 — `t080_create_vet_grooming_packages_table.php` → `vet_grooming_packages`

**Antes** que servicios si FK de servicio a paquete; orden del doc maestro: paquetes primero.

---

## t081 — `t081_create_vet_grooming_services_table.php` → `vet_grooming_services`

Índices estado, patient, groomer.

---

## t082 — `t082_alter_vet_appointments_add_grooming_fk.php`

FK `grooming_id` → `vet_grooming_services`.

---

## t083 — `t083_create_vet_boarding_table.php` → `vet_boarding`

Guardería/hotel; `idx_boarding_activos`.

---

## t084 — `t084_create_vet_boarding_daily_logs_table.php` → `vet_boarding_daily_logs`

`BIGSERIAL`; índice por `boarding_id`, `fecha`.

---

## t090 — `t090_create_notifications_queue_table.php` → `notifications_queue`

Cola saliente; índice `enviar_at`, `prioridad` pendientes.

---

## t091 — `t091_create_notifications_sent_table.php` → `notifications_sent`

Histórico inmutable; índices tipo/fecha/destino/referencia.

---

## t092 — `t092_create_notifications_templates_table.php` → `notifications_templates`

Plantillas campañas (complementa `cfg_recordatorio_templates`).

---

## t100 — `t100_create_report_snapshots_table.php` → `report_snapshots`

Métricas diarias agregadas; `fecha` `UNIQUE`; `idx_snapshots_fecha`.

---

## t101 — `t101_create_mv_dashboard_metrics.php`

**Objeto:** `MATERIALIZED VIEW mv_dashboard_metrics` (no tabla).  
Índice único en `ultima_actualizacion` (patrón doc maestro). Refresco job Horizon.

---

## t102 — `t102_create_mv_financial_month.php`

**Objeto:** `MATERIALIZED VIEW mv_financial_month` + `UNIQUE(fecha)` — agregados mensuales ventas/CPE.

---

## t103 — `t103_create_mv_top_patients.php`

**Objeto:** `MATERIALIZED VIEW mv_top_patients` + `UNIQUE(id)` — ranking consultas.

---

## t110 — `t110_create_audit_logs_table.php` → `audit_logs`

**Solo INSERT** recomendado a nivel rol BD; columnas `origen`, `sede_id`, snapshots JSONB, `request_id`. Índices usuario, acción, tabla, fecha, origen.

---

## t111 — `t111_create_login_attempts_table.php` → `login_attempts`

Brute force; índices `email+fecha`, `ip+fecha`.

---

## t112 — `t112_create_api_request_logs_table.php` → `api_request_logs`

Uso API; índices token/fecha.

---

# PARTE C — Migraciones solo lógica (sin tabla nueva o ALTER)

| Archivo | Contenido |
|---------|-------------|
| `t120_create_trigger_updated_at.php` | Trigger `fn_set_updated_at` en tablas con `updated_at` |
| `t121_create_trigger_sync_patient_hc.php` | Tras INSERT HC actualiza cache en `vet_patients` |
| `t122_create_trigger_sync_stock.php` | Tras INSERT movimiento actualiza `vet_stock_items.cantidad` |
| `t123_create_trigger_audit_appointment.php` | Cambio estado cita → `vet_appointment_history` |
| `t124_create_trigger_check_stock.php` | Evita salidas que dejen stock negativo |
| `t125_create_function_next_correlativo.php` | Funciones correlativos ventas/HC/FEL según doc |
| `t126_create_trigger_hospitalization_dias.php` | `fn_sync_hospitalization_dias` BEFORE INSERT/UPDATE hospitalizaciones |

---

## Tabla ↔ migración (referencia rápida)

| Tabla / objeto | Migración típica |
|----------------|------------------|
| `ubigeos` | 001 |
| `plans` | 003 |
| `plan_features` | 004 |
| `promo_codes` | 006 |
| `tenants` | 007 |
| `subscriptions` | 008 |
| `subscription_payments` | 009 |
| `global_notifications` | 010 |
| `users` … `personal_access_tokens` | t001–t004 |
| `cfg_*` | t010–t015 (+ t016 seed) |
| `vet_owners` … `vet_owner_consents` | t020–t024 |
| `vet_appointments` (+ alter HC, grooming) | t030, t041, t082 |
| `vet_appointment_history`, `vet_waiting_list` | t031, t032 |
| `vet_clinical_records` (+ alter venta) | t040, t065 |
| HC satélites | t042–t049 |
| Inventario / compras | t050–t057 |
| Ventas / caja / pagos | t060–t064 |
| FEL | t070–t075 (+ seed t071) |
| Grooming / boarding | t080–t084 |
| Notificaciones | t090–t092 |
| `report_snapshots` + MVs | t100–t103 |
| Auditoría / logs | t110–t112 |
| Triggers / funciones | t120–t126 |

---

*Documento derivado de `vetsaas_db_completo.md` — enfoque estructura + orden Laravel. Mantener ambos alineados al cambiar el esquema.*
