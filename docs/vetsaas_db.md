# VetSaaS — Arquitectura de Base de Datos
**PostgreSQL 16 · Multi-Tenant por Schema · UUID v4**
> Arquitecto: ORVAE Software | Stack: Laravel 12 + PostgreSQL 16 | Mayo 2026

---

## Índice

1. [Filosofía de diseño](#1-filosofía-de-diseño)
2. [Arquitectura Multi-Tenant](#2-arquitectura-multi-tenant)
3. [Convenciones globales](#3-convenciones-globales)
4. [Schema `public` — Tablas SaaS globales](#4-schema-public--tablas-saas-globales)
5. [Schema por tenant — Dominio veterinario](#5-schema-por-tenant--dominio-veterinario)
6. [Planes y gastos operativos incluidos](#6-planes-y-gastos-operativos-incluidos)
7. [Índices críticos](#7-índices-críticos)
8. [Triggers y funciones PostgreSQL](#8-triggers-y-funciones-postgresql)
9. [Diagrama de relaciones por dominio](#9-diagrama-de-relaciones-por-dominio)
10. [Migraciones Laravel — orden de ejecución](#10-migraciones-laravel--orden-de-ejecución)

---

## 1. Filosofía de diseño

### Principios que guían cada decisión

| Principio | Implementación |
|-----------|---------------|
| **UUID v4 en toda entidad de negocio** | `gen_random_uuid()` nativo de PostgreSQL 16 — sin extensión extra |
| **BIGSERIAL solo en tablas de alto volumen** | `stock_movements`, `audit_logs` — millones de filas, nunca expuestas al API |
| **Soft delete siempre** | Columna `deleted_at TIMESTAMPTZ NULL` — nunca `DELETE` en tablas de negocio |
| **TIMESTAMPTZ, nunca TIMESTAMP** | Timezone-aware — Perú es UTC-5, el servidor puede estar en otro TZ |
| **JSONB para datos flexibles** | Historia clínica, adjuntos, configuración — no tablas EAV |
| **Snake_case** | Todas las tablas, columnas, índices y constraints |
| **Prefijo de dominio** | `vet_` para veterinario, `fel_` para facturación, `cfg_` para configuración |
| **Columnas de auditoría en toda tabla** | `created_at`, `updated_at`, `deleted_at`, `created_by_id` |
| **Inmutabilidad en tablas financieras** | `fel_documents`, `vet_stock_movements` — solo INSERT, nunca UPDATE |
| **No NULL en campos de negocio críticos** | Número de documento, tipo de CPE, total de venta — siempre NOT NULL |

---

## 2. Arquitectura Multi-Tenant

### Estrategia: Schema por Tenant en PostgreSQL

```
PostgreSQL 16
├── Schema: public                  ← Tablas globales del SaaS
│   ├── tenants                     ← Cada clínica registrada
│   ├── plans                       ← Planes de suscripción
│   ├── subscriptions               ← Suscripción activa por tenant
│   ├── plan_features               ← Features por plan (JSON o tabla)
│   └── ubigeos                     ← Catálogo INEI (compartido)
│
├── Schema: tenant_abc123           ← Clínica "Veterinaria Los Andes"
│   ├── users
│   ├── vet_owners
│   ├── vet_patients
│   ├── vet_appointments
│   ├── vet_clinical_records
│   ├── vet_vaccinations
│   ├── vet_prescriptions
│   ├── vet_products
│   ├── vet_stock_items
│   ├── vet_stock_movements
│   ├── vet_sales
│   ├── vet_sale_items
│   ├── vet_grooming_services
│   ├── fel_series
│   ├── fel_documents
│   ├── fel_document_items
│   ├── fel_cdr_responses
│   ├── cfg_clinic_settings
│   └── audit_logs
│
└── Schema: tenant_xyz789           ← Clínica "PetClinic Chiclayo"
    └── (misma estructura)
```

### Por qué schema por tenant y no tenant_id en tablas compartidas

```sql
-- ❌ PATRÓN INCORRECTO: tenant_id en cada tabla
SELECT * FROM vet_patients WHERE tenant_id = 'abc' AND id = '...';
-- Problema: RLS, índices compuestos, riesgo de data leak si se olvida el WHERE

-- ✅ PATRÓN CORRECTO: search_path por conexión
SET search_path TO tenant_abc123, public;
SELECT * FROM vet_patients WHERE id = '...';
-- El schema ya aísla los datos — sin riesgo de cross-tenant
```

### Configuración en Laravel

```php
// app/Models/Tenant.php
public function configure(): void
{
    DB::statement("SET search_path TO {$this->schema_name}, public");
}

// Middleware: SetTenantScope.php
// Se ejecuta en cada request — lee el subdominio o header X-Tenant-ID
// Ejemplo: vetperu-losandes.orvae.pe → busca tenant con slug 'vetperu-losandes'
```

---

## 3. Convenciones globales

### Tipos de datos estándar

```sql
-- IDs de entidades de negocio (expuestas al API)
id UUID DEFAULT gen_random_uuid() PRIMARY KEY

-- IDs de tablas de alto volumen (logs, movimientos)
id BIGSERIAL PRIMARY KEY

-- Dinero: SIEMPRE decimal exacto, nunca FLOAT
precio DECIMAL(10, 2) NOT NULL   -- hasta 99,999,999.99 — suficiente para Perú

-- Cantidades de stock: permite fracciones (ml, kg)
cantidad DECIMAL(10, 3) NOT NULL

-- Porcentajes
igv_porcentaje DECIMAL(5, 2) NOT NULL DEFAULT 18.00

-- Textos cortos categóricos → ENUM o VARCHAR con CHECK
-- ENUM cuando el dominio es cerrado y no cambia
-- VARCHAR + CHECK cuando puede evolucionar sin migración

-- Fechas con timezone
created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
deleted_at  TIMESTAMPTZ NULL                    -- NULL = activo

-- Auditoría de usuario
created_by_id UUID NULL REFERENCES users(id) ON DELETE SET NULL
```

### Columnas de auditoría — fragmento reutilizable

```sql
-- Estas columnas van en TODAS las tablas de negocio
created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
deleted_at    TIMESTAMPTZ NULL,
created_by_id UUID NULL
```

---

## 4. Schema `public` — Tablas SaaS globales

### 4.1. `tenants` — Clínicas registradas

```sql
CREATE TABLE public.tenants (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    slug            VARCHAR(60) NOT NULL UNIQUE,    -- 'vetlosandes-chiclayo' → subdominio
    schema_name     VARCHAR(60) NOT NULL UNIQUE,    -- 'tenant_abc123' → schema PostgreSQL
    razon_social    VARCHAR(200) NOT NULL,
    nombre_comercial VARCHAR(150) NULL,
    ruc             VARCHAR(11) NULL UNIQUE,         -- RUC SUNAT del propietario
    email_admin     VARCHAR(150) NOT NULL UNIQUE,
    telefono        VARCHAR(20) NULL,
    ubigeo_id       INTEGER NULL REFERENCES public.ubigeos(id),
    direccion       VARCHAR(255) NULL,
    logo_url        VARCHAR(500) NULL,

    -- NubeFact / SUNAT config (encriptado en aplicación, guardado como texto cifrado)
    nubefact_token_enc  TEXT NULL,                  -- AES-256 en app layer
    nubefact_ruc        VARCHAR(11) NULL,
    sunat_configurado   BOOLEAN NOT NULL DEFAULT FALSE,

    -- Estado del tenant
    estado          VARCHAR(20) NOT NULL DEFAULT 'trial'
                    CHECK (estado IN ('trial','active','suspended','cancelled')),
    trial_ends_at   TIMESTAMPTZ NULL,
    suspended_at    TIMESTAMPTZ NULL,
    suspension_reason TEXT NULL,

    -- Metadata
    timezone        VARCHAR(50) NOT NULL DEFAULT 'America/Lima',
    locale          VARCHAR(10) NOT NULL DEFAULT 'es_PE',
    onboarding_completado BOOLEAN NOT NULL DEFAULT FALSE,
    onboarding_paso SMALLINT NOT NULL DEFAULT 0,    -- 0..5 pasos del wizard

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at  TIMESTAMPTZ NULL
);

CREATE INDEX idx_tenants_slug   ON public.tenants(slug)   WHERE deleted_at IS NULL;
CREATE INDEX idx_tenants_estado ON public.tenants(estado) WHERE deleted_at IS NULL;
```

---

### 4.2. `plans` — Planes de suscripción

> El diseño es intencionalmente flexible. Los límites no son columnas rígidas
> sino un JSONB `features` — así puedes agregar nuevas restricciones
> sin una migración de base de datos.

```sql
CREATE TABLE public.plans (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    codigo          VARCHAR(30) NOT NULL UNIQUE,    -- 'free', 'starter', 'pro', 'clinica'
    nombre          VARCHAR(80) NOT NULL,
    descripcion     TEXT NULL,
    activo          BOOLEAN NOT NULL DEFAULT TRUE,

    -- Precio en soles (S/) sin IGV — el IGV se calcula al cobrar
    precio_mensual  DECIMAL(10, 2) NOT NULL DEFAULT 0,
    precio_anual    DECIMAL(10, 2) NULL,            -- si ofreces descuento anual

    -- Período de prueba (solo para planes pagos)
    trial_days      SMALLINT NOT NULL DEFAULT 0,

    -- Límites y features como JSONB
    -- Ventaja: agregar nueva feature = UPDATE de un registro, no ALTER TABLE
    features        JSONB NOT NULL DEFAULT '{}'::JSONB,

    -- Costos operativos que el plan cubre (ver sección 6)
    -- Guardado como JSONB para transparencia y flexibilidad
    costos_operativos JSONB NULL,

    orden           SMALLINT NOT NULL DEFAULT 0,    -- para ordenar en la UI
    es_publico      BOOLEAN NOT NULL DEFAULT TRUE,  -- FALSE = plan enterprise manual

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

#### Datos semilla — los 4 planes

```sql
INSERT INTO public.plans (codigo, nombre, precio_mensual, trial_days, features, costos_operativos, orden)
VALUES

-- ─── PLAN FREE ──────────────────────────────────────────────────────────────
('free', 'Free', 0.00, 0,
'{
  "max_pacientes":        50,
  "max_usuarios":         1,
  "max_citas_mes":        100,
  "historia_clinica":     true,
  "agenda":               true,
  "facturacion_sunat":    false,
  "recordatorios_wa":     false,
  "modulo_stock":         false,
  "modulo_grooming":      false,
  "multi_sede":           false,
  "reportes_avanzados":   false,
  "soporte":              "documentacion",
  "exportar_pdf":         true,
  "api_acceso":           false
}'::JSONB,
'{
  "nubefact_mensual_s":   0,
  "twilio_wa_mensual_s":  0,
  "r2_storage_gb":        1,
  "r2_costo_s":           0,
  "total_costo_s":        0,
  "margen_bruto_s":       0,
  "fuente": "Plan gancho — costo para ORVAE ~S/ 5-8/mes en infra compartida"
}'::JSONB,
1),

-- ─── PLAN STARTER ───────────────────────────────────────────────────────────
('starter', 'Starter', 149.00, 14,
'{
  "max_pacientes":        300,
  "max_usuarios":         2,
  "max_citas_mes":        500,
  "historia_clinica":     true,
  "agenda":               true,
  "facturacion_sunat":    true,
  "max_cpe_mes":          100,
  "recordatorios_wa":     true,
  "max_wa_mes":           50,
  "modulo_stock":         true,
  "modulo_grooming":      false,
  "multi_sede":           false,
  "reportes_avanzados":   false,
  "soporte":              "email",
  "exportar_pdf":         true,
  "api_acceso":           false
}'::JSONB,
'{
  "nubefact_mensual_s":   40,
  "fuente_nubefact":      "nubefact.com/precios — tarifa minima S/ 40 incl. IGV por cuenta",
  "twilio_wa_50msgs_s":   12,
  "fuente_twilio":        "twilio.com/whatsapp — ~$0.005 USD/msg saliente = ~S/ 0.019, 50 msgs = ~S/ 1 real, presupuestado S/ 12 con buffer",
  "brevo_email_s":        0,
  "fuente_brevo":         "brevo.com — plan gratis hasta 300 emails/dia, suficiente para Starter",
  "r2_storage_gb":        5,
  "r2_costo_s":           0,
  "fuente_r2":            "cloudflare.com/r2 — 10 GB gratis, suficiente para Starter",
  "vps_proporcion_s":     15,
  "fuente_vps":           "Costo VPS total / numero de tenants activos — estimado con 30 tenants Starter",
  "sentry_proporcion_s":  3,
  "soporte_tiempo_hrs":   0.5,
  "soporte_costo_s":      0,
  "fuente_soporte":       "Email — asincronico, bajo costo de tiempo en etapa inicial",
  "total_costo_s":        70,
  "ingreso_s":            149,
  "margen_bruto_s":       79,
  "margen_pct":           53
}'::JSONB,
2),

-- ─── PLAN PRO ───────────────────────────────────────────────────────────────
('pro', 'Pro', 249.00, 14,
'{
  "max_pacientes":        -1,
  "max_usuarios":         5,
  "max_citas_mes":        -1,
  "historia_clinica":     true,
  "agenda":               true,
  "facturacion_sunat":    true,
  "max_cpe_mes":          300,
  "recordatorios_wa":     true,
  "max_wa_mes":           -1,
  "modulo_stock":         true,
  "modulo_grooming":      true,
  "multi_sede":           false,
  "reportes_avanzados":   true,
  "soporte":              "whatsapp",
  "exportar_pdf":         true,
  "api_acceso":           false,
  "pwa_propietario":      true
}'::JSONB,
'{
  "nubefact_mensual_s":   40,
  "fuente_nubefact":      "nubefact.com/precios — tarifa minima S/ 40, suficiente hasta ~500 CPE/mes",
  "twilio_wa_ilimitado_s": 35,
  "fuente_twilio":        "Estimado 150 msgs/mes por clínica activa × S/ 0.019 = S/ 2.85 real, presupuestado S/ 35 con buffer de pico",
  "brevo_email_s":        0,
  "fuente_brevo":         "brevo.com — plan gratis suficiente para Pro en etapa inicial",
  "r2_storage_gb":        20,
  "r2_costo_s":           4,
  "fuente_r2":            "cloudflare.com/r2 — $0.015/GB despues de 10 GB gratis = $0.15 USD = ~S/ 0.56 real, presupuestado S/ 4 con buffer",
  "vps_proporcion_s":     20,
  "fuente_vps":           "Mayor uso de CPU por reportes avanzados y jobs WhatsApp",
  "sentry_s":             5,
  "soporte_tiempo_hrs":   1,
  "soporte_costo_s":      20,
  "fuente_soporte":       "WhatsApp — 1h/mes estimada por cliente Pro a S/ 20/hr oportunidad",
  "total_costo_s":        124,
  "ingreso_s":            249,
  "margen_bruto_s":       125,
  "margen_pct":           50
}'::JSONB,
3),

-- ─── PLAN CLÍNICA ──────────────────────────────────────────────────────────
('clinica', 'Clínica', 399.00, 7,
'{
  "max_pacientes":        -1,
  "max_usuarios":         -1,
  "max_citas_mes":        -1,
  "historia_clinica":     true,
  "agenda":               true,
  "facturacion_sunat":    true,
  "max_cpe_mes":          -1,
  "recordatorios_wa":     true,
  "max_wa_mes":           -1,
  "modulo_stock":         true,
  "modulo_grooming":      true,
  "multi_sede":           true,
  "reportes_avanzados":   true,
  "soporte":              "whatsapp_prioritario",
  "exportar_pdf":         true,
  "api_acceso":           true,
  "pwa_propietario":      true,
  "white_label":          false,
  "sla_uptime":           "99.5%"
}'::JSONB,
'{
  "nubefact_mensual_s":   40,
  "fuente_nubefact":      "nubefact.com/precios — S/ 40 cubre hasta ~500 CPE. Clinicas grandes pueden necesitar plan mayor — revisar nubefact.com/precios#volumen",
  "twilio_wa_s":          55,
  "fuente_twilio":        "Estimado 250+ msgs/mes, múltiples sedes. Twilio cobra por mensaje enviado — ver twilio.com/pricing",
  "brevo_s":              12,
  "fuente_brevo":         "brevo.com — plan Starter $9 USD cuando superan 300 emails/dia",
  "r2_storage_gb":        50,
  "r2_costo_s":           12,
  "fuente_r2":            "cloudflare.com/r2 — $0.015/GB × 40 GB extra = $0.60 USD = ~S/ 2.3 real, presupuestado S/ 12 con fotos grooming multi-sede",
  "vps_proporcion_s":     30,
  "fuente_vps":           "Multi-sede = más conexiones DB concurrentes y más jobs en Horizon",
  "sentry_s":             5,
  "soporte_tiempo_hrs":   2,
  "soporte_costo_s":      40,
  "fuente_soporte":       "WA prioritario — 2h/mes estimadas por cliente grande",
  "total_costo_s":        194,
  "ingreso_s":            399,
  "margen_bruto_s":       205,
  "margen_pct":           51
}'::JSONB,
4);
```

---

### 4.3. `subscriptions` — Suscripción activa por tenant

```sql
CREATE TABLE public.subscriptions (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    tenant_id       UUID        NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
    plan_id         UUID        NOT NULL REFERENCES public.plans(id),

    estado          VARCHAR(20) NOT NULL DEFAULT 'trial'
                    CHECK (estado IN (
                        'trial',        -- período de prueba activo
                        'active',       -- pago al día
                        'grace',        -- pago vencido, aún con acceso (7 días)
                        'suspended',    -- sin acceso, pendiente de pago
                        'cancelled'     -- baja voluntaria
                    )),

    -- Fechas del ciclo
    trial_ends_at       TIMESTAMPTZ NULL,
    current_period_start TIMESTAMPTZ NULL,
    current_period_end   TIMESTAMPTZ NULL,
    grace_ends_at        TIMESTAMPTZ NULL,  -- 7 días post-vencimiento antes de suspend
    cancelled_at         TIMESTAMPTZ NULL,
    cancel_reason        TEXT NULL,

    -- Precio pactado (puede diferir del plan si hay descuento/promo)
    precio_pactado  DECIMAL(10,2) NOT NULL,
    descuento_pct   DECIMAL(5,2) NOT NULL DEFAULT 0,
    codigo_promo    VARCHAR(30) NULL,

    -- Pagos (integración futura con Culqi o Niubiz)
    ultimo_pago_at      TIMESTAMPTZ NULL,
    proximo_cobro_at    TIMESTAMPTZ NULL,
    metodo_pago_ref     VARCHAR(200) NULL,  -- token encriptado del método de pago

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Un tenant solo puede tener UNA suscripción activa o en trial
CREATE UNIQUE INDEX idx_subscriptions_tenant_active
    ON public.subscriptions(tenant_id)
    WHERE estado NOT IN ('cancelled');

CREATE INDEX idx_subscriptions_proximo_cobro
    ON public.subscriptions(proximo_cobro_at)
    WHERE estado = 'active';  -- para el job de cobro automático

CREATE INDEX idx_subscriptions_grace
    ON public.subscriptions(grace_ends_at)
    WHERE estado = 'grace';   -- para el job de suspensión
```

---

### 4.4. `ubigeos` — Catálogo INEI compartido

```sql
CREATE TABLE public.ubigeos (
    id          SERIAL PRIMARY KEY,
    ubigeo      VARCHAR(6) NOT NULL UNIQUE,   -- código INEI: '140101'
    departamento VARCHAR(50) NOT NULL,
    provincia    VARCHAR(50) NOT NULL,
    distrito     VARCHAR(50) NOT NULL,

    CONSTRAINT chk_ubigeo_len CHECK (LENGTH(ubigeo) = 6)
);

CREATE INDEX idx_ubigeos_departamento ON public.ubigeos(departamento);
-- Importar los 1,874 distritos del Perú desde el CSV del INEI
-- Fuente: https://www.inei.gob.pe/media/MenuRecursivo/nomenclator/ubigeo_inei.csv
```

---

## 5. Schema por tenant — Dominio veterinario

> Todas las tablas de esta sección viven dentro del schema del tenant.
> No tienen columna `tenant_id` — el aislamiento lo da el schema de PostgreSQL.
> En Laravel: `SET search_path TO {schema_name}, public` al inicio del request.

---

### 5.1. Usuarios y roles

```sql
-- users — veterinarios, recepcionistas, administradores de la clínica
CREATE TABLE users (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    nombres         VARCHAR(100) NOT NULL,
    apellidos       VARCHAR(100) NOT NULL,
    email           VARCHAR(150) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,           -- bcrypt desde Laravel
    telefono        VARCHAR(20) NULL,

    rol             VARCHAR(30) NOT NULL DEFAULT 'recepcionista'
                    CHECK (rol IN (
                        'admin_clinica',   -- dueño de la clínica, todo acceso
                        'veterinario',     -- acceso clínico completo
                        'asistente_vet',   -- acceso clínico lectura + citas
                        'recepcionista',   -- agenda + caja + clientes
                        'groomer'          -- solo módulo peluquería
                    )),

    especialidad    VARCHAR(100) NULL,               -- ej: "Cirugía · Dermatología"
    colegio_vet_num VARCHAR(30) NULL,                -- número de colegiatura
    activo          BOOLEAN NOT NULL DEFAULT TRUE,
    avatar_url      VARCHAR(500) NULL,
    sede_id         UUID NULL,                       -- FK → cfg_sedes (plan Clínica)

    -- Configuración de notificaciones
    notif_nueva_cita    BOOLEAN NOT NULL DEFAULT TRUE,
    notif_cita_cancelada BOOLEAN NOT NULL DEFAULT TRUE,
    notif_stock_minimo   BOOLEAN NOT NULL DEFAULT TRUE,

    last_login_at   TIMESTAMPTZ NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ NULL,
    created_by_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_users_email  ON users(email)  WHERE deleted_at IS NULL;
CREATE INDEX idx_users_rol    ON users(rol)    WHERE deleted_at IS NULL;
```

---

### 5.2. Propietarios de mascotas

```sql
CREATE TABLE vet_owners (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    nombres         VARCHAR(100) NOT NULL,
    apellidos       VARCHAR(100) NOT NULL,

    -- Documento de identidad — requerido para CPE SUNAT
    tipo_documento  VARCHAR(10) NOT NULL DEFAULT 'DNI'
                    CHECK (tipo_documento IN ('DNI','RUC','CE','PTP','PASAPORTE')),
    numero_documento VARCHAR(20) NOT NULL,

    -- Contacto
    telefono        VARCHAR(20) NOT NULL,             -- WhatsApp recordatorios
    telefono_alt    VARCHAR(20) NULL,
    email           VARCHAR(150) NULL,
    direccion       VARCHAR(255) NULL,
    ubigeo_id       INTEGER NULL REFERENCES public.ubigeos(id),

    -- Preferencia de contacto para recordatorios
    canal_contacto  VARCHAR(20) NOT NULL DEFAULT 'whatsapp'
                    CHECK (canal_contacto IN ('whatsapp','email','sms','ninguno')),

    -- Datos complementarios
    fecha_nacimiento DATE NULL,
    ocupacion       VARCHAR(100) NULL,
    como_nos_conocio VARCHAR(100) NULL,              -- google / referido / pasé / redes
    notas           TEXT NULL,

    activo          BOOLEAN NOT NULL DEFAULT TRUE,

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ NULL,
    created_by_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL,

    -- Índice de unicidad: un DNI/RUC no puede repetirse en la misma clínica
    CONSTRAINT uq_owner_documento UNIQUE (tipo_documento, numero_documento)
);

CREATE INDEX idx_owners_telefono  ON vet_owners(telefono)  WHERE deleted_at IS NULL;
CREATE INDEX idx_owners_apellidos ON vet_owners(apellidos) WHERE deleted_at IS NULL;
-- Para búsqueda por nombre completo
CREATE INDEX idx_owners_nombre_completo
    ON vet_owners(LOWER(nombres || ' ' || apellidos))
    WHERE deleted_at IS NULL;
```

---

### 5.3. Pacientes (mascotas)

```sql
CREATE TABLE vet_patients (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    owner_id        UUID        NOT NULL REFERENCES vet_owners(id),

    nombre          VARCHAR(80) NOT NULL,
    especie         VARCHAR(20) NOT NULL
                    CHECK (especie IN ('canino','felino','ave','reptil','roedor','lagomorfo','otro')),
    raza            VARCHAR(80) NULL,                -- 'Mestizo' si no tiene
    sexo            VARCHAR(15) NOT NULL
                    CHECK (sexo IN ('macho','hembra','indeterminado')),

    fecha_nacimiento DATE NULL,
    -- edad_display es calculada en app, no guardada — evita datos obsoletos

    color_pelaje    VARCHAR(80) NULL,
    esterilizado    BOOLEAN NOT NULL DEFAULT FALSE,
    microchip       VARCHAR(30) NULL UNIQUE,
    foto_url        VARCHAR(500) NULL,

    -- Alertas clínicas — visibles al abrir la ficha
    alergias_conocidas TEXT NULL,
    condiciones_cronicas TEXT NULL,                  -- diabetes, epilepsia, etc.
    notas_internas  TEXT NULL,                       -- NO visible al propietario

    -- Cache de último control (actualizado por trigger)
    peso_ultimo_kg  DECIMAL(5,2) NULL,
    peso_fecha      DATE NULL,

    -- Estado
    fallecido       BOOLEAN NOT NULL DEFAULT FALSE,
    fecha_fallecimiento DATE NULL,
    causa_fallecimiento VARCHAR(200) NULL,

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ NULL,
    created_by_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_patients_owner    ON vet_patients(owner_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_patients_especie  ON vet_patients(especie)  WHERE deleted_at IS NULL;
CREATE INDEX idx_patients_nombre   ON vet_patients(LOWER(nombre)) WHERE deleted_at IS NULL;
CREATE INDEX idx_patients_microchip ON vet_patients(microchip) WHERE microchip IS NOT NULL;
```

---

### 5.4. Citas

```sql
CREATE TABLE vet_appointments (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    patient_id      UUID        NOT NULL REFERENCES vet_patients(id),
    veterinario_id  UUID        NOT NULL REFERENCES users(id),
    sede_id         UUID        NULL,                -- FK cfg_sedes si multi-sede

    tipo_consulta   VARCHAR(30) NOT NULL
                    CHECK (tipo_consulta IN (
                        'consulta_general',
                        'vacunacion',
                        'desparasitacion',
                        'cirugia',
                        'peluqueria',
                        'urgencia',
                        'control',
                        'laboratorio',
                        'ecografia',
                        'otro'
                    )),

    estado          VARCHAR(20) NOT NULL DEFAULT 'programada'
                    CHECK (estado IN (
                        'programada',
                        'confirmada',       -- propietario confirmó
                        'en_sala_espera',   -- llegó a la clínica
                        'en_atencion',      -- está siendo atendido
                        'atendida',         -- consulta terminada
                        'cancelada',        -- canceló el propietario o clínica
                        'no_asistio'        -- no se presentó
                    )),

    fecha_hora_inicio   TIMESTAMPTZ NOT NULL,
    fecha_hora_fin      TIMESTAMPTZ NULL,
    duracion_min        SMALLINT NOT NULL DEFAULT 30,
    motivo_consulta     VARCHAR(500) NOT NULL,
    notas_previas       TEXT NULL,                   -- info del propietario al agendar

    -- Recordatorios automáticos
    recordatorio_1_enviado_at   TIMESTAMPTZ NULL,    -- 48h antes
    recordatorio_2_enviado_at   TIMESTAMPTZ NULL,    -- 2h antes
    confirmado_por_propietario  BOOLEAN NOT NULL DEFAULT FALSE,
    confirmacion_at             TIMESTAMPTZ NULL,

    -- Vinculación post-atención
    historia_clinica_id UUID NULL REFERENCES vet_clinical_records(id),
    venta_id            UUID NULL,                   -- FK vet_sales — FK circular, se agrega al final

    cancelado_por       VARCHAR(20) NULL
                        CHECK (cancelado_por IN ('propietario','clinica',NULL)),
    motivo_cancelacion  TEXT NULL,

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ NULL,
    created_by_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

-- Índice principal de agenda: consultas por veterinario y fecha
CREATE INDEX idx_appointments_vet_fecha
    ON vet_appointments(veterinario_id, fecha_hora_inicio)
    WHERE deleted_at IS NULL AND estado NOT IN ('cancelada','no_asistio');

-- Para job de recordatorios: citas pendientes de recordatorio en las próximas 48h
CREATE INDEX idx_appointments_recordatorio
    ON vet_appointments(fecha_hora_inicio)
    WHERE recordatorio_1_enviado_at IS NULL
      AND estado IN ('programada','confirmada')
      AND deleted_at IS NULL;

-- Para la vista de agenda diaria
CREATE INDEX idx_appointments_fecha
    ON vet_appointments(DATE(fecha_hora_inicio))
    WHERE deleted_at IS NULL;
```

---

### 5.5. Historia clínica

```sql
-- Tabla core del sistema — el corazón del producto
CREATE TABLE vet_clinical_records (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    patient_id      UUID        NOT NULL REFERENCES vet_patients(id),
    appointment_id  UUID        NULL REFERENCES vet_appointments(id),
    veterinario_id  UUID        NOT NULL REFERENCES users(id),

    fecha_atencion  TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    -- Signos vitales
    peso_kg             DECIMAL(5,2) NULL,
    temperatura_c       DECIMAL(4,1) NULL,
    frecuencia_cardiaca SMALLINT NULL,           -- bpm
    frecuencia_resp     SMALLINT NULL,           -- rpm
    -- Valores de referencia en la UI, no en DB
    mucosas             VARCHAR(30) NULL
                        CHECK (mucosas IN ('rosadas','palidas','ictericas','cianosadas','congestionadas',NULL)),
    hidratacion_pct     SMALLINT NULL
                        CHECK (hidratacion_pct BETWEEN 0 AND 15),

    -- Formato SOAP (estándar en medicina veterinaria)
    -- S: Subjetivo — historia del propietario
    motivo_consulta         TEXT NOT NULL,
    -- O: Objetivo — hallazgos físicos del vet
    exploracion_fisica      TEXT NULL,
    -- A: Assessment — diagnóstico
    diagnostico_presuntivo  TEXT NULL,
    diagnostico_definitivo  TEXT NULL,
    codigos_cie             VARCHAR(200) NULL,   -- para uso futuro
    -- P: Plan — tratamiento
    tratamiento             TEXT NULL,
    proxima_visita_dias     SMALLINT NULL,       -- días hasta próximo control recomendado

    -- Datos adicionales como JSONB
    -- Ejemplo: [{"tipo": "hemograma", "resultado": "normal", "archivo_url": "..."}]
    examenes_solicitados    JSONB NULL,
    -- Ejemplo: ["https://r2.../rx_torax.jpg", "https://r2.../eco_abd.jpg"]
    adjuntos_url            JSONB NULL,

    observaciones           TEXT NULL,

    -- Facturación
    estado_facturacion  VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                        CHECK (estado_facturacion IN ('pendiente','facturado','exento','sin_cargo')),
    venta_id            UUID NULL,               -- FK vet_sales — se agrega al final

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ NULL,
    created_by_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

-- Historial del paciente ordenado por fecha
CREATE INDEX idx_clinical_patient_fecha
    ON vet_clinical_records(patient_id, fecha_atencion DESC)
    WHERE deleted_at IS NULL;

CREATE INDEX idx_clinical_vet_fecha
    ON vet_clinical_records(veterinario_id, fecha_atencion DESC)
    WHERE deleted_at IS NULL;

CREATE INDEX idx_clinical_facturacion
    ON vet_clinical_records(estado_facturacion)
    WHERE estado_facturacion = 'pendiente' AND deleted_at IS NULL;
```

---

### 5.6. Vacunaciones

```sql
CREATE TABLE vet_vaccinations (
    id                  UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    patient_id          UUID        NOT NULL REFERENCES vet_patients(id),
    clinical_record_id  UUID        NULL REFERENCES vet_clinical_records(id),
    veterinario_id      UUID        NOT NULL REFERENCES users(id),

    vacuna_nombre       VARCHAR(150) NOT NULL,
    vacuna_tipo         VARCHAR(50) NULL,             -- 'antirrábica','polivalente','leishmania'
    laboratorio         VARCHAR(100) NULL,
    lote                VARCHAR(50) NULL,
    vencimiento_vacuna  DATE NULL,                   -- fecha de vencimiento del vial

    fecha_aplicacion    DATE NOT NULL,
    fecha_proxima       DATE NULL,                   -- calculada en app según protocolo

    stock_item_id       UUID NULL,                   -- FK vet_stock_items — descuento automático
    dosis_ml            DECIMAL(5,2) NULL,

    -- Control de recordatorio
    recordatorio_enviado    BOOLEAN NOT NULL DEFAULT FALSE,
    recordatorio_enviado_at TIMESTAMPTZ NULL,

    notas               TEXT NULL,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_by_id       UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

-- Para job de recordatorios de vacunas próximas a vencer
CREATE INDEX idx_vaccinations_proxima
    ON vet_vaccinations(fecha_proxima)
    WHERE recordatorio_enviado = FALSE AND fecha_proxima IS NOT NULL;

CREATE INDEX idx_vaccinations_patient
    ON vet_vaccinations(patient_id, fecha_aplicacion DESC);
```

---

### 5.7. Recetas médicas

```sql
CREATE TABLE vet_prescriptions (
    id                  UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    clinical_record_id  UUID        NOT NULL REFERENCES vet_clinical_records(id),
    patient_id          UUID        NOT NULL REFERENCES vet_patients(id),   -- desnormalizado para queries rápidas
    veterinario_id      UUID        NOT NULL REFERENCES users(id),

    -- Items de la receta en JSONB
    -- [{
    --   "medicamento": "Amoxicilina 250mg",
    --   "dosis": "1 comprimido",
    --   "frecuencia": "cada 12 horas",
    --   "duracion": "7 días",
    --   "via": "oral",
    --   "observaciones": "con comida"
    -- }]
    items               JSONB NOT NULL,
    indicaciones_generales TEXT NULL,
    alertas_propietario    TEXT NULL,            -- "No exponer al sol durante tratamiento"

    -- PDF generado
    pdf_url             VARCHAR(500) NULL,
    pdf_generado_at     TIMESTAMPTZ NULL,

    -- Envío al propietario
    enviado_propietario     BOOLEAN NOT NULL DEFAULT FALSE,
    enviado_at              TIMESTAMPTZ NULL,
    canal_envio             VARCHAR(20) NULL
                            CHECK (canal_envio IN ('whatsapp','email',NULL)),

    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_by_id       UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_prescriptions_patient
    ON vet_prescriptions(patient_id, created_at DESC);
```

---

### 5.8. Inventario

```sql
-- Catálogo de productos y servicios
CREATE TABLE vet_products (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    tipo            VARCHAR(20) NOT NULL
                    CHECK (tipo IN ('producto','servicio','vacuna','medicamento','alimento','accesorio')),

    nombre          VARCHAR(200) NOT NULL,
    codigo          VARCHAR(50) NULL UNIQUE,          -- código interno o código de barras
    descripcion     TEXT NULL,
    marca           VARCHAR(100) NULL,

    -- Precios
    precio_venta    DECIMAL(10,2) NOT NULL,
    precio_costo    DECIMAL(10,2) NULL,               -- para margen en reportes
    unidad_medida   VARCHAR(20) NOT NULL DEFAULT 'unidad'
                    CHECK (unidad_medida IN ('unidad','ml','mg','pastilla','kg','gr','ampolla','caja','frasco')),

    -- SUNAT
    codigo_sunat    VARCHAR(10) NULL,                 -- código de catálogo de bienes/servicios SUNAT
    igv_tipo        VARCHAR(15) NOT NULL DEFAULT 'gravado'
                    CHECK (igv_tipo IN ('gravado','exonerado','inafecto')),

    -- Control
    requiere_receta BOOLEAN NOT NULL DEFAULT FALSE,
    controla_stock  BOOLEAN NOT NULL DEFAULT TRUE,   -- FALSE para servicios
    stock_minimo_alerta DECIMAL(10,3) NULL,

    activo          BOOLEAN NOT NULL DEFAULT TRUE,
    categoria       VARCHAR(80) NULL,

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ NULL,
    created_by_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_products_tipo    ON vet_products(tipo)   WHERE deleted_at IS NULL;
CREATE INDEX idx_products_nombre  ON vet_products(LOWER(nombre)) WHERE deleted_at IS NULL;
CREATE INDEX idx_products_codigo  ON vet_products(codigo) WHERE codigo IS NOT NULL AND deleted_at IS NULL;

-- ─────────────────────────────────────────────────────────────────────────────

-- Lotes de stock por producto (un producto puede tener varios lotes)
CREATE TABLE vet_stock_items (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    product_id      UUID        NOT NULL REFERENCES vet_products(id),

    lote            VARCHAR(50) NULL,
    fecha_vencimiento DATE NULL,
    ubicacion       VARCHAR(80) NULL,                 -- 'Refrigerador A', 'Estante 2-B'

    -- Stock actual (calculado como suma de movimientos, mantenido por trigger)
    cantidad        DECIMAL(10,3) NOT NULL DEFAULT 0,
    stock_minimo    DECIMAL(10,3) NOT NULL DEFAULT 0,
    alerta_enviada  BOOLEAN NOT NULL DEFAULT FALSE,  -- flag para no repetir alerta

    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_stock_product        ON vet_stock_items(product_id);
CREATE INDEX idx_stock_vencimiento    ON vet_stock_items(fecha_vencimiento)
    WHERE fecha_vencimiento IS NOT NULL;

-- ─────────────────────────────────────────────────────────────────────────────

-- Kardex — INMUTABLE (solo INSERT, nunca UPDATE ni DELETE)
CREATE TABLE vet_stock_movements (
    id              BIGSERIAL PRIMARY KEY,            -- BIGSERIAL aquí: alto volumen, no expuesto al API
    stock_item_id   UUID NOT NULL REFERENCES vet_stock_items(id),
    product_id      UUID NOT NULL REFERENCES vet_products(id),  -- desnormalizado

    tipo_movimiento VARCHAR(20) NOT NULL
                    CHECK (tipo_movimiento IN (
                        'entrada',        -- compra a proveedor
                        'salida_venta',   -- vendido en mostrador
                        'salida_consulta',-- usado en consulta clínica
                        'salida_vacuna',  -- usado en vacunación
                        'ajuste_positivo',-- corrección de inventario
                        'ajuste_negativo',
                        'merma',          -- vencimiento, rotura
                        'devolucion'      -- devolución de venta
                    )),

    -- Positivo = entrada de stock, Negativo = salida
    cantidad        DECIMAL(10,3) NOT NULL,
    cantidad_anterior DECIMAL(10,3) NOT NULL,         -- para auditoría
    cantidad_resultante DECIMAL(10,3) NOT NULL,       -- para auditoría

    -- Referencia al documento origen
    referencia_tipo VARCHAR(30) NULL,                 -- 'venta','receta','vacuna','ajuste'
    referencia_id   UUID NULL,
    referencia_num  VARCHAR(50) NULL,                 -- número legible: 'VTA-0001'

    precio_unitario DECIMAL(10,2) NULL,               -- precio al momento del movimiento
    notas           VARCHAR(255) NULL,
    usuario_id      UUID NOT NULL REFERENCES users(id),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_stockmov_item   ON vet_stock_movements(stock_item_id, created_at DESC);
CREATE INDEX idx_stockmov_fecha  ON vet_stock_movements(created_at DESC);
```

---

### 5.9. Ventas y facturación

```sql
-- Orden de venta / ticket de cobro
CREATE TABLE vet_sales (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    numero          VARCHAR(20) NOT NULL UNIQUE,      -- 'VTA-2026-0001' — correlativo por tenant

    owner_id        UUID        NOT NULL REFERENCES vet_owners(id),
    patient_id      UUID        NULL REFERENCES vet_patients(id),
    clinical_record_id UUID     NULL REFERENCES vet_clinical_records(id),

    estado          VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                    CHECK (estado IN ('pendiente','pagado','parcial','anulado')),

    -- Totales (calculados al guardar los items)
    subtotal        DECIMAL(10,2) NOT NULL DEFAULT 0, -- sin IGV
    igv_monto       DECIMAL(10,2) NOT NULL DEFAULT 0,
    descuento_monto DECIMAL(10,2) NOT NULL DEFAULT 0,
    total           DECIMAL(10,2) NOT NULL DEFAULT 0,

    -- Pago
    metodo_pago     VARCHAR(20) NULL
                    CHECK (metodo_pago IN ('efectivo','yape','plin','tarjeta','transferencia','mixto',NULL)),
    monto_recibido  DECIMAL(10,2) NULL,               -- para calcular vuelto en efectivo
    vuelto          DECIMAL(10,2) NULL,
    fecha_pago      TIMESTAMPTZ NULL,

    notas           TEXT NULL,

    -- Estado de facturación electrónica
    fel_estado      VARCHAR(20) NOT NULL DEFAULT 'sin_cpe'
                    CHECK (fel_estado IN ('sin_cpe','pendiente_emision','emitido','rechazado','anulado')),
    fel_document_id UUID NULL,                        -- FK fel_documents

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ NULL,
    created_by_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_sales_owner    ON vet_sales(owner_id)  WHERE deleted_at IS NULL;
CREATE INDEX idx_sales_estado   ON vet_sales(estado)    WHERE deleted_at IS NULL;
CREATE INDEX idx_sales_fecha    ON vet_sales(created_at DESC) WHERE deleted_at IS NULL;

-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE vet_sale_items (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    sale_id         UUID        NOT NULL REFERENCES vet_sales(id) ON DELETE CASCADE,
    product_id      UUID        NOT NULL REFERENCES vet_products(id),

    -- Snapshot al momento de la venta — no cambia aunque el producto cambie
    descripcion_snapshot VARCHAR(300) NOT NULL,
    igv_tipo_snapshot    VARCHAR(15) NOT NULL,

    cantidad        DECIMAL(10,3) NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,           -- sin IGV
    descuento_pct   DECIMAL(5,2) NOT NULL DEFAULT 0,
    subtotal        DECIMAL(10,2) NOT NULL,           -- calculado: cantidad × precio × (1 - descuento)

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_sale_items_sale ON vet_sale_items(sale_id);
```

---

### 5.10. Facturación electrónica SUNAT / NubeFact

```sql
-- Series por tipo de CPE — F001, B001, etc.
CREATE TABLE fel_series (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    tipo_cpe        SMALLINT NOT NULL
                    CHECK (tipo_cpe IN (1,3,7,8)),    -- 1=Factura, 3=Boleta, 7=NC, 8=ND
    serie           VARCHAR(4) NOT NULL,               -- 'F001', 'B001'
    correlativo_actual BIGINT NOT NULL DEFAULT 0,
    activo          BOOLEAN NOT NULL DEFAULT TRUE,

    CONSTRAINT uq_serie_tipo UNIQUE (tipo_cpe, serie)
);

-- ─────────────────────────────────────────────────────────────────────────────

-- Registro de comprobantes emitidos — INMUTABLE
CREATE TABLE fel_documents (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    venta_id        UUID        NOT NULL REFERENCES vet_sales(id),

    -- Datos del CPE
    tipo_cpe        SMALLINT NOT NULL,
    serie           VARCHAR(4) NOT NULL,
    correlativo     BIGINT NOT NULL,
    numero_completo VARCHAR(15) NOT NULL,              -- 'F001-00000127'
    fecha_emision   DATE NOT NULL,
    moneda          CHAR(3) NOT NULL DEFAULT 'PEN',

    -- Receptor
    receptor_tipo_doc   SMALLINT NOT NULL,             -- 6=RUC, 1=DNI
    receptor_num_doc    VARCHAR(15) NOT NULL,
    receptor_nombre     VARCHAR(200) NOT NULL,
    receptor_direccion  VARCHAR(255) NULL,

    -- Totales
    total_gravada   DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_exonerada DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_inafecta  DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_igv       DECIMAL(10,2) NOT NULL DEFAULT 0,
    total           DECIMAL(10,2) NOT NULL,

    -- Estado SUNAT
    estado          VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                    CHECK (estado IN (
                        'pendiente',          -- en cola, aún no enviado
                        'enviado',            -- enviado a NubeFact
                        'aceptado',           -- CDR código 0 — válido
                        'observado',          -- CDR con advertencias — válido tributariamente
                        'rechazado',          -- CDR error — inválido
                        'baja_pendiente',     -- solicitada anulación
                        'baja_aceptada'       -- anulación confirmada por SUNAT
                    )),

    -- Respuesta NubeFact
    nubefact_id         VARCHAR(100) NULL,            -- ID interno de NubeFact
    nubefact_url_pdf    VARCHAR(500) NULL,
    nubefact_url_xml    VARCHAR(500) NULL,
    nubefact_url_cdr    VARCHAR(500) NULL,
    nubefact_enlace_consulta VARCHAR(500) NULL,

    -- CDR SUNAT
    cdr_codigo          VARCHAR(10) NULL,             -- '0' = aceptado
    cdr_descripcion     TEXT NULL,
    cdr_notas           TEXT NULL,                    -- observaciones

    -- Control de reintentos (para el job)
    intentos_envio      SMALLINT NOT NULL DEFAULT 0,
    ultimo_intento_at   TIMESTAMPTZ NULL,
    error_mensaje       TEXT NULL,

    payload_enviado     JSONB NULL,                   -- payload completo enviado a NubeFact (debug)
    respuesta_recibida  JSONB NULL,                   -- respuesta raw de NubeFact (debug)

    emitido_at          TIMESTAMPTZ NULL,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    -- NO tiene updated_at — tabla inmutable. El estado cambia solo en una columna.
    -- Los campos de CDR se llenan una vez y no cambian.
    created_by_id       UUID NULL REFERENCES users(id) ON DELETE SET NULL,

    CONSTRAINT uq_fel_serie_correlativo UNIQUE (tipo_cpe, serie, correlativo)
);

CREATE INDEX idx_fel_estado    ON fel_documents(estado) WHERE estado IN ('pendiente','enviado');
CREATE INDEX idx_fel_fecha     ON fel_documents(fecha_emision DESC);
CREATE INDEX idx_fel_venta     ON fel_documents(venta_id);

-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE fel_document_items (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    document_id     UUID        NOT NULL REFERENCES fel_documents(id) ON DELETE CASCADE,

    numero_orden    SMALLINT NOT NULL,                -- orden de aparición en el CPE
    unidad_medida   VARCHAR(5) NOT NULL DEFAULT 'ZZ', -- ZZ=servicio, NIU=unidad
    codigo_producto VARCHAR(50) NULL,
    descripcion     VARCHAR(500) NOT NULL,
    cantidad        DECIMAL(10,3) NOT NULL,
    valor_unitario  DECIMAL(10,6) NOT NULL,           -- sin IGV — 6 decimales para precisión
    precio_unitario DECIMAL(10,6) NOT NULL,           -- con IGV
    descuento       DECIMAL(10,2) NOT NULL DEFAULT 0,
    tipo_igv        SMALLINT NOT NULL DEFAULT 1,      -- 1=gravado, 2=exonerado, 3=inafecto
    igv_monto       DECIMAL(10,2) NOT NULL DEFAULT 0,
    subtotal        DECIMAL(10,2) NOT NULL
);
```

---

### 5.11. Peluquería / Grooming

```sql
CREATE TABLE vet_grooming_services (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    patient_id      UUID        NOT NULL REFERENCES vet_patients(id),
    appointment_id  UUID        NULL REFERENCES vet_appointments(id),
    groomer_id      UUID        NOT NULL REFERENCES users(id),

    servicio_tipo   VARCHAR(30) NOT NULL
                    CHECK (servicio_tipo IN (
                        'bano_completo',
                        'bano_medicado',
                        'corte_estandar',
                        'corte_especial',
                        'deslanado',
                        'desparasitacion_externa',
                        'peluqueria_completa',
                        'otro'
                    )),

    estado          VARCHAR(20) NOT NULL DEFAULT 'recibido'
                    CHECK (estado IN ('recibido','en_proceso','listo','retirado')),

    hora_recepcion  TIMESTAMPTZ NULL,
    hora_listo      TIMESTAMPTZ NULL,
    hora_retiro     TIMESTAMPTZ NULL,

    -- Notificación WhatsApp automática cuando estado = 'listo'
    notificado_listo    BOOLEAN NOT NULL DEFAULT FALSE,
    notificado_listo_at TIMESTAMPTZ NULL,

    -- Observaciones físicas al recibir
    observaciones_recepcion TEXT NULL,               -- pelaje, parásitos, lesiones, nódulos
    observaciones_finales   TEXT NULL,               -- notas del groomer al terminar

    -- Fotos antes/después en Cloudflare R2
    -- ["https://pub-xxx.r2.dev/tenant/grooming/foto1.jpg", ...]
    fotos_antes_url JSONB NULL,
    fotos_despues_url JSONB NULL,

    precio          DECIMAL(10,2) NOT NULL,
    venta_id        UUID NULL,                       -- FK vet_sales

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_by_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_grooming_estado   ON vet_grooming_services(estado)
    WHERE estado IN ('recibido','en_proceso','listo');
CREATE INDEX idx_grooming_patient  ON vet_grooming_services(patient_id);
```

---

### 5.12. Configuración de la clínica

```sql
CREATE TABLE cfg_clinic_settings (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,

    -- Datos tributarios
    ruc             VARCHAR(11) NULL,
    razon_social    VARCHAR(200) NULL,
    nombre_comercial VARCHAR(150) NULL,
    direccion_fiscal VARCHAR(255) NULL,
    ubigeo_id       INTEGER NULL REFERENCES public.ubigeos(id),
    logo_url        VARCHAR(500) NULL,

    -- Configuración de agenda
    horario_atencion JSONB NOT NULL DEFAULT
        '{"lunes":{"inicio":"08:00","fin":"18:00"},"martes":{"inicio":"08:00","fin":"18:00"},"miercoles":{"inicio":"08:00","fin":"18:00"},"jueves":{"inicio":"08:00","fin":"18:00"},"viernes":{"inicio":"08:00","fin":"18:00"},"sabado":{"inicio":"09:00","fin":"13:00"},"domingo":null}'::JSONB,
    duracion_cita_default_min SMALLINT NOT NULL DEFAULT 30,
    intervalo_agenda_min      SMALLINT NOT NULL DEFAULT 15,

    -- NubeFact (encriptado en app layer)
    nubefact_token_enc  TEXT NULL,
    nubefact_ruc        VARCHAR(11) NULL,
    nubefact_configurado BOOLEAN NOT NULL DEFAULT FALSE,

    -- WhatsApp / Brevo
    twilio_account_sid_enc TEXT NULL,
    twilio_auth_token_enc  TEXT NULL,
    twilio_wa_from         VARCHAR(30) NULL,         -- 'whatsapp:+14155238886'
    brevo_api_key_enc      TEXT NULL,

    -- Recordatorios automáticos
    recordatorio_48h_activo BOOLEAN NOT NULL DEFAULT TRUE,
    recordatorio_2h_activo  BOOLEAN NOT NULL DEFAULT TRUE,
    recordatorio_vacuna_activo BOOLEAN NOT NULL DEFAULT TRUE,
    recordatorio_vacuna_dias_antes SMALLINT NOT NULL DEFAULT 7,

    -- Moneda y formatos
    moneda              CHAR(3) NOT NULL DEFAULT 'PEN',
    igv_porcentaje      DECIMAL(5,2) NOT NULL DEFAULT 18.00,

    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_by_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL
);
-- Solo existe 1 fila por tenant
```

---

### 5.13. Audit log

```sql
-- Inmutable — BIGSERIAL — solo INSERT
CREATE TABLE audit_logs (
    id              BIGSERIAL PRIMARY KEY,
    usuario_id      UUID NULL REFERENCES users(id) ON DELETE SET NULL,
    accion          VARCHAR(50) NOT NULL,             -- 'login','emitir_cpe','eliminar_paciente'
    tabla_afectada  VARCHAR(60) NULL,
    registro_id     VARCHAR(100) NULL,                -- UUID o número del registro
    datos_anteriores JSONB NULL,                      -- snapshot antes del cambio
    datos_nuevos     JSONB NULL,                      -- snapshot después del cambio
    ip_address      VARCHAR(45) NULL,
    user_agent      VARCHAR(300) NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_audit_usuario ON audit_logs(usuario_id, created_at DESC);
CREATE INDEX idx_audit_accion  ON audit_logs(accion, created_at DESC);
-- Particionar por mes si el volumen crece (>500K registros/mes)
```

---

## 6. Planes y gastos operativos incluidos

### Estructura del JSONB `costos_operativos` — fuentes reales

El campo `costos_operativos` en la tabla `plans` documenta exactamente **de dónde viene cada gasto** para que puedas:
- Revisar si subieron los precios de los proveedores y ajustar tu pricing
- Entender tu margen real antes de hacer un descuento
- Presentar transparencia si algún inversionista te pregunta

| Proveedor | Costo real | Fuente para verificar |
|-----------|-----------|----------------------|
| NubeFact (PSE SUNAT) | S/ 40/mes mínimo por cuenta | `nubefact.com/precios` |
| Twilio WhatsApp API | ~$0.005 USD/msg saliente | `twilio.com/whatsapp/pricing` |
| Brevo (email) | Gratis hasta 300 emails/día | `brevo.com/pricing` |
| Cloudflare R2 | Gratis primeros 10 GB, $0.015/GB extra | `cloudflare.com/developer-platform/r2` |
| VPS (proporción) | S/ 15–30 según carga | Costo total VPS ÷ tenants activos |
| Sentry (errores) | Gratis plan dev, $26/mes pro | `sentry.io/pricing` |

### Resumen de márgenes por plan

```
Plan Free     →  Ingreso: S/ 0    | Costo: ~S/ 8   | Margen: negativo (plan gancho)
Plan Starter  →  Ingreso: S/ 149  | Costo: ~S/ 70  | Margen: ~S/ 79  (53%)
Plan Pro      →  Ingreso: S/ 249  | Costo: ~S/ 124 | Margen: ~S/ 125 (50%)
Plan Clínica  →  Ingreso: S/ 399  | Costo: ~S/ 194 | Margen: ~S/ 205 (51%)
```

> **Nota crítica sobre NubeFact:** El costo de S/ 40/mes es **por tu cuenta ORVAE como integrador**, no por tenant. Si tienes 50 tenants Starter y todos emiten menos de 500 CPE/mes entre todos, pagas S/ 40 total. Pero si un tenant Clínica emite muchos comprobantes, puede subir. Revisar el tier por volumen en `nubefact.com/precios#volumen`.

### Cómo leer los límites del plan en Laravel

```php
// app/Services/PlanLimitService.php

class PlanLimitService
{
    public function puedeAgregarPaciente(): bool
    {
        $plan = auth()->user()->tenant->subscription->plan;
        $max  = data_get($plan->features, 'max_pacientes', 0);

        if ($max === -1) return true; // -1 = ilimitado

        $actual = VetPatient::count();
        return $actual < $max;
    }

    public function puedeEmitirCPE(): bool
    {
        $features = auth()->user()->tenant->subscription->plan->features;
        return (bool) data_get($features, 'facturacion_sunat', false);
    }
}
```

---

## 7. Índices críticos

### Índices adicionales de rendimiento

```sql
-- Búsqueda de agenda por rango de fechas (vista semanal/mensual)
CREATE INDEX idx_appointments_rango
    ON vet_appointments(fecha_hora_inicio, fecha_hora_fin)
    WHERE deleted_at IS NULL;

-- Stock bajo mínimo — para alerta de reposición
CREATE INDEX idx_stock_bajo_minimo
    ON vet_stock_items(product_id)
    WHERE cantidad <= stock_minimo;

-- Facturas pendientes de reintento (job de reintentos)
CREATE INDEX idx_fel_reintentos
    ON fel_documents(ultimo_intento_at)
    WHERE estado IN ('pendiente','rechazado') AND intentos_envio < 3;

-- Propietario por número de documento (lookup en caja/citas)
CREATE UNIQUE INDEX idx_owner_doc_activo
    ON vet_owners(tipo_documento, numero_documento)
    WHERE deleted_at IS NULL;
```

---

## 8. Triggers y funciones PostgreSQL

### 8.1. Auto-actualizar `updated_at`

```sql
-- Función reutilizable en todos los schemas
CREATE OR REPLACE FUNCTION trigger_set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Aplicar a cada tabla que tenga updated_at
-- Ejemplo para vet_patients:
CREATE TRIGGER trg_vet_patients_updated_at
    BEFORE UPDATE ON vet_patients
    FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();
```

### 8.2. Actualizar peso del paciente al registrar historia clínica

```sql
CREATE OR REPLACE FUNCTION sync_patient_weight()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.peso_kg IS NOT NULL THEN
        UPDATE vet_patients
        SET peso_ultimo_kg = NEW.peso_kg,
            peso_fecha     = DATE(NEW.fecha_atencion),
            updated_at     = NOW()
        WHERE id = NEW.patient_id;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_sync_patient_weight
    AFTER INSERT ON vet_clinical_records
    FOR EACH ROW EXECUTE FUNCTION sync_patient_weight();
```

### 8.3. Actualizar stock al insertar movimiento

```sql
CREATE OR REPLACE FUNCTION sync_stock_after_movement()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE vet_stock_items
    SET cantidad   = NEW.cantidad_resultante,
        updated_at = NOW()
    WHERE id = NEW.stock_item_id;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_sync_stock
    AFTER INSERT ON vet_stock_movements
    FOR EACH ROW EXECUTE FUNCTION sync_stock_after_movement();
```

### 8.4. Correlativo de venta — thread-safe

```sql
-- Función para obtener el próximo número de venta sin race condition
CREATE OR REPLACE FUNCTION next_venta_numero()
RETURNS VARCHAR AS $$
DECLARE
    v_anio  TEXT := TO_CHAR(NOW(), 'YYYY');
    v_count BIGINT;
BEGIN
    SELECT COUNT(*) + 1
    INTO v_count
    FROM vet_sales
    WHERE EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM NOW());

    RETURN 'VTA-' || v_anio || '-' || LPAD(v_count::TEXT, 4, '0');
END;
$$ LANGUAGE plpgsql;

-- Uso en Laravel:
-- $numero = DB::selectOne("SELECT next_venta_numero() AS numero")->numero;
```

---

## 9. Diagrama de relaciones por dominio

```
SCHEMA PUBLIC (global)
══════════════════════
plans ──────────────────────────────────┐
                                        │
tenants ──── subscriptions ─── plan_id ─┘
   │
   └── schema_name → SET search_path → schema tenant

SCHEMA TENANT (por clínica)
════════════════════════════

                    ┌──── vet_vaccinations
                    │
users ──────────────┤
   │                │
   ├─ (vet_id) ─────┼──── vet_clinical_records ──── vet_prescriptions
   │                │              │
   │                │              │
vet_owners ─────────┤         vet_sale_items
   │                │              │
   └── vet_patients ┼──── vet_appointments        vet_sales ──── fel_documents
              │     │                                │                 │
              │     └──── vet_grooming_services      │           fel_document_items
              │                                      │
              │                              vet_products ──── vet_stock_items
              │                                                      │
              └────────────────────────────────────── vet_stock_movements

cfg_clinic_settings (1 fila por tenant)
audit_logs          (inmutable, BIGSERIAL)
fel_series          (correlativo por tipo de CPE)
```

---

## 10. Migraciones Laravel — orden de ejecución

```
Orden  Archivo de migración
─────  ────────────────────────────────────────────────────────
001    create_public_ubigeos_table.php
002    create_public_plans_table.php
003    create_public_tenants_table.php
004    create_public_subscriptions_table.php

─── A partir de aquí, cada migración va dentro del schema del tenant ───

005    create_users_table.php
006    create_cfg_clinic_settings_table.php
007    create_vet_owners_table.php
008    create_vet_patients_table.php
009    create_vet_appointments_table.php             ← sin FK a clinical_records aún
010    create_vet_clinical_records_table.php
011    alter_vet_appointments_add_historia_fk.php    ← agrega FK circular
012    create_vet_vaccinations_table.php
013    create_vet_prescriptions_table.php
014    create_vet_products_table.php
015    create_vet_stock_items_table.php
016    create_vet_stock_movements_table.php
017    create_vet_sales_table.php
018    create_vet_sale_items_table.php
019    alter_vet_clinical_records_add_venta_fk.php   ← FK circular
020    create_vet_grooming_services_table.php
021    create_fel_series_table.php
022    create_fel_documents_table.php
023    create_fel_document_items_table.php
024    create_audit_logs_table.php
025    create_triggers_and_functions.php             ← ejecuta el SQL de triggers
026    seed_plans_table.php                          ← INSERT de los 4 planes con JSONB
027    seed_ubigeos_table.php                        ← 1,874 distritos del INEI
```

---

*ORVAE Software · Documento interno · Arquitectura VetSaaS Peru v1.0 · Mayo 2026*
