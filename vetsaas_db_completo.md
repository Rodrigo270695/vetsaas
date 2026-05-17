# VetSaaS Peru — Arquitectura Completa de Base de Datos
**PostgreSQL 16 · Multi-Tenant por Schema · UUID v4 · Producción-Ready**
> ORVAE Software | Laravel 12 + PostgreSQL 16 + Redis 7 | Mayo 2026
> Arquitecto Senior — Documento definitivo v2.1

---

## Índice general

### PARTE I — FUNDAMENTOS
1. [Filosofía y principios de diseño](#1-filosofía-y-principios-de-diseño)
2. [Arquitectura Multi-Tenant](#2-arquitectura-multi-tenant)
3. [Convenciones y tipos de datos](#3-convenciones-y-tipos-de-datos)

### PARTE II — SCHEMA PUBLIC (SaaS Global)
4. [tenants — Clínicas registradas](#4-tenants)
5. [plans — Planes de suscripción](#5-plans)
6. [plan_features — Features por plan](#6-plan_features)
7. [subscriptions — Suscripciones activas](#7-subscriptions)
8. [subscription_payments — Historial de cobros](#8-subscription_payments)
9. [promo_codes — Códigos de descuento](#9-promo_codes)
10. [ubigeos — Catálogo INEI Peru](#10-ubigeos)
11. [global_notifications — Avisos del SaaS](#11-global_notifications)

### PARTE III — SCHEMA TENANT (Por clínica)
#### Usuarios y acceso
12. [users — Personal de la clínica](#12-users)
13. [password_reset_tokens](#13-password_reset_tokens)
14. [sessions — Sesiones activas](#14-sessions)
15. [personal_access_tokens — API tokens](#15-personal_access_tokens)

#### Configuración de la clínica
16. [cfg_clinic_settings — Configuración general](#16-cfg_clinic_settings)
17. [cfg_sedes — Sucursales](#17-cfg_sedes)
18. [cfg_horarios — Horarios por veterinario/sede](#18-cfg_horarios)
19. [cfg_bloqueos_agenda — Bloqueos de agenda](#19-cfg_bloqueos_agenda)
20. [cfg_tarifas — Tarifas por tipo de consulta](#20-cfg_tarifas)
21. [cfg_recordatorio_templates — Plantillas de mensajes](#21-cfg_recordatorio_templates)

#### Clientes y pacientes
22. [vet_owners — Propietarios](#22-vet_owners)
23. [vet_patients — Mascotas/Pacientes](#23-vet_patients)
24. [vet_patient_owners — Propietarios adicionales](#24-vet_patient_owners)
25. [vet_patient_documents — Documentos del paciente](#25-vet_patient_documents)
26. [vet_owner_consents — Consentimientos Ley 29733](#26-vet_owner_consents)

#### Agenda y citas
27. [vet_appointments — Citas](#27-vet_appointments)
28. [vet_appointment_history — Historial de cambios de cita](#28-vet_appointment_history)
29. [vet_waiting_list — Lista de espera](#29-vet_waiting_list)

#### Historia clínica
30. [vet_clinical_records — Historia clínica SOAP](#30-vet_clinical_records)
31. [vet_vaccinations — Vacunaciones](#31-vet_vaccinations)
32. [vet_vaccination_protocols — Protocolos vacunales](#32-vet_vaccination_protocols)
33. [vet_prescriptions — Recetas médicas](#33-vet_prescriptions)
34. [vet_lab_orders — Órdenes de laboratorio](#34-vet_lab_orders)
35. [vet_lab_results — Resultados de laboratorio](#35-vet_lab_results)
36. [vet_surgeries — Registro quirúrgico](#36-vet_surgeries)
37. [vet_hospitalizations — Hospitalizaciones](#37-vet_hospitalizations)
38. [vet_vital_signs_log — Log de signos vitales en hospitalización](#38-vet_vital_signs_log)

#### Inventario y compras
39. [vet_suppliers — Proveedores](#39-vet_suppliers)
40. [vet_products — Catálogo de productos y servicios](#40-vet_products)
41. [vet_product_categories — Categorías](#41-vet_product_categories)
42. [vet_stock_items — Lotes de stock](#42-vet_stock_items)
43. [vet_stock_movements — Kardex (inmutable)](#43-vet_stock_movements)
44. [vet_stock_alerts — Alertas de stock](#44-vet_stock_alerts)
45. [vet_purchases — Órdenes de compra a proveedor](#45-vet_purchases)
46. [vet_purchase_items — Detalle de compra](#46-vet_purchase_items)

#### Ventas y caja
47. [vet_sales — Ventas / Tickets de cobro](#47-vet_sales)
48. [vet_sale_items — Detalle de venta](#48-vet_sale_items)
49. [vet_payments — Pagos recibidos](#49-vet_payments)
50. [vet_cash_sessions — Sesiones de caja](#50-vet_cash_sessions)
51. [vet_discounts — Descuentos aplicables](#51-vet_discounts)

#### Facturación electrónica SUNAT
52. [fel_series — Series de comprobantes](#52-fel_series)
53. [fel_documents — Comprobantes emitidos (inmutable)](#53-fel_documents)
54. [fel_document_items — Detalle del comprobante](#54-fel_document_items)
55. [fel_void_requests — Comunicaciones de baja](#55-fel_void_requests)
56. [fel_summary_documents — Resumen boletas batch](#56-fel_summary_documents)

#### Peluquería y servicios adicionales
57. [vet_grooming_services — Peluquería/Grooming](#57-vet_grooming_services)
58. [vet_grooming_packages — Paquetes de peluquería](#58-vet_grooming_packages)
59. [vet_boarding — Guardería/Hotel para mascotas](#59-vet_boarding)
60. [vet_boarding_daily_logs — Control diario de guardería](#60-vet_boarding_daily_logs)

#### Comunicaciones y notificaciones
61. [notifications_queue — Cola de mensajes a enviar](#61-notifications_queue)
62. [notifications_sent — Historial de mensajes enviados](#62-notifications_sent)
63. [notifications_templates — Plantillas personalizadas](#63-notifications_templates)

#### Reportes y métricas
64. [report_snapshots — Snapshots de métricas diarias](#64-report_snapshots)
65. [mv_dashboard_metrics — Vista materializada del dashboard](#65-mv_dashboard_metrics)

#### Auditoría y seguridad
66. [audit_logs — Log de auditoría (inmutable)](#66-audit_logs)
67. [login_attempts — Control de intentos de acceso](#67-login_attempts)
68. [api_request_logs — Log de uso del API](#68-api_request_logs)

### PARTE IV — LÓGICA DE NEGOCIO
69. [Planes y gastos operativos detallados](#69-planes-y-gastos-operativos-detallados)
70. [Triggers y funciones PostgreSQL](#70-triggers-y-funciones-postgresql)
71. [Índices críticos de rendimiento](#71-índices-críticos-de-rendimiento)
72. [Vistas materializadas](#72-vistas-materializadas)
73. [Políticas de particionamiento](#73-políticas-de-particionamiento)
74. [Orden de migraciones Laravel](#74-orden-de-migraciones-laravel)
75. [Política de auditoría, retención e inmutabilidad en BD](#75-política-de-auditoría-retención-e-inmutabilidad-en-bd)
76. [Operación multi-tenant: conexiones, pool y migraciones](#76-operación-multi-tenant-conexiones-pool-y-migraciones)

---

## PARTE I — FUNDAMENTOS

---

## 1. Filosofía y principios de diseño

### Principios que guían cada decisión de arquitectura

| Principio | Implementación | Razón |
|-----------|---------------|-------|
| **UUID v4 en entidades de negocio** | `gen_random_uuid()` nativo PostgreSQL 16 | Sin extensión `uuid-ossp`, seguro, no predecible en API |
| **BIGSERIAL en tablas de volumen** | `audit_logs`, `stock_movements`, `notifications_sent` | Millones de filas, nunca expuestas al API externo |
| **Soft delete obligatorio** | `deleted_at TIMESTAMPTZ NULL` | Nunca perder datos clínicos — regulación veterinaria |
| **TIMESTAMPTZ siempre** | En toda columna de fecha+hora | Perú es UTC-5, el servidor puede estar en US-East |
| **JSONB para datos flexibles** | Historia clínica, config, features | Evitar EAV (Entity-Attribute-Value) — el peor antipatrón |
| **Snapshot en ventas** | `descripcion_snapshot`, `igv_tipo_snapshot` | Los precios cambian — la venta histórica no debe cambiar |
| **Inmutabilidad financiera** | `fel_documents`, `stock_movements` — solo INSERT | Integridad contable y tributaria |
| **Snake_case universal** | Tablas, columnas, índices, funciones | Consistencia con Laravel + PostgreSQL |
| **Prefijos de dominio** | `vet_`, `fel_`, `cfg_`, `rpt_` | Claridad visual en psql/DBeaver con muchas tablas |
| **Auditoría en toda tabla** | `created_at`, `updated_at`, `deleted_at`, `created_by_id` | Trazabilidad completa |
| **Auditoría fuerte a nivel BD** | `audit_logs` solo INSERT; rol de app sin UPDATE/DELETE; columnas `origen` + contexto | Evita repudio y borrado silencioso aunque falle la app |
| **Columnas generadas (GENERATED)** | Solo expresiones **inmutables** en PostgreSQL | `NOW()` en `GENERATED STORED` **no es válido** — usar columna normal + trigger |
| **CHECK constraints en enums críticos** | Estados, tipos, roles | Validación en DB — no solo en app |
| **Índices parciales** | `WHERE deleted_at IS NULL` | Más pequeños, más rápidos — ignoran registros borrados |

### Qué NO hacer (antipatrones evitados)

```sql
-- ❌ NUNCA guardar dinero en FLOAT o REAL
precio FLOAT  -- ERROR: 149.99 puede ser 149.98999999 en binario

-- ✅ SIEMPRE DECIMAL exacto
precio DECIMAL(10, 2)

-- ❌ NUNCA usar TIMESTAMP sin zona horaria
created_at TIMESTAMP

-- ✅ SIEMPRE timezone-aware
created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()

-- ❌ NUNCA borrar registros clínicos o financieros
DELETE FROM vet_clinical_records WHERE id = '...';

-- ✅ Soft delete siempre
UPDATE vet_clinical_records SET deleted_at = NOW() WHERE id = '...';

-- ❌ NUNCA auto-increment expuesto al API (predecible, enumerable)
GET /api/patients/1234  -- el cliente sabe que existe el 1233

-- ✅ UUID en el API
GET /api/patients/550e8400-e29b-41d4-a716-446655440000
```

---

## 2. Arquitectura Multi-Tenant

### Estrategia: Schema por Tenant en PostgreSQL

```
PostgreSQL 16 — Servidor VPS ORVAE
│
├── Schema: public                      ← Tablas SaaS globales
│   ├── tenants                         ← Cada clínica registrada
│   ├── plans                           ← Planes de suscripción
│   ├── plan_features                   ← Features por plan
│   ├── subscriptions                   ← Suscripción activa por tenant
│   ├── subscription_payments           ← Historial de pagos
│   ├── promo_codes                     ← Códigos de descuento
│   ├── ubigeos                         ← 1,874 distritos INEI Peru
│   └── global_notifications            ← Avisos del SaaS a todos los tenants
│
├── Schema: vet_a1b2c3                  ← "Veterinaria Los Andes - Chiclayo"
│   ├── [usuarios y acceso]
│   ├── [configuración de clínica]
│   ├── [clientes y pacientes]
│   ├── [agenda y citas]
│   ├── [historia clínica]
│   ├── [inventario y compras]
│   ├── [ventas y caja]
│   ├── [facturación SUNAT]
│   ├── [peluquería y servicios]
│   ├── [comunicaciones]
│   └── [auditoría]
│
├── Schema: vet_d4e5f6                  ← "PetClinic Trujillo"
│   └── (misma estructura — aislada)
│
└── Schema: vet_g7h8i9                  ← "Clínica Veterinaria Lima Norte"
    └── (misma estructura — aislada)
```

### Por qué schema por tenant

| Criterio | Shared DB + tenant_id | Schema por tenant ✅ | DB dedicada |
|----------|----------------------|---------------------|-------------|
| Aislamiento de datos | Bajo (depende del WHERE) | Alto (PostgreSQL lo garantiza) | Muy alto |
| Riesgo de data leak | Alto si falta el WHERE | Nulo | Nulo |
| Costo de infra | Muy bajo | Bajo | Alto |
| Backup granular | No | Sí (pg_dump por schema) | Sí |
| Escalabilidad | Alta | Media → migración sencilla | Alta |
| Complejidad de dev | Baja | Media | Alta |
| RLS necesario | Sí (obligatorio) | No | No |

### Cómo funciona en Laravel

```php
// El tenant se resuelve del subdominio en cada request:
// vetlosandes.orvae.pe → slug 'vetlosandes' → schema 'vet_a1b2c3'

// Middleware: app/Http/Middleware/SetTenantScope.php
public function handle(Request $request, Closure $next): Response
{
    $host   = $request->getHost();                        // vetlosandes.orvae.pe
    $slug   = explode('.', $host)[0];                    // vetlosandes
    $tenant = Tenant::where('slug', $slug)
                    ->where('estado', '!=', 'cancelled')
                    ->firstOrFail();

    // Verificar que la suscripción esté activa
    abort_if($tenant->subscription->estado === 'suspended', 402, 'Suscripción suspendida');

    // NUNCA interpolar schema sin validar — riesgo de inyección SQL si el valor se corrompe en BD
    $schema = $tenant->schema_name;
    abort_unless(
        is_string($schema) && preg_match('/^[a-z_][a-z0-9_]{0,62}$/i', $schema),
        500,
        'Schema de tenant inválido'
    );
    // PostgreSQL: identificador entre comillas dobles si en el futuro usas mayúsculas (no recomendado)
    DB::statement('SET search_path TO "' . str_replace('"', '', $schema) . '", public');

    // Compartir el tenant con toda la aplicación
    app()->instance('tenant', $tenant);

    return $next($request);
}
```

### Creación de schema al registrar una nueva clínica

```php
// app/Services/TenantProvisioningService.php
public function provision(Tenant $tenant): void
{
    $schema = $tenant->schema_name; // 'vet_' . Str::random(6)

    DB::transaction(function () use ($schema, $tenant) {
        // 1. Crear schema
        DB::statement("CREATE SCHEMA IF NOT EXISTS {$schema}");

        // 2. Aplicar todas las migraciones del tenant en el nuevo schema
        DB::statement("SET search_path TO {$schema}, public");
        Artisan::call('migrate', [
            '--path'     => 'database/migrations/tenant',
            '--force'    => true,
        ]);

        // 3. Insertar configuración inicial (una sola fila; índice único uq_cfg_clinic_settings_single_row)
        DB::table('cfg_clinic_settings')->insert([
            'id'         => Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Crear usuario admin de la clínica
        DB::table('users')->insert([...]);
    });
}
```

---

## 3. Convenciones y tipos de datos

### Referencia rápida de tipos

```sql
-- ══════════════════════════════════════════════════════
-- IDENTIFICADORES
-- ══════════════════════════════════════════════════════
id UUID DEFAULT gen_random_uuid() PRIMARY KEY
-- Para tablas de alto volumen (logs, movimientos):
id BIGSERIAL PRIMARY KEY

-- ══════════════════════════════════════════════════════
-- DINERO — SIEMPRE DECIMAL, NUNCA FLOAT
-- ══════════════════════════════════════════════════════
precio          DECIMAL(10, 2)   -- hasta 99,999,999.99 soles
precio_unitario DECIMAL(10, 6)   -- en comprobantes SUNAT (6 decimales requeridos)
cantidad_stock  DECIMAL(10, 3)   -- permite fracciones: ml, kg, gr
porcentaje      DECIMAL(5,  2)   -- 18.00%, 99.99%

-- ══════════════════════════════════════════════════════
-- FECHAS — SIEMPRE TIMESTAMPTZ
-- ══════════════════════════════════════════════════════
created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
deleted_at  TIMESTAMPTZ NULL          -- NULL = registro activo
fecha_pago  TIMESTAMPTZ NULL          -- con hora
solo_fecha  DATE         NOT NULL     -- sin hora (nacimiento, vencimiento)

-- ══════════════════════════════════════════════════════
-- AUDITORÍA — En TODA tabla de negocio
-- ══════════════════════════════════════════════════════
created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
deleted_at    TIMESTAMPTZ NULL,
created_by_id UUID NULL REFERENCES users(id) ON DELETE SET NULL,
updated_by_id UUID NULL REFERENCES users(id) ON DELETE SET NULL

-- ══════════════════════════════════════════════════════
-- TEXTOS
-- ══════════════════════════════════════════════════════
nombre    VARCHAR(100)   -- nombre de persona
email     VARCHAR(150)   -- email
telefono  VARCHAR(20)    -- con prefijo internacional
codigo    VARCHAR(50)    -- códigos internos
url       VARCHAR(500)   -- URLs de archivos en R2
texto_largo TEXT         -- descripciones, notas, observaciones clínicas
jsonb_col   JSONB        -- datos estructurados flexibles
```

---

## PARTE II — SCHEMA PUBLIC (SaaS Global)

> **Integración Orvae PE:** La venta y renovación de planes frente al cliente ORVAE se gestionan en el proyecto **Orvae PE** (mismo patrón que Aula Virtual: checkout + `*PlanProvisioner` con llamada firmada al SaaS). VetSaaS expone un endpoint de **aprovisionamiento** que crea `tenants`, schema tenant y usuario inicial; las tablas `subscriptions` / `subscription_payments` de este schema sirven como **estado operativo** en la app veterinaria (y pueden sincronizarse o enriquecerse desde Orvae). El enlace de ingreso al subdominio lo genera el flujo de Orvae y/o la respuesta del provision de VetSaaS.

---

## 4. `tenants`

```sql
CREATE TABLE public.tenants (
    id               UUID        DEFAULT gen_random_uuid() PRIMARY KEY,

    -- Identificación
    slug             VARCHAR(60) NOT NULL UNIQUE,
    -- slug = subdominio: 'vetlosandes' → vetlosandes.orvae.pe
    -- Solo letras minúsculas, números y guiones
    CONSTRAINT chk_slug_format CHECK (slug ~ '^[a-z0-9\-]+$'),

    schema_name      VARCHAR(60) NOT NULL UNIQUE,
    -- schema_name = 'vet_' || random(6) → 'vet_a1b2c3'
    -- Nunca cambiar después de creado

    -- Datos de la clínica
    razon_social     VARCHAR(200) NOT NULL,
    nombre_comercial VARCHAR(150) NULL,
    ruc              VARCHAR(11)  NULL UNIQUE,
    CONSTRAINT chk_ruc_format CHECK (ruc ~ '^\d{11}$' OR ruc IS NULL),

    email_admin      VARCHAR(150) NOT NULL UNIQUE,
    telefono         VARCHAR(20)  NULL,
    ubigeo_id        INTEGER      NULL REFERENCES public.ubigeos(id),
    direccion        VARCHAR(255) NULL,
    logo_url         VARCHAR(500) NULL,

    -- Configuración SUNAT/NubeFact (encriptado AES-256 en capa de aplicación)
    nubefact_token_enc  TEXT NULL,
    nubefact_ruc        VARCHAR(11) NULL,
    sunat_configurado   BOOLEAN NOT NULL DEFAULT FALSE,

    -- Estado del tenant
    estado           VARCHAR(20) NOT NULL DEFAULT 'trial'
                     CHECK (estado IN ('trial','active','suspended','cancelled')),

    trial_ends_at    TIMESTAMPTZ NULL,
    suspended_at     TIMESTAMPTZ NULL,
    suspension_reason TEXT NULL,
    cancelled_at     TIMESTAMPTZ NULL,
    cancel_reason    TEXT NULL,

    -- Onboarding wizard (5 pasos)
    onboarding_completado BOOLEAN NOT NULL DEFAULT FALSE,
    onboarding_paso       SMALLINT NOT NULL DEFAULT 0
                          CHECK (onboarding_paso BETWEEN 0 AND 5),
    -- Paso 0: datos clínica | 1: SUNAT | 2: primer user | 3: primer paciente
    -- 4: primera cita | 5: completado

    -- Localización
    timezone         VARCHAR(50) NOT NULL DEFAULT 'America/Lima',
    locale           VARCHAR(10) NOT NULL DEFAULT 'es_PE',

    -- Metadata de adquisición (para analytics de marketing)
    canal_adquisicion VARCHAR(50) NULL,
    -- 'facebook_ads','google','referido','organico','tiktok'
    referido_por_tenant_id UUID NULL REFERENCES public.tenants(id),

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at  TIMESTAMPTZ NULL
);

CREATE INDEX idx_tenants_slug    ON public.tenants(slug)   WHERE deleted_at IS NULL;
CREATE INDEX idx_tenants_estado  ON public.tenants(estado) WHERE deleted_at IS NULL;
CREATE INDEX idx_tenants_trial   ON public.tenants(trial_ends_at)
    WHERE estado = 'trial';
```

---

## 5. `plans`

```sql
CREATE TABLE public.plans (
    id               UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    codigo           VARCHAR(30) NOT NULL UNIQUE,
    -- 'free' | 'starter' | 'pro' | 'clinica'

    nombre           VARCHAR(80)  NOT NULL,
    descripcion      TEXT NULL,
    badge            VARCHAR(50)  NULL,   -- 'Más popular', 'Mejor valor'
    color_hex        VARCHAR(7)   NULL,   -- '#0F6E56' para la UI

    -- Precios en soles sin IGV
    precio_mensual   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    precio_anual     DECIMAL(10,2) NULL,
    -- Si precio_anual < precio_mensual × 12 → hay descuento anual

    -- Período de prueba
    trial_days       SMALLINT NOT NULL DEFAULT 0,

    -- Orden de aparición en la landing page
    orden            SMALLINT NOT NULL DEFAULT 0,

    -- ¿Aparece en la landing pública?
    es_publico       BOOLEAN NOT NULL DEFAULT TRUE,
    -- FALSE = plan enterprise negociado manualmente

    activo           BOOLEAN NOT NULL DEFAULT TRUE,

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Datos semilla de los 4 planes
INSERT INTO public.plans
    (codigo, nombre, descripcion, badge, color_hex, precio_mensual, trial_days, orden)
VALUES
    ('free',    'Free',    'Para conocer el sistema sin compromiso', NULL, '#6B7280', 0.00, 0, 1),
    ('starter', 'Starter', 'Para clínicas pequeñas que inician su digitalización', NULL, '#0F6E56', 149.00, 14, 2),
    ('pro',     'Pro',     'Para clínicas en crecimiento con facturación activa', 'Más popular', '#1D4ED8', 249.00, 14, 3),
    ('clinica', 'Clínica', 'Para clínicas grandes con múltiples sedes y equipo', 'Mejor valor', '#7C3AED', 399.00, 7, 4);
```

---

## 6. `plan_features`

```sql
-- Tabla separada para features del plan
-- Ventaja vs JSONB puro: se puede hacer JOIN, filtrar, y cambiar sin tocar el JSONB
-- Mantiene JSONB en plans.features como caché de lectura rápida

CREATE TABLE public.plan_features (
    id       UUID    DEFAULT gen_random_uuid() PRIMARY KEY,
    plan_id  UUID    NOT NULL REFERENCES public.plans(id) ON DELETE CASCADE,

    feature  VARCHAR(60) NOT NULL,
    -- Catálogo de features posibles:
    -- 'max_pacientes'        → INTEGER (-1 = ilimitado)
    -- 'max_usuarios'         → INTEGER (-1 = ilimitado)
    -- 'max_citas_mes'        → INTEGER (-1 = ilimitado)
    -- 'max_cpe_mes'          → INTEGER (-1 = ilimitado, 0 = no disponible)
    -- 'max_wa_mes'           → INTEGER (-1 = ilimitado, 0 = no disponible)
    -- 'historia_clinica'     → BOOLEAN
    -- 'facturacion_sunat'    → BOOLEAN
    -- 'modulo_stock'         → BOOLEAN
    -- 'modulo_grooming'      → BOOLEAN
    -- 'modulo_guarderia'     → BOOLEAN
    -- 'modulo_laboratorio'   → BOOLEAN
    -- 'multi_sede'           → BOOLEAN
    -- 'api_acceso'           → BOOLEAN
    -- 'reportes_avanzados'   → BOOLEAN
    -- 'pwa_propietario'      → BOOLEAN
    -- 'soporte_tipo'         → STRING: 'docs'|'email'|'whatsapp'|'whatsapp_prioritario'
    -- 'backup_frecuencia'    → STRING: 'semanal'|'diario'|'tiempo_real'

    valor_int     INTEGER     NULL,   -- para límites numéricos
    valor_bool    BOOLEAN     NULL,   -- para flags on/off
    valor_str     VARCHAR(50) NULL,   -- para valores de texto

    CONSTRAINT uq_plan_feature UNIQUE (plan_id, feature)
);

-- Semilla: Plan Free
WITH p AS (SELECT id FROM public.plans WHERE codigo = 'free')
INSERT INTO public.plan_features (plan_id, feature, valor_int, valor_bool, valor_str)
SELECT p.id, feature, vi, vb, vs FROM p, (VALUES
    ('max_pacientes',      50,   NULL,  NULL),
    ('max_usuarios',       1,    NULL,  NULL),
    ('max_citas_mes',      100,  NULL,  NULL),
    ('max_cpe_mes',        0,    NULL,  NULL),
    ('max_wa_mes',         0,    NULL,  NULL),
    ('historia_clinica',   NULL, TRUE,  NULL),
    ('facturacion_sunat',  NULL, FALSE, NULL),
    ('modulo_stock',       NULL, FALSE, NULL),
    ('modulo_grooming',    NULL, FALSE, NULL),
    ('modulo_guarderia',   NULL, FALSE, NULL),
    ('modulo_laboratorio', NULL, FALSE, NULL),
    ('multi_sede',         NULL, FALSE, NULL),
    ('api_acceso',         NULL, FALSE, NULL),
    ('reportes_avanzados', NULL, FALSE, NULL),
    ('soporte_tipo',       NULL, NULL,  'docs')
) AS t(feature, vi, vb, vs);

-- Semilla: Plan Starter
WITH p AS (SELECT id FROM public.plans WHERE codigo = 'starter')
INSERT INTO public.plan_features (plan_id, feature, valor_int, valor_bool, valor_str)
SELECT p.id, feature, vi, vb, vs FROM p, (VALUES
    ('max_pacientes',      300,  NULL,  NULL),
    ('max_usuarios',       2,    NULL,  NULL),
    ('max_citas_mes',      500,  NULL,  NULL),
    ('max_cpe_mes',        100,  NULL,  NULL),
    ('max_wa_mes',         50,   NULL,  NULL),
    ('historia_clinica',   NULL, TRUE,  NULL),
    ('facturacion_sunat',  NULL, TRUE,  NULL),
    ('modulo_stock',       NULL, TRUE,  NULL),
    ('modulo_grooming',    NULL, FALSE, NULL),
    ('modulo_guarderia',   NULL, FALSE, NULL),
    ('modulo_laboratorio', NULL, FALSE, NULL),
    ('multi_sede',         NULL, FALSE, NULL),
    ('api_acceso',         NULL, FALSE, NULL),
    ('reportes_avanzados', NULL, FALSE, NULL),
    ('soporte_tipo',       NULL, NULL,  'email')
) AS t(feature, vi, vb, vs);

-- Semilla: Plan Pro
WITH p AS (SELECT id FROM public.plans WHERE codigo = 'pro')
INSERT INTO public.plan_features (plan_id, feature, valor_int, valor_bool, valor_str)
SELECT p.id, feature, vi, vb, vs FROM p, (VALUES
    ('max_pacientes',      -1,   NULL,  NULL),
    ('max_usuarios',       5,    NULL,  NULL),
    ('max_citas_mes',      -1,   NULL,  NULL),
    ('max_cpe_mes',        300,  NULL,  NULL),
    ('max_wa_mes',         -1,   NULL,  NULL),
    ('historia_clinica',   NULL, TRUE,  NULL),
    ('facturacion_sunat',  NULL, TRUE,  NULL),
    ('modulo_stock',       NULL, TRUE,  NULL),
    ('modulo_grooming',    NULL, TRUE,  NULL),
    ('modulo_guarderia',   NULL, FALSE, NULL),
    ('modulo_laboratorio', NULL, TRUE,  NULL),
    ('multi_sede',         NULL, FALSE, NULL),
    ('api_acceso',         NULL, FALSE, NULL),
    ('reportes_avanzados', NULL, TRUE,  NULL),
    ('soporte_tipo',       NULL, NULL,  'whatsapp')
) AS t(feature, vi, vb, vs);

-- Semilla: Plan Clínica
WITH p AS (SELECT id FROM public.plans WHERE codigo = 'clinica')
INSERT INTO public.plan_features (plan_id, feature, valor_int, valor_bool, valor_str)
SELECT p.id, feature, vi, vb, vs FROM p, (VALUES
    ('max_pacientes',      -1,   NULL,  NULL),
    ('max_usuarios',       -1,   NULL,  NULL),
    ('max_citas_mes',      -1,   NULL,  NULL),
    ('max_cpe_mes',        -1,   NULL,  NULL),
    ('max_wa_mes',         -1,   NULL,  NULL),
    ('historia_clinica',   NULL, TRUE,  NULL),
    ('facturacion_sunat',  NULL, TRUE,  NULL),
    ('modulo_stock',       NULL, TRUE,  NULL),
    ('modulo_grooming',    NULL, TRUE,  NULL),
    ('modulo_guarderia',   NULL, TRUE,  NULL),
    ('modulo_laboratorio', NULL, TRUE,  NULL),
    ('multi_sede',         NULL, TRUE,  NULL),
    ('api_acceso',         NULL, TRUE,  NULL),
    ('reportes_avanzados', NULL, TRUE,  NULL),
    ('soporte_tipo',       NULL, NULL,  'whatsapp_prioritario')
) AS t(feature, vi, vb, vs);
```

---

## 7. `subscriptions`

```sql
CREATE TABLE public.subscriptions (
    id          UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    tenant_id   UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
    plan_id     UUID NOT NULL REFERENCES public.plans(id),

    estado      VARCHAR(20) NOT NULL DEFAULT 'trial'
                CHECK (estado IN (
                    'trial',       -- período de prueba activo
                    'active',      -- pago al día
                    'grace',       -- pago vencido, acceso por 7 días de gracia
                    'suspended',   -- sin acceso — requiere pago para reactivar
                    'cancelled'    -- baja definitiva
                )),

    -- Ciclo de facturación
    ciclo               VARCHAR(10) NOT NULL DEFAULT 'mensual'
                        CHECK (ciclo IN ('mensual','anual')),
    trial_ends_at       TIMESTAMPTZ NULL,
    current_period_start TIMESTAMPTZ NULL,
    current_period_end   TIMESTAMPTZ NULL,
    grace_ends_at        TIMESTAMPTZ NULL,
    -- grace = current_period_end + 7 días

    cancelled_at         TIMESTAMPTZ NULL,
    cancel_reason        TEXT NULL,
    cancel_feedback      TEXT NULL,   -- qué mejorarías (churn analysis)

    -- Precio pactado (puede tener descuento por promo)
    precio_pactado       DECIMAL(10,2) NOT NULL,
    descuento_pct        DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    promo_code_id        UUID NULL REFERENCES public.promo_codes(id),

    -- Cobro automático (integración futura Culqi/Niubiz)
    proximo_cobro_at     TIMESTAMPTZ NULL,
    metodo_pago_token    VARCHAR(200) NULL,  -- token encriptado

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Un tenant solo puede tener UNA suscripción no cancelada
CREATE UNIQUE INDEX idx_subscriptions_tenant_active
    ON public.subscriptions(tenant_id)
    WHERE estado != 'cancelled';

CREATE INDEX idx_subscriptions_cobro
    ON public.subscriptions(proximo_cobro_at)
    WHERE estado = 'active';

CREATE INDEX idx_subscriptions_grace
    ON public.subscriptions(grace_ends_at)
    WHERE estado = 'grace';
```

---

## 8. `subscription_payments`

```sql
-- Historial inmutable de cada pago procesado
CREATE TABLE public.subscription_payments (
    id              UUID    DEFAULT gen_random_uuid() PRIMARY KEY,
    subscription_id UUID    NOT NULL REFERENCES public.subscriptions(id),
    tenant_id       UUID    NOT NULL REFERENCES public.tenants(id),
    plan_id         UUID    NOT NULL REFERENCES public.plans(id),

    -- Monto
    monto           DECIMAL(10,2) NOT NULL,
    moneda          CHAR(3) NOT NULL DEFAULT 'PEN',
    igv_monto       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    descuento_monto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total           DECIMAL(10,2) NOT NULL,

    -- Estado del pago
    estado          VARCHAR(20) NOT NULL
                    CHECK (estado IN ('pendiente','procesado','fallido','reembolsado')),

    -- Pasarela de pago
    pasarela        VARCHAR(30) NULL,
    -- 'culqi' | 'niubiz' | 'transferencia' | 'yape' | 'manual'
    pasarela_transaction_id VARCHAR(200) NULL,
    pasarela_response       JSONB NULL,      -- respuesta raw de la pasarela

    -- Período que cubre
    periodo_inicio  TIMESTAMPTZ NOT NULL,
    periodo_fin     TIMESTAMPTZ NOT NULL,

    -- Para emitir comprobante SUNAT al cliente del SaaS (plan pro+)
    fel_emitido     BOOLEAN NOT NULL DEFAULT FALSE,
    fel_numero      VARCHAR(15) NULL,

    error_mensaje   TEXT NULL,
    pagado_at       TIMESTAMPTZ NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_sub_payments_tenant ON public.subscription_payments(tenant_id, created_at DESC);
CREATE INDEX idx_sub_payments_estado ON public.subscription_payments(estado)
    WHERE estado = 'pendiente';
```

---

## 9. `promo_codes`

```sql
CREATE TABLE public.promo_codes (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    codigo          VARCHAR(30) NOT NULL UNIQUE,       -- 'CHICLAYO30', 'LAUNCH50'
    descripcion     VARCHAR(200) NULL,

    tipo_descuento  VARCHAR(15) NOT NULL
                    CHECK (tipo_descuento IN ('porcentaje','monto_fijo','meses_gratis')),
    valor           DECIMAL(10,2) NOT NULL,
    -- Si tipo = 'porcentaje': valor = 30 (→ 30% off)
    -- Si tipo = 'monto_fijo': valor = 50 (→ S/ 50 off)
    -- Si tipo = 'meses_gratis': valor = 2 (→ 2 meses gratis)

    -- Restricciones
    plan_id_restriccion UUID NULL REFERENCES public.plans(id),
    -- NULL = aplica a cualquier plan

    max_usos        INTEGER NULL,     -- NULL = usos ilimitados
    usos_actuales   INTEGER NOT NULL DEFAULT 0,
    un_uso_por_tenant BOOLEAN NOT NULL DEFAULT TRUE,

    activo          BOOLEAN NOT NULL DEFAULT TRUE,
    valido_desde    TIMESTAMPTZ NULL,
    valido_hasta    TIMESTAMPTZ NULL,

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_promo_activo ON public.promo_codes(codigo) WHERE activo = TRUE;
```

---

## 10. `ubigeos`

```sql
CREATE TABLE public.ubigeos (
    id           SERIAL PRIMARY KEY,
    ubigeo       VARCHAR(6) NOT NULL UNIQUE,
    departamento VARCHAR(50) NOT NULL,
    provincia    VARCHAR(50) NOT NULL,
    distrito     VARCHAR(50) NOT NULL,

    CONSTRAINT chk_ubigeo_len CHECK (LENGTH(ubigeo) = 6)
);

CREATE INDEX idx_ubigeos_dep ON public.ubigeos(departamento);
CREATE INDEX idx_ubigeos_pro ON public.ubigeos(provincia);
-- Importar desde: https://github.com/jamesvillarreal/ubigeo-peru
-- CSV con los 1,874 distritos del Peru (fuente INEI)
```

---

## 11. `global_notifications`

```sql
-- Avisos del SaaS a todos o algunos tenants (mantenimientos, nuevas features, etc.)
CREATE TABLE public.global_notifications (
    id          UUID    DEFAULT gen_random_uuid() PRIMARY KEY,
    titulo      VARCHAR(200) NOT NULL,
    mensaje     TEXT NOT NULL,
    tipo        VARCHAR(20) NOT NULL DEFAULT 'info'
                CHECK (tipo IN ('info','warning','error','success','mantenimiento')),

    -- Target: NULL = todos los tenants
    plan_id_target UUID NULL REFERENCES public.plans(id),
    tenant_id_target UUID NULL REFERENCES public.tenants(id),

    activo      BOOLEAN NOT NULL DEFAULT TRUE,
    publicado_at TIMESTAMPTZ NULL,
    expira_at   TIMESTAMPTZ NULL,

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

---

## PARTE III — SCHEMA TENANT (Por clínica)

> **Importante:** Todas las tablas de esta sección se crean dentro del schema del tenant.
> No existe columna `tenant_id` en ninguna — el aislamiento lo provee PostgreSQL.
> Laravel cambia el `search_path` en cada request via middleware.

---

## 12. `users`

```sql
CREATE TABLE users (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,

    -- Datos personales
    nombres         VARCHAR(100) NOT NULL,
    apellidos       VARCHAR(100) NOT NULL,
    email           VARCHAR(150) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    telefono        VARCHAR(20)  NULL,
    avatar_url      VARCHAR(500) NULL,

    -- Rol dentro de la clínica
    rol             VARCHAR(30) NOT NULL DEFAULT 'recepcionista'
                    CHECK (rol IN (
                        'admin_clinica',    -- dueño/gerente — acceso total
                        'veterinario',      -- acceso clínico completo
                        'asistente_vet',    -- clínico con restricciones
                        'recepcionista',    -- agenda, caja, clientes
                        'groomer',          -- solo módulo peluquería
                        'laboratorista'     -- solo laboratorio
                    )),

    -- Datos profesionales (para veterinarios)
    especialidad        VARCHAR(150) NULL,   -- 'Cirugía · Dermatología'
    colegio_vet_num     VARCHAR(30)  NULL,   -- N° de colegiatura
    firma_url           VARCHAR(500) NULL,   -- firma digital para recetas/certificados

    -- Sede asignada (plan Clínica multi-sede)
    sede_id         UUID NULL,               -- FK → cfg_sedes (se agrega después)

    -- Color en la agenda (para diferencias entre veterinarios)
    color_agenda    VARCHAR(7) NULL DEFAULT '#0F6E56',

    -- Estado
    activo          BOOLEAN NOT NULL DEFAULT TRUE,
    email_verificado BOOLEAN NOT NULL DEFAULT FALSE,

    -- Configuración de notificaciones personales
    notif_nueva_cita        BOOLEAN NOT NULL DEFAULT TRUE,
    notif_cita_cancelada    BOOLEAN NOT NULL DEFAULT TRUE,
    notif_cita_modificada   BOOLEAN NOT NULL DEFAULT TRUE,
    notif_stock_minimo      BOOLEAN NOT NULL DEFAULT TRUE,
    notif_resultado_lab     BOOLEAN NOT NULL DEFAULT TRUE,

    -- Seguridad
    last_login_at       TIMESTAMPTZ NULL,
    last_login_ip       VARCHAR(45)  NULL,
    must_change_password BOOLEAN NOT NULL DEFAULT FALSE,

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ NULL,
    created_by_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_users_email    ON users(email) WHERE deleted_at IS NULL;
CREATE INDEX idx_users_rol      ON users(rol)   WHERE deleted_at IS NULL;
CREATE INDEX idx_users_activo   ON users(activo) WHERE deleted_at IS NULL;
```

---

## 13. `password_reset_tokens`

```sql
CREATE TABLE password_reset_tokens (
    id          UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    user_id     UUID        NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash  VARCHAR(255) NOT NULL UNIQUE,  -- hash del token enviado por email
    expira_at   TIMESTAMPTZ NOT NULL,           -- DEFAULT: NOW() + 1 hour
    usado       BOOLEAN NOT NULL DEFAULT FALSE,
    usado_at    TIMESTAMPTZ NULL,
    ip_solicitud VARCHAR(45) NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_pwreset_user  ON password_reset_tokens(user_id);
CREATE INDEX idx_pwreset_token ON password_reset_tokens(token_hash) WHERE usado = FALSE;
```

---

## 14. `sessions`

```sql
CREATE TABLE sessions (
    id              VARCHAR(255) PRIMARY KEY,   -- session ID de Laravel
    user_id         UUID NULL REFERENCES users(id) ON DELETE CASCADE,
    ip_address      VARCHAR(45)  NULL,
    user_agent      TEXT NULL,
    payload         TEXT NOT NULL,
    last_activity   INTEGER NOT NULL            -- Unix timestamp
);

CREATE INDEX idx_sessions_user     ON sessions(user_id);
CREATE INDEX idx_sessions_activity ON sessions(last_activity);
```

---

## 15. `personal_access_tokens`

```sql
-- Para el API externo — Plan Clínica con api_acceso = true
CREATE TABLE personal_access_tokens (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    user_id         UUID        NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    nombre          VARCHAR(100) NOT NULL,
    token_hash      VARCHAR(255) NOT NULL UNIQUE,
    permisos        JSONB NULL,
    -- ['patients:read', 'appointments:read', 'appointments:write']

    ultimo_uso_at   TIMESTAMPTZ NULL,
    expira_at       TIMESTAMPTZ NULL,
    activo          BOOLEAN NOT NULL DEFAULT TRUE,

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_pat_user  ON personal_access_tokens(user_id) WHERE activo = TRUE;
CREATE INDEX idx_pat_token ON personal_access_tokens(token_hash) WHERE activo = TRUE;
```

---

## 16. `cfg_clinic_settings`

```sql
-- 1 sola fila por tenant — configuración maestra de la clínica
CREATE TABLE cfg_clinic_settings (
    id  UUID DEFAULT gen_random_uuid() PRIMARY KEY,

    -- Datos tributarios / SUNAT
    ruc                 VARCHAR(11)  NULL,
    razon_social        VARCHAR(200) NULL,
    nombre_comercial    VARCHAR(150) NULL,
    direccion_fiscal    VARCHAR(255) NULL,
    ubigeo_id           INTEGER NULL REFERENCES public.ubigeos(id),
    logo_url            VARCHAR(500) NULL,
    email_institucional VARCHAR(150) NULL,
    telefono_principal  VARCHAR(20)  NULL,
    web_url             VARCHAR(200) NULL,

    -- Configuración de agenda
    duracion_cita_default_min   SMALLINT NOT NULL DEFAULT 30,
    intervalo_agenda_min        SMALLINT NOT NULL DEFAULT 15,
    -- Ejemplo horario_atencion:
    -- {"lunes":{"inicio":"08:00","fin":"18:00","activo":true}, "sabado":{"inicio":"09:00","fin":"13:00","activo":true}, "domingo":{"activo":false}}
    horario_atencion    JSONB NOT NULL DEFAULT '{}'::JSONB,
    dias_anticipacion_cita SMALLINT NOT NULL DEFAULT 30,
    -- Máximo de días en el futuro para agendar una cita

    -- Configuración de recordatorios
    recordatorio_48h_activo         BOOLEAN NOT NULL DEFAULT TRUE,
    recordatorio_2h_activo          BOOLEAN NOT NULL DEFAULT TRUE,
    recordatorio_vacuna_activo      BOOLEAN NOT NULL DEFAULT TRUE,
    recordatorio_vacuna_dias_antes  SMALLINT NOT NULL DEFAULT 7,
    recordatorio_cumple_activo      BOOLEAN NOT NULL DEFAULT FALSE,
    -- Recordatorio de cumpleaños de la mascota

    -- NubeFact (encriptado AES-256 en app)
    nubefact_token_enc  TEXT NULL,
    nubefact_ruc        VARCHAR(11) NULL,
    nubefact_configurado BOOLEAN NOT NULL DEFAULT FALSE,

    -- WhatsApp / Twilio (encriptado)
    twilio_account_sid_enc  TEXT NULL,
    twilio_auth_token_enc   TEXT NULL,
    twilio_wa_number        VARCHAR(30) NULL,   -- 'whatsapp:+51XXXXXXXXX'
    wa_configurado          BOOLEAN NOT NULL DEFAULT FALSE,

    -- Email / Brevo (encriptado)
    brevo_api_key_enc   TEXT NULL,
    email_from          VARCHAR(150) NULL,
    email_from_nombre   VARCHAR(100) NULL,

    -- Configuración financiera
    moneda              CHAR(3) NOT NULL DEFAULT 'PEN',
    igv_porcentaje      DECIMAL(5,2) NOT NULL DEFAULT 18.00,
    precio_incluye_igv  BOOLEAN NOT NULL DEFAULT TRUE,

    -- Política de cancelación
    horas_min_cancelacion   SMALLINT NOT NULL DEFAULT 24,
    -- Menos de 24h de anticipación se cobra como consulta

    -- Personalización visual del sistema
    color_primario      VARCHAR(7) NULL,
    color_secundario    VARCHAR(7) NULL,

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_by_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

-- Exactamente UNA fila por tenant (configuración maestra). No usar múltiples INSERT.
CREATE UNIQUE INDEX uq_cfg_clinic_settings_single_row ON cfg_clinic_settings ((TRUE));
```

---

## 17. `cfg_sedes`

```sql
-- Solo disponible en plan Clínica (multi_sede = true)
CREATE TABLE cfg_sedes (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    nombre          VARCHAR(150) NOT NULL,
    codigo          VARCHAR(10)  NOT NULL UNIQUE,   -- 'SEDE-01', 'LIMA-NORTE'
    direccion       VARCHAR(255) NULL,
    ubigeo_id       INTEGER NULL REFERENCES public.ubigeos(id),
    telefono        VARCHAR(20)  NULL,
    email           VARCHAR(150) NULL,
    responsable_id  UUID NULL REFERENCES users(id),   -- veterinario/admin responsable

    -- Config propia de esta sede
    nubefact_serie_factura  VARCHAR(4) NULL,   -- 'F002' si tiene serie propia
    nubefact_serie_boleta   VARCHAR(4) NULL,   -- 'B002'

    activa          BOOLEAN NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at  TIMESTAMPTZ NULL
);
```

---

## 18. `cfg_horarios`

```sql
-- Horario por veterinario (puede diferir del horario general de la clínica)
CREATE TABLE cfg_horarios (
    id              UUID    DEFAULT gen_random_uuid() PRIMARY KEY,
    veterinario_id  UUID    NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    sede_id         UUID    NULL REFERENCES cfg_sedes(id),

    dia_semana      SMALLINT NOT NULL CHECK (dia_semana BETWEEN 0 AND 6),
    -- 0=domingo, 1=lunes, ... 6=sabado

    hora_inicio     TIME NOT NULL,
    hora_fin        TIME NOT NULL,
    activo          BOOLEAN NOT NULL DEFAULT TRUE,

    CONSTRAINT chk_horario_valido CHECK (hora_fin > hora_inicio),
    CONSTRAINT uq_horario_vet_dia UNIQUE (veterinario_id, dia_semana, sede_id)
);
```

---

## 19. `cfg_bloqueos_agenda`

```sql
-- Bloqueos temporales: feriados, vacaciones, reuniones
CREATE TABLE cfg_bloqueos_agenda (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    veterinario_id  UUID NULL REFERENCES users(id),
    -- NULL = bloqueo para toda la clínica

    sede_id         UUID NULL REFERENCES cfg_sedes(id),
    titulo          VARCHAR(200) NOT NULL,   -- 'Vacaciones', 'Feriado nacional'
    fecha_inicio    TIMESTAMPTZ NOT NULL,
    fecha_fin       TIMESTAMPTZ NOT NULL,
    todo_el_dia     BOOLEAN NOT NULL DEFAULT FALSE,
    recurrente      BOOLEAN NOT NULL DEFAULT FALSE,
    patron_recurrencia VARCHAR(50) NULL,     -- 'anual', 'semanal:1,5' (lun,vie)

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_by_id UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_bloqueos_fecha ON cfg_bloqueos_agenda(fecha_inicio, fecha_fin);
```

---

## 20. `cfg_tarifas`

```sql
-- Tarifas base por tipo de consulta (visible en la agenda al crear cita)
CREATE TABLE cfg_tarifas (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    nombre          VARCHAR(150) NOT NULL,
    tipo_consulta   VARCHAR(30)  NULL,   -- vincula con vet_appointments.tipo_consulta
    especie         VARCHAR(20)  NULL,   -- NULL = aplica a todas las especies
    precio          DECIMAL(10,2) NOT NULL,
    duracion_min    SMALLINT NOT NULL DEFAULT 30,
    activo          BOOLEAN NOT NULL DEFAULT TRUE,

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

---

## 21. `cfg_recordatorio_templates`

```sql
-- Plantillas de mensajes personalizables por la clínica
CREATE TABLE cfg_recordatorio_templates (
    id          UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    tipo        VARCHAR(40) NOT NULL UNIQUE,
    -- 'cita_48h' | 'cita_2h' | 'cita_confirmacion' | 'vacuna_proxima'
    -- 'grooming_listo' | 'resultado_lab' | 'cumple_mascota' | 'bienvenida'

    canal       VARCHAR(20) NOT NULL CHECK (canal IN ('whatsapp','email','sms')),
    asunto      VARCHAR(200) NULL,   -- solo para email
    -- Variables disponibles: {{nombre_mascota}}, {{nombre_propietario}},
    -- {{fecha_cita}}, {{hora_cita}}, {{veterinario}}, {{clinica}}
    cuerpo      TEXT NOT NULL,
    activo      BOOLEAN NOT NULL DEFAULT TRUE,

    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_by_id UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

-- Insertar plantillas base al provisionar el tenant
INSERT INTO cfg_recordatorio_templates (tipo, canal, asunto, cuerpo) VALUES
('cita_48h', 'whatsapp', NULL,
 'Hola {{nombre_propietario}} 👋 Le recordamos que *{{nombre_mascota}}* tiene cita veterinaria el *{{fecha_cita}} a las {{hora_cita}}* con el Dr. {{veterinario}}. ¿Confirmamos su asistencia? Responda SI o NO.'),
('cita_2h', 'whatsapp', NULL,
 '⏰ *Recordatorio:* La cita de *{{nombre_mascota}}* es hoy a las *{{hora_cita}}*. ¡Los esperamos! 🐾'),
('vacuna_proxima', 'whatsapp', NULL,
 'Hola {{nombre_propietario}}, *{{nombre_mascota}}* tiene próxima vacunación el *{{fecha_vacuna}}*. Comuníquese con nosotros para agendar su cita. 💉'),
('grooming_listo', 'whatsapp', NULL,
 '✅ ¡*{{nombre_mascota}}* ya está lista! Puede pasar a recogerla cuando guste. 🐶🛁'),
('bienvenida', 'email', 'Bienvenido a {{clinica}}',
 'Estimado {{nombre_propietario}},\n\nBienvenido. Hemos registrado a {{nombre_mascota}} en nuestro sistema...');
```

---

## 22. `vet_owners`

```sql
CREATE TABLE vet_owners (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,

    nombres         VARCHAR(100) NOT NULL,
    apellidos       VARCHAR(100) NOT NULL,

    -- Documento de identidad (requerido para CPE SUNAT)
    tipo_documento  VARCHAR(10)  NOT NULL DEFAULT 'DNI'
                    CHECK (tipo_documento IN ('DNI','RUC','CE','PTP','PASAPORTE','SIN_DOC')),
    numero_documento VARCHAR(20) NOT NULL,

    -- Contacto
    telefono        VARCHAR(20)  NOT NULL,
    telefono_alt    VARCHAR(20)  NULL,
    email           VARCHAR(150) NULL,
    direccion       VARCHAR(255) NULL,
    ubigeo_id       INTEGER NULL REFERENCES public.ubigeos(id),

    -- Canal preferido para recordatorios
    canal_contacto  VARCHAR(20)  NOT NULL DEFAULT 'whatsapp'
                    CHECK (canal_contacto IN ('whatsapp','email','sms','ninguno')),

    -- Datos opcionales
    fecha_nacimiento DATE NULL,
    ocupacion        VARCHAR(100) NULL,
    empresa          VARCHAR(150) NULL,   -- si tiene RUC corporativo
    como_nos_conocio VARCHAR(100) NULL,   -- 'google','referido','pasé','redes','otro'
    referido_por_owner_id UUID NULL REFERENCES vet_owners(id),

    -- Programa de fidelización (futuro)
    puntos_fidelidad INTEGER NOT NULL DEFAULT 0,

    -- Control
    es_empresa      BOOLEAN NOT NULL DEFAULT FALSE,
    activo          BOOLEAN NOT NULL DEFAULT TRUE,
    notas           TEXT NULL,   -- notas internas del personal

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ NULL,
    created_by_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL,
    updated_by_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL,

    CONSTRAINT uq_owner_documento UNIQUE (tipo_documento, numero_documento)
);

CREATE INDEX idx_owners_apellidos ON vet_owners(LOWER(apellidos)) WHERE deleted_at IS NULL;
CREATE INDEX idx_owners_telefono  ON vet_owners(telefono)         WHERE deleted_at IS NULL;
CREATE INDEX idx_owners_email     ON vet_owners(LOWER(email))     WHERE deleted_at IS NULL AND email IS NOT NULL;
CREATE INDEX idx_owners_busqueda  ON vet_owners(LOWER(nombres || ' ' || apellidos)) WHERE deleted_at IS NULL;
```

---

## 23. `vet_patients`

```sql
CREATE TABLE vet_patients (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    owner_id        UUID        NOT NULL REFERENCES vet_owners(id),
    -- owner_id = propietario principal (puede tener propietarios adicionales en vet_patient_owners)

    -- Identificación
    nombre          VARCHAR(80)  NOT NULL,
    especie         VARCHAR(20)  NOT NULL
                    CHECK (especie IN ('canino','felino','ave','reptil','roedor','lagomorfo','pez','otro')),
    raza            VARCHAR(80)  NULL,
    sexo            VARCHAR(15)  NOT NULL
                    CHECK (sexo IN ('macho','hembra','indeterminado')),
    color_pelaje    VARCHAR(80)  NULL,
    fecha_nacimiento DATE NULL,
    es_fecha_aprox  BOOLEAN NOT NULL DEFAULT FALSE,
    -- Muchos propietarios no saben la fecha exacta — flag para la UI

    -- Identificación física
    microchip       VARCHAR(30)  NULL,
    tatuaje         VARCHAR(30)  NULL,
    pasaporte_num   VARCHAR(50)  NULL,   -- para mascotas de viaje

    -- Estado reproductivo
    esterilizado    BOOLEAN NOT NULL DEFAULT FALSE,
    fecha_esterilizacion DATE NULL,

    -- Foto
    foto_url        VARCHAR(500) NULL,

    -- Alertas clínicas — visibles al abrir la ficha del paciente
    alergias_conocidas   TEXT NULL,
    condiciones_cronicas TEXT NULL,  -- 'Diabetes tipo 2 · Epilepsia'
    medicacion_permanente TEXT NULL, -- medicamentos que toma siempre
    notas_internas  TEXT NULL,       -- NO visible al propietario

    -- Cache de último control (actualizado por trigger al insertar historia clínica)
    peso_ultimo_kg  DECIMAL(5,2) NULL,
    peso_fecha      DATE NULL,
    ultima_consulta_at TIMESTAMPTZ NULL,
    ultimo_veterinario_id UUID NULL REFERENCES users(id),

    -- Estado
    fallecido       BOOLEAN NOT NULL DEFAULT FALSE,
    fecha_fallecimiento DATE NULL,
    causa_fallecimiento VARCHAR(200) NULL,

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ NULL,
    created_by_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL,
    updated_by_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_patients_owner    ON vet_patients(owner_id)      WHERE deleted_at IS NULL;
CREATE INDEX idx_patients_nombre   ON vet_patients(LOWER(nombre)) WHERE deleted_at IS NULL;
CREATE INDEX idx_patients_especie  ON vet_patients(especie)       WHERE deleted_at IS NULL;
CREATE INDEX idx_patients_microchip ON vet_patients(microchip)   WHERE microchip IS NOT NULL;
```

---

## 24. `vet_patient_owners`

```sql
-- Un paciente puede tener múltiples propietarios/responsables
-- Ej: pareja de esposos, familia con hijos mayores
CREATE TABLE vet_patient_owners (
    id          UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    patient_id  UUID NOT NULL REFERENCES vet_patients(id) ON DELETE CASCADE,
    owner_id    UUID NOT NULL REFERENCES vet_owners(id) ON DELETE CASCADE,
    es_principal BOOLEAN NOT NULL DEFAULT FALSE,
    relacion    VARCHAR(50) NULL,   -- 'Cónyuge', 'Hijo/a', 'Cuidador'
    puede_autorizar_tratamiento BOOLEAN NOT NULL DEFAULT TRUE,
    puede_retirar_mascota       BOOLEAN NOT NULL DEFAULT TRUE,

    CONSTRAINT uq_patient_owner UNIQUE (patient_id, owner_id)
);

CREATE INDEX idx_pat_owners_patient ON vet_patient_owners(patient_id);
CREATE INDEX idx_pat_owners_owner   ON vet_patient_owners(owner_id);
```

---

## 25. `vet_patient_documents`

```sql
-- Documentos externos del paciente: carnet de vacunas, resultados previos, etc.
CREATE TABLE vet_patient_documents (
    id          UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    patient_id  UUID        NOT NULL REFERENCES vet_patients(id) ON DELETE CASCADE,
    tipo        VARCHAR(30) NOT NULL
                CHECK (tipo IN ('carnet_vacunas','resultado_externo','certificado','otro')),
    nombre      VARCHAR(200) NOT NULL,
    descripcion TEXT NULL,
    archivo_url VARCHAR(500) NOT NULL,   -- Cloudflare R2
    fecha_documento DATE NULL,
    subido_por_propietario BOOLEAN NOT NULL DEFAULT FALSE,

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_by_id UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_pat_docs_patient ON vet_patient_documents(patient_id);
```

---

## 26. `vet_owner_consents`

```sql
-- Consentimientos bajo Ley 29733 (Protección de Datos Personales Peru)
-- y DS 016-2024-JUS — OBLIGATORIO para operar legalmente
CREATE TABLE vet_owner_consents (
    id          UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    owner_id    UUID        NOT NULL REFERENCES vet_owners(id) ON DELETE CASCADE,

    tipo        VARCHAR(50) NOT NULL
                CHECK (tipo IN (
                    'datos_personales',       -- Ley 29733
                    'comunicaciones_marketing',
                    'compartir_con_terceros',
                    'politica_cancelacion',
                    'terminos_servicio'
                )),

    acepto      BOOLEAN NOT NULL,
    version_doc VARCHAR(10) NOT NULL,  -- '1.0', '1.1' — versión del documento aceptado
    texto_doc   TEXT NULL,             -- snapshot del texto al momento de aceptar
    ip_address  VARCHAR(45) NULL,
    user_agent  TEXT NULL,
    canal       VARCHAR(20) NOT NULL DEFAULT 'presencial'
                CHECK (canal IN ('presencial','web','whatsapp','email')),

    aceptado_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_consents_owner ON vet_owner_consents(owner_id);
```

---

## 27. `vet_appointments`

```sql
CREATE TABLE vet_appointments (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    patient_id      UUID        NOT NULL REFERENCES vet_patients(id),
    owner_id        UUID        NOT NULL REFERENCES vet_owners(id),
    -- owner_id desnormalizado para evitar JOIN vet_patients en vistas de agenda
    veterinario_id  UUID        NOT NULL REFERENCES users(id),
    sede_id         UUID        NULL REFERENCES cfg_sedes(id),
    tarifa_id       UUID        NULL REFERENCES cfg_tarifas(id),

    tipo_consulta   VARCHAR(30) NOT NULL
                    CHECK (tipo_consulta IN (
                        'consulta_general', 'vacunacion', 'desparasitacion',
                        'cirugia', 'peluqueria', 'urgencia', 'control',
                        'laboratorio', 'ecografia', 'radiologia',
                        'hospitalizacion', 'guarderia', 'otro'
                    )),

    estado          VARCHAR(25) NOT NULL DEFAULT 'programada'
                    CHECK (estado IN (
                        'programada',       -- agendada sin confirmar
                        'confirmada',       -- propietario confirmó por WA/email
                        'en_sala_espera',   -- llegó a la clínica
                        'en_atencion',      -- en consultorio
                        'atendida',         -- terminó la consulta
                        'cancelada',        -- canceló (propietario o clínica)
                        'no_asistio',       -- no se presentó sin avisar
                        'reagendada'        -- se reagendó (mantiene registro original)
                    )),

    -- Horario
    fecha_hora_inicio   TIMESTAMPTZ NOT NULL,
    fecha_hora_fin      TIMESTAMPTZ NULL,
    duracion_min        SMALLINT NOT NULL DEFAULT 30,

    -- Información de la cita
    motivo_consulta     VARCHAR(500) NOT NULL,
    notas_previas       TEXT NULL,   -- notas del propietario al agendar online
    notas_internas      TEXT NULL,   -- notas del personal (no visibles al propietario)

    -- Recordatorios automáticos
    recordatorio_48h_at     TIMESTAMPTZ NULL,   -- cuándo enviar
    recordatorio_48h_enviado BOOLEAN NOT NULL DEFAULT FALSE,
    recordatorio_2h_at      TIMESTAMPTZ NULL,
    recordatorio_2h_enviado  BOOLEAN NOT NULL DEFAULT FALSE,
    confirmado_propietario  BOOLEAN NOT NULL DEFAULT FALSE,
    confirmacion_at         TIMESTAMPTZ NULL,
    confirmacion_canal      VARCHAR(20) NULL,

    -- Cancelación
    cancelado_por       VARCHAR(20) NULL CHECK (cancelado_por IN ('propietario','clinica',NULL)),
    motivo_cancelacion  TEXT NULL,
    cancelado_at        TIMESTAMPTZ NULL,

    -- Reagendamiento
    cita_original_id    UUID NULL REFERENCES vet_appointments(id),
    -- Si esta cita es un reagendamiento, apunta a la original

    -- Vinculación post-atención
    historia_clinica_id UUID NULL,   -- FK → vet_clinical_records (se agrega después)
    venta_id            UUID NULL,   -- FK → vet_sales (se agrega después)
    grooming_id         UUID NULL,   -- FK → vet_grooming_services (se agrega después)

    -- Canal de origen de la cita
    origen_cita         VARCHAR(20) NOT NULL DEFAULT 'presencial'
                        CHECK (origen_cita IN ('presencial','whatsapp','telefono','web','pwa')),

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ NULL,
    created_by_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

-- Agenda por veterinario y fecha (vista semanal)
CREATE INDEX idx_appt_vet_fecha ON vet_appointments(veterinario_id, fecha_hora_inicio)
    WHERE deleted_at IS NULL AND estado NOT IN ('cancelada','no_asistio');

-- Para el job de recordatorios
CREATE INDEX idx_appt_recordatorio_48h ON vet_appointments(recordatorio_48h_at)
    WHERE recordatorio_48h_enviado = FALSE
      AND estado IN ('programada','confirmada')
      AND deleted_at IS NULL;

CREATE INDEX idx_appt_recordatorio_2h ON vet_appointments(recordatorio_2h_at)
    WHERE recordatorio_2h_enviado = FALSE
      AND estado IN ('programada','confirmada')
      AND deleted_at IS NULL;

-- Vista de agenda del día
CREATE INDEX idx_appt_fecha ON vet_appointments(DATE(fecha_hora_inicio))
    WHERE deleted_at IS NULL;
```

---

## 28. `vet_appointment_history`

```sql
-- Cada cambio de estado de una cita queda registrado
CREATE TABLE vet_appointment_history (
    id              BIGSERIAL PRIMARY KEY,
    appointment_id  UUID        NOT NULL REFERENCES vet_appointments(id) ON DELETE CASCADE,
    estado_anterior VARCHAR(25) NULL,
    estado_nuevo    VARCHAR(25) NOT NULL,
    motivo          TEXT NULL,
    usuario_id      UUID NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_appt_history ON vet_appointment_history(appointment_id, created_at DESC);
```

---

## 29. `vet_waiting_list`

```sql
-- Lista de espera cuando no hay disponibilidad
CREATE TABLE vet_waiting_list (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    patient_id      UUID        NOT NULL REFERENCES vet_patients(id),
    owner_id        UUID        NOT NULL REFERENCES vet_owners(id),
    veterinario_id  UUID        NULL REFERENCES users(id),
    tipo_consulta   VARCHAR(30) NULL,
    fecha_preferida DATE NULL,
    horario_preferido VARCHAR(20) NULL,   -- 'mañana','tarde','cualquiera'
    notas           TEXT NULL,
    estado          VARCHAR(20) NOT NULL DEFAULT 'espera'
                    CHECK (estado IN ('espera','contactado','agendado','cancelado')),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_waitlist_estado ON vet_waiting_list(estado, created_at)
    WHERE estado = 'espera';
```

---

## 30. `vet_clinical_records`

```sql
-- La tabla más importante del sistema — historial clínico completo (SOAP)
CREATE TABLE vet_clinical_records (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    patient_id      UUID        NOT NULL REFERENCES vet_patients(id),
    appointment_id  UUID        NULL REFERENCES vet_appointments(id),
    veterinario_id  UUID        NOT NULL REFERENCES users(id),
    sede_id         UUID        NULL REFERENCES cfg_sedes(id),

    fecha_atencion  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    numero_hc       VARCHAR(20) NOT NULL,   -- 'HC-2026-00001' — correlativo

    -- Signos vitales
    peso_kg             DECIMAL(5,2) NULL,
    temperatura_c       DECIMAL(4,1) NULL,
    frecuencia_cardiaca SMALLINT NULL,   -- bpm
    frecuencia_resp     SMALLINT NULL,   -- rpm
    frecuencia_pulso    SMALLINT NULL,
    tiempo_llenado_capilar DECIMAL(3,1) NULL,   -- segundos
    saturacion_o2       SMALLINT NULL,   -- % SpO2
    mucosas             VARCHAR(30) NULL
                        CHECK (mucosas IN ('rosadas','palidas','ictericas',
                                           'cianosadas','congestionadas','secas',NULL)),
    hidratacion_pct     SMALLINT NULL CHECK (hidratacion_pct BETWEEN 0 AND 15),
    condicion_corporal  SMALLINT NULL CHECK (condicion_corporal BETWEEN 1 AND 9),
    -- Escala Body Condition Score (BCS): 1=caquéctico, 5=ideal, 9=obeso

    -- SOAP
    -- S — Subjetivo: historia del propietario
    motivo_consulta         TEXT NOT NULL,
    historia_enfermedad     TEXT NULL,   -- evolución, duración, tratamientos previos

    -- O — Objetivo: exploración física
    exploracion_fisica      TEXT NULL,
    hallazgos_por_sistemas  JSONB NULL,
    -- {
    --   "cardiovascular": "normal",
    --   "respiratorio": "estertores basales",
    --   "digestivo": "abdomen tenso a la palpación",
    --   "musculoesqueletico": "normal",
    --   "neurologico": "normal",
    --   "piel_anexos": "lesiones pustulosas en dorso"
    -- }

    -- A — Assessment: diagnóstico
    diagnostico_presuntivo  TEXT NULL,
    diagnostico_definitivo  TEXT NULL,
    cie_codigos             VARCHAR(200) NULL,   -- para uso estadístico futuro

    -- P — Plan: tratamiento y seguimiento
    tratamiento             TEXT NULL,
    dieta_recomendada       TEXT NULL,
    actividad_recomendada   TEXT NULL,
    proxima_visita_dias     SMALLINT NULL,
    proxima_visita_motivo   VARCHAR(200) NULL,

    -- Archivos adjuntos (Cloudflare R2)
    adjuntos_url            JSONB NULL,
    -- [{"tipo": "radiografia", "descripcion": "Tórax AP", "url": "https://..."}]

    -- Observaciones y notas finales
    observaciones           TEXT NULL,
    confidencial            BOOLEAN NOT NULL DEFAULT FALSE,
    -- Si TRUE, solo el veterinario que lo creó puede verlo

    -- Facturación
    estado_facturacion      VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                            CHECK (estado_facturacion IN ('pendiente','facturado','exento','sin_cargo')),
    venta_id                UUID NULL,   -- FK → vet_sales

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ NULL,
    created_by_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL,
    updated_by_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_hc_patient_fecha   ON vet_clinical_records(patient_id, fecha_atencion DESC) WHERE deleted_at IS NULL;
CREATE INDEX idx_hc_vet_fecha       ON vet_clinical_records(veterinario_id, fecha_atencion DESC) WHERE deleted_at IS NULL;
CREATE INDEX idx_hc_facturacion     ON vet_clinical_records(estado_facturacion) WHERE estado_facturacion = 'pendiente' AND deleted_at IS NULL;
CREATE INDEX idx_hc_numero          ON vet_clinical_records(numero_hc) WHERE deleted_at IS NULL;
```

---

## 31. `vet_vaccinations`

```sql
CREATE TABLE vet_vaccinations (
    id                  UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    patient_id          UUID        NOT NULL REFERENCES vet_patients(id),
    clinical_record_id  UUID        NULL REFERENCES vet_clinical_records(id),
    veterinario_id      UUID        NOT NULL REFERENCES users(id),

    vacuna_nombre       VARCHAR(150) NOT NULL,
    vacuna_tipo         VARCHAR(60)  NULL,
    -- 'antirrábica' | 'polivalente' | 'leishmania' | 'bordetella' | 'FeLV' | 'FVRCP'
    laboratorio         VARCHAR(100) NULL,
    lote                VARCHAR(50)  NULL,
    vencimiento_vial    DATE NULL,           -- fecha de vencimiento del frasco

    fecha_aplicacion    DATE NOT NULL,
    fecha_proxima       DATE NULL,           -- calculado por protocolo en la app
    protocolo_vacunal   VARCHAR(50) NULL,    -- 'cachorro_1','cachorro_2','anual','bianual'

    via_aplicacion      VARCHAR(20) NULL
                        CHECK (via_aplicacion IN ('subcutanea','intramuscular','intranasal','oral',NULL)),
    dosis_ml            DECIMAL(5,2) NULL,
    lugar_aplicacion    VARCHAR(50) NULL,    -- 'cuello derecho', 'muslo izquierdo'

    -- Vinculación con stock
    stock_item_id       UUID NULL,           -- FK → vet_stock_items (descuento automático)

    -- Control de recordatorio
    recordatorio_enviado    BOOLEAN NOT NULL DEFAULT FALSE,
    recordatorio_enviado_at TIMESTAMPTZ NULL,
    recordatorio_wa_msg_id  VARCHAR(100) NULL,  -- ID del mensaje Twilio para tracking

    notas               TEXT NULL,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_by_id       UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_vacc_patient     ON vet_vaccinations(patient_id, fecha_aplicacion DESC);
CREATE INDEX idx_vacc_recordatorio ON vet_vaccinations(fecha_proxima)
    WHERE recordatorio_enviado = FALSE AND fecha_proxima IS NOT NULL;
```

---

## 32. `vet_vaccination_protocols`

```sql
-- Protocolos vacunales estándar configurables por la clínica
CREATE TABLE vet_vaccination_protocols (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    nombre          VARCHAR(150) NOT NULL,   -- 'Protocolo cachorro canino', 'Gatito'
    especie         VARCHAR(20) NOT NULL,
    activo          BOOLEAN NOT NULL DEFAULT TRUE,

    -- Pasos del protocolo en JSONB
    pasos           JSONB NOT NULL,
    -- [
    --   {"semana": 6,  "vacuna": "Polivalente 1a dosis", "recordatorio_semanas_antes": 1},
    --   {"semana": 9,  "vacuna": "Polivalente 2a dosis"},
    --   {"semana": 12, "vacuna": "Polivalente 3a dosis + Antirrábica"},
    --   {"semana": 52, "vacuna": "Refuerzo anual", "repite_cada_semanas": 52}
    -- ]

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

---

## 33. `vet_prescriptions`

```sql
CREATE TABLE vet_prescriptions (
    id                  UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    clinical_record_id  UUID        NOT NULL REFERENCES vet_clinical_records(id),
    patient_id          UUID        NOT NULL REFERENCES vet_patients(id),
    veterinario_id      UUID        NOT NULL REFERENCES users(id),

    numero_receta       VARCHAR(20) NOT NULL,   -- 'RX-2026-00001'

    -- Items de la receta
    items   JSONB NOT NULL,
    -- [
    --   {
    --     "medicamento": "Amoxicilina 250mg",
    --     "dosis": "1 comprimido",
    --     "frecuencia": "cada 12 horas",
    --     "duracion": "7 días",
    --     "via": "oral",
    --     "cantidad_total": "14 comprimidos",
    --     "observaciones": "Administrar con alimento",
    --     "stock_item_id": "uuid-opcional"
    --   }
    -- ]

    indicaciones_generales  TEXT NULL,
    alertas_propietario     TEXT NULL,   -- 'No exponer al sol', 'Vigilar reacciones'

    -- Documentos generados
    pdf_url             VARCHAR(500) NULL,   -- Cloudflare R2
    pdf_generado_at     TIMESTAMPTZ NULL,

    -- Envío al propietario
    enviado_propietario     BOOLEAN NOT NULL DEFAULT FALSE,
    enviado_at              TIMESTAMPTZ NULL,
    canal_envio             VARCHAR(20) NULL CHECK (canal_envio IN ('whatsapp','email',NULL)),

    -- Control de dispensación en farmacia de la clínica
    dispensado              BOOLEAN NOT NULL DEFAULT FALSE,
    dispensado_at           TIMESTAMPTZ NULL,
    dispensado_por_id       UUID NULL REFERENCES users(id),

    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_by_id       UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_rx_patient ON vet_prescriptions(patient_id, created_at DESC);
CREATE INDEX idx_rx_pendientes ON vet_prescriptions(dispensado)
    WHERE dispensado = FALSE;
```

---

## 34. `vet_lab_orders`

```sql
-- Órdenes de laboratorio (exámenes solicitados)
CREATE TABLE vet_lab_orders (
    id                  UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    clinical_record_id  UUID        NOT NULL REFERENCES vet_clinical_records(id),
    patient_id          UUID        NOT NULL REFERENCES vet_patients(id),
    veterinario_id      UUID        NOT NULL REFERENCES users(id),

    numero_orden        VARCHAR(20) NOT NULL,   -- 'LAB-2026-00001'

    -- Exámenes solicitados
    examenes    JSONB NOT NULL,
    -- [
    --   {"codigo": "HEM", "nombre": "Hemograma completo", "urgente": false},
    --   {"codigo": "BQ",  "nombre": "Bioquímica sérica", "urgente": false},
    --   {"codigo": "URI", "nombre": "Urianálisis", "urgente": true}
    -- ]

    tipo_laboratorio    VARCHAR(20) NOT NULL DEFAULT 'externo'
                        CHECK (tipo_laboratorio IN ('interno','externo')),
    laboratorio_nombre  VARCHAR(150) NULL,   -- nombre del lab externo
    instrucciones       TEXT NULL,           -- preparación del paciente

    estado              VARCHAR(20) NOT NULL DEFAULT 'solicitado'
                        CHECK (estado IN ('solicitado','en_proceso','resultados_parciales','completado','cancelado')),

    fecha_solicitud     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    fecha_estimada      DATE NULL,
    fecha_recepcion     TIMESTAMPTZ NULL,

    -- Venta
    venta_id    UUID NULL,   -- FK → vet_sales
    precio      DECIMAL(10,2) NULL,

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_by_id UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_lab_orders_patient ON vet_lab_orders(patient_id, created_at DESC);
CREATE INDEX idx_lab_orders_estado  ON vet_lab_orders(estado)
    WHERE estado IN ('solicitado','en_proceso');
```

---

## 35. `vet_lab_results`

```sql
CREATE TABLE vet_lab_results (
    id              UUID    DEFAULT gen_random_uuid() PRIMARY KEY,
    lab_order_id    UUID    NOT NULL REFERENCES vet_lab_orders(id) ON DELETE CASCADE,
    patient_id      UUID    NOT NULL REFERENCES vet_patients(id),

    examen_codigo   VARCHAR(20) NOT NULL,
    examen_nombre   VARCHAR(150) NOT NULL,

    -- Resultados
    resultados  JSONB NOT NULL,
    -- [
    --   {"parametro": "Hematocrito", "valor": "42", "unidad": "%", "referencia": "37-55", "fuera_rango": false},
    --   {"parametro": "Hemoglobina", "valor": "14.2", "unidad": "g/dL", "referencia": "12-18", "fuera_rango": false}
    -- ]

    interpretacion      TEXT NULL,   -- texto libre del laboratorista
    archivo_pdf_url     VARCHAR(500) NULL,   -- PDF del resultado — Cloudflare R2
    tiene_valores_criticos BOOLEAN NOT NULL DEFAULT FALSE,

    -- Notificación al veterinario
    veterinario_notificado      BOOLEAN NOT NULL DEFAULT FALSE,
    veterinario_notificado_at   TIMESTAMPTZ NULL,

    registrado_por_id   UUID NULL REFERENCES users(id),
    fecha_resultado     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_lab_results_order   ON vet_lab_results(lab_order_id);
CREATE INDEX idx_lab_results_criticos ON vet_lab_results(tiene_valores_criticos, created_at)
    WHERE tiene_valores_criticos = TRUE AND veterinario_notificado = FALSE;
```

---

## 36. `vet_surgeries`

```sql
-- Registro quirúrgico detallado
CREATE TABLE vet_surgeries (
    id                  UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    clinical_record_id  UUID        NOT NULL REFERENCES vet_clinical_records(id),
    patient_id          UUID        NOT NULL REFERENCES vet_patients(id),
    cirujano_id         UUID        NOT NULL REFERENCES users(id),
    anestesista_id      UUID        NULL REFERENCES users(id),

    tipo_cirugia        VARCHAR(150) NOT NULL,   -- 'Orquiectomía', 'Cesárea', 'OVH'
    clasificacion       VARCHAR(20) NOT NULL DEFAULT 'electiva'
                        CHECK (clasificacion IN ('electiva','urgencia','emergencia')),

    -- Anestesia
    protocolo_anestesia TEXT NULL,
    medicacion_preanestesica JSONB NULL,
    tipo_anestesia      VARCHAR(30) NULL,   -- 'general_inhalada','local','loco-regional'
    agente_anestesico   VARCHAR(100) NULL,

    -- Tiempos quirúrgicos
    hora_entrada_cirugia    TIMESTAMPTZ NULL,
    hora_inicio_anestesia   TIMESTAMPTZ NULL,
    hora_inicio_cirugia     TIMESTAMPTZ NULL,
    hora_fin_cirugia        TIMESTAMPTZ NULL,
    hora_alta_cirugia       TIMESTAMPTZ NULL,

    -- Monitoreo intraoperatorio en JSONB
    monitoreo_intraop   JSONB NULL,
    -- [{"hora": "10:15", "fc": 80, "fr": 18, "sao2": 98, "temp": 37.5, "pa_sis": 110}]

    -- Descripción quirúrgica
    descripcion_cirugia TEXT NULL,
    hallazgos           TEXT NULL,
    complicaciones      TEXT NULL,
    material_utilizado  JSONB NULL,   -- sutura, implantes, etc.

    -- Postoperatorio
    instrucciones_postop    TEXT NULL,
    medicacion_postop       JSONB NULL,
    control_postop_dias     SMALLINT NULL,

    -- Estado del paciente
    estado_alta             VARCHAR(20) NULL
                            CHECK (estado_alta IN ('estable','grave','critico','fallecio',NULL)),

    -- Consentimiento quirúrgico
    consentimiento_firmado  BOOLEAN NOT NULL DEFAULT FALSE,
    consentimiento_url      VARCHAR(500) NULL,   -- PDF firmado en Cloudflare R2

    venta_id    UUID NULL,   -- FK → vet_sales
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_by_id UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_surgeries_patient   ON vet_surgeries(patient_id, created_at DESC);
CREATE INDEX idx_surgeries_cirujano  ON vet_surgeries(cirujano_id, created_at DESC);
```

---

## 37. `vet_hospitalizations`

```sql
CREATE TABLE vet_hospitalizations (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    patient_id      UUID        NOT NULL REFERENCES vet_patients(id),
    clinical_record_id UUID     NULL REFERENCES vet_clinical_records(id),
    veterinario_id  UUID        NOT NULL REFERENCES users(id),
    sede_id         UUID        NULL REFERENCES cfg_sedes(id),

    motivo          TEXT NOT NULL,
    jaula_numero    VARCHAR(20) NULL,

    estado          VARCHAR(20) NOT NULL DEFAULT 'internado'
                    CHECK (estado IN ('internado','en_cirugia','recuperacion','alta','fallecio')),

    fecha_ingreso   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    fecha_alta      TIMESTAMPTZ NULL,
    -- Días de internamiento: columna normal mantenida por trigger (PostgreSQL no permite NOW()
    -- dentro de GENERATED STORED — la expresión debe ser inmutable).
    dias_internado  SMALLINT NOT NULL DEFAULT 0,

    -- Plan de tratamiento
    tratamiento_activo  JSONB NULL,
    -- [{"medicamento": "...", "dosis": "...", "frecuencia": "c/8h", "via": "IV"}]

    dieta_indicada  VARCHAR(200) NULL,
    restricciones   TEXT NULL,

    -- Alta
    instrucciones_alta  TEXT NULL,
    condicion_al_alta   VARCHAR(20) NULL
                        CHECK (condicion_al_alta IN ('mejorado','estable','sin_cambios','empeorado','fallecio',NULL)),

    venta_id        UUID NULL,   -- FK → vet_sales
    precio_dia      DECIMAL(10,2) NULL,

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_by_id UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_hosp_activas ON vet_hospitalizations(estado, fecha_ingreso)
    WHERE estado IN ('internado','en_cirugia','recuperacion');
```

---

## 38. `vet_vital_signs_log`

```sql
-- Monitoreo cada N horas de pacientes hospitalizados
CREATE TABLE vet_vital_signs_log (
    id                  BIGSERIAL PRIMARY KEY,
    hospitalization_id  UUID NOT NULL REFERENCES vet_hospitalizations(id) ON DELETE CASCADE,
    patient_id          UUID NOT NULL REFERENCES vet_patients(id),

    registrado_por_id   UUID NOT NULL REFERENCES users(id),
    fecha_hora          TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    peso_kg             DECIMAL(5,2) NULL,
    temperatura_c       DECIMAL(4,1) NULL,
    frecuencia_cardiaca SMALLINT NULL,
    frecuencia_resp     SMALLINT NULL,
    saturacion_o2       SMALLINT NULL,
    presion_arterial_s  SMALLINT NULL,
    presion_arterial_d  SMALLINT NULL,
    mucosas             VARCHAR(30) NULL,
    hidratacion_pct     SMALLINT NULL,
    nivel_consciencia   VARCHAR(20) NULL
                        CHECK (nivel_consciencia IN ('alerta','deprimido','estuporoso','comatoso',NULL)),
    ingesta_agua_ml     SMALLINT NULL,
    orina_ml            SMALLINT NULL,
    heces               VARCHAR(20) NULL,   -- 'normal','diarrea','ausentes'

    notas               TEXT NULL,
    alertas             TEXT NULL   -- valores fuera de rango para llamar al veterinario
);

CREATE INDEX idx_vitals_hosp ON vet_vital_signs_log(hospitalization_id, fecha_hora DESC);
```

---

## 39. `vet_suppliers`

```sql
CREATE TABLE vet_suppliers (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    razon_social    VARCHAR(200) NOT NULL,
    nombre_comercial VARCHAR(150) NULL,
    ruc             VARCHAR(11)  NULL UNIQUE,
    contacto_nombre VARCHAR(100) NULL,
    telefono        VARCHAR(20)  NULL,
    telefono_alt    VARCHAR(20)  NULL,
    email           VARCHAR(150) NULL,
    direccion       VARCHAR(255) NULL,
    ubigeo_id       INTEGER NULL REFERENCES public.ubigeos(id),

    -- Condiciones comerciales
    dias_credito        SMALLINT NULL DEFAULT 0,   -- días de crédito otorgado
    limite_credito      DECIMAL(10,2) NULL,
    moneda_negociacion  CHAR(3) NOT NULL DEFAULT 'PEN',

    -- Productos principales que provee
    categorias_producto JSONB NULL,   -- ['medicamentos','vacunas','alimentos']

    activo          BOOLEAN NOT NULL DEFAULT TRUE,
    notas           TEXT NULL,

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at  TIMESTAMPTZ NULL,
    created_by_id UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_suppliers_nombre ON vet_suppliers(LOWER(razon_social)) WHERE deleted_at IS NULL;
```

---

## 40. `vet_products`

```sql
CREATE TABLE vet_products (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    categoria_id    UUID        NULL REFERENCES vet_product_categories(id),

    tipo            VARCHAR(20) NOT NULL
                    CHECK (tipo IN ('producto','servicio','vacuna','medicamento','alimento','accesorio')),

    nombre          VARCHAR(200) NOT NULL,
    nombre_generico VARCHAR(200) NULL,   -- para medicamentos con nombre comercial y genérico
    codigo          VARCHAR(50)  NULL UNIQUE,
    codigo_barras   VARCHAR(50)  NULL UNIQUE,
    descripcion     TEXT NULL,
    marca           VARCHAR(100) NULL,
    presentacion    VARCHAR(100) NULL,   -- '10 comprimidos', 'frasco 250ml'
    concentracion   VARCHAR(100) NULL,   -- '250mg/5ml'
    especie_indicada VARCHAR(100) NULL,  -- 'Caninos', 'Caninos y felinos'

    -- Precios
    precio_venta    DECIMAL(10,2) NOT NULL,
    precio_costo    DECIMAL(10,2) NULL,
    precio_mayor    DECIMAL(10,2) NULL,   -- precio al por mayor (si aplica)

    -- Unidades
    unidad_medida   VARCHAR(20) NOT NULL DEFAULT 'unidad'
                    CHECK (unidad_medida IN ('unidad','ml','mg','pastilla','kg','gr','ampolla','caja','frasco','sobre','dosis')),
    unidad_compra   VARCHAR(20) NULL,   -- 'caja × 100' — diferente a la unidad de venta

    -- SUNAT
    codigo_sunat    VARCHAR(10) NULL,   -- código de producto/servicio SUNAT
    igv_tipo        VARCHAR(15) NOT NULL DEFAULT 'gravado'
                    CHECK (igv_tipo IN ('gravado','exonerado','inafecto')),

    -- Control
    requiere_receta     BOOLEAN NOT NULL DEFAULT FALSE,
    es_controlado       BOOLEAN NOT NULL DEFAULT FALSE,   -- psicotrópicos/estupefacientes
    controla_stock      BOOLEAN NOT NULL DEFAULT TRUE,    -- FALSE para servicios
    stock_minimo_global DECIMAL(10,3) NULL,

    -- Proveedor habitual
    supplier_id     UUID NULL REFERENCES vet_suppliers(id),

    activo          BOOLEAN NOT NULL DEFAULT TRUE,

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ NULL,
    created_by_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_products_nombre   ON vet_products(LOWER(nombre))       WHERE deleted_at IS NULL;
CREATE INDEX idx_products_tipo     ON vet_products(tipo)                 WHERE deleted_at IS NULL;
CREATE INDEX idx_products_codigo   ON vet_products(codigo)               WHERE codigo IS NOT NULL AND deleted_at IS NULL;
CREATE INDEX idx_products_barras   ON vet_products(codigo_barras)        WHERE codigo_barras IS NOT NULL;
CREATE INDEX idx_products_supplier ON vet_products(supplier_id)          WHERE deleted_at IS NULL;
```

---

## 41. `vet_product_categories`

```sql
CREATE TABLE vet_product_categories (
    id          UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    nombre      VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT NULL,
    parent_id   UUID NULL REFERENCES vet_product_categories(id),
    -- Categorías anidadas: Medicamentos → Antibióticos → Penicilinas
    orden       SMALLINT NOT NULL DEFAULT 0,
    activa      BOOLEAN NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

---

## 42. `vet_stock_items`

```sql
-- Cada lote de un producto es un stock_item independiente
CREATE TABLE vet_stock_items (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    product_id      UUID        NOT NULL REFERENCES vet_products(id),
    sede_id         UUID        NULL REFERENCES cfg_sedes(id),

    lote            VARCHAR(50) NULL,
    fecha_vencimiento DATE NULL,
    ubicacion       VARCHAR(80) NULL,   -- 'Refrigerador A', 'Estante 2-B'

    -- Stock actual (mantenido por trigger al insertar en stock_movements)
    cantidad        DECIMAL(10,3) NOT NULL DEFAULT 0,
    stock_minimo    DECIMAL(10,3) NOT NULL DEFAULT 0,

    -- Control de alertas (para no repetir alertas innecesariamente)
    alerta_minimo_enviada   BOOLEAN NOT NULL DEFAULT FALSE,
    alerta_vencimiento_enviada BOOLEAN NOT NULL DEFAULT FALSE,

    purchase_item_id UUID NULL,   -- FK → vet_purchase_items (trazabilidad de compra)

    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_stock_product        ON vet_stock_items(product_id);
CREATE INDEX idx_stock_sede           ON vet_stock_items(sede_id) WHERE sede_id IS NOT NULL;
CREATE INDEX idx_stock_vencimiento    ON vet_stock_items(fecha_vencimiento)
    WHERE fecha_vencimiento IS NOT NULL;
CREATE INDEX idx_stock_bajo_minimo    ON vet_stock_items(product_id)
    WHERE cantidad <= stock_minimo AND cantidad > 0;
CREATE INDEX idx_stock_agotado        ON vet_stock_items(product_id)
    WHERE cantidad = 0;
```

---

## 43. `vet_stock_movements`

```sql
-- INMUTABLE — Solo INSERT. Nunca UPDATE ni DELETE.
-- El stock actual = suma de todos los movimientos del lote.
CREATE TABLE vet_stock_movements (
    id              BIGSERIAL PRIMARY KEY,
    stock_item_id   UUID NOT NULL REFERENCES vet_stock_items(id),
    product_id      UUID NOT NULL REFERENCES vet_products(id),   -- desnormalizado
    sede_id         UUID NULL,

    tipo_movimiento VARCHAR(25) NOT NULL
                    CHECK (tipo_movimiento IN (
                        'entrada_compra',      -- recepción de OC a proveedor
                        'entrada_ajuste',      -- ajuste positivo de inventario
                        'salida_venta',        -- vendido en mostrador
                        'salida_consulta',     -- usado en consulta clínica
                        'salida_vacuna',       -- aplicado en vacunación
                        'salida_cirugia',      -- usado en cirugía
                        'salida_hospitalizacion',
                        'salida_ajuste',       -- ajuste negativo
                        'merma',               -- vencimiento, rotura, pérdida
                        'devolucion_proveedor',
                        'devolucion_cliente',
                        'traslado_entrada',    -- traslado entre sedes (entrada)
                        'traslado_salida'      -- traslado entre sedes (salida)
                    )),

    -- Positivo = entrada de stock, Negativo = salida
    cantidad            DECIMAL(10,3) NOT NULL,
    cantidad_anterior   DECIMAL(10,3) NOT NULL,   -- auditoría
    cantidad_resultante DECIMAL(10,3) NOT NULL,   -- auditoría

    -- Referencia al documento origen
    referencia_tipo     VARCHAR(30) NULL,   -- 'venta','receta','cirugia','orden_compra'
    referencia_id       UUID NULL,
    referencia_num      VARCHAR(50) NULL,   -- número legible: 'VTA-2026-0001'

    precio_unitario     DECIMAL(10,2) NULL,  -- precio al momento del movimiento

    notas               VARCHAR(255) NULL,
    usuario_id          UUID NOT NULL REFERENCES users(id),
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_stockmov_item    ON vet_stock_movements(stock_item_id, created_at DESC);
CREATE INDEX idx_stockmov_product ON vet_stock_movements(product_id, created_at DESC);
CREATE INDEX idx_stockmov_fecha   ON vet_stock_movements(created_at DESC);
```

---

## 44. `vet_stock_alerts`

```sql
-- Registro de alertas de stock enviadas al equipo
CREATE TABLE vet_stock_alerts (
    id              BIGSERIAL PRIMARY KEY,
    stock_item_id   UUID NOT NULL REFERENCES vet_stock_items(id),
    product_id      UUID NOT NULL REFERENCES vet_products(id),
    tipo_alerta     VARCHAR(20) NOT NULL
                    CHECK (tipo_alerta IN ('stock_minimo','stock_agotado','por_vencer','vencido')),
    cantidad_actual DECIMAL(10,3) NOT NULL,
    stock_minimo    DECIMAL(10,3) NULL,
    fecha_vencimiento DATE NULL,
    enviado_a       JSONB NOT NULL,   -- [{"user_id": "...", "canal": "email"}]
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

---

## 45. `vet_purchases`

```sql
-- Órdenes de compra a proveedores
CREATE TABLE vet_purchases (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    numero          VARCHAR(20) NOT NULL UNIQUE,   -- 'OC-2026-0001'
    supplier_id     UUID        NOT NULL REFERENCES vet_suppliers(id),
    sede_id         UUID        NULL REFERENCES cfg_sedes(id),

    estado          VARCHAR(20) NOT NULL DEFAULT 'borrador'
                    CHECK (estado IN ('borrador','enviada','confirmada','recibida_parcial','recibida','cancelada')),

    fecha_orden     DATE NOT NULL DEFAULT CURRENT_DATE,
    fecha_entrega_estimada DATE NULL,
    fecha_recepcion DATE NULL,

    -- Totales
    subtotal        DECIMAL(10,2) NOT NULL DEFAULT 0,
    igv_monto       DECIMAL(10,2) NOT NULL DEFAULT 0,
    total           DECIMAL(10,2) NOT NULL DEFAULT 0,

    -- Factura del proveedor
    factura_proveedor_num   VARCHAR(30) NULL,
    factura_proveedor_fecha DATE NULL,

    -- Condición de pago
    condicion_pago  VARCHAR(20) NOT NULL DEFAULT 'contado'
                    CHECK (condicion_pago IN ('contado','credito_15','credito_30','credito_60')),
    fecha_vencimiento_pago DATE NULL,

    notas           TEXT NULL,
    archivos_url    JSONB NULL,   -- facturas escaneadas, guías de remisión

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ NULL,
    created_by_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_purchases_supplier ON vet_purchases(supplier_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_purchases_estado   ON vet_purchases(estado)      WHERE deleted_at IS NULL;
```

---

## 46. `vet_purchase_items`

```sql
CREATE TABLE vet_purchase_items (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    purchase_id     UUID        NOT NULL REFERENCES vet_purchases(id) ON DELETE CASCADE,
    product_id      UUID        NOT NULL REFERENCES vet_products(id),

    cantidad_pedida     DECIMAL(10,3) NOT NULL,
    cantidad_recibida   DECIMAL(10,3) NOT NULL DEFAULT 0,
    precio_unitario     DECIMAL(10,2) NOT NULL,
    descuento_pct       DECIMAL(5,2) NOT NULL DEFAULT 0,
    subtotal            DECIMAL(10,2) NOT NULL,

    -- Datos del lote recibido (se llenan al marcar como recibida)
    lote                VARCHAR(50) NULL,
    fecha_vencimiento   DATE NULL,
    ubicacion_destino   VARCHAR(80) NULL,

    -- Una vez recibido, crea un vet_stock_item
    stock_item_id       UUID NULL REFERENCES vet_stock_items(id),

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

---

## 47. `vet_sales`

```sql
CREATE TABLE vet_sales (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    numero          VARCHAR(20) NOT NULL UNIQUE,   -- 'VTA-2026-0001'

    owner_id        UUID        NOT NULL REFERENCES vet_owners(id),
    patient_id      UUID        NULL REFERENCES vet_patients(id),
    clinical_record_id UUID     NULL REFERENCES vet_clinical_records(id),
    sede_id         UUID        NULL REFERENCES cfg_sedes(id),
    cajero_id       UUID        NOT NULL REFERENCES users(id),
    cash_session_id UUID        NULL,   -- FK → vet_cash_sessions

    estado          VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                    CHECK (estado IN ('pendiente','pagado','parcial','anulado','reembolsado')),

    -- Totales
    subtotal            DECIMAL(10,2) NOT NULL DEFAULT 0,
    descuento_monto     DECIMAL(10,2) NOT NULL DEFAULT 0,
    igv_monto           DECIMAL(10,2) NOT NULL DEFAULT 0,
    total               DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_pagado        DECIMAL(10,2) NOT NULL DEFAULT 0,
    saldo_pendiente     DECIMAL(10,2) GENERATED ALWAYS AS (total - total_pagado) STORED,

    notas           TEXT NULL,

    -- Facturación electrónica SUNAT
    fel_estado      VARCHAR(20) NOT NULL DEFAULT 'sin_cpe'
                    CHECK (fel_estado IN ('sin_cpe','pendiente_emision','emitido','rechazado','anulado')),
    fel_document_id UUID NULL,   -- FK → fel_documents

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ NULL,
    created_by_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_sales_owner    ON vet_sales(owner_id)        WHERE deleted_at IS NULL;
CREATE INDEX idx_sales_estado   ON vet_sales(estado)          WHERE deleted_at IS NULL;
CREATE INDEX idx_sales_fecha    ON vet_sales(created_at DESC) WHERE deleted_at IS NULL;
CREATE INDEX idx_sales_fel      ON vet_sales(fel_estado)      WHERE fel_estado IN ('pendiente_emision','rechazado');
CREATE INDEX idx_sales_session  ON vet_sales(cash_session_id) WHERE cash_session_id IS NOT NULL;
```

---

## 48. `vet_sale_items`

```sql
CREATE TABLE vet_sale_items (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    sale_id         UUID        NOT NULL REFERENCES vet_sales(id) ON DELETE CASCADE,
    product_id      UUID        NOT NULL REFERENCES vet_products(id),

    -- SNAPSHOT al momento de la venta — inmutable ante cambios futuros del producto
    descripcion_snapshot VARCHAR(300) NOT NULL,
    codigo_snapshot      VARCHAR(50)  NULL,
    igv_tipo_snapshot    VARCHAR(15)  NOT NULL,
    unidad_snapshot      VARCHAR(20)  NOT NULL,

    cantidad        DECIMAL(10,3) NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,   -- sin IGV
    descuento_pct   DECIMAL(5,2)  NOT NULL DEFAULT 0,
    descuento_monto DECIMAL(10,2) NOT NULL DEFAULT 0,
    igv_monto       DECIMAL(10,2) NOT NULL DEFAULT 0,
    subtotal        DECIMAL(10,2) NOT NULL,   -- con IGV aplicado

    -- Si se descontó de un lote específico de stock
    stock_item_id   UUID NULL REFERENCES vet_stock_items(id),

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_sale_items_sale    ON vet_sale_items(sale_id);
CREATE INDEX idx_sale_items_product ON vet_sale_items(product_id, created_at DESC);
```

---

## 49. `vet_payments`

```sql
-- Pagos recibidos por una venta (puede haber múltiples para pagos mixtos)
CREATE TABLE vet_payments (
    id          UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    sale_id     UUID        NOT NULL REFERENCES vet_sales(id) ON DELETE CASCADE,

    monto       DECIMAL(10,2) NOT NULL,
    metodo      VARCHAR(20)   NOT NULL
                CHECK (metodo IN ('efectivo','yape','plin','tarjeta_debito','tarjeta_credito',
                                  'transferencia','deposito','credito_clinica','otro')),

    referencia  VARCHAR(100) NULL,   -- N° operación Yape/Plin, N° transferencia
    comprobante_url VARCHAR(500) NULL,

    cajero_id   UUID NOT NULL REFERENCES users(id),
    pagado_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_payments_sale  ON vet_payments(sale_id);
CREATE INDEX idx_payments_fecha ON vet_payments(pagado_at DESC);
```

---

## 50. `vet_cash_sessions`

```sql
-- Sesiones de caja (turno del cajero)
CREATE TABLE vet_cash_sessions (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    cajero_id       UUID        NOT NULL REFERENCES users(id),
    sede_id         UUID        NULL REFERENCES cfg_sedes(id),

    estado          VARCHAR(10) NOT NULL DEFAULT 'abierta'
                    CHECK (estado IN ('abierta','cerrada')),

    apertura_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    cierre_at       TIMESTAMPTZ NULL,

    monto_inicial   DECIMAL(10,2) NOT NULL DEFAULT 0,   -- sencillo inicial
    monto_real_cierre DECIMAL(10,2) NULL,               -- conteo físico al cerrar
    diferencia      DECIMAL(10,2) GENERATED ALWAYS AS (
                        monto_real_cierre - (
                            monto_inicial +
                            COALESCE((SELECT SUM(monto) FROM vet_payments WHERE metodo = 'efectivo'
                                      -- Esto no puede ser una expresión generada con subquery en PG
                                      -- Se calculará en la app al cerrar caja
                            ), 0)
                        )
                    ) STORED,
    -- NOTA: diferencia se calcula en la aplicación al cerrar.
    -- Reemplazar el GENERATED ALWAYS con columna normal calculada en app.

    notas_apertura  TEXT NULL,
    notas_cierre    TEXT NULL,
    arqueo_detalle  JSONB NULL,
    -- {"billetes_200": 3, "billetes_100": 5, "billetes_50": 2, ...}

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_cash_sessions_cajero ON vet_cash_sessions(cajero_id, apertura_at DESC);
CREATE INDEX idx_cash_sessions_abierta ON vet_cash_sessions(cajero_id)
    WHERE estado = 'abierta';
```

---

## 51. `vet_discounts`

```sql
-- Descuentos configurables aplicables en ventas
CREATE TABLE vet_discounts (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    nombre          VARCHAR(100) NOT NULL,
    codigo          VARCHAR(30)  NULL UNIQUE,   -- para aplicar en caja

    tipo            VARCHAR(15) NOT NULL
                    CHECK (tipo IN ('porcentaje','monto_fijo')),
    valor           DECIMAL(10,2) NOT NULL,

    -- Restricciones
    aplica_a        VARCHAR(20) NOT NULL DEFAULT 'venta_total'
                    CHECK (aplica_a IN ('venta_total','producto','servicio','categoria')),
    product_id      UUID NULL REFERENCES vet_products(id),
    categoria_id    UUID NULL REFERENCES vet_product_categories(id),

    requiere_autorizacion BOOLEAN NOT NULL DEFAULT FALSE,
    autorizador_rol       VARCHAR(30) NULL,   -- rol mínimo para aprobar

    activo          BOOLEAN NOT NULL DEFAULT TRUE,
    valido_desde    DATE NULL,
    valido_hasta    DATE NULL,

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

---

## 52. `fel_series`

```sql
CREATE TABLE fel_series (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    sede_id         UUID        NULL REFERENCES cfg_sedes(id),
    tipo_cpe        SMALLINT    NOT NULL
                    CHECK (tipo_cpe IN (1,3,7,8)),
    -- 1=Factura | 3=Boleta | 7=Nota de Crédito | 8=Nota de Débito

    serie           VARCHAR(4) NOT NULL,   -- 'F001', 'B001', 'FC01', 'BC01'
    correlativo_actual BIGINT NOT NULL DEFAULT 0,
    activo          BOOLEAN NOT NULL DEFAULT TRUE,

    CONSTRAINT uq_serie_tipo_sede UNIQUE (tipo_cpe, serie, sede_id)
);

-- Función thread-safe para obtener el siguiente correlativo
CREATE OR REPLACE FUNCTION next_correlativo(p_serie_id UUID)
RETURNS BIGINT AS $$
DECLARE
    v_next BIGINT;
BEGIN
    UPDATE fel_series
    SET correlativo_actual = correlativo_actual + 1
    WHERE id = p_serie_id
    RETURNING correlativo_actual INTO v_next;
    RETURN v_next;
END;
$$ LANGUAGE plpgsql;
```

---

## 53. `fel_documents`

```sql
-- INMUTABLE — Solo INSERT. El estado puede actualizarse en columnas específicas.
CREATE TABLE fel_documents (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    venta_id        UUID        NOT NULL REFERENCES vet_sales(id),
    serie_id        UUID        NOT NULL REFERENCES fel_series(id),

    -- Datos del comprobante
    tipo_cpe        SMALLINT    NOT NULL,
    serie           VARCHAR(4)  NOT NULL,
    correlativo     BIGINT      NOT NULL,
    numero_completo VARCHAR(15) NOT NULL,   -- 'F001-00000127'
    fecha_emision   DATE        NOT NULL,
    moneda          CHAR(3)     NOT NULL DEFAULT 'PEN',

    -- Receptor
    receptor_tipo_doc   SMALLINT NOT NULL,   -- 6=RUC, 1=DNI, 4=CE, 7=Pasaporte
    receptor_num_doc    VARCHAR(15) NOT NULL,
    receptor_nombre     VARCHAR(200) NOT NULL,
    receptor_direccion  VARCHAR(255) NULL,
    receptor_email      VARCHAR(150) NULL,

    -- Totales
    total_gravada   DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_exonerada DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_inafecta  DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_igv       DECIMAL(10,2) NOT NULL DEFAULT 0,
    descuento_global DECIMAL(10,2) NOT NULL DEFAULT 0,
    total           DECIMAL(10,2) NOT NULL,

    -- Estado SUNAT
    estado          VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                    CHECK (estado IN (
                        'pendiente',
                        'enviado',
                        'aceptado',
                        'observado',
                        'rechazado',
                        'baja_pendiente',
                        'baja_aceptada'
                    )),

    -- Respuesta NubeFact
    nubefact_id             VARCHAR(100) NULL,
    nubefact_url_pdf        VARCHAR(500) NULL,
    nubefact_url_xml        VARCHAR(500) NULL,
    nubefact_url_cdr        VARCHAR(500) NULL,
    nubefact_enlace_consulta VARCHAR(500) NULL,

    -- CDR SUNAT
    cdr_codigo          VARCHAR(10) NULL,   -- '0' = aceptado
    cdr_descripcion     TEXT NULL,
    cdr_notas           TEXT NULL,

    -- Control de envío y reintentos (job de Laravel Horizon)
    intentos_envio      SMALLINT NOT NULL DEFAULT 0,
    ultimo_intento_at   TIMESTAMPTZ NULL,
    error_mensaje       TEXT NULL,

    -- Payload para auditoría y debugging
    payload_enviado     JSONB NULL,
    respuesta_recibida  JSONB NULL,

    -- Envío al cliente
    enviado_cliente     BOOLEAN NOT NULL DEFAULT FALSE,
    enviado_cliente_at  TIMESTAMPTZ NULL,
    canal_envio         VARCHAR(20) NULL,

    emitido_at          TIMESTAMPTZ NULL,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_by_id       UUID NULL REFERENCES users(id) ON DELETE SET NULL,

    CONSTRAINT uq_fel_serie_correlativo UNIQUE (tipo_cpe, serie, correlativo)
);

CREATE INDEX idx_fel_estado     ON fel_documents(estado) WHERE estado IN ('pendiente','enviado','rechazado');
CREATE INDEX idx_fel_fecha      ON fel_documents(fecha_emision DESC);
CREATE INDEX idx_fel_venta      ON fel_documents(venta_id);
CREATE INDEX idx_fel_reintentos ON fel_documents(ultimo_intento_at)
    WHERE estado IN ('pendiente','rechazado') AND intentos_envio < 3;
```

---

## 54. `fel_document_items`

```sql
CREATE TABLE fel_document_items (
    id              UUID    DEFAULT gen_random_uuid() PRIMARY KEY,
    document_id     UUID    NOT NULL REFERENCES fel_documents(id) ON DELETE CASCADE,

    numero_orden    SMALLINT NOT NULL,
    unidad_medida   VARCHAR(5) NOT NULL DEFAULT 'ZZ',   -- ZZ=servicio, NIU=unidad SUNAT
    codigo_producto VARCHAR(50) NULL,
    descripcion     VARCHAR(500) NOT NULL,
    cantidad        DECIMAL(10,3) NOT NULL,
    valor_unitario  DECIMAL(10,6) NOT NULL,   -- sin IGV — 6 decimales exigidos por SUNAT
    precio_unitario DECIMAL(10,6) NOT NULL,   -- con IGV — 6 decimales
    descuento       DECIMAL(10,2) NOT NULL DEFAULT 0,
    tipo_igv        SMALLINT NOT NULL DEFAULT 1,   -- 1=gravado 2=exonerado 3=inafecto
    igv_monto       DECIMAL(10,2) NOT NULL DEFAULT 0,
    subtotal        DECIMAL(10,2) NOT NULL
);
```

---

## 55. `fel_void_requests`

```sql
-- Comunicación de baja (anulación de CPE) ante SUNAT
CREATE TABLE fel_void_requests (
    id              UUID    DEFAULT gen_random_uuid() PRIMARY KEY,
    document_id     UUID    NOT NULL REFERENCES fel_documents(id),
    motivo          TEXT    NOT NULL,
    fecha_baja      DATE    NOT NULL DEFAULT CURRENT_DATE,

    estado          VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                    CHECK (estado IN ('pendiente','enviado','aceptado','rechazado')),

    nubefact_ticket VARCHAR(100) NULL,   -- ticket de la comunicación de baja en NubeFact
    cdr_baja_codigo VARCHAR(10) NULL,
    cdr_baja_desc   TEXT NULL,

    intentos        SMALLINT NOT NULL DEFAULT 0,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_by_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL
);
```

---

## 56. `fel_summary_documents`

```sql
-- Resumen de boletas (batch diario) — SUNAT exige enviar boletas en lote al día siguiente
CREATE TABLE fel_summary_documents (
    id              UUID    DEFAULT gen_random_uuid() PRIMARY KEY,
    fecha_referencia DATE   NOT NULL,   -- fecha de las boletas del lote
    estado          VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                    CHECK (estado IN ('pendiente','enviado','aceptado','rechazado')),

    -- Lista de boletas incluidas en el resumen
    documentos_incluidos JSONB NOT NULL,
    -- [{"serie": "B001", "correlativo_inicio": 1, "correlativo_fin": 45}]

    nubefact_ticket VARCHAR(100) NULL,
    cdr_codigo      VARCHAR(10) NULL,
    cdr_descripcion TEXT NULL,

    total_documentos SMALLINT NOT NULL DEFAULT 0,
    enviado_at      TIMESTAMPTZ NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX idx_fel_summary_fecha ON fel_summary_documents(fecha_referencia)
    WHERE estado NOT IN ('rechazado');
```

---

## 57. `vet_grooming_services`

```sql
CREATE TABLE vet_grooming_services (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    patient_id      UUID        NOT NULL REFERENCES vet_patients(id),
    owner_id        UUID        NOT NULL REFERENCES vet_owners(id),
    appointment_id  UUID        NULL REFERENCES vet_appointments(id),
    groomer_id      UUID        NOT NULL REFERENCES users(id),
    paquete_id      UUID        NULL REFERENCES vet_grooming_packages(id),
    sede_id         UUID        NULL REFERENCES cfg_sedes(id),

    servicio_tipo   VARCHAR(30) NOT NULL
                    CHECK (servicio_tipo IN (
                        'bano_completo', 'bano_medicado', 'corte_estandar',
                        'corte_especial', 'deslanado', 'desparasitacion_externa',
                        'peluqueria_completa', 'bano_y_corte', 'solo_bano', 'otro'
                    )),

    estado          VARCHAR(20) NOT NULL DEFAULT 'recibido'
                    CHECK (estado IN ('recibido','en_proceso','listo','retirado','cancelado')),

    hora_recepcion  TIMESTAMPTZ NULL,
    hora_inicio     TIMESTAMPTZ NULL,
    hora_listo      TIMESTAMPTZ NULL,
    hora_retiro     TIMESTAMPTZ NULL,

    -- Notificación automática WhatsApp cuando estado = 'listo'
    notificado_listo        BOOLEAN NOT NULL DEFAULT FALSE,
    notificado_listo_at     TIMESTAMPTZ NULL,
    notificado_wa_msg_id    VARCHAR(100) NULL,

    -- Observaciones al recibir la mascota
    observaciones_recepcion TEXT NULL,
    -- Condición del pelaje, parásitos visibles, lesiones encontradas, nódulos

    -- Observaciones del groomer al terminar
    observaciones_finales   TEXT NULL,
    notas_veterinario       TEXT NULL,   -- si encuentra algo sospechoso → deriva al vet

    -- Fotos antes y después (Cloudflare R2) — diferenciador competitivo
    fotos_antes_url     JSONB NULL,   -- ["https://r2.../antes_1.jpg"]
    fotos_despues_url   JSONB NULL,   -- ["https://r2.../despues_1.jpg"]

    -- Insumos utilizados
    productos_usados    JSONB NULL,
    -- [{"product_id": "uuid", "nombre": "Shampoo medicado", "cantidad_ml": 50}]

    precio          DECIMAL(10,2) NOT NULL,
    venta_id        UUID NULL,   -- FK → vet_sales

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_by_id UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_grooming_estado   ON vet_grooming_services(estado)
    WHERE estado IN ('recibido','en_proceso','listo');
CREATE INDEX idx_grooming_patient  ON vet_grooming_services(patient_id, created_at DESC);
CREATE INDEX idx_grooming_groomer  ON vet_grooming_services(groomer_id, hora_recepcion DESC);
```

---

## 58. `vet_grooming_packages`

```sql
-- Paquetes de peluquería con precio fijo y servicios incluidos
CREATE TABLE vet_grooming_packages (
    id          UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    nombre      VARCHAR(150) NOT NULL,
    descripcion TEXT NULL,
    especie     VARCHAR(20)  NULL,      -- NULL = aplica a todas
    tamano      VARCHAR(20)  NULL
                CHECK (tamano IN ('pequeno','mediano','grande','gigante',NULL)),
    precio      DECIMAL(10,2) NOT NULL,
    duracion_min SMALLINT NULL,
    incluye     JSONB NULL,             -- lista de servicios del paquete
    activo      BOOLEAN NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

---

## 59. `vet_boarding`

```sql
-- Guardería / Hotel para mascotas
CREATE TABLE vet_boarding (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    patient_id      UUID        NOT NULL REFERENCES vet_patients(id),
    owner_id        UUID        NOT NULL REFERENCES vet_owners(id),
    responsable_id  UUID        NOT NULL REFERENCES users(id),
    sede_id         UUID        NULL REFERENCES cfg_sedes(id),

    tipo            VARCHAR(20) NOT NULL DEFAULT 'guarderia'
                    CHECK (tipo IN ('guarderia','hotel','spa')),
    -- guarderia = por horas | hotel = por noches | spa = día completo con servicios

    estado          VARCHAR(20) NOT NULL DEFAULT 'reservado'
                    CHECK (estado IN ('reservado','ingresado','en_cuidado','alta','cancelado')),

    jaula_numero    VARCHAR(20) NULL,
    fecha_ingreso   TIMESTAMPTZ NOT NULL,
    fecha_alta      TIMESTAMPTZ NULL,
    fecha_alta_estimada TIMESTAMPTZ NULL,

    -- Información de cuidado
    alimentacion_indicada   TEXT NULL,   -- 'Acana adulto 1 taza cada 12h'
    medicacion_activa       JSONB NULL,
    instrucciones_especiales TEXT NULL,
    nivel_actividad         VARCHAR(20) NULL
                            CHECK (nivel_actividad IN ('bajo','normal','alto',NULL)),

    -- Contacto de emergencia durante la estadía
    contacto_emergencia_nombre VARCHAR(100) NULL,
    contacto_emergencia_tel    VARCHAR(20)  NULL,

    -- Precio
    precio_dia      DECIMAL(10,2) NULL,
    precio_hora     DECIMAL(10,2) NULL,
    total_estimado  DECIMAL(10,2) NULL,
    venta_id        UUID NULL,

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_by_id UUID NULL REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_boarding_activos ON vet_boarding(estado, fecha_ingreso)
    WHERE estado IN ('reservado','ingresado','en_cuidado');
```

---

## 60. `vet_boarding_daily_logs`

```sql
-- Control diario de mascotas en guardería/hotel
CREATE TABLE vet_boarding_daily_logs (
    id              BIGSERIAL PRIMARY KEY,
    boarding_id     UUID NOT NULL REFERENCES vet_boarding(id) ON DELETE CASCADE,
    patient_id      UUID NOT NULL REFERENCES vet_patients(id),

    fecha           DATE NOT NULL,
    turno           VARCHAR(10) NOT NULL CHECK (turno IN ('manana','tarde','noche')),
    responsable_id  UUID NOT NULL REFERENCES users(id),

    -- Alimentación
    comio           BOOLEAN NULL,
    cantidad_comida VARCHAR(50) NULL,
    bebio_agua      BOOLEAN NULL,

    -- Eliminación
    orina           VARCHAR(20) NULL CHECK (orina IN ('normal','abundante','escasa','ausente',NULL)),
    heces           VARCHAR(20) NULL CHECK (heces IN ('normal','diarrea','con_sangre','ausente',NULL)),
    vomito          BOOLEAN NULL DEFAULT FALSE,

    -- Estado general
    estado_animo    VARCHAR(20) NULL CHECK (estado_animo IN ('activo','tranquilo','deprimido','ansioso','agresivo',NULL)),
    actividad_fisica VARCHAR(20) NULL,
    observaciones   TEXT NULL,
    foto_url        VARCHAR(500) NULL,   -- foto del día para enviar al propietario

    -- Envío al propietario
    enviado_propietario     BOOLEAN NOT NULL DEFAULT FALSE,
    enviado_propietario_at  TIMESTAMPTZ NULL,

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_boarding_logs_boarding ON vet_boarding_daily_logs(boarding_id, fecha DESC);
```

---

## 61. `notifications_queue`

```sql
-- Cola de mensajes pendientes de enviar (WhatsApp, email, SMS)
CREATE TABLE notifications_queue (
    id              UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    tipo            VARCHAR(40) NOT NULL,
    -- 'cita_48h' | 'cita_2h' | 'vacuna_proxima' | 'grooming_listo' | 'resultado_lab'
    -- 'boarding_update' | 'stock_minimo' | 'bienvenida' | 'custom'

    canal           VARCHAR(20) NOT NULL CHECK (canal IN ('whatsapp','email','sms')),
    destinatario    VARCHAR(150) NOT NULL,   -- número WA o email
    destinatario_nombre VARCHAR(100) NULL,

    asunto          VARCHAR(200) NULL,   -- solo email
    cuerpo          TEXT NOT NULL,       -- mensaje ya procesado con variables

    -- Referencia al objeto que generó la notificación
    referencia_tipo VARCHAR(30) NULL,   -- 'appointment','vaccination','grooming'
    referencia_id   UUID NULL,

    -- Programación
    enviar_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    prioridad       SMALLINT NOT NULL DEFAULT 5 CHECK (prioridad BETWEEN 1 AND 10),
    -- 1=máxima (urgencias), 5=normal, 10=baja (marketing)

    -- Estado de envío
    estado          VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                    CHECK (estado IN ('pendiente','procesando','enviado','fallido','cancelado')),
    intentos        SMALLINT NOT NULL DEFAULT 0,
    max_intentos    SMALLINT NOT NULL DEFAULT 3,
    ultimo_intento_at TIMESTAMPTZ NULL,
    error_mensaje   TEXT NULL,

    -- Metadatos de la respuesta del proveedor
    proveedor_msg_id VARCHAR(200) NULL,   -- ID del mensaje en Twilio/Brevo

    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_notif_queue_enviar ON notifications_queue(enviar_at, prioridad)
    WHERE estado = 'pendiente' AND intentos < max_intentos;
CREATE INDEX idx_notif_queue_ref    ON notifications_queue(referencia_tipo, referencia_id);
```

---

## 62. `notifications_sent`

```sql
-- Historial inmutable de mensajes enviados (auditoría + analytics)
CREATE TABLE notifications_sent (
    id              BIGSERIAL PRIMARY KEY,
    queue_id        UUID NULL,   -- puede ser NULL si se envió directo sin queue
    tipo            VARCHAR(40) NOT NULL,
    canal           VARCHAR(20) NOT NULL,
    destinatario    VARCHAR(150) NOT NULL,
    cuerpo          TEXT NOT NULL,

    -- Respuesta del proveedor
    proveedor           VARCHAR(20) NOT NULL,   -- 'twilio','brevo','sms_masivo'
    proveedor_msg_id    VARCHAR(200) NULL,
    proveedor_status    VARCHAR(30) NULL,       -- 'delivered','read','failed','bounced'
    proveedor_response  JSONB NULL,

    -- Referencia
    referencia_tipo VARCHAR(30) NULL,
    referencia_id   UUID NULL,
    usuario_id      UUID NULL REFERENCES users(id) ON DELETE SET NULL,

    enviado_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_notif_sent_tipo  ON notifications_sent(tipo, enviado_at DESC);
CREATE INDEX idx_notif_sent_dest  ON notifications_sent(destinatario, enviado_at DESC);
CREATE INDEX idx_notif_sent_ref   ON notifications_sent(referencia_tipo, referencia_id);
```

---

## 63. `notifications_templates`

> Ver tabla `cfg_recordatorio_templates` (sección 21) — es la misma funcionalidad,
> pero `notifications_templates` permite templates adicionales para campañas
> y comunicaciones no programadas.

```sql
CREATE TABLE notifications_templates (
    id          UUID        DEFAULT gen_random_uuid() PRIMARY KEY,
    nombre      VARCHAR(100) NOT NULL,
    tipo        VARCHAR(40) NOT NULL,
    canal       VARCHAR(20) NOT NULL CHECK (canal IN ('whatsapp','email','sms')),
    asunto      VARCHAR(200) NULL,
    cuerpo      TEXT NOT NULL,
    variables   JSONB NULL,
    -- ["nombre_propietario", "nombre_mascota", "fecha_cita"]
    -- documentación de las variables disponibles para la UI

    es_sistema  BOOLEAN NOT NULL DEFAULT FALSE,
    -- TRUE = creada por ORVAE, no editable
    -- FALSE = personalizada por la clínica

    activo      BOOLEAN NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

---

## 64. `report_snapshots`

```sql
-- Snapshots diarios de métricas clave para reportes históricos
-- Se genera automáticamente al final de cada día por un Job programado
CREATE TABLE report_snapshots (
    id              UUID    DEFAULT gen_random_uuid() PRIMARY KEY,
    fecha           DATE    NOT NULL UNIQUE,

    -- Métricas de agenda
    citas_programadas   INTEGER NOT NULL DEFAULT 0,
    citas_atendidas     INTEGER NOT NULL DEFAULT 0,
    citas_canceladas    INTEGER NOT NULL DEFAULT 0,
    citas_no_asistio    INTEGER NOT NULL DEFAULT 0,

    -- Métricas financieras
    ventas_total        DECIMAL(10,2) NOT NULL DEFAULT 0,
    ventas_efectivo     DECIMAL(10,2) NOT NULL DEFAULT 0,
    ventas_digital      DECIMAL(10,2) NOT NULL DEFAULT 0,
    cpe_emitidos        INTEGER NOT NULL DEFAULT 0,
    facturas_emitidas   INTEGER NOT NULL DEFAULT 0,
    boletas_emitidas    INTEGER NOT NULL DEFAULT 0,

    -- Métricas clínicas
    consultas_nuevas        INTEGER NOT NULL DEFAULT 0,
    pacientes_nuevos        INTEGER NOT NULL DEFAULT 0,
    propietarios_nuevos     INTEGER NOT NULL DEFAULT 0,
    vacunas_aplicadas       INTEGER NOT NULL DEFAULT 0,
    cirugias_realizadas     INTEGER NOT NULL DEFAULT 0,

    -- Métricas de servicios
    groomings_realizados    INTEGER NOT NULL DEFAULT 0,
    ingresos_guarderia      INTEGER NOT NULL DEFAULT 0,

    -- Métricas de stock
    alertas_stock_minimo    INTEGER NOT NULL DEFAULT 0,
    productos_vencidos      INTEGER NOT NULL DEFAULT 0,

    generado_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_snapshots_fecha ON report_snapshots(fecha DESC);
```

---

## 65. `mv_dashboard_metrics`

```sql
-- Vista materializada para el dashboard principal
-- Se refresca cada 15 minutos por un Job de Horizon
-- Evita queries pesadas en tiempo real sobre tablas de producción

CREATE MATERIALIZED VIEW mv_dashboard_metrics AS
SELECT
    -- Citas de hoy
    COUNT(a.id) FILTER (WHERE DATE(a.fecha_hora_inicio) = CURRENT_DATE
                          AND a.estado NOT IN ('cancelada','no_asistio')
                          AND a.deleted_at IS NULL)
        AS citas_hoy_total,

    COUNT(a.id) FILTER (WHERE DATE(a.fecha_hora_inicio) = CURRENT_DATE
                          AND a.estado = 'atendida'
                          AND a.deleted_at IS NULL)
        AS citas_hoy_atendidas,

    COUNT(a.id) FILTER (WHERE DATE(a.fecha_hora_inicio) = CURRENT_DATE
                          AND a.estado IN ('programada','confirmada','en_sala_espera','en_atencion')
                          AND a.deleted_at IS NULL)
        AS citas_hoy_pendientes,

    -- Ventas del día
    COALESCE(SUM(s.total) FILTER (WHERE DATE(s.created_at) = CURRENT_DATE
                                    AND s.estado = 'pagado'
                                    AND s.deleted_at IS NULL), 0)
        AS ventas_hoy_total,

    -- Pacientes activos
    COUNT(DISTINCT p.id) FILTER (WHERE p.deleted_at IS NULL AND p.fallecido = FALSE)
        AS pacientes_activos_total,

    -- Stock en alerta
    COUNT(si.id) FILTER (WHERE si.cantidad <= si.stock_minimo AND si.cantidad > 0)
        AS stock_en_minimo,

    COUNT(si.id) FILTER (WHERE si.cantidad = 0)
        AS stock_agotado,

    -- Groomings del día
    COUNT(g.id) FILTER (WHERE DATE(g.created_at) = CURRENT_DATE
                          AND g.estado NOT IN ('cancelado','retirado'))
        AS groomings_activos_hoy,

    -- Hospitalizaciones activas
    COUNT(h.id) FILTER (WHERE h.estado IN ('internado','en_cirugia','recuperacion'))
        AS hospitalizaciones_activas,

    NOW() AS ultima_actualizacion

FROM vet_appointments a
FULL OUTER JOIN vet_sales s ON FALSE
FULL OUTER JOIN vet_patients p ON FALSE
FULL OUTER JOIN vet_stock_items si ON FALSE
FULL OUTER JOIN vet_grooming_services g ON FALSE
FULL OUTER JOIN vet_hospitalizations h ON FALSE;

CREATE UNIQUE INDEX ON mv_dashboard_metrics (ultima_actualizacion);

-- Comando para refrescar (ejecutar en el Job de Horizon cada 15 min):
-- REFRESH MATERIALIZED VIEW CONCURRENTLY mv_dashboard_metrics;
```

---

## 66. `audit_logs`

```sql
-- INMUTABLE — Solo INSERT. BIGSERIAL. Nunca se borra.
-- Registra toda acción crítica del sistema (quién, qué, cuándo, desde dónde, resultado).
CREATE TABLE audit_logs (
    id              BIGSERIAL PRIMARY KEY,
    usuario_id      UUID NULL REFERENCES users(id) ON DELETE SET NULL,
    rol_usuario     VARCHAR(30) NULL,   -- snapshot del rol al momento de la acción

    -- Origen de la acción (forense + segregación de responsabilidad)
    origen          VARCHAR(20) NOT NULL DEFAULT 'web'
                    CHECK (origen IN ('web','api','job','cli','sistema')),

    -- Contexto opcional (multi-sede): NULL si no aplica o no se conoce en el request
    sede_id         UUID NULL REFERENCES cfg_sedes(id) ON DELETE SET NULL,

    accion          VARCHAR(60) NOT NULL,
    -- Ejemplos: 'login_exitoso','login_fallido','emitir_cpe','anular_cpe',
    --           'eliminar_paciente','cambiar_precio','editar_historia_clinica',
    --           'exportar_reporte','cambiar_plan','cancelar_suscripcion'

    tabla_afectada  VARCHAR(60) NULL,
    registro_id     VARCHAR(100) NULL,   -- UUID del registro afectado

    datos_anteriores JSONB NULL,    -- snapshot BEFORE
    datos_nuevos     JSONB NULL,    -- snapshot AFTER

    resultado       VARCHAR(10) NOT NULL DEFAULT 'exito'
                    CHECK (resultado IN ('exito','error','bloqueado')),
    mensaje_error   TEXT NULL,

    ip_address      VARCHAR(45) NULL,
    user_agent      VARCHAR(300) NULL,
    request_id      VARCHAR(100) NULL,   -- X-Request-ID header

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_audit_usuario  ON audit_logs(usuario_id, created_at DESC);
CREATE INDEX idx_audit_accion   ON audit_logs(accion, created_at DESC);
CREATE INDEX idx_audit_tabla    ON audit_logs(tabla_afectada, registro_id) WHERE tabla_afectada IS NOT NULL;
CREATE INDEX idx_audit_fecha    ON audit_logs(created_at DESC);
CREATE INDEX idx_audit_origen   ON audit_logs(origen, created_at DESC);

-- Inmutabilidad en BD (crear rol dedicado p.ej. vetsaas_app y ejecutar tras las migraciones):
-- REVOKE UPDATE, DELETE, TRUNCATE ON audit_logs FROM vetsaas_app;
-- GRANT INSERT, SELECT ON audit_logs TO vetsaas_app;
-- El usuario superadmin de migraciones conserva DDL; la app de producción no debe poder borrar huella.

-- PARTICIONAMIENTO POR MES — Para cuando el volumen supere 500K registros/mes
-- Ver sección 73 para la implementación completa.
```

---

## 67. `login_attempts`

```sql
-- Control de intentos fallidos de acceso (brute force protection)
CREATE TABLE login_attempts (
    id          BIGSERIAL PRIMARY KEY,
    email       VARCHAR(150) NOT NULL,
    ip_address  VARCHAR(45) NOT NULL,
    exitoso     BOOLEAN NOT NULL,
    user_agent  VARCHAR(300) NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_login_email_fecha ON login_attempts(email, created_at DESC);
CREATE INDEX idx_login_ip_fecha    ON login_attempts(ip_address, created_at DESC);

-- Query para bloquear en la app:
-- SELECT COUNT(*) FROM login_attempts
-- WHERE ip_address = $1 AND exitoso = FALSE
-- AND created_at > NOW() - INTERVAL '15 minutes'
-- HAVING COUNT(*) >= 5  → bloquear por 30 minutos
```

---

## 68. `api_request_logs`

```sql
-- Log de uso del API externo (Plan Clínica con api_acceso = true)
CREATE TABLE api_request_logs (
    id              BIGSERIAL PRIMARY KEY,
    token_id        UUID NULL REFERENCES personal_access_tokens(id) ON DELETE SET NULL,
    usuario_id      UUID NULL REFERENCES users(id) ON DELETE SET NULL,

    metodo          VARCHAR(10) NOT NULL,    -- 'GET','POST','PUT','DELETE'
    endpoint        VARCHAR(200) NOT NULL,
    status_code     SMALLINT NOT NULL,
    tiempo_ms       SMALLINT NULL,           -- tiempo de respuesta en ms
    ip_address      VARCHAR(45) NULL,

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_api_logs_token ON api_request_logs(token_id, created_at DESC);
CREATE INDEX idx_api_logs_fecha ON api_request_logs(created_at DESC);
```

---

## PARTE IV — LÓGICA DE NEGOCIO

---

## 69. Planes y gastos operativos detallados

### Resumen de precios (en soles con IGV incluido)

| | Free | Starter | Pro | Clínica |
|--|------|---------|-----|---------|
| **Precio/mes** | S/ 0 | S/ 149 | S/ 249 | S/ 399 |
| Precio/año | — | S/ 1,490 | S/ 2,490 | S/ 3,990 |
| Trial | No | 14 días | 14 días | 7 días |
| Pacientes | 50 | 300 | Ilimitados | Ilimitados |
| Usuarios | 1 | 2 | 5 | Ilimitados |
| Citas/mes | 100 | 500 | Ilimitadas | Ilimitadas |
| Facturación SUNAT | ❌ | ✅ 100 CPE/mes | ✅ 300 CPE/mes | ✅ Ilimitado |
| WhatsApp recordatorios | ❌ | ✅ 50/mes | ✅ Ilimitado | ✅ Ilimitado |
| Stock | ❌ | ✅ | ✅ | ✅ |
| Grooming | ❌ | ❌ | ✅ | ✅ |
| Laboratorio | ❌ | ❌ | ✅ | ✅ |
| Guardería/Hotel | ❌ | ❌ | ❌ | ✅ |
| Multi-sede | ❌ | ❌ | ❌ | ✅ |
| API externa | ❌ | ❌ | ❌ | ✅ |
| Reportes avanzados | ❌ | ❌ | ✅ | ✅ |
| Soporte | Docs | Email | WhatsApp | WA prioritario |

---

### Estructura de costos por plan — de dónde viene cada número

#### Plan Starter — S/ 149/mes

| Concepto | Costo real (S/) | Fuente donde verificar |
|----------|----------------|----------------------|
| NubeFact PSE | S/ 40 | [nubefact.com/precios](https://nubefact.com/precios) — tarifa mínima mensual incl. IGV |
| Twilio WhatsApp 50 msgs | S/ 3–5 | [twilio.com/en-us/whatsapp/pricing](https://www.twilio.com/en-us/whatsapp/pricing) — ~$0.005/msg + template fee |
| Brevo email | S/ 0 | [brevo.com/pricing](https://brevo.com/pricing) — plan gratuito hasta 300 emails/día |
| Cloudflare R2 storage | S/ 0 | [cloudflare.com/developer-platform/r2](https://cloudflare.com/developer-platform/r2) — 10 GB gratis |
| VPS (proporción 30 tenants) | S/ 15 | Costo total VPS mensual ÷ tenants activos pagos |
| Sentry errores | S/ 3 | [sentry.io/pricing](https://sentry.io/pricing) — plan dev gratuito, prorrateado |
| Soporte (email async) | S/ 5 | ~15 min/mes estimado × S/ 20/hr |
| **Total costo** | **S/ 66–68** | |
| **Ingreso** | **S/ 149** | |
| **Margen bruto** | **~S/ 81 (54%)** | |

#### Plan Pro — S/ 249/mes

| Concepto | Costo real (S/) | Fuente |
|----------|----------------|--------|
| NubeFact PSE | S/ 40 | nubefact.com/precios |
| Twilio WA ilimitado (estimado 150 msgs/cliente activo) | S/ 20–30 | twilio.com/whatsapp/pricing — $0.005/msg saliente |
| Brevo email | S/ 0 | plan gratuito suficiente hasta ~50 tenants Pro |
| Cloudflare R2 (20 GB avg con fotos grooming) | S/ 4 | $0.015/GB después de 10 GB gratis |
| VPS proporción (más uso CPU por reportes) | S/ 20 | Costo VPS ÷ tenants activos |
| Sentry | S/ 5 | plan gratuito compartido |
| Soporte WA (1h/mes estimado) | S/ 20 | S/ 20/hr de tu tiempo |
| **Total costo** | **~S/ 109–119** | |
| **Ingreso** | **S/ 249** | |
| **Margen bruto** | **~S/ 130–140 (52–56%)** | |

#### Plan Clínica — S/ 399/mes

| Concepto | Costo real (S/) | Fuente |
|----------|----------------|--------|
| NubeFact PSE | S/ 40 | nubefact.com/precios — revisar tier por volumen si emite +500 CPE/mes |
| Twilio WA (multi-sede, 300+ msgs estimados) | S/ 50–70 | twilio.com — presupuestar $0.006/msg con template |
| Brevo email plan Starter | S/ 12 | brevo.com — $9 USD/mes cuando supera 300 emails/día |
| Cloudflare R2 (50 GB avg con fotos multi-sede) | S/ 12 | $0.015/GB × 40 GB extra |
| VPS proporción (multi-sede = más conexiones DB) | S/ 30 | Mayor uso de PG connections y Horizon workers |
| Sentry | S/ 5 | plan gratuito compartido |
| Soporte WA prioritario (2h/mes) | S/ 40 | S/ 20/hr |
| **Total costo** | **~S/ 189–209** | |
| **Ingreso** | **S/ 399** | |
| **Margen bruto** | **~S/ 190–210 (48–53%)** | |

#### Plan Free — S/ 0/mes

| Concepto | Costo para ORVAE |
|----------|-----------------|
| Infra (proporción mínima) | ~S/ 5–8/mes |
| Sin NubeFact, sin Twilio | S/ 0 |
| **Es un plan gancho** — convierte en Starter promedio en 30–45 días si el onboarding es bueno |

> **Nota crítica NubeFact:** El costo de S/ 40/mes es **una sola cuenta por tu RUC de integrador ORVAE**, no por tenant. Si tienes 50 tenants Starter emitiendo en total menos de 1,000 CPE/mes, sigues pagando S/ 40. Si algún tenant Clínica emite muchos comprobantes (>500 CPE/mes), consulta el tier por volumen directamente en [nubefact.com/contacto](https://nubefact.com/contacto).

---

### Cómo leer los límites del plan en Laravel

```php
// app/Services/PlanLimitService.php

class PlanLimitService
{
    private array $features;

    public function __construct()
    {
        // Se cachea en Redis por 10 minutos para no consultar DB en cada request
        $tenant = app('tenant');
        $this->features = Cache::remember(
            "tenant:{$tenant->id}:plan_features",
            600,
            fn() => $tenant->subscription->plan
                         ->features()
                         ->pluck('valor_int', 'feature')
                         ->merge(
                              $tenant->subscription->plan
                                   ->features()
                                   ->whereNotNull('valor_bool')
                                   ->pluck('valor_bool', 'feature')
                         )->merge(
                              $tenant->subscription->plan
                                   ->features()
                                   ->whereNotNull('valor_str')
                                   ->pluck('valor_str', 'feature')
                         )->toArray()
        );
    }

    public function puedeAgregarPaciente(): bool
    {
        $max = $this->features['max_pacientes'] ?? 0;
        if ($max === -1) return true;
        return VetPatient::whereNull('deleted_at')->count() < $max;
    }

    public function puedeEmitirCPE(): bool
    {
        return (bool) ($this->features['facturacion_sunat'] ?? false);
    }

    public function puedeUsarGrooming(): bool
    {
        return (bool) ($this->features['modulo_grooming'] ?? false);
    }

    public function puedeUsarMultiSede(): bool
    {
        return (bool) ($this->features['multi_sede'] ?? false);
    }

    public function getMaxCPEMes(): int
    {
        return (int) ($this->features['max_cpe_mes'] ?? 0);
    }

    public function getCPEUsadosMes(): int
    {
        return FelDocument::whereMonth('created_at', now()->month)
                          ->whereYear('created_at', now()->year)
                          ->whereIn('estado', ['aceptado','observado'])
                          ->count();
    }

    public function puedeEmitirOtroCPE(): bool
    {
        $max = $this->getMaxCPEMes();
        if ($max === -1) return true;
        if ($max === 0) return false;
        return $this->getCPEUsadosMes() < $max;
    }
}
```

---

## 70. Triggers y funciones PostgreSQL

```sql
-- ══════════════════════════════════════════════════════════════════════════════
-- FUNCIÓN 1: Auto-actualizar updated_at en toda tabla que lo tenga
-- ══════════════════════════════════════════════════════════════════════════════
CREATE OR REPLACE FUNCTION fn_set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Aplicar a cada tabla con updated_at:
-- vet_owners, vet_patients, vet_appointments, vet_clinical_records,
-- vet_products, vet_stock_items, vet_sales, vet_grooming_services, etc.
CREATE TRIGGER trg_updated_at_vet_owners
    BEFORE UPDATE ON vet_owners
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

-- (Repetir para cada tabla)


-- ══════════════════════════════════════════════════════════════════════════════
-- FUNCIÓN 2: Sincronizar peso y última consulta del paciente
-- Se ejecuta al INSERT de historia clínica
-- ══════════════════════════════════════════════════════════════════════════════
CREATE OR REPLACE FUNCTION fn_sync_patient_after_hc()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE vet_patients
    SET
        peso_ultimo_kg          = COALESCE(NEW.peso_kg, peso_ultimo_kg),
        peso_fecha              = CASE WHEN NEW.peso_kg IS NOT NULL
                                       THEN DATE(NEW.fecha_atencion)
                                       ELSE peso_fecha END,
        ultima_consulta_at      = NEW.fecha_atencion,
        ultimo_veterinario_id   = NEW.veterinario_id,
        updated_at              = NOW()
    WHERE id = NEW.patient_id;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_sync_patient_hc
    AFTER INSERT ON vet_clinical_records
    FOR EACH ROW EXECUTE FUNCTION fn_sync_patient_after_hc();


-- ══════════════════════════════════════════════════════════════════════════════
-- FUNCIÓN 3: Actualizar cantidad de stock tras cada movimiento
-- ══════════════════════════════════════════════════════════════════════════════
CREATE OR REPLACE FUNCTION fn_sync_stock_on_movement()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE vet_stock_items
    SET
        cantidad   = NEW.cantidad_resultante,
        updated_at = NOW()
    WHERE id = NEW.stock_item_id;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_sync_stock
    AFTER INSERT ON vet_stock_movements
    FOR EACH ROW EXECUTE FUNCTION fn_sync_stock_on_movement();


-- ══════════════════════════════════════════════════════════════════════════════
-- FUNCIÓN 4: Registrar cambio de estado de cita en el historial
-- ══════════════════════════════════════════════════════════════════════════════
CREATE OR REPLACE FUNCTION fn_audit_appointment_state()
RETURNS TRIGGER AS $$
BEGIN
    IF OLD.estado IS DISTINCT FROM NEW.estado THEN
        INSERT INTO vet_appointment_history
            (appointment_id, estado_anterior, estado_nuevo)
        VALUES
            (NEW.id, OLD.estado, NEW.estado);
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_audit_appointment_state
    AFTER UPDATE OF estado ON vet_appointments
    FOR EACH ROW EXECUTE FUNCTION fn_audit_appointment_state();


-- ══════════════════════════════════════════════════════════════════════════════
-- FUNCIÓN 4b: Días de internamiento (columna materializada por trigger; NOW() aquí SÍ es válido)
-- ══════════════════════════════════════════════════════════════════════════════
CREATE OR REPLACE FUNCTION fn_sync_hospitalization_dias()
RETURNS TRIGGER AS $$
DECLARE
    v_dias INTEGER;
BEGIN
    v_dias := FLOOR(
        EXTRACT(EPOCH FROM (COALESCE(NEW.fecha_alta, clock_timestamp()) - NEW.fecha_ingreso)) / 86400.0
    )::INTEGER;
    IF v_dias < 0 THEN
        v_dias := 0;
    ELSIF v_dias > 32767 THEN
        v_dias := 32767;
    END IF;
    NEW.dias_internado := v_dias::SMALLINT;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_hospitalization_dias_bi
    BEFORE INSERT OR UPDATE OF fecha_ingreso, fecha_alta, estado
    ON vet_hospitalizations
    FOR EACH ROW EXECUTE FUNCTION fn_sync_hospitalization_dias();


-- ══════════════════════════════════════════════════════════════════════════════
-- FUNCIÓN 5: Número correlativo thread-safe para ventas
-- ══════════════════════════════════════════════════════════════════════════════
CREATE OR REPLACE FUNCTION fn_next_venta_numero()
RETURNS VARCHAR AS $$
DECLARE
    v_anio  TEXT := TO_CHAR(NOW(), 'YYYY');
    v_count BIGINT;
BEGIN
    -- SELECT FOR UPDATE en una tabla de secuencia evita race conditions
    -- En producción usar pg_advisory_lock o una tabla de secuencias dedicada
    SELECT COUNT(*) + 1 INTO v_count
    FROM vet_sales
    WHERE EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM NOW());

    RETURN 'VTA-' || v_anio || '-' || LPAD(v_count::TEXT, 4, '0');
END;
$$ LANGUAGE plpgsql;


-- ══════════════════════════════════════════════════════════════════════════════
-- FUNCIÓN 6: Número correlativo para historia clínica
-- ══════════════════════════════════════════════════════════════════════════════
CREATE OR REPLACE FUNCTION fn_next_hc_numero()
RETURNS VARCHAR AS $$
DECLARE
    v_count BIGINT;
BEGIN
    SELECT COUNT(*) + 1 INTO v_count FROM vet_clinical_records;
    RETURN 'HC-' || TO_CHAR(NOW(), 'YYYY') || '-' || LPAD(v_count::TEXT, 5, '0');
END;
$$ LANGUAGE plpgsql;


-- ══════════════════════════════════════════════════════════════════════════════
-- FUNCIÓN 7: Verificar stock antes de insertar salida
-- Previene stock negativo accidental
-- ══════════════════════════════════════════════════════════════════════════════
CREATE OR REPLACE FUNCTION fn_check_stock_before_salida()
RETURNS TRIGGER AS $$
DECLARE
    v_actual DECIMAL(10,3);
BEGIN
    IF NEW.cantidad < 0 THEN   -- Es una salida
        SELECT cantidad INTO v_actual
        FROM vet_stock_items WHERE id = NEW.stock_item_id;

        IF (v_actual + NEW.cantidad) < 0 THEN
            RAISE EXCEPTION 'Stock insuficiente. Disponible: %, Solicitado: %',
                v_actual, ABS(NEW.cantidad);
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_check_stock
    BEFORE INSERT ON vet_stock_movements
    FOR EACH ROW EXECUTE FUNCTION fn_check_stock_before_salida();
```

---

## 71. Índices críticos de rendimiento

```sql
-- ══════════════════════════════════════════════════════════════════════════════
-- BÚSQUEDAS FRECUENTES
-- ══════════════════════════════════════════════════════════════════════════════

-- Búsqueda de propietario en caja (por apellido, teléfono o número de documento)
CREATE INDEX idx_owners_busqueda_texto
    ON vet_owners USING gin(
        to_tsvector('spanish', nombres || ' ' || apellidos)
    ) WHERE deleted_at IS NULL;

-- Búsqueda de paciente por nombre
CREATE INDEX idx_patients_busqueda_texto
    ON vet_patients USING gin(
        to_tsvector('spanish', nombre)
    ) WHERE deleted_at IS NULL;

-- Agenda: citas en rango de fechas (vista semanal/mensual)
CREATE INDEX idx_appt_rango
    ON vet_appointments(fecha_hora_inicio, veterinario_id)
    WHERE deleted_at IS NULL
    AND estado NOT IN ('cancelada', 'no_asistio');

-- Historial clínico del paciente (el más consultado)
CREATE INDEX idx_hc_patient_fecha
    ON vet_clinical_records(patient_id, fecha_atencion DESC)
    WHERE deleted_at IS NULL;

-- Kardex por producto
CREATE INDEX idx_stockmov_product_fecha
    ON vet_stock_movements(product_id, created_at DESC);

-- ══════════════════════════════════════════════════════════════════════════════
-- JOBS Y PROCESOS BATCH
-- ══════════════════════════════════════════════════════════════════════════════

-- Job de recordatorios de citas 48h antes
CREATE INDEX idx_appt_job_48h
    ON vet_appointments(recordatorio_48h_at)
    WHERE recordatorio_48h_enviado = FALSE
    AND estado IN ('programada','confirmada')
    AND deleted_at IS NULL;

-- Job de recordatorios de citas 2h antes
CREATE INDEX idx_appt_job_2h
    ON vet_appointments(recordatorio_2h_at)
    WHERE recordatorio_2h_enviado = FALSE
    AND estado IN ('programada','confirmada')
    AND deleted_at IS NULL;

-- Job de recordatorios de vacunas próximas
CREATE INDEX idx_vacc_job_recordatorio
    ON vet_vaccinations(fecha_proxima)
    WHERE recordatorio_enviado = FALSE
    AND fecha_proxima IS NOT NULL
    AND fecha_proxima <= CURRENT_DATE + INTERVAL '8 days';

-- Job de reintentos de CPE fallidos
CREATE INDEX idx_fel_job_reintentos
    ON fel_documents(ultimo_intento_at)
    WHERE estado IN ('pendiente','rechazado')
    AND intentos_envio < 3;

-- Job de envío de notificaciones en cola
CREATE INDEX idx_notif_job_enviar
    ON notifications_queue(enviar_at, prioridad DESC)
    WHERE estado = 'pendiente'
    AND intentos < max_intentos;

-- Job de alertas de stock mínimo
CREATE INDEX idx_stock_job_alerta
    ON vet_stock_items(product_id)
    WHERE cantidad <= stock_minimo
    AND alerta_minimo_enviada = FALSE;

-- Job de alertas de vencimiento (30 días antes)
CREATE INDEX idx_stock_job_vencimiento
    ON vet_stock_items(fecha_vencimiento)
    WHERE fecha_vencimiento IS NOT NULL
    AND fecha_vencimiento <= CURRENT_DATE + INTERVAL '30 days'
    AND alerta_vencimiento_enviada = FALSE;
```

---

## 72. Vistas materializadas

```sql
-- ══════════════════════════════════════════════════════════════════════════════
-- Vista: Resumen financiero del mes actual
-- Refrescar: una vez al día (Job nocturno)
-- ══════════════════════════════════════════════════════════════════════════════
CREATE MATERIALIZED VIEW mv_financial_month AS
SELECT
    DATE_TRUNC('day', s.created_at)::DATE AS fecha,
    COUNT(s.id)                            AS total_ventas,
    SUM(s.total)                           AS ingreso_bruto,
    SUM(s.igv_monto)                       AS igv_total,
    SUM(s.total - s.igv_monto)             AS ingreso_sin_igv,
    COUNT(p.id) FILTER (WHERE p.metodo = 'efectivo')  AS pagos_efectivo,
    COUNT(p.id) FILTER (WHERE p.metodo IN ('yape','plin')) AS pagos_digital,
    SUM(p.monto) FILTER (WHERE p.metodo = 'efectivo') AS monto_efectivo,
    SUM(p.monto) FILTER (WHERE p.metodo IN ('yape','plin')) AS monto_digital
FROM vet_sales s
LEFT JOIN vet_payments p ON p.sale_id = s.id
WHERE s.deleted_at IS NULL
  AND s.estado = 'pagado'
  AND s.created_at >= DATE_TRUNC('month', NOW())
GROUP BY DATE_TRUNC('day', s.created_at)::DATE
ORDER BY fecha;

CREATE UNIQUE INDEX ON mv_financial_month(fecha);


-- ══════════════════════════════════════════════════════════════════════════════
-- Vista: Top pacientes por número de consultas (para el vet)
-- ══════════════════════════════════════════════════════════════════════════════
CREATE MATERIALIZED VIEW mv_top_patients AS
SELECT
    p.id,
    p.nombre,
    p.especie,
    o.nombres || ' ' || o.apellidos AS propietario,
    o.telefono,
    COUNT(hc.id) AS total_consultas,
    MAX(hc.fecha_atencion) AS ultima_consulta,
    p.peso_ultimo_kg,
    p.ultima_consulta_at
FROM vet_patients p
JOIN vet_owners o ON o.id = p.owner_id
LEFT JOIN vet_clinical_records hc ON hc.patient_id = p.id AND hc.deleted_at IS NULL
WHERE p.deleted_at IS NULL AND p.fallecido = FALSE
GROUP BY p.id, p.nombre, p.especie, o.nombres, o.apellidos, o.telefono,
         p.peso_ultimo_kg, p.ultima_consulta_at
ORDER BY total_consultas DESC;

CREATE UNIQUE INDEX ON mv_top_patients(id);
```

---

## 73. Políticas de particionamiento

```sql
-- Aplicar cuando audit_logs supere ~500K registros/mes (estimado mes 12-15)
-- El particionamiento por rango de fecha mejora el rendimiento de queries históricas

-- 1. Crear tabla madre particionada
CREATE TABLE audit_logs_partitioned (
    LIKE audit_logs INCLUDING ALL
) PARTITION BY RANGE (created_at);

-- 2. Crear particiones por mes
CREATE TABLE audit_logs_2026_01 PARTITION OF audit_logs_partitioned
    FOR VALUES FROM ('2026-01-01') TO ('2026-02-01');

CREATE TABLE audit_logs_2026_02 PARTITION OF audit_logs_partitioned
    FOR VALUES FROM ('2026-02-01') TO ('2026-03-01');

-- ... (automatizar con pg_partman en producción)

-- 3. Automatización con pg_partman (extensión PostgreSQL)
-- CREATE EXTENSION pg_partman;
-- SELECT partman.create_parent('public.audit_logs', 'created_at', 'native', 'monthly');

-- Aplicar la misma estrategia a:
-- vet_stock_movements  → cuando supere 1M registros
-- notifications_sent   → cuando supere 500K registros/mes
-- api_request_logs     → cuando supere 1M registros/mes
```

---

## 74. Orden de migraciones Laravel

```
CARPETA: database/migrations/
ESTRUCTURA:
  /public/     → migraciones del schema global (correr en deploy del SaaS)
  /tenant/     → migraciones del schema por clínica (correr al provisionar cada tenant)

═══════════════════════════════════════════════════════════════════════
SCHEMA PUBLIC — Ejecutar una sola vez al desplegar el SaaS
═══════════════════════════════════════════════════════════════════════
001_create_ubigeos_table.php
002_seed_ubigeos_peru.php                   ← 1,874 distritos INEI
003_create_plans_table.php
004_create_plan_features_table.php
005_seed_plans_and_features.php             ← 4 planes + sus features
006_create_promo_codes_table.php
007_create_tenants_table.php
008_create_subscriptions_table.php
009_create_subscription_payments_table.php
010_create_global_notifications_table.php

═══════════════════════════════════════════════════════════════════════
SCHEMA TENANT — Ejecutar al provisionar cada nueva clínica
═══════════════════════════════════════════════════════════════════════
BLOQUE 1: Usuarios y acceso
t001_create_users_table.php
t002_create_password_reset_tokens_table.php
t003_create_sessions_table.php
t004_create_personal_access_tokens_table.php

BLOQUE 2: Configuración de la clínica
t010_create_cfg_clinic_settings_table.php   -- incluye índice único una-fila + created_at/updated_at
t011_create_cfg_sedes_table.php
t012_create_cfg_horarios_table.php
t013_create_cfg_bloqueos_agenda_table.php
t014_create_cfg_tarifas_table.php
t015_create_cfg_recordatorio_templates_table.php
t016_seed_cfg_recordatorio_templates.php    ← plantillas base de mensajes

BLOQUE 3: Clientes y pacientes
t020_create_vet_owners_table.php
t021_create_vet_patients_table.php
t022_create_vet_patient_owners_table.php
t023_create_vet_patient_documents_table.php
t024_create_vet_owner_consents_table.php

BLOQUE 4: Agenda
t030_create_vet_appointments_table.php      ← SIN FK a clinical_records aún
t031_create_vet_appointment_history_table.php
t032_create_vet_waiting_list_table.php

BLOQUE 5: Historia clínica
t040_create_vet_clinical_records_table.php
t041_alter_vet_appointments_add_hc_fk.php  ← FK circular, ahora sí existe la tabla
t042_create_vet_vaccinations_table.php
t043_create_vet_vaccination_protocols_table.php
t044_create_vet_prescriptions_table.php
t045_create_vet_lab_orders_table.php
t046_create_vet_lab_results_table.php
t047_create_vet_surgeries_table.php
t048_create_vet_hospitalizations_table.php
t049_create_vet_vital_signs_log_table.php

BLOQUE 6: Inventario y compras
t050_create_vet_suppliers_table.php
t051_create_vet_product_categories_table.php
t052_create_vet_products_table.php
t053_create_vet_stock_items_table.php
t054_create_vet_stock_movements_table.php
t055_create_vet_stock_alerts_table.php
t056_create_vet_purchases_table.php
t057_create_vet_purchase_items_table.php

BLOQUE 7: Ventas y caja
t060_create_vet_cash_sessions_table.php
t061_create_vet_discounts_table.php
t062_create_vet_sales_table.php
t063_create_vet_sale_items_table.php
t064_create_vet_payments_table.php
t065_alter_vet_clinical_records_add_venta_fk.php   ← FK circular

BLOQUE 8: Facturación SUNAT
t070_create_fel_series_table.php
t071_seed_fel_series_default.php            ← F001, B001 para la sede principal
t072_create_fel_documents_table.php
t073_create_fel_document_items_table.php
t074_create_fel_void_requests_table.php
t075_create_fel_summary_documents_table.php

BLOQUE 9: Servicios adicionales
t080_create_vet_grooming_packages_table.php
t081_create_vet_grooming_services_table.php
t082_alter_vet_appointments_add_grooming_fk.php
t083_create_vet_boarding_table.php
t084_create_vet_boarding_daily_logs_table.php

BLOQUE 10: Comunicaciones
t090_create_notifications_queue_table.php
t091_create_notifications_sent_table.php
t092_create_notifications_templates_table.php

BLOQUE 11: Reportes y métricas
t100_create_report_snapshots_table.php
t101_create_mv_dashboard_metrics.php        ← Vista materializada
t102_create_mv_financial_month.php
t103_create_mv_top_patients.php

BLOQUE 12: Auditoría y seguridad
t110_create_audit_logs_table.php
t111_create_login_attempts_table.php
t112_create_api_request_logs_table.php

BLOQUE 13: Triggers y funciones (ejecutar al final)
t120_create_trigger_updated_at.php
t121_create_trigger_sync_patient_hc.php
t122_create_trigger_sync_stock.php
t123_create_trigger_audit_appointment.php
t124_create_trigger_check_stock.php
t125_create_function_next_correlativo.php
t126_create_trigger_hospitalization_dias.php   -- fn_sync_hospitalization_dias + BEFORE INSERT/UPDATE
```

---

## 75. Política de auditoría, retención e inmutabilidad en BD

### Objetivo

Que la auditoría sea **útil en incidentes y cumplimiento** (LPDP Ley 29733, trazabilidad clínica y financiera) y **difícil de borrar** sin acceso privilegiado a la base de datos.

### Capas recomendadas

| Capa | Qué hacer | Para qué |
|------|-----------|----------|
| **Aplicación** | Registrar en `audit_logs` toda acción sensible (login, cambios en HC, precios, FEL, exportaciones, cambios de rol, acceso a datos masivos). Rellenar `origen` (`web`, `api`, `job`, etc.), `request_id`, `ip_address`, `sede_id` cuando exista. | Evidencia contextual |
| **PostgreSQL** | Rol de aplicación (`vetsaas_app`) con `INSERT` y `SELECT` sobre `audit_logs`; **sin** `UPDATE`, `DELETE`, `TRUNCATE`. Migraciones con otro rol. | Incluso con bug o credencial filtrada, no se “limpia” el log desde la app |
| **Infra** | Backups inmutables (WORM o retención en objeto storage), réplica de solo lectura para análisis. | Recuperación y cadena de custodia |
| **Retención** | Definir política por tipo de dato: p. ej. `audit_logs` **24–36 meses** online; `login_attempts` / `api_request_logs` **90–180 días**; archivar a tabla fría o object storage antes de `DELETE` controlado por job con su propio registro en `audit_logs` (`origen = 'job'`). | LPDP (minimización) vs coste de disco y rendimiento |

### Qué auditar como mínimo (checklist de negocio)

- Autenticación y fallos (`login_attempts` + `audit_logs`).
- Cambios en datos personales de titulares y mascotas; consentimientos (`vet_owner_consents`).
- Historia clínica, recetas, laboratorio, cirugías (quién abrió/editó/exportó).
- Emisión y anulación de comprobantes (`fel_*`); apertura/cierre de caja; movimientos de stock (ya inmutables en kardex).
- Cambios en usuarios, roles y tokens de API.

### Qué no confundir

- **`deleted_at` (soft delete)** en tablas clínicas: correcto para no perder historia de negocio.
- **`audit_logs`**: no es soft delete; es **append-only**. El borrado solo como política de retención documentada, ejecutada por proceso controlado, no desde pantallas de usuario.

---

## 76. Operación multi-tenant: conexiones, pool y migraciones

### `search_path` y pool de conexiones

- Cada request debe fijar `search_path` al schema del tenant **después** de obtener conexión del pool (o usar `SET LOCAL` dentro de transacción si el pool reutiliza conexiones y el driver lo permite).
- **PgBouncer** en modo `transaction`: el `SET` no persiste entre transacciones; hay que ejecutarlo **al inicio de cada transacción** o usar `SET LOCAL` en la misma transacción que las consultas del tenant.
- Validar siempre el nombre del schema (regex + longitud) antes de interpolarlo; ver middleware en [sección 2](#2-arquitectura-multi-tenant).

### Migraciones en muchos tenants

- Orden estricto de migraciones tenant (sección 74); jobs en cola (`tenant:migrate` por schema) con reintentos y registro de fallos.
- En despliegues grandes: ventana de mantenimiento o migración gradual por lote de schemas; smoke test en un tenant piloto antes del resto.
- Versionar el esquema esperado en tabla de control en `public` (p. ej. `tenants.last_migration_at`, `migration_batch`) para soporte y alertas.

### Rendimiento y límites

- Monitorear conexiones totales = `(workers PHP/Laravel) × conexiones por worker` frente al `max_connections` de PostgreSQL.
- Particionar `audit_logs` / logs de API cuando el volumen lo justifique (sección 73).

---

## Resumen de tablas por dominio

| Dominio | Tablas | Volumen esperado |
|---------|--------|-----------------|
| SaaS Global (public) | 8 | Bajo — crecen con los tenants |
| Usuarios y acceso | 4 | Bajo — 2–5 usuarios por clínica |
| Configuración | 7 | Muy bajo — casi estáticas |
| Clientes y pacientes | 5 | Medio — 300–5,000 por clínica |
| Agenda | 3 | Medio — 500–3,000 citas/año |
| Historia clínica | 9 | Alto — crece indefinidamente |
| Inventario y compras | 8 | Medio-alto — movimientos diarios |
| Ventas y caja | 5 | Alto — transacciones diarias |
| Facturación SUNAT | 5 | Alto — inmutable por ley |
| Servicios adicionales | 4 | Medio |
| Comunicaciones | 3 | Alto — logs crecen rápido |
| Reportes y métricas | 4 | Bajo — snapshots diarios |
| Auditoría y seguridad | 3 | Muy alto — cada acción |
| **TOTAL** | **68 tablas** | |

---

*ORVAE Software — VetSaaS Peru — Arquitectura de Base de Datos v2.1 — Mayo 2026*
*Documento definitivo — Arquitecto Senior (refuerzo auditoría, operación multi-tenant, DDL PostgreSQL)*
