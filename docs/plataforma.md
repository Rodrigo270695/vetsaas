# 📋 Módulo Plataforma + Multi-tenancy

> Documentación interna de todo lo construido en el **menú Plataforma** del panel del superadmin y la **arquitectura multi-tenant** que sostiene el SaaS.
>
> Pensado para el equipo de soporte / desarrollo: explica qué hace cada pantalla, por qué existe, qué endpoints expone y cómo se conecta con el resto.

---
php artisan vetsaas:tenant-migrate-all --slug=mi-clinica
## Tabla de contenidos

1. [Contexto general](#contexto-general)
2. [Permisos y RBAC del panel](#permisos-y-rbac-del-panel)
3. [Módulo: Tenants](#módulo-tenants)
4. [Módulo: Planes](#módulo-planes)
5. [Módulo: Suscripciones](#módulo-suscripciones)
6. [Módulo: Cobros](#módulo-cobros)
7. [Multi-tenancy en runtime (Fase 1)](#multi-tenancy-en-runtime-fase-1)
8. [Multi-tenancy en runtime (Fase 2) — Routing](#multi-tenancy-en-runtime-fase-2--routing-y-separación-de-hosts)
9. [Multi-tenancy en runtime (Fase 2.5) — Autenticación](#multi-tenancy-en-runtime-fase-25--autenticación-del-tenant) ⚠️ _histórica_
10. [Refactor a single-login (Fase 2.5-bis)](#refactor-a-single-login-fase-25-bis) ← **arquitectura actual**
11. [Recuperación de contraseña y cambio obligatorio (Fase 2.6)](#recuperación-de-contraseña-y-cambio-obligatorio-fase-26)
12. [Módulo Configuración › General (Fase 4 · módulo 1)](#módulo-configuración--general-fase-4--módulo-1)
13. [Módulo Plataforma › Configuración global del SaaS (Fase 4 · módulo 1.5)](#módulo-plataforma--configuración-global-del-saas-fase-4--módulo-15)
14. [Convenciones compartidas](#convenciones-compartidas)
15. [Módulo Inventario del tenant (mayo 2026)](#módulo-inventario-del-tenant-mayo-2026)
16. [Módulo Caja del tenant (mayo 2026)](#módulo-caja-del-tenant-mayo-2026)
17. [Módulo clínico del tenant: historias, plan, vacunas e historial del paciente (mayo 2026)](#módulo-clínico-del-tenant-historias-plan-vacunas-e-historial-del-paciente-mayo-2026)
18. [Módulo Servicios › Grooming (jun. 2026)](#módulo-servicios--grooming-jun-2026)
19. [Módulo Servicios › Hotel / guardería (jun. 2026)](#módulo-servicios--hotel--guardería-jun-2026)
20. [Hoja de ruta restante](#hoja-de-ruta-restante)

---

## Contexto general

**VetSaaS** es una plataforma multi-tenant para gestión de clínicas veterinarias. La arquitectura combina **identidad unificada** (un solo `User`) con **aislamiento físico de datos** (schemas separados por tenant):

```
┌──────────────────────────────────────────────────────────┐
│ Identidad (compartida en `public.users`)                 │
│                                                          │
│  superadmin@vetsaas.com   tenant_id=NULL    rol=super…   │
│  admin@miclinica.com      tenant_id=A       rol=admin_…  │
│  ana@miclinica.com        tenant_id=A       rol=veter…   │
│  luis@otraclinica.com     tenant_id=B       rol=recep…   │
└──────────────────────────────────────────────────────────┘
              │
              │ host determina contexto
              ▼
┌──────────────────────────────────────────────────────────┐
│ Datos OPERATIVOS (aislados por schema)                   │
│                                                          │
│  schema=public:            tenants, plans, subscriptions │
│  schema=vet_mi_clinica:    cfg_clinic, pacientes, citas… │
│  schema=vet_otra_clinica:  cfg_clinic, pacientes, citas… │
└──────────────────────────────────────────────────────────┘
```

### Dos hosts, mismo backend

| Host (dev) | Quién entra | Qué ve |
|---|---|---|
| `localhost:8000` | Usuarios con `tenant_id IS NULL` (superadmin / staff) | Menú Plataforma + módulos centrales |
| `<slug>.localhost:8000` | Usuarios con `tenant_id = uuid(slug)` | Módulos operativos de **su** clínica |

> En producción los hosts son `app.vetsaas.com` (central) y `<slug>.vetsaas.com` (clínicas). El `.env` lo configura vía `TENANT_CENTRAL_DOMAINS` y `TENANT_ROOT_DOMAIN`.

El **menú Plataforma** del sidebar pertenece al panel central: solo lo ven usuarios con permisos `plataforma-*` (en la práctica, los superadmins).

### Stack que sostiene todo

| Capa | Tecnología |
|---|---|
| Backend | Laravel 13.7 (PHP 8.3) |
| BD | PostgreSQL (multi-schema) |
| RBAC | Spatie laravel-permission 7.4 |
| Frontend | Inertia 3 + React 19 + TypeScript |
| UI | Tailwind 4 + shadcn/ui (Verde Bosque Clínico) |
| Tests | Pest 4 |

---

## Permisos y RBAC del panel

Todo el menú Plataforma vive bajo permisos `plataforma-*` gestionados por Spatie. El catálogo está en `database/seeders/PermissionsSeeder.php`:

| Módulo | Acciones |
|---|---|
| `plataforma-tenants` | `view`, `create`, `update`, `suspend`, `resume`, `delete`, `export`, `bulk-delete`, `impersonate` |
| `plataforma-planes` | `view`, `create`, `update`, `delete`, `export`, `bulk-delete` |
| `plataforma-suscripciones` | `view`, `create`, `update`, `delete`, `export`, `bulk-delete`, `extend-trial`, `change-plan`, `cancel` |
| `plataforma-cobros` | `view`, `export`, `refund`, `resend-invoice`, `add-note` |

**Total**: 30 permisos en el namespace `plataforma-*`. El rol **superadmin** los tiene todos asignados (174 permisos globales).

**Sidebar inteligente**: el grupo "Plataforma" del sidebar se oculta automáticamente si el usuario no tiene NINGÚN permiso del namespace `plataforma-*`. Cada item del grupo se oculta independientemente si falta el `*.view`.

---

## Módulo: Tenants

> **Propósito**: dar al superadmin una vista operativa de todas las clínicas (tenants) del SaaS, con capacidad de soporte (crear manualmente, suspender, reanudar, eliminar).

### Origen del dato

Los tenants nacen normalmente vía **provisioner HTTP desde Orvae PE** (sistema externo de cobros). VetSaaS solo expone CRUD para casos de migración manual, debugging y soporte interno.

### Modelos involucrados

```
App\Models\Tenant
  ├── id (UUID)
  ├── slug                    ← Subdominio (clinica-rivera)
  ├── schema_name             ← Schema PostgreSQL (vet_xxx)
  ├── razon_social, nombre_comercial, ruc
  ├── email_admin, telefono
  ├── distrito_id             ← FK a catálogo geográfico
  ├── estado                  ← trial | active | suspended | cancelled
  ├── trial_ends_at, suspended_at, cancelled_at
  ├── suspension_reason, cancel_reason
  ├── onboarding_completado, onboarding_paso
  ├── timezone, locale, canal_adquisicion
  └── referido_por_tenant_id  ← Self-FK para referrals
```

### Endpoints (`/plataforma/tenants/*`)

| Verbo | URI | Acción | Permiso |
|---|---|---|---|
| GET | `tenants` | index (lista + filtros + paginación) | `plataforma-tenants.view` |
| GET | `tenants/export` | XLSX | `plataforma-tenants.export` |
| POST | `tenants` | crear (estado = `trial`, 14 días por defecto) | `plataforma-tenants.create` |
| PUT/PATCH | `tenants/{tenant}` | actualizar (bloquea cambio de slug si está active/suspended) | `plataforma-tenants.update` |
| POST | `tenants/{tenant}/suspend` | suspender (requiere `reason`) | `plataforma-tenants.suspend` |
| POST | `tenants/{tenant}/resume` | reanudar | `plataforma-tenants.resume` |
| DELETE | `tenants/{tenant}` | soft delete (bloqueado si `estado=active`) | `plataforma-tenants.delete` |
| DELETE | `tenants/bulk` | bulk delete (omite activos) | `plataforma-tenants.bulk-delete` |

### Frontend

- **Página**: `resources/js/pages/plataforma/tenants/index.tsx`
- **Stats**: total, en trial, activos, suspendidos, cancelados, expirados
- **Filtros**: por estado, plan
- **Modales** (state machine): create, edit, delete, bulk-delete, suspend, resume
- **i18n**: `resources/js/lang/{es,en}/tenants.json`

### Reglas de negocio importantes

| Regla | Implementación |
|---|---|
| `schema_name` se deriva del slug (`vet_<slug_normalizado>`) | `TenantController::schemaNameFromSlug()` |
| El controlador NO ejecuta `CREATE SCHEMA` | Lo hace el provisioner externo o `vetsaas:tenant-migrate` |
| Bloqueado cambiar slug si tenant está activo o suspendido | `TenantController::update()` |
| Solo se puede `delete` si NO está activo | `TenantController::destroy()` |
| Suspender requiere motivo (≥ 5 caracteres) | Validación inline |
| Mutaciones invalidan cache del `TenantManager` | `$manager->flushCacheFor()` en suspend/resume/update/destroy |

---

## Módulo: Planes

> **Propósito**: catálogo de productos del SaaS. Cada plan define precio, ciclo, días de prueba y features (límites + módulos habilitados).

### Decisión arquitectónica clave

La **fuente de verdad** de los planes vive en VetSaaS (no en Orvae), porque:

1. La app del cliente necesita consultar features en runtime (`max_pacientes`, `factura_electronica`, etc.) sin depender de un servicio externo.
2. Orvae solo se ocupa del cobro; las reglas de "qué puede hacer cada plan" son lógica del producto.

### Modelos

```
App\Models\Plan
  ├── id (UUID)
  ├── codigo                  ← Identificador estable (FREE, BASIC, PRO...) — NO se cambia tras crear
  ├── nombre, descripcion
  ├── precio_mensual, precio_anual, moneda
  ├── trial_days, max_dias_grace
  ├── orden, es_publico, activo, es_destacado
  └── HasMany: features, subscriptions

App\Models\PlanFeature
  ├── plan_id, feature
  ├── valor_int, valor_bool, valor_str  ← Columnas tipadas según FEATURE_CATALOG
```

### Catálogo de features (`Plan::FEATURE_CATALOG`)

Constante PHP que define todos los features conocidos del sistema, agrupados por categoría (`limites`, `clinico`, `servicios`, `facturacion`, `comunicaciones`, `reportes`, `soporte`). Cada uno tiene:

- `type`: `int` | `bool` | `str`
- `group`: agrupador visual en el modal
- `default`: valor por defecto al crear un plan nuevo

**Ejemplo**:
```php
'max_sedes' => ['type' => 'int', 'group' => 'limites', 'default' => 1],
'historias_clinicas' => ['type' => 'bool', 'group' => 'clinico', 'default' => true],
'factura_electronica' => ['type' => 'bool', 'group' => 'facturacion', 'default' => false],
```

### Consulta en runtime

Cualquier código (controlador, middleware, vista) puede preguntar:

```php
$plan = $tenant->activeSubscription()?->plan;
$limite = $plan->resolveFeature('max_pacientes');  // → int
$habilitado = $plan->resolveFeature('cirugias');   // → bool
```

Si el plan no tiene la entrada en `plan_features`, devuelve el `default` del catálogo. Esto permite añadir features nuevas sin migrar datos.

### Endpoints (`/plataforma/planes/*`)

| Verbo | URI | Acción |
|---|---|---|
| GET | `planes` | index |
| GET | `planes/export` | XLSX |
| POST | `planes` | crear |
| PUT/PATCH | `planes/{plan}` | actualizar (NO permite cambiar `codigo`) |
| PUT | `planes/{plan}/features` | actualizar features (endpoint dedicado) |
| DELETE | `planes/{plan}` | soft delete (bloqueado si tiene suscripciones) |
| DELETE | `planes/bulk` | bulk delete (omite planes con suscripciones) |

### Frontend

- **Página**: `resources/js/pages/plataforma/planes/index.tsx`
- **6 stats**: total, públicos, destacados, activos, gratis, pagos
- **Modales**: create, edit, delete, bulk-delete, **features** (modal dedicado con búsqueda, dinámico según `FEATURE_CATALOG`)
- **i18n**: `planes.json`

### Por qué `codigo` es inmutable

El campo `codigo` se usa como identificador estable en runtime (`Plan::resolveFeature($plan, 'max_sedes')` se basa en lookups por código). Si se permitiera cambiarlo:
- Webhooks de Orvae que mencionen `BASIC` quedarían huérfanos.
- Configuraciones que hagan referencia al código en `.env` o en código se romperían.
- Reportes históricos pierden trazabilidad.

---

## Módulo: Suscripciones

> **Propósito**: vincula tenant + plan + fechas + estado de cobro. Es el "contrato" entre VetSaaS y la clínica.

### Modelo

```
App\Models\Subscription
  ├── id (UUID)
  ├── tenant_id, plan_id
  ├── estado                  ← trial | active | grace | suspended | cancelled
  ├── ciclo                   ← mensual | anual
  ├── precio_pactado, moneda  ← Snapshot del precio (puede diferir del plan actual)
  ├── trial_ends_at
  ├── current_period_start, current_period_end
  ├── cancelled_at, cancel_reason, cancel_feedback
  └── BelongsTo: tenant, plan / HasMany: payments
```

### Endpoints (`/plataforma/suscripciones/*`)

| Verbo | URI | Acción | Permiso |
|---|---|---|---|
| GET | `suscripciones` | index | `view` |
| GET | `suscripciones/export` | XLSX | `export` |
| POST | `suscripciones` | crear | `create` |
| PUT/PATCH | `suscripciones/{id}` | actualizar | `update` |
| POST | `suscripciones/{id}/extend-trial` | extender prueba | `extend-trial` |
| POST | `suscripciones/{id}/change-plan` | cambiar plan | `change-plan` |
| POST | `suscripciones/{id}/cancel` | cancelar | `cancel` |
| DELETE | `suscripciones/{id}` | hard delete (solo si está cancelada) | `delete` |
| DELETE | `suscripciones/bulk` | bulk delete (omite no canceladas) | `bulk-delete` |

### Acciones especializadas (por qué son endpoints separados)

En lugar de sobrecargar el `update` genérico, cada acción crítica tiene su endpoint para:

- **Auditoría limpia**: el log refleja exactamente qué se hizo, sin tener que diff-ear payloads.
- **Permisos finos**: `extend-trial` y `cancel` pueden delegarse a roles de soporte sin darle `update` completo.
- **Validación específica**: `cancel` exige motivo, `extend-trial` exige días, `change-plan` permite congelar precio.

### Frontend

- **Página**: `resources/js/pages/plataforma/suscripciones/index.tsx`
- **9 stats**: total + breakdown por estado + MRR estimado
- **Modales**: create, edit, delete, bulk-delete, **actions** (modal unificado para extend-trial / change-plan / cancel con UI dinámica)
- **Conexión visual**: botón "Ver historial de cobros" en cada fila → redirige a `/plataforma/cobros` filtrado por `subscription_id`
- **i18n**: `suscripciones.json`

---

## Módulo: Cobros

> **Propósito**: panel **read-only** sobre `subscription_payments`. Los datos los escribe Orvae vía webhook; VetSaaS los lee para soporte y reconciliación, con acciones limitadas (refund manual, nota interna, reenviar factura).

### Decisión arquitectónica: back-office, no procesador

VetSaaS **NO procesa pagos**. Orvae es el "Stripe-equivalente" del SaaS. VetSaaS solo:

1. **Lee** lo que Orvae ya guardó (filas creadas por webhook).
2. **Marca** acciones de soporte que NO afectan al gateway (refund manual = anotación, no orden real al banco).
3. **Reenvía** facturas electrónicas a través del módulo FEL (cuando exista).

Esto evita duplicar la lógica de procesamiento de pagos y deja claros los límites de responsabilidad.

### Modelo

```
App\Models\SubscriptionPayment
  ├── id (UUID)
  ├── subscription_id, plan_id, tenant_id (denormalizados)
  ├── pasarela                ← culqi | mercadopago | manual
  ├── transaction_id          ← Único por pasarela
  ├── monto, moneda
  ├── estado                  ← pending | success | failed | refunded
  ├── periodo_inicio, periodo_fin
  ├── pagado_at, fel_numero, fel_estado
  ├── error_mensaje
  ├── raw_response (JSONB)    ← Payload completo del webhook (debugging)
  └── ─── Campos de soporte (Fase Cobros) ───
  ├── internal_note           ← Notas internas del equipo de soporte
  ├── refunded_at, refunded_by (FK users), refund_reason  ← Refund manual
  └── invoice_resent_at       ← Trazo de reenvío de factura
```

### Endpoints (`/plataforma/cobros/*`)

| Verbo | URI | Acción | Permiso |
|---|---|---|---|
| GET | `cobros` | index (read-only, 8 stats) | `view` |
| GET | `cobros/export` | XLSX | `export` |
| POST | `cobros/{id}/mark-refunded` | marcar refund manual (motivo obligatorio) | `refund` |
| POST | `cobros/{id}/note` | añadir/editar/borrar nota interna | `add-note` |
| POST | `cobros/{id}/resend-invoice` | registrar reenvío (envío real lo hace FEL futuro) | `resend-invoice` |

### Frontend

- **Página**: `resources/js/pages/plataforma/cobros/index.tsx`
- **8 stats**: total, exitosos, pendientes, fallidos, reembolsados, monto total, etc.
- **Búsqueda**: por `transaction_id`, número FEL, tenant, RUC
- **Modales**: detalle (con raw JSON expandible), refund, note, resend
- **i18n**: `cobros.json`

### Cuándo NO usar el botón "Marcar refund"

Es exclusivamente para reflejar refunds **ya ejecutados en Orvae**. Marcar uno aquí sin acción real en el banco genera inconsistencia. La UI lo aclara con un disclaimer visible.

---

## Multi-tenancy en runtime (Fase 1)

> **Propósito**: cuando un empleado de Clínica Rivera entra a `clinica-rivera.vetsaas.com` (prod) o `clinica-rivera.localhost:8000` (dev), todo Laravel debe ver solo los datos de ese tenant, **sin filtros manuales** y **sin riesgo de cruces accidentales**.

### Garantía de aislamiento: 5 capas apiladas

| Capa | Mecanismo | Defensa |
|---|---|---|
| 1 | `SET search_path TO "<schema>", public` en Postgres | Aislamiento físico a nivel motor |
| 2 | Subdominio → slug → schema | El usuario no puede saltar a otro tenant cambiando URL |
| 3 | Autenticación por schema | Sesiones no se transportan entre subdominios |
| 4 | Permisos Spatie dentro del schema | RBAC fino por rol de la clínica |
| 5 | Backend ignora `tenant_id` en payloads | Defensa contra inyección en JSON |

### Componentes implementados

```
app/Tenancy/
├── TenantContext.php              ← DTO inmutable (readonly)
│                                     {tenant, schema, slug}
├── TenantManager.php              ← Singleton orquestador
│                                     resolveBySlug, resolveById,
│                                     runForSlug, forget, flushCacheFor
├── Resolvers/SubdomainResolver.php← Parser host → slug (validado)
├── Exceptions/
│   ├── TenantNotFoundException
│   └── TenantSuspendedException
├── Facades/Tenant.php             ← Facade global
└── helpers.php                    ← current_tenant(), tenant_id()

app/Http/Middleware/
├── ResolveTenant.php              ← Lee subdominio → fija search_path
├── EnsureTenant.php               ← Bloquea ruta si NO hay tenant
└── EnsureNoTenant.php             ← Bloquea ruta si SÍ hay tenant

app/Providers/
└── TenancyServiceProvider.php     ← Singleton + alias bindings

config/tenant.php                  ← central_domains, root_domain,
                                     schema_prefix, allowed_states,
                                     cache_ttl, migration_schema
```

### Configuración (`.env`)

```env
# Hosts que NO son tenants (panel del superadmin)
# Dev:  TENANT_CENTRAL_DOMAINS="localhost,127.0.0.1"
# Prod: TENANT_CENTRAL_DOMAINS="app.vetsaas.com,vetsaas.com"
TENANT_CENTRAL_DOMAINS="localhost,127.0.0.1"

# Sufijo desde el cual se extrae el slug
# Dev:  TENANT_ROOT_DOMAIN=localhost   → mi-clinica.localhost:8000
# Prod: TENANT_ROOT_DOMAIN=vetsaas.com → mi-clinica.vetsaas.com
TENANT_ROOT_DOMAIN=localhost

# Prefijo obligatorio en schema_name
TENANT_SCHEMA_PREFIX=vet_

# Segundos de cache para la resolución tenant → schema. 0 = sin cache.
# En dev se deja en 0 para evitar problemas al recargar migraciones.
# En prod súbelo a 60–300.
TENANT_CACHE_TTL=0
```

> **Tip de dev**: los navegadores modernos resuelven cualquier `*.localhost` a `127.0.0.1` automáticamente, así que no hace falta tocar `C:\Windows\System32\drivers\etc\hosts`. Solo abre `http://mi-clinica.localhost:8000` y funciona.

### Flujo de un request a `clinica-rivera.vetsaas.com`

```
1. Request entra al kernel HTTP
2. Middleware `ResolveTenant` (web global) se ejecuta
   ├─ SubdomainResolver: host → "clinica-rivera"
   ├─ TenantManager::resolveBySlug("clinica-rivera")
   │    ├─ Lee `tenants` (con cache 60s)
   │    ├─ Valida estado ∈ allowed_states
   │    ├─ Sanea schema_name
   │    └─ DB::statement('SET search_path TO "<schema>", public')
   └─ TenantContext queda guardado en el singleton
3. El resto del pipeline corre con el search_path apuntando al tenant
4. Cualquier Eloquent → busca PRIMERO en el schema del tenant
5. Al final del request, conexión vuelve al pool con su search_path
   (la próxima request lo re-evalúa desde cero)
```

### API pública

```php
// Atajos en views/jobs/blade
use function tenant_id;
use function current_tenant;

$id = tenant_id();         // ?string (UUID)
$ctx = current_tenant();   // ?TenantContext

// Facade
use App\Tenancy\Facades\Tenant;

if (Tenant::check()) {
    $slug = Tenant::slug();
    $razon = Tenant::current()->razonSocial();
}

// Inyección por constructor (recomendado, testeable)
use App\Tenancy\TenantManager;

public function __construct(private TenantManager $tenant) {}

// Ejecutar código en otro tenant (jobs, comandos)
$manager->runForSlug('clinica-rivera', function ($ctx) {
    return Owner::count();  // Cuenta dueños de Clínica Rivera
});
```

### Aliases de middleware

| Alias | Clase | Uso |
|---|---|---|
| `tenant` | `ResolveTenant` | **Aplicado globalmente al grupo `web`**. Inocuo en dominio central |
| `tenant.required` | `EnsureTenant` | Para rutas que SOLO funcionan en subdominio (la landing del tenant) |
| `tenant.none` | `EnsureNoTenant` | Para rutas que SOLO funcionan en dominio central. **Sin uso activo** desde Fase 2.5-bis (se mantiene el alias por si vuelve a necesitarse) |
| `tenant.match-user` | `MatchUserTenant` | Validar que `user.tenant_id` coincide con el tenant del host. Va en grupos `auth` (Fase 2.5-bis) |

### Comandos relacionados

```bash
# Crear schema físico + ejecutar migraciones tenant
php artisan vetsaas:tenant-migrate vet_clinica_rivera

# Replay (borrar historial y volver a correr — solo desarrollo)
php artisan vetsaas:tenant-migrate vet_clinica_rivera --replay

# Aplicar migraciones tenant **pendientes** a todos los tenants registrados en `public.tenants`
# (típico al desplegar un módulo nuevo en producción). Opcional: un solo tenant.
php artisan vetsaas:tenant-migrate-all
php artisan vetsaas:tenant-migrate-all --dry-run
php artisan vetsaas:tenant-migrate-all --slug=paws-care
php artisan vetsaas:tenant-migrate-all --schema=vet_paws_care
php artisan vetsaas:tenant-migrate-all --stop-on-error
```

> **Multi-schema:** el log de migraciones tenant debe quedar en la tabla `migrations` **de cada schema**, no depender de `public.migrations` para varios tenants. Si el `search_path` volviera solo a `public` entre pasos del migrador, Laravel registraría ahí las migraciones y el resto de clínicas vería *Nothing to migrate* sin tener las tablas. Lo cubren `TenantMigration` y `TenantSchemaMigrator`. Al añadir un `.php` nuevo en `database/migrations/tenant/`, amplía el *match* en `TenantSchemaMigrator::tenantMigrationIsMaterialized()` para el bootstrap de legado (schemas ya poblados pero sin log por tenant).

### Cache de resolución

- TTL configurable vía `TENANT_CACHE_TTL` (default 60s).
- Llaves: `tenant:slug:<slug>` y `tenant:id:<id>`.
- **Invalidación automática**: `TenantController::suspend|resume|update|destroy|bulkDestroy` llaman a `$manager->flushCacheFor($tenant)`. Cambios de estado se reflejan inmediatamente.

### Tests (`tests/Feature/Tenancy/`)

| Archivo | Cobertura |
|---|---|
| `SubdomainResolverTest.php` | 14 casos: centrales, válidos, sub-subdomains, inyección, etc. |
| `TenantManagerTest.php` | 6 casos: search_path, forget, runForSlug, suspended, cache, not-found |

Los tests del manager se saltan automáticamente si la conexión no es PostgreSQL (la suite usa SQLite por defecto).

### Tests Pest con `tenant-migrate` o `RefreshDatabase` en PostgreSQL

Algunos tests de Feature (`ClinicSettingTest`, `ClinicaHistorialCoreTest`) llaman a `vetsaas:tenant-migrate` y/o `migrate:fresh`. Contra la misma base que usas en desarrollo (p. ej. `vetsaas`) eso puede vaciar tablas o alterar el historial de migraciones. La suite detecta PostgreSQL con un `DB_DATABASE` que **no** termina en `_test` o `_testing` y **omite** esos casos. Para ejecutarlos en Postgres crea una base dedicada (p. ej. `vetsaas_test`) o, solo si aceptas el riesgo, define `VETSAAS_ALLOW_TENANT_MIGRATE_TESTS=true`.

### Lo que la Fase 1 NO incluye todavía

| Punto | Status | Fase |
|---|---|---|
| Routing por subdominio (`tenant.php` para landing) | ✅ | Fase 2 |
| Páginas de error (suspendido / cancelado / 404) | ✅ | Fase 2 |
| Compartir tenant con Inertia | ✅ | Fase 2 |
| Auth por host (single-login con filtrado por `tenant_id`) | ✅ | Fase 2.5-bis |
| Auto-provisión del schema al crear tenant | ❌ | Fase 3 |
| Jobs en cola que necesiten contexto de tenant | ❌ | Fase 3 |
| Auditoría con `tenant_id` en logs | ❌ | Fase 3 |
| Feature gating por plan (límites en Policies) | ❌ | Fase 3.5 |

---

## Multi-tenancy en runtime (Fase 2) — Routing y separación de hosts

> **Propósito original**: garantizar que las rutas del panel SaaS **solo** respondan en el dominio central, y las rutas del cliente **solo** respondan en su subdominio. Renderizar páginas Inertia bonitas para los errores de tenant.

> ⚠️ **Cambio aplicado en Fase 2.5-bis**: `routes/web.php` **ya no está envuelto en `tenant.none`**: responde en cualquier host. La separación ahora es por **usuario** (vía `tenant.match-user` que valida `user.tenant_id` ↔ host) en lugar de por **ruta**. `routes/tenant.php` se reduce a la landing pública `tenant.home`. El resto de Fase 2 (renderers de excepción, prop Inertia `tenant`, dominio dinámico) sigue vigente.

### Diagrama mental (estado actual)

```
┌────────────────────────────────┐   ┌──────────────────────────────────────┐
│ localhost / app.vetsaas.com    │   │ clinica-rivera.localhost / .com      │
│                                │   │                                      │
│  routes/web.php (sin envoltura)│   │  routes/web.php (mismas rutas)       │
│                                │   │                                      │
│  - /                           │   │  - /                                 │
│  - /login → Fortify            │   │  - /login → Fortify + filtro tenant  │
│  - /dashboard                  │   │  - /dashboard                        │
│  - /plataforma/*  (oculto)     │   │  - /plataforma/*  (oculto)           │
│  - /configuracion/*            │   │  - /configuracion/*                  │
│  - /clinica/*                  │   │  - /clinica/*                        │
│                                │   │                                      │
│                                │   │  routes/tenant.php                   │
│                                │   │  - / → tenant.home (landing pública) │
└────────────────────────────────┘   └──────────────────────────────────────┘
        ↑                                    ↑
   Sólo entran users con              Sólo entran users cuyo
   tenant_id IS NULL                  tenant_id = uuid(clinica-rivera)
   (filtrado por Fortify +            (filtrado por Fortify +
    tenant.match-user)                 tenant.match-user)

Items del sidebar y rutas se ocultan/bloquean por permisos Spatie
   (`plataforma-*` solo lo tienen superadmins).
```

### Cómo se monta el routing dinámico (`bootstrap/app.php`)

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    // ...
    then: function (): void {
        Route::middleware('web')
            ->domain('{tenant_subdomain}.'.config('tenant.root_domain'))
            ->group(base_path('routes/tenant.php'));
    },
)
```

Esto registra `routes/tenant.php` con un patrón de dominio dinámico. Laravel matchea por **host completo**: cualquier subdominio del root es candidato, y `ResolveTenant` decide si el slug corresponde a un tenant real.

### Renderers de excepciones (páginas bonitas)

Cuando el middleware `ResolveTenant` no encuentra el tenant o lo encuentra bloqueado, lanza una excepción. Los renderers globales (en `withExceptions`) las traducen a páginas Inertia:

| Excepción | Página Inertia | HTTP |
|---|---|---|
| `TenantNotFoundException` | `tenant/errors/not-found` | 404 |
| `TenantSuspendedException` (suspended) | `tenant/errors/blocked` | 403 |
| `TenantSuspendedException` (cancelled) | `tenant/errors/blocked` (variante visual) | 403 |

### Tenant compartido con Inertia

`HandleInertiaRequests` añade a las props globales:

```typescript
page.props.tenant: TenantShared | null
```

- `null` en el panel central (panel SaaS del superadmin).
- Objeto con `{ id, slug, razon_social, nombre_comercial, estado }` en subdominios.

Cualquier componente React puede consultarlo. Los tipos están en `resources/js/types/tenant.ts`.

### Orden de middlewares: la trampa de Laravel (legado)

Originalmente fue necesario registrar `ResolveTenant`, `EnsureTenant` y `EnsureNoTenant` antes de la interfaz `AuthenticatesRequests` para evitar que `auth` redirigiera a `/login` antes de que el 404 del tenant disparara. En Fase 2.5-bis se simplificó: `ResolveTenant` sigue siendo global (ya no hay `EnsureNoTenant`), y el orden ya no es crítico porque `routes/web.php` responde en cualquier host.

### Páginas creadas

```
resources/js/
└── pages/
    └── tenant/
        ├── welcome.tsx              ← Landing del tenant (pública)
        └── errors/
            ├── not-found.tsx        ← Subdominio no existe
            └── blocked.tsx          ← Suspendido / cancelado
```

`resources/js/app.tsx` mapea `tenant/welcome` y `tenant/errors/*` a `AuthLayout`. Las páginas autenticadas del tenant usan `AppLayout` (el mismo del panel) gracias a Fase 2.5-bis. **Ya no existen** `TenantPublicLayout` ni `TenantAppLayout`.

### Tests añadidos en Fase 2 (`tests/Feature/Tenancy/TenantRoutingTest.php`)

| Caso | Cobertura |
|---|---|
| `tenant.home responde 200 desde el subdominio correcto` | Routing positivo |
| `Comparte el tenant resuelto como prop de Inertia` | Datos al frontend |
| `Subdominio inexistente renderiza "not-found" con 404` | Renderer global |
| `Tenant suspendido renderiza "blocked" con 403` | Renderer global |
| `/plataforma/tenants devuelve 404 desde subdominio` | Aislamiento del panel SaaS |
| `/plataforma/tenants existe desde el dominio central` | Sigue accesible donde toca |

---

## Multi-tenancy en runtime (Fase 2.5) — Autenticación del tenant

> ⚠️ **SECCIÓN HISTÓRICA** — La implementación descrita aquí fue **reemplazada** por la [Fase 2.5-bis](#refactor-a-single-login-fase-25-bis) en mayo 2026. Se conserva como contexto sobre la decisión de diseño y por qué se hizo el refactor. Para el comportamiento actual del sistema, salta a la siguiente sección.

> **Propósito original (descartado)**: que cada clínica tenga su propio sistema de login, completamente separado del panel SaaS del superadmin. Un empleado de Clínica Rivera entra a `clinica-rivera.vetsaas.test/login`, autentica contra la tabla `users` de SU schema, y trabaja en un dashboard con sidebar específico.

### Decisión arquitectónica: dos modelos, dos guards, dos cookies

| Dimensión | Panel SaaS | Tenant |
|---|---|---|
| **Modelo** | `App\Models\User` | `App\Models\TenantUser` |
| **Tabla** | `public.users` (auto-incremental int, `name`, Spatie) | `<schema>.users` (UUID, `nombres`/`apellidos`, enum `rol`) |
| **Guard** | `web` | `tenant` |
| **Provider** | `users` | `tenant-users` |
| **Cookie** | `vetsaas_session` para `vetsaas.test` | `vetsaas_session` para `*.vetsaas.test` (host-scoped) |
| **RBAC** | Spatie con permisos finos (174 permisos) | Enum `rol` en 6 valores fijos |

Las cookies son host-bound (`SESSION_DOMAIN=null` por defecto) y las sesiones se guardan en BD bajo el `search_path` del request, así que físicamente **es imposible que la sesión de Clínica A se trasplante a Clínica B**.

### Modelo `App\Models\TenantUser`

Hereda de `Authenticatable` y vive contra la tabla `users` del schema activo:

```php
class TenantUser extends Authenticatable
{
    use HasUuids, Notifiable, SoftDeletes;
    
    protected $table = 'users';
    
    public const ROL_ADMIN = 'admin_clinica';
    public const ROL_VETERINARIO = 'veterinario';
    public const ROL_ASISTENTE_VET = 'asistente_vet';
    public const ROL_RECEPCIONISTA = 'recepcionista';
    public const ROL_GROOMER = 'groomer';
    public const ROL_LABORATORISTA = 'laboratorista';
    
    // Cast password => 'hashed', helpers nombreCompleto(),
    // iniciales(), isAdmin(), isVeterinario(), ...
}
```

**No usa Spatie**. Los permisos del tenant se derivan del campo `rol` (enum string en BD con CHECK constraint). Esto es deliberadamente más simple que el panel SaaS porque las clínicas tienen 6 roles fijos del dominio, no un sistema permisivo de granularidad arbitraria.

### Configuración (`config/auth.php`)

```php
'guards' => [
    'web' => ['driver' => 'session', 'provider' => 'users'],
    'tenant' => ['driver' => 'session', 'provider' => 'tenant-users'],
],

'providers' => [
    'users' => ['driver' => 'eloquent', 'model' => User::class],
    'tenant-users' => ['driver' => 'eloquent', 'model' => TenantUser::class],
],

'passwords' => [
    'users' => [...],
    'tenant-users' => [
        'provider' => 'tenant-users',
        'table' => 'password_reset_tokens',   // vive en el schema del tenant
        'expire' => 60,
        'throttle' => 60,
    ],
],
```

### Routing autenticado del tenant (`routes/tenant.php`)

```
GET  /              → tenant.home          (público, redirige a dashboard si auth)
GET  /login         → tenant.login         (guest:tenant only)
POST /login         → tenant.login.store   (throttle 6/min)
GET  /dashboard     → tenant.dashboard     (auth:tenant)
POST /logout        → tenant.logout        (auth:tenant)
```

Todas viven bajo `{tenant_subdomain}.vetsaas.test` (montadas vía `Route::domain(...)` en `bootstrap/app.php`). El parámetro `{tenant_subdomain}` se inyecta como default automáticamente desde `ResolveTenant` para que `route('tenant.login')` y similares funcionen sin pasarlo manualmente.

### Redirect dinámico cuando `auth:tenant` falla

Por defecto el middleware `Authenticate` redirige a `route('login')` (del panel central). Eso no aplica para el subdominio. En `TenancyServiceProvider::boot()`:

```php
Authenticate::redirectUsing(function (Request $request) {
    if (app(TenantManager::class)->check()) {
        return route('tenant.login');
    }
    return route('login');
});
```

### `HandleInertiaRequests` adaptado

Comparte el usuario del guard correcto según el contexto:

```php
$usingTenantGuard = $tenantContext !== null;
$guard = $usingTenantGuard ? 'tenant' : 'web';
$user = Auth::guard($guard)->user();

'auth' => [
    'user' => $user,
    'permissions' => $usingTenantGuard ? [] : $user?->getAllPermissions()?->pluck('name')->all(),
    'roles' => $usingTenantGuard ? [$user?->rol] : $user?->getRoleNames()->all(),
]
```

En el panel central → permisos Spatie. En el tenant → `rol` enum como único item de roles.

### Pantallas Inertia añadidas

```
resources/js/
├── layouts/
│   └── tenant-app-layout.tsx       ← Sidebar + topbar para empleados autenticados
├── components/
│   └── tenant-sidebar.tsx          ← Sidebar específico de clínica (módulos en placeholder)
└── pages/
    └── tenant/
        ├── welcome.tsx             ← Landing pública (con CTA "Ingresar")
        ├── auth/
        │   └── login.tsx           ← Form de login con branding del tenant
        └── dashboard.tsx           ← Dashboard autenticado (saludo + 4 stats placeholder)
```

`app.tsx` mapea el layout correcto:

```ts
case name.startsWith('tenant/auth/'):
case name.startsWith('tenant/errors/'):
case name === 'tenant/welcome':
    return TenantPublicLayout;   // chrome delgado
case name.startsWith('tenant/'):
    return TenantAppLayout;      // sidebar de clínica
```

### Comando: sembrar el primer admin

Una vez aprovisionado el tenant (schema + migraciones), no hay usuarios todavía. Este comando crea el bootstrap:

```bash
php artisan vetsaas:tenant-create-admin clinica-rivera \
    --email=admin@rivera.com \
    --password="secreto-seguro" \
    --nombres="María Elena" \
    --apellidos="Quispe Quiroz"
```

- Internamente usa `TenantManager::runForSlug()` para entrar al schema.
- Valida email, contraseña (mín. 8 chars) y nombres antes de tocar la BD.
- Si el email ya existe, pide confirmación para sobrescribir (o usar `--force`).
- El usuario queda con `rol=admin_clinica`, `activo=true`, `email_verificado=true`.

### `LoginRequest` — rate limiting por (email + IP)

Replica el comportamiento estándar de Laravel pero usando el guard `tenant`:

- 5 intentos fallidos → lockout temporal con mensaje específico.
- La clave de rate limit combina email e IP, así un empleado bloqueado no afecta a otros de la misma clínica (NAT compartido).
- Incluye `'activo' => true` en las credenciales, así un usuario marcado inactivo NO puede entrar aunque la contraseña sea correcta.

### Tests añadidos en Fase 2.5 (`tests/Feature/Tenancy/TenantAuthTest.php`)

| Caso | Cobertura |
|---|---|
| GET /login en subdominio responde con `tenant/auth/login` | Routing positivo |
| POST /login válido redirige a `/dashboard` | Login OK |
| POST /login inválido devuelve error y no autentica | Credenciales malas |
| Usuario inactivo NO puede iniciar sesión | Filtro `activo=true` |
| GET /dashboard sin sesión redirige a /login (del tenant) | `Authenticate::redirectUsing` |
| Logout cierra sesión y redirige | `LoginController@destroy` |
| El comando `tenant-create-admin` siembra el admin | Bootstrap del tenant |
| Mismos emails en clínicas distintas → tablas físicamente aisladas | Aislamiento de datos |

### Lo que la Fase 2.5 NO incluye todavía

| Punto | Status | Cuándo |
|---|---|---|
| Forgot/reset password (UI + envío de email) | ❌ | Fase 2.6 cuando montemos colas |
| Cambio de contraseña obligatorio (`must_change_password`) | ❌ | Fase 2.6 |
| 2FA en el tenant | ❌ | Fase 5 |
| Roles → permisos mapeo declarativo (gates por rol) | ❌ | Fase 4 (en cada módulo) |
| CRUD de usuarios del tenant | ❌ | Fase 4 (`/configuracion/usuarios` dentro del tenant) |

---

## Refactor a single-login (Fase 2.5-bis)

> **Cambio de arquitectura** sobre la Fase 2.5 original. Decisión tomada en mayo 2026: en vez de mantener DOS sistemas de autenticación separados (`web` para el SaaS + `tenant` para cada clínica), se unifica todo en un solo modelo `User` y un solo guard `web`. Los DATOS operativos siguen aislados por schema.

### Motivación

La Fase 2.5 original montaba dos guards completamente paralelos:
- `web` (modelo `App\Models\User`, tabla `public.users`) → superadmin.
- `tenant` (modelo `App\Models\TenantUser`, tabla `<schema>.users`) → empleados de clínica.

Dos sistemas distintos de permisos (Spatie para uno, enum `rol` para el otro), dos pantallas de login, dos sidebars, dos layouts. Mucha duplicación para nada porque los empleados de cada clínica son simplemente **otro tipo de usuario** del mismo SaaS, no una aplicación distinta.

La arquitectura actualizada se inspira en cómo lo hace Shopify, Linear, Notion, Vercel: **un solo modelo `User` con campo `tenant_id`** + roles/permisos globales (Spatie) deciden quién ve qué.

```
public.users:
┌──────────────────────┬────────────────┬─────────────┬──────────────┐
│ email                │ rol (Spatie)   │ tenant_id   │ Rol funcional│
├──────────────────────┼────────────────┼─────────────┼──────────────┤
│ superadmin@vetsaas   │ superadmin     │ NULL        │ Panel SaaS   │
│ admin@miclinica.com  │ admin_clinica  │ uuid-A      │ Dueño clínica│
│ ana@miclinica.com    │ veterinario    │ uuid-A      │ Veterinaria  │
│ luis@otraclinica.com │ recepcionista  │ uuid-B      │ Recepción    │
└──────────────────────┴────────────────┴─────────────┴──────────────┘
```

### Qué cambia (resumen ejecutivo)

| Concepto | Antes (2.5) | Ahora (2.5-bis) |
|---|---|---|
| Modelo de usuario | `User` + `TenantUser` | Solo `User` (con `tenant_id` nullable) |
| Tabla | `public.users` + `<schema>.users` | Solo `public.users` |
| Guards | `web` + `tenant` | Solo `web` |
| Cookies de sesión | Distintas (host-scoped) | Una sola (host-scoped) + middleware valida |
| Sistema de permisos | Spatie (SaaS) + enum (tenant) | Solo Spatie, para todos |
| UI de login | 2 pantallas distintas | 1 sola, con branding contextual |
| Sidebar | `AppSidebar` + `TenantSidebar` | Solo `AppSidebar`, items filtrados por permisos |
| Aislamiento de DATOS operativos | Schema por tenant | Schema por tenant (sin cambios) |

### Arquitectura final

#### 1. Modelo `User`

`app/Models/User.php` ahora incluye:
- Campo `tenant_id` (uuid, nullable). `null` = usuario del panel central.
- Relación `tenant(): BelongsTo<Tenant>`.
- Helpers `isCentral()`, `isTenantUser()`, `belongsToTenant(?string)`.

Email único POR tenant — un mismo `maria@gmail.com` puede ser veterinaria en clínica A **y** recepcionista en clínica B sin colisión:

```sql
CREATE UNIQUE INDEX users_tenant_email_unique
ON users (COALESCE(tenant_id::text, '__central__'), lower(email));
```

Migración: `database/migrations/2026_05_15_100000_add_tenant_id_to_users_table.php`.

#### 2. Autenticación contextual (Fortify hook)

Fortify ya manejaba el login estándar. Le inyectamos en `FortifyServiceProvider::configureAuthentication()` un `authenticateUsing` que:

```php
Fortify::authenticateUsing(function (Request $request) {
    $hostTenantId = app(TenantManager::class)->check()
        ? app(TenantManager::class)->current()?->id()
        : null;

    return User::query()
        ->where('email', $request->input('email'))
        ->when($hostTenantId === null,
            fn ($q) => $q->whereNull('tenant_id'),
            fn ($q) => $q->where('tenant_id', $hostTenantId),
        )
        ->where('is_active', true)
        ->first();
    // ... password check con Hash::check ...
});
```

Efectos:
- Desde `localhost:8000/login` → solo entran users con `tenant_id IS NULL`.
- Desde `mi-clinica.localhost:8000/login` → solo entran users con `tenant_id = uuid(mi-clinica)`.
- Cuentas con `is_active = false` no pueden entrar (igual que antes).

#### 3. Middleware `MatchUserTenant`

`app/Http/Middleware/MatchUserTenant.php`. Se aplica a todo el grupo de rutas autenticadas (`web.php`). Después del login, en cada request:

| Host actual | `user.tenant_id` | Resultado |
|---|---|---|
| Central (sin tenant) | `null` | OK |
| Central | `<uuid X>` | Logout + 403 (mensaje: "inicia sesión desde tu clínica") |
| Tenant `X` | `<uuid X>` | OK |
| Tenant `X` | `null` (superadmin) | 403 (en el futuro habrá impersonation explícita) |
| Tenant `X` | `<uuid Y>` distinto | 403 ("no perteneces a esta clínica") |

Esto impide que una sesión "se cuele" a otro host aunque la cookie sí esté presente.

#### 4. Rutas

`routes/web.php` ya **no está envuelto en `tenant.none`**. Las rutas operativas (`/dashboard`, `/clinica/*`, `/configuracion/*`, `/plataforma/*`) responden en cualquier host. La diferencia es qué puede entrar gracias a `tenant.match-user` + `permission:*`.

`routes/tenant.php` se reduce a una sola ruta:

```php
Route::middleware(['tenant.required'])->group(function (): void {
    Route::get('/', [TenantDashboardController::class, 'welcome'])
        ->name('tenant.home');
});
```

Es la landing pública del subdominio. El login, logout, dashboard, etc. son **rutas globales** definidas por Fortify y `routes/web.php`.

#### 5. Frontend unificado

- `resources/js/app.tsx`: una sola tabla de layouts. Todas las páginas (`tenant/welcome`, `tenant/errors/*` aparte) usan `AppLayout`. **El sidebar es el mismo para todos**.
- `resources/js/components/app-sidebar.tsx`: cada item lleva un `permission: '...'`. Si el usuario no tiene ese permiso, el item se oculta automáticamente (`NavMainCollapsible`).
- `resources/js/layouts/auth/auth-split-layout.tsx`: detecta `page.props.tenant` y, si existe, personaliza el saludo y el header con el nombre comercial de la clínica.
- **Borrados** (ya no se necesitan):
  - `tenant/auth/login.tsx`
  - `tenant/dashboard.tsx` (Inertia ahora carga el `dashboard.tsx` central)
  - `layouts/tenant-public-layout.tsx`
  - `layouts/tenant-app-layout.tsx`
  - `components/tenant-sidebar.tsx`

#### 6. Comando `vetsaas:tenant-create-admin`

Reescrito en `app/Console/Commands/TenantCreateAdminCommand.php`:

```bash
php artisan vetsaas:tenant-create-admin mi-clinica \
    --email=admin@miclinica.com \
    --password=secret123 \
    --name="Admin Clinic"
```

Crea (o actualiza) un usuario en `public.users` con:
- `tenant_id` = uuid del tenant `mi-clinica`.
- Rol Spatie `admin_clinica` sincronizado (144 permisos, ver `TenantRolesSeeder`).
- `is_active = true`, `email_verified_at = now()`.

> **Sintaxis en PowerShell**: usar todo en una línea o `` ` `` para continuación. Las `\` de bash NO funcionan en PowerShell.

### Qué se preserva sin cambios

- **Schemas por tenant** para datos operativos (pacientes, citas, historias, inventario, caja…). El aislamiento físico de datos es la fortaleza de la arquitectura.
- **Resolución de tenant por subdominio** (`SubdomainResolver`, `ResolveTenant`, `TenantManager`).
- **Páginas de error** para subdominio inexistente (`tenant/errors/not-found`, 404) y suspendido (`tenant/errors/blocked`, 403).
- **Inertia shared prop** `page.props.tenant` (snapshot del tenant actual) — sigue disponible para que cualquier componente sepa "en qué clínica está".
- **Comando `vetsaas:tenant-migrate`** para crear schemas operativos por clínica.

### Eliminados

| Archivo | Reemplazado por |
|---|---|
| `App\Models\TenantUser` | `App\Models\User` con `tenant_id` |
| `App\Http\Controllers\Tenant\Auth\LoginController` | Fortify (rutas estándar `/login`) |
| `App\Http\Requests\Tenant\Auth\LoginRequest` | Fortify default + `Fortify::authenticateUsing` hook |
| Guard `tenant` + provider `tenant-users` en `config/auth.php` | Solo guard `web` |
| Password broker `tenant-users` | Solo broker `users` |
| Migraciones tenant `t001`–`t004` (users, sessions, pwd_reset_tokens, personal_access_tokens) | Las tablas viven en `public` |
| `Authenticate::redirectUsing` custom en `TenancyServiceProvider` | Innecesario (Fortify maneja un solo `/login`) |
| `tests/Feature/Tenancy/TenantAuthTest.php` | (Pendiente: nuevo test E2E con el flujo unificado) |

### Verificación post-refactor

Con tenant `mi-clinica` creado y admin sembrado, estos son los conteos:

```text
public.users:
  superadmin@vetsaas.com  | tenant=(central)    | rol=superadmin     | 174 permisos
  admin@miclinica.com     | tenant=mi-clinica   | rol=admin_clinica  | 144 permisos

Verificación de RBAC:
  ¿admin_clinica puede ver plataforma-tenants? → NO ✓ (correcto)
  ¿admin_clinica puede ver pacientes?           → SÍ ✓ (correcto)
```

URLs operativas en dev:

| Quién | URL | Credenciales |
|---|---|---|
| Superadmin | `http://localhost:8000/login` | `superadmin@vetsaas.com` / `superadmin` |
| Admin de mi-clinica | `http://mi-clinica.localhost:8000/login` | `admin@miclinica.com` / `secret123` |

### Restricciones por plan (próximo paso natural)

Con esta base, el siguiente paso es conectar `plan_features` a los Policies:

```php
public function create(User $user): bool
{
    if (! $user->can('pacientes.create')) return false;

    $plan = $user->tenant->subscription->plan;
    $limite = $plan->getFeatureValue('max_pacientes'); // null = ilimitado
    return $limite === null || $user->tenant->pacientes()->count() < $limite;
}
```

Plan FREE con `max_pacientes = 5` → al sexto, devuelve `false` y la UI muestra "Mejora tu plan". Plan PRO con `max_pacientes = null` → ilimitado. Esto es **feature gating** y se aplica en cada Policy de cada módulo, sin tocar más la auth.

---

## Recuperación de contraseña y cambio obligatorio (Fase 2.6)

> **Propósito**: que cualquier usuario del SaaS (superadmin o empleado de clínica) pueda **recuperar su contraseña él mismo** sin intervención de soporte, y que las cuentas recién provisionadas se vean **obligadas a definir una contraseña personal** antes de operar. Pre-requisito técnico de Fase 3 (provisión automática) porque comparte el mismo magic-link y la misma infraestructura de mailer + colas.

### Lo que añade esta fase, en una línea cada cosa

- **Forgot password** con conciencia de host: cada `<slug>.vetsaas.com/forgot-password` envía el reset SOLO al usuario que pertenece a esa clínica, aunque exista el mismo email en otras.
- **Reset password** con tokens segregados por tenant: dos usuarios con `maria@gmail.com` en clínicas distintas pueden tener tokens vivos a la vez sin pisarse.
- **Notificaciones brandeadas** y **queueables**: el correo sale con el nombre comercial de la clínica y el HTTP responde inmediato (envío en cola `mails`).
- **`must_change_password`**: bandera en `users` que fuerza al primer login a definir clave nueva. La aplica un middleware global a todas las rutas operativas.
- **`vetsaas:tenant-create-admin --email=… --name=…`** (sin `--password`): genera cuenta + token y dispara invitación por correo; soporte nunca conoce la contraseña final.

### Decisión clave: scope tenant en el broker, no en el controller

El broker de password de Laravel (`Illuminate\Auth\Passwords\PasswordBroker`) usa solo `email` como clave del usuario y de la tabla `password_reset_tokens`. En un SaaS multi-tenant con emails repetidos, eso provoca **dos colisiones**:

1. `Password::sendResetLink(['email' => $email])` → busca al usuario solo por email → encuentra cualquiera (no necesariamente el del host).
2. La tabla `password_reset_tokens` tiene `email` como PK → cuando dos usuarios distintos con el mismo email piden reset, el segundo borra el token del primero.

Resolverlo en cada controlador sería frágil. Lo correcto es resolverlo a nivel de **provider de auth** + **repositorio de tokens**:

```
config('auth.providers.users.driver')   = 'tenant-eloquent'
            │
            ▼
TenantAwareEloquentUserProvider
   · retrieveByCredentials() inyecta `tenant_id` del host
       (TenantManager::current()?->id() || null)
   · todo lo demás idéntico al EloquentUserProvider stock

container('auth.password')
            │
            ▼
TenantAwarePasswordBrokerManager (extend del singleton)
            │
            ▼
TenantAwarePasswordTokenRepository
   · password_reset_tokens AHORA tiene columna tenant_id
   · todas las queries (create, exists, deleteExisting,
     recentlyCreatedToken) scopean por (email, tenant_id)
   · índice composite COALESCE(tenant_id::text, '__central__'), lower(email)
```

Con esos dos componentes, NINGÚN call site cambia: `Password::sendResetLink(['email'=>X])` desde el subdominio `clinica-a.vetsaas.com` automáticamente resuelve al usuario de Clínica A y genera el token bajo `tenant_id = A`. Es transparente para Fortify.

### Esquema de tablas (delta)

```sql
-- users
ALTER TABLE users ADD COLUMN must_change_password BOOLEAN DEFAULT false;

-- password_reset_tokens
ALTER TABLE password_reset_tokens
    ADD COLUMN tenant_id UUID NULL REFERENCES tenants(id) ON DELETE CASCADE;
ALTER TABLE password_reset_tokens DROP CONSTRAINT password_reset_tokens_pkey;
CREATE UNIQUE INDEX password_reset_tokens_tenant_email_unique
    ON password_reset_tokens (COALESCE(tenant_id::text, '__central__'), lower(email));
```

Migración: `database/migrations/2026_05_22_100000_add_password_lifecycle_columns.php`.

### Archivos clave

```
app/
├── Auth/
│   ├── TenantAwareEloquentUserProvider.php      ← driver "tenant-eloquent"
│   ├── TenantAwarePasswordTokenRepository.php   ← repo scopeado por tenant_id
│   └── TenantAwarePasswordBrokerManager.php     ← reemplaza al manager stock
├── Notifications/Auth/
│   ├── PasswordResetLinkNotification.php        ← reemplaza Illuminate\Auth\Notifications\ResetPassword
│   └── TenantAdminInvitationNotification.php    ← correo de "Tu clínica está lista"
├── Http/
│   ├── Middleware/EnsurePasswordIsChanged.php   ← alias `force-password-change`
│   └── Controllers/Auth/ChangePasswordController.php
├── Models/User.php                              ← sendPasswordResetNotification() override
├── Providers/
│   ├── AuthServiceProvider.php                  ← `extend('auth.password')` + Auth::provider()
│   └── FortifyServiceProvider.php               ← authenticateUsing simplificado
└── Console/Commands/TenantCreateAdminCommand.php ← modo invitación

resources/js/pages/auth/
├── forgot-password.tsx       ← existente (rehidratada con branding del tenant)
├── reset-password.tsx        ← existente (rehidratada con branding del tenant)
└── change-password.tsx       ← NUEVA: pantalla del cambio obligatorio
```

### Notificaciones: por qué reemplazamos el `ResetPassword` por defecto

El default de Laravel:
- No es queueable → bloquea el HTTP hasta que el SMTP responde.
- Genera la URL con `route('password.reset', ...)` resolviendo el host **del request actual**. En un job, el host es el del worker, no el de la clínica.
- Texto en inglés y sin branding.

Nuestro `PasswordResetLinkNotification` (queue `mails`) construye la URL apuntando explícitamente al subdominio del tenant:

```
https://clinica-a.vetsaas.com/reset-password/<token>?email=user@x.com
                    ↑
       deducido de `Tenant::find($user->tenant_id)->slug`
```

Y formatea el cuerpo con el `nombre_comercial` de la clínica para que el correo se vea "de la clínica", no "de VetSaaS genérico".

### Cambio obligatorio: cómo funciona el middleware

```
Request → auth → tenant.match-user → force-password-change → permission:* → controller
                                          │
                                          ▼
                          ¿user->must_change_password == true?
                                          │
                       ┌─────────── sí ───┼─── no ───────────┐
                       ▼                                     ▼
            ¿Estamos ya en una de               $next($request)
            estas rutas allowlist?
              · password.change.form
              · password.change.update
              · logout
                       │
              ┌─── sí ─┼── no ─────────────────────┐
              ▼                                    ▼
        $next($request)                redirect(password.change.form)
```

La allowlist evita el bucle infinito y permite al usuario cerrar sesión si está bloqueado. El controlador `ChangePasswordController`:
- Valida la nueva contraseña con `PasswordValidationRules` (las mismas reglas que usa Fortify).
- Rechaza si la nueva clave coincide con la actual (la bandera quedaría sin sentido).
- Setea `password = nueva`, `must_change_password = false` y redirige al `intended` (o al dashboard).

### Flujo completo de "invitar admin"

```
Soporte ejecuta:
    php artisan vetsaas:tenant-create-admin mi-clinica \
        --email=admin@miclinica.com --name="Admin Clinic"

           │
           ▼
TenantCreateAdminCommand:
   1. Busca tenant por slug.
   2. Genera password aleatoria (Str::password 20 chars).
   3. INSERT en public.users con:
        tenant_id = uuid(mi-clinica)
        password = Hash::make(random)
        must_change_password = true
        is_active = true
        email_verified_at = now()
   4. Asigna rol Spatie 'admin_clinica'.
   5. Password::broker()->createToken($user)
        → inserta en password_reset_tokens con tenant_id.
   6. $user->notify(new TenantAdminInvitationNotification($token))
        → cola "mails" → Brevo SMTP.

Worker procesa la cola:
    php artisan queue:work --queue=mails

Admin recibe correo "Bienvenido a Mi Clínica":
    → Click "Definir contraseña"
    → Reset password form → POST /reset-password
       (host: mi-clinica.vetsaas.com)
    → TenantAwarePasswordBrokerManager:
       resuelve userA (tenant_id=A)
       valida token vs scopedQuery(userA)
       actualiza password, borra token
    → Redirect /login con flash "Contraseña actualizada"
    → Admin loguea → must_change_password ahora es FALSE
      (porque el reset NO setea esta bandera; ver más abajo)
```

> **Detalle sobre el flag tras el reset**: cuando el usuario completa el reset (vía link de invitación o vía "olvidé mi contraseña"), `ResetUserPassword` solo actualiza `password`. NO toca `must_change_password`. Eso es intencional: si el admin definió su clave personal con el magic-link, ya no necesita ser forzado a cambiarla otra vez. El flag solo se "consume" en `ChangePasswordController::update()`. Para alinear ambos flujos, en el comando de invitación podríamos extender `ResetUserPassword` para resetear la bandera; por ahora la bandera sobrevive al reset y se limpia cuando el usuario pase por `/cuenta/cambiar-password` (o cuando soporte la baje manualmente en su próximo perfilamiento).

### Rutas añadidas

```
GET   /cuenta/cambiar-password   → password.change.form    (auth + tenant.match-user)
POST  /cuenta/cambiar-password   → password.change.update  (auth + tenant.match-user)
```

Quedan **fuera** del grupo con `force-password-change` (para evitar el bucle). Todas las demás rutas operativas (`/dashboard`, `/clinica/*`, `/configuracion/*`, `/plataforma/*`, `/settings/*`) ahora pasan por el middleware.

### Configuración de mailer y cola (`.env`)

Ya estaba listo desde antes de esta fase:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=…
MAIL_PASSWORD=…
MAIL_FROM_ADDRESS="notificaciones@orvae.pe"

QUEUE_CONNECTION=database
```

En desarrollo se procesa con `php artisan queue:work --queue=mails`. En producción (Fase 5) lo hará Horizon.

### Tests añadidos (`tests/Feature/`)

| Archivo | Cobertura |
|---|---|
| `Auth/ForcePasswordChangeTest.php` | 7 casos: redirect del flag, allowlist (form/update/logout), update válido, rechaza "misma clave", rechaza confirmación distinta, flag-off entra sin redirect |
| `Tenancy/TenantPasswordResetTest.php` | 5 casos (pgsql-only): forgot desde tenant solo afecta a SU user; forgot desde central solo afecta al central; los dos tenants conviven con tokens distintos; token de tenant A no resetea password de tenant B; token de A SÍ resetea password de A |
| `Auth/PasswordResetTest.php` | Actualizado para usar `PasswordResetLinkNotification` en lugar del default de Laravel (5 casos verdes) |

Total: **23 tests verdes** del flujo Fase 2.6 (suite completa en PostgreSQL).

### Lo que la Fase 2.6 NO incluye todavía

| Punto | Estado | Cuándo |
|---|---|---|
| Resetear `must_change_password` al completar reset desde link de invitación | ❌ | Cuando llegue el patrón en Fase 3 (`ProvisionTenantJob`) |
| 2FA (TOTP) en el panel | ❌ | Fase 5 |
| Recuperación por SMS / WhatsApp | ❌ | Fase 6 (operativa con Twilio o gateway local) |
| Logs de auditoría de resets (quién, cuándo, desde qué IP) | ❌ | Fase 3 (auditoría central) |
| Reenviar invitación desde `/plataforma/tenants/<id>` | ❌ | Pequeño UI follow-up |

---

## Módulo Configuración › General (Fase 4 · módulo 1)

> **Objetivo**: que cada clínica tenga un único lugar para editar su **identidad fiscal**, **branding**, **agenda**, **recordatorios automáticos** y **facturación electrónica (Lucode / APISUNAT)**. Es el primer módulo del bloque "Fase 4 — app del tenant" y sirve de **patrón base** para los módulos clínicos que vienen después.
>
> **Nota jul. 2026**: el emisor CPE en producción del producto es **Lucode (APISUNAT v3)** (`apisunat_*` en `cfg_clinic_settings`). Campos `nubefact_*` pueden existir por legado; la emisión/anulación actuales no dependen de Nubefact.

### División de responsabilidades: cliente vs SaaS

Antes de hablar del modelo, una decisión clave: **¿qué configuraciones son del cliente y cuáles del SaaS?** Cada integración externa cae en una de dos categorías:

| Integración | ¿Quién la configura? | ¿Dónde vive? |
|---|---|---|
| **Nubefact** (facturación electrónica SUNAT) | **Cliente** — cada clínica tiene su propio RUC y su contrato directo con SUNAT/Nubefact. Las boletas se emiten contra el RUC del cliente, no contra el nuestro. | `cfg_clinic_settings.nubefact_token_enc` (schema tenant). |
| **Twilio Cloud** (WhatsApp) | **SaaS** — la plataforma envía mensajes con su propia cuenta y número aprobado por Meta. El cliente solo personaliza el **número visible** y la firma. | `public.platform_settings.twilio_*` (singleton global). |
| **Brevo** (correo transaccional) | **SaaS** — todos los correos salen por la cuenta del operador del SaaS. El cliente solo personaliza el **Reply-To** y el nombre del remitente. | `public.platform_settings.brevo_*` (singleton global). |

Esto se traduce en **dos pantallas**:

1. **Configuración › General** (`/configuracion/general`, tenant) — accesible a `admin_clinica` y `superadmin` (vía impersonation futura). Edita los datos del cliente.
2. **Plataforma › Configuración** (`/plataforma/configuracion`, central) — accesible **solo a `superadmin`**. Edita las credenciales globales del SaaS. Las clínicas nunca ven esta pantalla.

Para más detalle de la pantalla 2 ver [Módulo Plataforma › Configuración global del SaaS](#módulo-plataforma--configuración-global-del-saas-fase-4--módulo-15).

### 1. Modelo de datos: singleton por schema

La configuración vive en `cfg_clinic_settings` dentro del **schema del tenant**. La migración garantiza que solo puede existir **una** fila por clínica usando un índice único sobre la constante `TRUE`:

```sql
CREATE UNIQUE INDEX uq_cfg_clinic_settings_single_row
  ON cfg_clinic_settings ((TRUE));
```

Esto convierte el modelo Eloquent en un **singleton** de hecho. La capa de aplicación lo accede vía:

```php
$setting = \App\Models\ClinicSetting::current();   // firstOrCreate([])
```

`current()` autoprovisiona la fila la primera vez que un usuario abre la pantalla (con los `default` de la migración: moneda PEN, IGV 18.00, cita 30 min, etc.), así que **el admin de una clínica nunca encuentra una página vacía**: siempre hay algo razonable que ajustar.

### 2. Campos por bloque

| Bloque | Campos relevantes |
|---|---|
| **Identidad fiscal** | `ruc` (11 dígitos), `razon_social`, `nombre_comercial`, `direccion_fiscal`, `distrito_id` (FK a `public.distritos`) |
| **Branding** | `logo_path` (path en disco `public`, expuesto al frontend como `logo_url` calculada por accesor), `color_primario`, `color_secundario` (hex `#RRGGBB`) |
| **Contacto** | `email_institucional`, `telefono_principal`, `web_url` |
| **Agenda** | `duracion_cita_default_min`, `intervalo_agenda_min`, `dias_anticipacion_cita`, `horas_min_cancelacion` |
| **Recordatorios** | toggles `recordatorio_48h`, `recordatorio_2h`, `recordatorio_vacuna_*`, `recordatorio_cumple_*` |
| **Facturación electrónica** | `moneda` (PEN/USD), `igv_porcentaje` (decimal 5,2), `precio_incluye_igv` (bool), `nubefact_token_enc`, `nubefact_ruc`, flag `nubefact_configurado` |
| **Remitente comercial visible** | `email_from` (Reply-To), `email_from_nombre` (sender name), `whatsapp_display_number` (número que aparece como contacto en el cuerpo del mensaje). **No autentican** contra Twilio/Brevo: solo personalizan la firma. |

> Las claves reales de Twilio (SID, token) y Brevo (API key) **no existen aquí**: viven en `public.platform_settings`. Esto evita que cada clínica tenga que abrir cuentas con esos proveedores.

### 3. Cifrado de credenciales sensibles (solo Nubefact)

La única credencial sensible que el cliente edita en esta pantalla es el **token de Nubefact**. Se guarda cifrado con `Crypt::encryptString` (AES-256-CBC con `APP_KEY`) en la columna `nubefact_token_enc`. El payload que viaja al frontend jamás incluye el token en claro: solo se expone el booleano `nubefact_configurado` que indica si hay clave guardada.

Para diferenciar "no toqué la credencial" de "quiero borrarla" (el input siempre se muestra vacío por seguridad), el cliente envía el flag explícito `clear_nubefact=true`. La lógica del controller sigue tres caminos:

1. `clear_nubefact=true` → limpia `nubefact_token_enc` y baja `nubefact_configurado=false`.
2. `nubefact_token` con valor no vacío → cifra y levanta `nubefact_configurado=true`.
3. Ninguno de los dos → preserva el valor anterior (el típico "guardé otros campos sin tocar la integración" no rompe nada).

### 3 bis. Subida de logo (Fase 4 · módulo 1)

El logo se sube como archivo, no como URL. El flujo de extremo a extremo:

| Paso | Capa | Detalle |
|---|---|---|
| 1 | **Frontend** | `<LogoUploader>` (`resources/js/pages/configuracion/general/components/logo-uploader.tsx`) acepta drag&drop o click. Valida tipo (`png/jpg/webp/svg`) y tamaño (≤ 2 MB) en cliente para feedback inmediato. |
| 2 | **Transporte** | El form se envía como `multipart/form-data` vía `router.post(..., { forceFormData: true })` con `_method=put`. Inertia detecta el `File` en el payload y lo serializa correctamente. |
| 3 | **Validación backend** | `ClinicSettingRequest` aplica `image|mimes:jpg,jpeg,png,webp,svg|max:2048`. Las validaciones cliente son una conveniencia; el backend es la autoridad. |
| 4 | **Almacenamiento** | El archivo se guarda en `storage/app/public/tenants/<slug>/logos/<uuid>.<ext>` vía disco `public`. Se accede públicamente bajo `/storage/...` gracias a `php artisan storage:link`. |
| 5 | **Persistencia** | `cfg_clinic_settings.logo_path` guarda el path relativo. El accesor `logo_url` del modelo lo expande a URL pública con `asset('storage/'.$path)`. |
| 6 | **Limpieza** | Si llega un logo nuevo, el anterior se borra del disco (no se dejan huérfanos). Si llega `clear_logo=true`, se borra el archivo y se pone `logo_path=null`. |

El path se namespacia por **slug del tenant** para evitar colisiones entre clínicas (`tenants/mi-clinica/logos/<uuid>.png`). Cuando una clínica se elimina (Fase 2 · plataforma), su carpeta puede limpiarse junto con el schema.

### 4. Aislamiento y autorización

| Capa | Mecanismo |
|---|---|
| **BD** | La tabla vive en el schema del tenant. El `search_path` lo fija `TenantManager` al resolver el subdominio. Cada clínica edita solo SU fila. |
| **HTTP** | Ruta dentro del grupo con `tenant.match-user`. Un usuario con `tenant_id=X` jamás puede llegar a la página de la clínica `Y`. |
| **Permisos** | Permiso `config-general.view` para abrir + `config-general.update` para guardar. Asignado **solo a `admin_clinica`** entre los roles tenant (veterinario, recepcionista, asistente, groomer NO lo tienen). El `superadmin` lo recibe automáticamente vía `SuperadminSeeder` pero solo podrá usarlo cuando exista **impersonation (Fase 5)**: hoy no tiene ruta para entrar al schema del tenant. |
| **Defensa profunda** | Si por alguna razón un request llega al controller sin tenant montado (no debería pasar en runtime real), `ClinicSettingController::abortIfNoTenant()` devuelve 404 antes de tocar la BD. |

### 5. Archivos creados

```text
app/Models/ClinicSetting.php
app/Http/Controllers/ClinicSettingController.php
app/Http/Requests/ClinicSettingRequest.php
resources/js/pages/configuracion/general/index.tsx
resources/js/pages/configuracion/general/types.ts
resources/js/pages/configuracion/general/components/section-card.tsx
resources/js/pages/configuracion/general/components/logo-uploader.tsx
resources/js/lang/es/general.json
resources/js/lang/en/general.json
tests/Feature/Configuracion/ClinicSettingTest.php
```

Rutas (en `routes/web.php`, dentro del grupo con `tenant.match-user` + `force-password-change`):

```php
Route::middleware('permission:config-general.view')
    ->get('general', [ClinicSettingController::class, 'show'])
    ->name('general.show');

Route::middleware('permission:config-general.update')
    ->match(['put', 'patch'], 'general', [ClinicSettingController::class, 'update'])
    ->name('general.update');
```

### 6. UI: patrón "formulario singleton" reutilizable

El módulo introduce un patrón visual que será reutilizado por los próximos módulos de configuración (Horarios, Bloqueos, Tarifas):

- `<PageHeader>` con título + descripción dinámica (incluye el nombre comercial del tenant) + 4 `StatBadge` que resumen **estado de completitud** (Identidad / Contacto / Branding) y **estado de facturación electrónica** (Nubefact configurado o no).
- Aviso explicativo arriba del formulario: "WhatsApp y correo: incluidos en tu plan" — clarifica al `admin_clinica` que NO tiene que configurar credenciales técnicas; la plataforma se encarga.
- Cuerpo dividido en **6 `SectionCard`**: Identidad, Contacto, Branding (con `<LogoUploader>`), Operación, Recordatorios, Facturación electrónica, Remitente comercial.
- `<LogoUploader>` (nuevo, `components/logo-uploader.tsx`) — dropzone con drag&drop, preview en tiempo real, estados "archivo nuevo seleccionado" / "logo guardado" / "se quitará al guardar", validación cliente y servidor.
- `<ToggleRow>` (helper local) para los recordatorios y switches booleanos.
- Acción primaria en **footer sticky** (única, no duplicada en el header). Muestra spinner durante `processing` y un `<CheckCircle2>` con label "Guardado" durante `recentlySuccessful`.
- Envío del form: `router.post(url, payload, { forceFormData: true })` con `_method=put` para soportar el archivo del logo en `multipart/form-data`.

### 7. Permisos por rol (estado tras Fase 4 · módulo 1)

| Rol | `config-general.view` | `config-general.update` | Notas |
|---|---|---|---|
| `superadmin` | ✅ | ✅ | Vía `SuperadminSeeder` (todos los permisos). Solo podrá usarlo desde Fase 5 (impersonation). |
| `admin_clinica` | ✅ | ✅ | El dueño de la clínica configura los datos de SU clínica. |
| `veterinario` | ❌ | ❌ | Solo necesita el módulo clínico. |
| `asistente_vet` | ❌ | ❌ | — |
| `recepcionista` | ❌ | ❌ | — |
| `groomer` | ❌ | ❌ | — |

### 8. Tests automáticos

`tests/Feature/Configuracion/ClinicSettingTest.php` cubre **16 escenarios** (62 assertions):

- Autoprovisión de la fila al primer acceso con valores por defecto de la migración.
- Un empleado sin permiso (`groomer`) recibe 403 en `view` y en `update`.
- Un invitado sin login es redirigido.
- Superadmin entrando desde el host central recibe `shared/tenant-required` con status 200 OK.
- Update con datos válidos persiste todos los campos.
- "Remitente comercial visible" (email_from, email_from_nombre, whatsapp_display_number) se persiste correctamente.
- Validación: RUC ≠ 11 dígitos → error; moneda fuera de `[PEN, USD]` → error.
- Cifrado: `nubefact_token` se guarda cifrado con `Crypt::encryptString`, decodificable solo con `APP_KEY`.
- `clear_nubefact=true` borra el token y baja `nubefact_configurado`.
- Preservación: enviar `nubefact_token=''` (vacío) no toca la credencial cifrada existente.
- Logo: subida de PNG/JPG válido persiste `logo_path` en `tenants/<slug>/logos/...`.
- Logo: rechaza archivos no-imagen (PDF) con error de validación.
- Logo: `clear_logo=true` borra archivo físico del disco y limpia `logo_path`.
- Logo: reemplazar el logo elimina el archivo previo (no deja huérfanos).

Los tests requieren **PostgreSQL** porque la tabla vive en un schema tenant. En SQLite la suite se autoomite. Para las pruebas de logo se usa `Storage::fake('public')` para no escribir en el disco real.

### 9. Limitaciones conocidas (TODOs para iteraciones futuras)

- **Validación de credenciales en vivo**: el módulo solo guarda el token de Nubefact. La validación real ("¿este token es válido para este RUC?") se hará desde el módulo de Facturación cuando emita la primera boleta de prueba.
- **Horario de atención** (`horario_atencion` JSON): el campo existe en la BD pero el formulario de bloques de tiempo por día se delega al próximo módulo **Configuración › Horarios** (donde tiene UI natural).
- **BYOK (Bring Your Own Keys)**: hoy Twilio/Brevo son siempre globales del SaaS. Cuando una clínica grande quiera usar su propia cuenta Twilio (para tener su número WhatsApp aprobado por Meta a su nombre), se podrá añadir un override por tenant en `cfg_clinic_settings` que tenga prioridad sobre `platform_settings` (Fase 4+).
- **Limpieza de archivos al borrar tenant**: el path `tenants/<slug>/logos/...` queda huérfano si se elimina una clínica. Conectar el `force-delete` de tenant para borrar la carpeta es un TODO de operaciones.

### 10. Aislamiento del sidebar por contexto (central vs tenant)

A raíz de este módulo se introdujo un sistema de filtrado del sidebar por **contexto de hosting**, además del filtro por permisos:

| Contexto | Significado | Ejemplos |
|---|---|---|
| `central` | Solo visible en el host central (sin tenant resuelto). | Grupo **Plataforma** (`/plataforma/tenants`, `/plataforma/planes`…). |
| `tenant`  | Solo visible dentro de un subdominio de clínica.       | **Clínica**, **Servicios**, **Inventario**, **Caja**, **Facturación**, **Comunicaciones**, **Reportes**, **Configuración** y **Auditoría**. |
| `both` (default) | Disponible en ambos hosts.                       | **Dashboard**, **perfil**, **seguridad**. |

Implementación:

- Nuevo campo opcional `context: NavContext` en `NavItem` y `NavGroup` (`resources/js/types/navigation.ts`).
- `NavMainCollapsible` lee `page.props.tenant` (compartido por `HandleInertiaRequests`) y descarta los items/grupos cuyo contexto no aplica.
- `app-sidebar.tsx` etiqueta cada grupo con su contexto correcto.

**Excepción: el rol `superadmin`** recibe bypass total del filtro de contexto (consistente con el bypass de permisos en `usePermission`). Eso significa que el superadmin ve **todos** los grupos en cualquier host: en el host central ve los módulos tenant aunque no haya tenant resuelto. Decisión de producto: el superadmin debe tener una vista mental completa de qué módulos existen y poder inspeccionar cualquier área durante soporte, en lugar de tener un sidebar que cambia de forma según dónde esté logueado.

> Cuando el superadmin hace click en un módulo tenant desde el host central, el middleware `tenant.required` renderiza una pantalla informativa amigable (`shared/tenant-required.tsx`) con CTA hacia **Plataforma › Tenants**. No es un error — es una UX deliberada que en **Fase 5** se reemplazará por impersonation directa desde el listado de clínicas (un click "Entrar como soporte"). La pantalla **no debe importar `AppLayout`**: `resources/js/app.tsx` ya aplica `createInertiaApp({ layout: … })` con `AppLayout` por defecto; anidar otro layout duplicaba sidebar y toggle. Tampoco usa breadcrumbs: el título hero basta.

Para los roles **no superadmin** (e.g. `admin_clinica`, recepción, veterinarios) el filtro sí se aplica estrictamente: un usuario de clínica nunca verá el grupo **Plataforma** dentro de su subdominio aunque alguien le asigne por error un permiso `plataforma-*`, y un admin operativo no verá los módulos tenant fuera de su subdominio.

#### Backend complementario: middleware `tenant.required`

Para que el bypass del sidebar no genere experiencias rotas cuando el superadmin entra a un módulo tenant-only desde el host central, las rutas de esos módulos llevan el alias `tenant.required` (ver {@see \App\Http\Middleware\EnsureTenant}):

```php
Route::middleware(['tenant.required', 'permission:config-general.view'])
    ->get('general', [ClinicSettingController::class, 'show']);
```

Lógica del middleware:

| Caso | Respuesta |
|---|---|
| Hay tenant resuelto (subdominio de clínica) | Pasa, ejecuta el controller normalmente. |
| No hay tenant + usuario es `superadmin` | Renderiza `shared/tenant-required.tsx` (status **200 OK**) con explicación, CTAs hacia **Plataforma › Tenants** y al **Dashboard**. |
| No hay tenant + usuario operativo | 404 (defensa en profundidad). |

> ⚠️ El status code de la pantalla informativa es **200**, no 4xx. Inertia interpreta cualquier 4xx/5xx como error de aplicación y dispara su modal interno descartando `page.props.auth`, lo que rompía el sidebar (`NavUser`). La semántica "esta página requiere contexto adicional" se comunica vía el componente renderizado, no vía el HTTP status.

La pantalla `shared/tenant-required.tsx` es **reutilizable** para todos los módulos tenant-scoped futuros (Pacientes, Citas, Caja, Facturación, etc.). Su mensaje y diseño anticipan la **impersonation** que llegará en Fase 5: el botón "Ir a Plataforma › Tenants" será la entrada a un futuro selector "Entrar como soporte".

> Nota: el filtro del sidebar es **UX/seguridad por capas**, no la única barrera. El permiso y el middleware de tenant matching siguen aplicándose en backend; ocultar un link no autoriza nada.

---

## Módulo Plataforma › Configuración global del SaaS (Fase 4 · módulo 1.5)

> **Objetivo**: que el operador del SaaS pueda cargar de forma centralizada las **credenciales de los proveedores externos compartidos** por todas las clínicas (Twilio para WhatsApp, Brevo para correo transaccional). Las clínicas nunca tocan estas claves: la plataforma envía los mensajes en su nombre.

### 1. Modelo de datos: singleton global en `public`

A diferencia de `cfg_clinic_settings` (que es singleton **por tenant**), `public.platform_settings` es singleton **por instalación**: una sola fila en toda la BD. La migración garantiza la unicidad con el mismo truco:

```sql
CREATE UNIQUE INDEX uq_platform_settings_single_row
  ON platform_settings ((TRUE));
```

Acceso desde código:

```php
$platform = \App\Models\PlatformSetting::current();   // firstOrCreate([])
```

### 2. Campos

| Bloque | Campos |
|---|---|
| **Twilio Cloud (WhatsApp)** | `twilio_sid_enc`, `twilio_token_enc` (ambos cifrados), `twilio_default_from` (número aprobado por Meta), `twilio_configurado` (bool) |
| **Brevo (correo transaccional)** | `brevo_api_key_enc` (cifrado), `brevo_default_from_email` (verificado en Brevo), `brevo_default_from_name`, `brevo_configurado` (bool) |
| **Audit** | `updated_by_id` (FK a `public.users`), `created_at`, `updated_at` |

### 3. Acceso y autorización

| Capa | Mecanismo |
|---|---|
| **HTTP** | Rutas `/plataforma/configuracion` (GET show, PUT update) dentro del grupo `plataforma.*`. **No requieren `tenant.required`** porque la configuración es global, no por tenant; se administra desde el host central. |
| **Permisos** | `platform-settings.view` para abrir + `platform-settings.update` para guardar. Asignados **solo a `superadmin`** (vía `SuperadminSeeder`, que sincroniza todos los permisos del catálogo). |
| **Cifrado** | Mismo patrón que en `ClinicSetting`: las credenciales viajan en claro una sola vez al guardar y se cifran con `Crypt::encryptString`. Tras eso, jamás vuelven al frontend en claro. |
| **Sidebar** | El item "Plataforma › Configuración" aparece en el grupo `Plataforma` (context: `central`), oculto a todos los roles no superadmin por el filtro de permisos. |

### 4. Flujo "tres caminos" para credenciales

Igual que con Nubefact en `cfg_clinic_settings`:

1. `clear_twilio=true` (o `clear_brevo=true`) → limpia las credenciales y baja el flag `*_configurado`.
2. Credenciales nuevas (no vacías) → se cifran y se levanta el flag (Twilio requiere SID **y** Token completos; Brevo solo necesita la API key).
3. Sin ninguna de las dos → preserva el valor anterior. Útil para guardar solo el nombre del remitente sin tocar la API key.

### 5. Archivos creados

```text
app/Models/PlatformSetting.php
app/Http/Controllers/PlatformSettingController.php
app/Http/Requests/PlatformSettingRequest.php
database/migrations/2026_05_12_120000_create_platform_settings_table.php
resources/js/pages/plataforma/configuracion/index.tsx
resources/js/lang/es/platform.json
resources/js/lang/en/platform.json
tests/Feature/Plataforma/PlatformSettingTest.php
```

Rutas (en `routes/web.php`, dentro del grupo `plataforma`):

```php
Route::middleware('permission:platform-settings.view')
    ->get('configuracion', [PlatformSettingController::class, 'show'])
    ->name('configuracion.show');

Route::middleware('permission:platform-settings.update')
    ->match(['put', 'patch'], 'configuracion', [PlatformSettingController::class, 'update'])
    ->name('configuracion.update');
```

### 6. UI

Reutiliza el `<SectionCard>` del módulo Configuración General + `<StatBadge>` para mostrar el estado de cada integración. Dos cards: Twilio y Brevo, cada una con su botón "Borrar credenciales" cuando ya hay clave guardada. Footer sticky con el botón "Guardar cambios".

### 7. Tests automáticos

`tests/Feature/Plataforma/PlatformSettingTest.php` cubre **8 escenarios** (29 assertions):

- Autoprovisión de la fila singleton al primer acceso del superadmin.
- `admin_clinica` recibe 403 al intentar acceder (no tiene el permiso global).
- Invitado sin login es redirigido.
- Superadmin guarda credenciales Twilio cifradas con `Crypt::encryptString` y marca `twilio_configurado=true`.
- Superadmin guarda credenciales Brevo igual.
- Validación: número WhatsApp con formato inválido → error en `twilio_default_from`.
- `clear_twilio=true` borra credenciales y baja el flag.
- Preservación on-the-fly: re-guardar sin enviar la API key no la pisa.

### 8. Cómo lo verán las clínicas

**Nunca verán esta pantalla**. En `Configuración › General` el cliente ve un aviso explícito:

> **WhatsApp y correo: incluidos en tu plan.** El envío de mensajes y correos transaccionales lo gestiona la plataforma con sus propias credenciales. Tú no necesitas configurar nada técnico: solo elige el nombre y los datos de contacto que verán tus clientes.

Y bajo el bloque "Remitente comercial" puede personalizar:

- `email_from` → Reply-To que aparecerá si un destinatario responde a un correo automático.
- `email_from_nombre` → Nombre del remitente visible en la bandeja del cliente final ("Clínica San Patricio").
- `whatsapp_display_number` → Número que se incluye en el cuerpo del mensaje como contacto de la clínica.

Esto cubre el 99% de casos. Si más adelante una clínica grande necesita usar SU PROPIA cuenta Twilio/Brevo (BYOK), se podrá añadir override por tenant en una fase futura sin romper compatibilidad.

---

## Convenciones compartidas

Todos los módulos del panel siguen el mismo patrón visual y técnico:

### Backend

| Convención | Implementación |
|---|---|
| `FormRequest` por módulo | `app/Http/Requests/<Modulo>Request.php` |
| Exportación XLSX por módulo | `app/Exports/<Modulo>XlsxExport.php` (StreamedResponse) |
| Controller con `index`, `store`, `update`, `destroy`, `bulkDestroy`, `export` | + acciones especializadas si aplica |
| Permisos: 1 por verbo HTTP | `Route::middleware('permission:modulo.verbo')` |
| Cada modelo crítico: `HasUuids` + `SoftDeletes` cuando hay borrado lógico | `App\Models\*` |

### Frontend (`resources/js/pages/<modulo>/`)

```
<modulo>/
├── index.tsx          ← Página principal con PageHeader, DataTable, state machine
├── types.ts           ← Tipos TypeScript del módulo
└── components/
    ├── <X>-form-modal.tsx      ← Create/edit en mismo modal
    ├── <X>-row-actions.tsx     ← Dropdown de acciones por fila
    ├── <X>-delete-dialog.tsx   ← Confirmación individual
    └── <X>-bulk-delete-dialog.tsx  ← Confirmación masiva
```

Componentes compartidos viven en `resources/js/components/data-page/`:
- `PageHeader` (con stats)
- `DataTable` (búsqueda, sort, paginación, filtros, bulk)
- `FormModal`, `BulkActionBar`, `EmptyState`, `FilterChips`

### Internacionalización

- 1 namespace JSON por módulo: `resources/js/lang/{es,en}/<modulo>.json`
- Registro en `resources/js/lib/i18n.ts`
- Convención de claves: `<modulo>.<seccion>.<elemento>`

### Diseño "Verde Bosque Clínico"

Paleta y tokens definidos en `tailwind.config.ts`. Todos los módulos los reutilizan sin overrides locales.

---

## Módulo Inventario del tenant (mayo 2026)

> Alcance: menú **Inventario** del panel en host de clínica (`<slug>.localhost:8000`). Datos en el **schema PostgreSQL del tenant**.

### Implementado y usable

| Área | Ruta / página | Backend principal | Notas |
|------|----------------|-------------------|--------|
| Categorías | `/inventario/categorias` | `CategoriaInventarioController` | CRUD, permisos `categorias-inventario.*` |
| Unidades de medida | `/inventario/unidades-medida` | `UnidadMedidaInventarioController` | Catálogo por tenant + unidades de sistema |
| Productos | `/inventario/productos` | `ProductoInventarioController` | CRUD, SKU, precio, categoría, unidad, flags medicamento/activo; campo **`stock_minimo`** (decimal opcional) enlazado a alertas; formulario: **precio de venta** y **stock mínimo** en rejilla de dos columnas desde breakpoint `sm` |
| Stock por sede | `/inventario/stock` | `StockInventarioController` | Listado por sede, stats, ajuste de existencia (`stock.adjust`) |
| Movimientos | `/inventario/movimientos` | `MovimientoInventarioController` | Alta (entrada / salida / merma / ajuste). Filtros: sede, tipo, **rango de fechas** (`creado_desde` / `creado_hasta`, mes actual por defecto). Listado paginado; **export XLSX** `GET inventario/movimientos/export` con permiso **`movimientos-stock.export`** (botón en **cabecera** junto a «Registrar movimiento»). Notas en kardex con texto legible (`notas_vista`). Tabla desktop con `DataTable` **`tableLayoutFixed`** en esta página para evitar solapamiento entre columnas **Usuario** y **Notas**. |
| Compras | `/inventario/compras` | `CompraInventarioController` | Registro de compras con líneas (entrada de stock por producto), proveedor combobox, factura opcional, filtros sede / proveedor / **fecha documento** (`fecha_desde` / `fecha_hasta`). **Export XLSX** `GET inventario/compras/export`: mismos filtros que el listado; permiso de ruta y UI alineados con **`compras.view`**. **Anular compra** (`DELETE`, `compras.delete`): marca `anulada_at` y revierte stock con movimientos de salida. Migraciones tenant **`t058`** (`compras`, `compra_lineas`, `compra_id` en movimientos) y **`t059`** (anulación + índices). |
| Alertas de stock | `/inventario/alertas` | `AlertaStockInventarioController::alertas` | Listado real (no placeholder): productos activos en riesgo por sede. Con **`stock_minimo` definido** y cantidad **≤ mínimo** (incluye stock **0**) → tipo **bajo mínimo** (ámbar). Sin mínimo útil y cantidad **≤ 0** → **agotado** (rojo). Filtros `tipo_alerta`, sede, búsqueda, ordenación |
| Proveedores | `/inventario/proveedores` | `ProveedorInventarioController` | CRUD maestro; consulta **RUC** vía **apiperu.dev** (`APIPERU_BASE_URL`, `APIPERU_TOKEN` en `.env` del servidor, `Authorization: Bearer`). Endpoint `GET inventario/proveedores/consulta-ruc?ruc=`. Permisos `proveedores.*`. Migración tenant `proveedores` (`t057`). |

**Detalles técnicos**

- Exportaciones: `App\Exports\MovimientosInventarioXlsxExport` y `App\Exports\ComprasInventarioXlsxExport` (streaming PhpSpreadsheet).
- Columna tenant `productos.stock_minimo` (migración materializada en `TenantSchemaMigrator` cuando aplique, p. ej. prefijo `t056`). Tras desplegar código nuevo, ejecutar migraciones tenant en cada clínica (`php artisan vetsaas:tenant-migrate <slug>` o el comando masivo que use el proyecto); incluir **`t058`/`t059`** si aún no existen tablas de compras.
- Tabla **`proveedores`** (`t057`): RUC único, razón social, datos SUNAT opcionales, contacto, soft deletes.
- Rutas TypeScript generadas con **Laravel Wayfinder**. En `resources/js/routes/inventario/movimientos/index.ts` (y en **compras**) la acción de export se nombra **`exportMethod`** (evita la palabra reservada `export`); las páginas importan el **named export** `exportMethod` desde `@/routes/inventario/movimientos` y `@/routes/inventario/compras` respectivamente.
- Filtro de rango de fechas reutiliza **`AtencionDateRangeFilter`** (`resources/js/pages/clinica/historias-clinicas/components/atencion-date-range-filter.tsx`) con namespaces i18n `movimientos-inventario` y `compras-inventario` (`date_filter.*`). En compras se usa **`triggerClassName`** para alinear la altura del disparador con otros filtros (`h-10`).

### Pendiente u omitido a propósito

| Tema | Estado |
|------|--------|
| **Compras — edición in-place del documento** | No implementado; flujo actual: anular + registrar compra nueva (valorar impacto en costos/stock antes de permitir edición). |
| **Punto de venta (POS)** | Implementado en **Caja › Ventas**: productos, cobro desde **pre-cuenta** (consulta), **internamiento**, **grooming** y **hotel/guardería**; líneas servicio con precio editable en carrito cuando aplica; ver [Módulo Caja](#módulo-caja-del-tenant-mayo-2026), [Grooming](#módulo-servicios--grooming-jun-2026) y [Hotel / guardería](#módulo-servicios--hotel--guardería-jun-2026). |
| **Lotes y fechas de vencimiento (FEFO)** | **Hecho (jul. 2026)**: tablas `producto_lotes` (**t108**), `fefo_grupo_id` en movimientos (**t109**), `InventarioLoteService::descontarFefo` / reversión por grupo; compra con lote/vencimiento por línea; UI de lotes en **Stock**, **Productos** y detalle de **Compras**; alertas de vencimiento en Inventario › Alertas (modo lotes). |
| **Traslados entre sedes** | Los movimientos actuales no modelan transferencia interna sede ↔ sede como flujo dedicado (valorar si se añade tipo + validación de stock en origen/destino). |
| **Tests Pest del módulo** | Parcial: `InventarioLoteFefoPlanTest`, `InventarioMovimientoCompraTest`. Ampliar exports/filtros de alertas según necesidad. |
| **Feature gating por plan** | Cuando exista Fase 3.5, enlazar `modulo_inventario` (u homónimo en `PlanFeatures`) a middleware/policies del menú Inventario. |

> **Referencia en roadmap genérico**: la tabla [§ 4.4 Bloque OPERACIONES](#44-bloque-operaciones) sigue describiendo la visión de producto; las secciones [Módulo Inventario del tenant (mayo 2026)](#módulo-inventario-del-tenant-mayo-2026) y [Módulo Caja del tenant (mayo 2026)](#módulo-caja-del-tenant-mayo-2026) concretan qué está construido hoy en código.

---

## Módulo Caja del tenant (mayo 2026)

> **Alcance**: menú **Caja** del panel en host de clínica (`<slug>.localhost:8000`). Datos en el **schema PostgreSQL del tenant**. Turno de caja, registro de ventas con productos y tickets térmicos según **Configuración › General** (`ticket_ancho_mm`: 58 o 80 mm).

### Implementado y usable

| Área | Ruta / página | Backend principal | Notas |
|------|----------------|-------------------|--------|
| Sesiones | `/caja/sesiones` | `CajaSesionController` | Apertura por sede + efectivo inicial; **una sesión abierta por sede**; solo quien abrió puede cerrar. Cierre con efectivo contado y notas. Permisos `caja-sesiones.view`, `open`, `close`. Migración tenant **`t071`** (`caja_sesiones`). |
| Ventas — listado | `/caja/ventas` | `VentaController::index` | Historial paginado: número, cliente, sede, total, estado, `fel_estado`. Filtros búsqueda y estado. Permiso `ventas.view`. |
| Ventas — nueva | `/caja/ventas/nuevo` | `VentaController::create`, `store` | Requiere **sesión de caja abierta del usuario actual**. **Búsqueda** solo añade **productos** de inventario (nombre/SKU/código de barras, stock por sede). Cliente (propietario) y paciente opcional. Métodos de pago: efectivo (monto recibido + vuelto), yape, plin, tarjeta, transferencia. Totales con IGV según `cfg_clinic_settings`. Líneas **servicio** llegan por **prefill** (desde consulta, internamiento, grooming o hotel), no por el buscador. Permiso `ventas.create`. |
| Ventas — desde pre-cuenta | `/caja/ventas/desde-consulta/{consulta}` | `VentaController::createDesdeConsulta` | Precarga líneas de `consulta_cargos` **confirmado** (productos + servicios, precios de la pre-cuenta). Vincula `consulta_id`, `consulta_cargo_id` y actualiza `consulta_cargos.venta_id` al guardar. Permisos `ventas.create` + `consulta-cargos.cobrar`. Botón **Cobrar en caja** en Clínica › Historias › Cargos. |
| Ventas — desde internamiento | `/caja/ventas/desde-internamiento/{internamiento}` | `VentaController::createDesdeInternamiento` | Precarga desde cargo de internamiento confirmado (análogo a consulta). Permisos `ventas.create` + `consulta-cargos.cobrar`. |
| Ventas — desde grooming | `/caja/ventas/desde-grooming/{grooming_turno}` | `VentaController::createDesdeGrooming` | Sin pantalla intermedia de pre-cuenta: una línea de **servicio** con concepto según tipo de baño/corte (`GroomingTurno::descripcionParaVenta()`), precio lista **0** hasta que el cajero lo ingrese en el carrito (columna **Precio unit.** editable solo si la línea no tiene `producto_id`). Requiere turno **`completada`**, `venta_id` null, paciente con propietario y sesión de caja abierta. Permisos **`ventas.create` + `grooming.view`**. Tras `store`, `VentaCheckoutService` persiste `grooming_turnos.venta_id` (bloqueo `lockForUpdate` anti doble cobro). Ver [Módulo Servicios › Grooming](#módulo-servicios--grooming-jun-2026). |
| Ventas — desde hotel | `/caja/ventas/desde-hotel/{hotel_estancia}` | `VentaController::createDesdeHotel` | Una línea de **servicio**: concepto desde `HotelEstancia::descripcionParaVenta()`, **cantidad** = noches sugeridas (ingreso/egreso), **precio unitario** = tarifa activa por `tipo_estancia` en `hotel_estancia_tarifas` (**t087**) o `0.00` si no hay tarifa. Requiere estancia **`completada`**, `venta_id` null, paciente con propietario y sesión de caja abierta del usuario. Permisos **`ventas.create` + `hotel.view`**. Tras `store`, `hotel_estancias.venta_id`. Ver [Módulo Servicios › Hotel / guardería](#módulo-servicios--hotel--guardería-jun-2026). |
| Ventas — detalle | `/caja/ventas/{venta}` | `VentaController::show` | Líneas, resumen de cobro, estado FEL. Enlace a la consulta si la venta proviene de pre-cuenta. Botón **Ver ticket** (modal + iframe). Si `ventas.delete` y estado pagado: **Anular venta** (motivo + reversión stock + FEL Lucode). |
| Ticket de venta | `GET /caja/ventas/{venta}/ticket` | `VentaController::ticket` | Vista Blade `caja/venta-ticket` para impresora térmica (ancho según config). Documento interno de caja; el CPE SUNAT es aparte (FEL). Query `?print=1` dispara `window.print()` al cargar. |
| Anulación | `POST /caja/ventas/{venta}/anular` | `VentaAnulacionService` | Solo ventas **pagadas**. Revierte salidas de inventario por `venta_id` (grupo FEFO si aplica). Libera vínculos cargo/grooming/hotel. Si CPE **emitido**: baja Lucode (`/voided` factura o `/daily-summary` boleta); si falla → **nota de crédito** (requiere serie NC activa en la sede). Permiso `ventas.delete`. Tests: `VentaAnulacionTest`. |
| Checkout | — | `VentaCheckoutService` | Transacción: correlativo `VTA-{año}-{#####}`, venta **`pagado`**, líneas con snapshot, **salida FEFO** en líneas con `producto_id` (si el cargo ya descontó stock, vincula `venta_id` al grupo FEFO sin doble descuento). Prefills consulta/internamiento/grooming/hotel. Si plan + Lucode configurado + tipo boleta/factura: emite CPE vía `FelEmisionVentaService` / `EmitirFelVentaJob`. |

**Migraciones tenant**

| Prefijo | Tablas / cambio |
|---------|------------------|
| **`t071`** | `caja_sesiones` |
| **`t073`** | `ventas`, `venta_lineas` |
| **`t074`** | `ventas.consulta_id`, `ventas.consulta_cargo_id`; `venta_lineas.producto_id` nullable; `tipo_linea`, `consulta_cargo_linea_id`; enlace `consulta_cargos.venta_id` |
| **`t082`** | `grooming_turnos.venta_id` (FK nullable a `ventas`, `nullOnDelete`) — materializado en `TenantSchemaMigrator` cuando exista la columna. |
| **`t084`**–**`t087`** | **Hotel / guardería**: **`t084`** `hotel_estancias` (+ `venta_id` → `ventas`), **`t085`** `hotel_estancia_diarios` (bitácora por día), **`t086`** `consulta_cargos.hotel_estancia_id` (opcional; XOR con otros orígenes de cargo), **`t087`** `hotel_estancia_tarifas` (precio lista por noche por `tipo_estancia`). Ver [Hotel / guardería](#módulo-servicios--hotel--guardería-jun-2026). |

**Permisos** (seeder `PermissionsSeeder` / `TenantRolesSeeder`): `caja-sesiones.*`, `ventas.view`, `ventas.create`, `ventas.delete` (**anular** venta), `consulta-cargos.cobrar` (cobro desde **pre-cuenta clínica / internamiento**), `grooming.view` (checkout desde grooming), `hotel.view` / `hotel.update`. `ventas.update` definido para admin; sin UI de edición de venta (flujo: anular + nueva).

**Detalles técnicos**

- Rutas bajo prefijo `caja.` en `routes/web.php`; TypeScript vía **Laravel Wayfinder** (`resources/js/routes/caja/`).
- i18n: `lang/{es,en}/caja.php` (validación, flash, textos de ticket Blade) y `resources/js/lang/{es,en}/caja.json` (UI Inertia).
- Pantallas: `resources/js/pages/caja/sesiones/`, `resources/js/pages/caja/ventas/` (`index`, `create`, `show`, `venta-pricing.ts`, `components/pos-panel.tsx`).

### Placeholder (menú visible, sin lógica de negocio)

| Área | Ruta | Estado |
|------|------|--------|
| Pagos | `/caja/pagos` | `PlaceholderPage` — cobranzas parciales / saldo pendiente (visión). |
| Descuentos | `/caja/descuentos` | `PlaceholderPage` — cupones y promociones (visión). |

### Pendiente u omitido a propósito

| Tema | Estado |
|------|--------|
| **Vínculo consulta ↔ venta** | Implementado (jun. 2026): migración **t074**, ruta `ventas/desde-consulta/{consulta}`, `VentaDesdeCargoPrefill`, UI en cargos y POS. |
| **Líneas de servicio en venta** | Soportadas al cobrar desde pre-cuenta, internamiento, grooming o hotel. Alta libre solo-servicio sin prefill **no** está en el MVP. |
| **Facturación electrónica (emisión + anulación)** | **Hecho (jul. 2026)**: emisor **Lucode / APISUNAT v3** (`ApisunatClient`, `FelEmisionVentaService`, `EmitirFelVentaJob`). Config en Configuración › General (`apisunat_*`). Anulación: `FelAnulacionComprobanteService` (baja factura / resumen boleta) + `FelNotaCreditoComprobanteService` (fallback; requiere serie NC). `NubefactClient` queda residual/legacy. Placeholders de menú Facturación (resúmenes agregados) pueden seguir sin UI completa. |
| **Pagos parciales** | Modelo admite `pendiente` / `parcial`; checkout actual siempre **`pagado`**. `/caja/pagos` = placeholder. |
| **Editar venta** | No; anular + registrar de nuevo. |
| **Arqueos de caja** | Visión en § 4.4; el cierre de sesión cubre efectivo contado. |
| **Tests Pest del módulo** | Parcial: `VentaDesdeConsultaCargoTest`, `VentaAnulacionTest`, `FelEmisionVentaTest` (actualizar fakes a Lucode si aún citan Nubefact), `HotelEstanciaServiciosTest`. Requieren **PostgreSQL**. |

### Cómo validar FEL con Lucode (APISUNAT)

1. **Staging / producción**: Configuración › General → token Lucode + modo sandbox/producción + «emite comprobantes»; series B001/F001 (y FC01/BC01 para NC) en Facturación › Series.
2. **Tests**: `Http::fake()` contra `sandbox.apisunat.pe/api/v3/*` en `VentaAnulacionTest` / emisión. Ejemplo: `DB_CONNECTION=pgsql DB_DATABASE=vetsaas_test php artisan test tests/Feature/Caja/VentaAnulacionTest.php`.
3. **Anulación**: boleta → resumen diario; factura → comunicación de baja; si falla → NC (mensaje claro si falta serie de nota de crédito).

> **Referencia cruzada**: ticket de **pre-cuenta clínica** en Clínica › Historias › Cargos (`ConsultaCargoController::ticket`). Independiente del ticket de **venta en caja**.

---

## Módulo Servicios › Grooming (jun. 2026)

> **Alcance**: menú **Servicios › Grooming** en host de tenant. Agenda de turnos (paciente, responsable, sede, inicio, duración, estado, tipo de servicio del catálogo). Datos en el **schema PostgreSQL del tenant**.

### Implementado y usable

| Área | Ruta / página | Backend principal | Notas |
|------|----------------|-------------------|--------|
| Listado y CRUD turnos | `/servicios/grooming` | `GroomingTurnoController` | Filtro por rango de fechas (inicio del turno), búsqueda, ordenación, paginación, modal crear/editar, eliminar. Estados: programada, confirmada, en proceso, completada, cancelada, no asistió. Permisos `grooming.*`. |
| Cobro en caja | Enlace **Cobrar** en menú de fila (solo si estado **`completada`**, `venta_id` null y paciente con **propietario**) | `GET /caja/ventas/desde-grooming/{grooming_turno}` | No hay pantalla de “cargo y pre-cuenta” exclusiva de grooming: se abre **Caja › Nueva venta** con línea de servicio precargada. Ver fila homónima en [Módulo Caja](#módulo-caja-del-tenant-mayo-2026). |

### Migraciones tenant (grooming y vínculos)

| Prefijo | Archivo / cambio |
|---------|------------------|
| **t079** | `grooming_turnos` (tabla base). |
| **t080** | `grooming_turnos.servicio_detalle` (texto para tipo “otro / personalizado”). |
| **t081** | `consulta_cargos.grooming_turno_id` (nullable) — enlace opcional de un **cargo clínico** a un turno; el flujo principal de cobro grooming **ya no** pasa por una UI de cargos dedicada. |
| **t082** | `grooming_turnos.venta_id` (FK nullable a `ventas`) — marca turno ya cobrado; evita doble registro en checkout. |
| **t083** | `grooming_servicio_tarifas` — precio lista sugerido por `servicio` (slug del catálogo); usado en **`VentaDesdeCargoPrefill::buildFromGrooming`** si la fila está **`activo`**. |

`TenantSchemaMigrator::tenantMigrationIsMaterialized()` reconoce **t079–t083** según tablas/columnas esperadas.

### Detalles técnicos

- Modelo **`App\Models\GroomingTurno`**: `descripcionParaVenta()` para concepto en línea de venta; relación `venta()`; sin relación activa `cargo()` en el modelo (listados no eager-load `cargo`).
- Modelo **`App\Models\GroomingServicioTarifa`**: tarifa por slug de servicio (**t083**); opcional para prefill de precio en caja.
- Prefill: **`App\Support\Venta\VentaDesdeCargoPrefill::buildFromGrooming`** (sesión caja del usuario, validaciones i18n bajo `caja.ventas.grooming.*`; precio lista desde tarifa activa o `0.00`).
- Request **`StoreVentaRequest`**: `grooming_turno_id` opcional + validación de coherencia (paciente, estado, `venta_id` null).
- Frontend: `resources/js/pages/servicios/grooming/`; carrito de ventas: `resources/js/pages/caja/ventas/create.tsx` (columna precio unitario editable si `producto_id === null`).

### Pendiente u omitido a propósito

| Tema | Estado |
|------|--------|
| **Tarifario grooming (BD + UI)** | **Hecho**: **t083** + CRUD en **Configuración › Tarifas** (`TarifaServiciosController`). Insumos por servicio: `GroomingInsumoController` + modal (`t106`). Prefill de precio en caja desde tarifa activa. |
| **Catálogo “servicios” genérico en POS** | Alta libre de líneas solo-servicio **sin** prefill no está en el MVP. |
| **Tests Pest** (grooming + venta con `grooming_turno_id`) | Pendiente / parcial. |
| **Migraciones tenant siguientes** | **`t084`–`t087`** hotel; **`t108`–`t109`** lotes FEFO. |

---

## Módulo Servicios › Hotel / guardería (jun. 2026)

> **Alcance**: menú **Servicios › Hotel / guardería** en host de tenant. Estancias (paciente, ingreso, egreso, tipo del catálogo, estado, sede/responsable opcional). **Bitácora diaria** (`hotel_estancia_diarios`): una fila por fecha por estancia; UI en modal desde el menú de fila (**Bitácora diaria**). Cobro en **Caja** con prefill por noches × tarifa (**`hotel_estancia_tarifas`**, precio por noche). Datos en el **schema PostgreSQL del tenant**.

### Implementado y usable

| Área | Ruta / página | Backend principal | Notas |
|------|----------------|-------------------|--------|
| Listado y CRUD estancias | `/servicios/hotel` | `HotelEstanciaController` | Filtro por rango (ingreso), búsqueda, ordenación, modal crear/editar, eliminar (soft delete). Estados: programada, confirmada, en_estancia, completada, cancelada, no_presento. Permisos `hotel.*`. |
| Bitácora (JSON + modal) | `GET/POST /servicios/hotel/{hotel_estancia}/diarios`, `DELETE …/diarios/{hotel_estancia_diario}` | `HotelEstanciaController::diariosIndex`, `diariosStore`, `diariosDestroy` | Listado y alta requieren `hotel.view` / `hotel.update` según acción; borrado `hotel.update`. Validación: fecha única por estancia. |
| Cobro en caja | Enlace **Cobrar** en menú de fila (solo si **`completada`**, `venta_id` null y paciente con **propietario**) | `GET /caja/ventas/desde-hotel/{hotel_estancia}` | **`VentaDesdeCargoPrefill::buildFromHotelEstancia`**: noches sugeridas + tarifa activa por `tipo_estancia`. Permisos **`ventas.create` + `hotel.view`**. |

### Migraciones tenant (**t084**–**t087**)

| Prefijo | Tablas / cambio |
|---------|----------------|
| **t084** | `hotel_estancias` (+ `venta_id` → `ventas`). |
| **t085** | `hotel_estancia_diarios` (`fecha` + `notas`, único `(hotel_estancia_id, fecha)`). |
| **t086** | `consulta_cargos.hotel_estancia_id` (opcional; exclusión XOR con consulta / internamiento / grooming). |
| **t087** | `hotel_estancia_tarifas` (`tipo_estancia` único, `precio_lista` por noche, `moneda`, `activo`). |

`TenantSchemaMigrator` materializa estas migraciones cuando las tablas/columnas esperadas existen.

### Detalles técnicos

- Modelos: **`HotelEstancia`**, **`HotelEstanciaDiario`**, **`HotelEstanciaTarifa`**.
- Prefill caja: **`VentaDesdeCargoPrefill::buildFromHotelEstancia`** (sesión abierta del usuario; mensajes bajo `caja.ventas.hotel.*`).
- Frontend: `resources/js/pages/servicios/hotel/` (modal **Bitácora diaria**, Wayfinder `resources/js/routes/servicios/hotel/diarios/`).
- Tests: **`tests/Feature/Servicios/HotelEstanciaServiciosTest.php`** (PostgreSQL + `vetsaas:tenant-migrate`).

### Pendiente u omitido a propósito

| Tema | Estado |
|------|--------|
| **CRUD tarifario en panel** | **Hecho**: misma pantalla **Configuración › Tarifas** (tab hotel) + rutas `tarifas/hotel/*`. |
| **Tests grooming-only + venta** | Sigue en roadmap; hotel tiene cobertura mínima en **`HotelEstanciaServiciosTest`**. |

---

## Módulo clínico del tenant: historias, plan, vacunas e historial del paciente (mayo 2026)

> **Alcance**: menú **Clínica** del panel en host de tenant (`<slug>.localhost:8000`): **Propietarios**, **Pacientes**, **Historias clínicas** (visitas SOAP), **Plan de tratamiento** por consulta, **Vacunaciones**, **Recetas**, **Laboratorio**, **Cirugías**, **Hospitalización** (internamientos + evolución + cargos; ver [§ 4.5](#45-bloque-clínico-avanzado)), e **Historial clínico del paciente** (línea de tiempo + resúmenes). Datos en el **schema PostgreSQL del tenant**.

### Migración tenant `t064`

- **Archivo**: `database/migrations/tenant/2026_05_23_120000_t064_consulta_vitales_cierre_vacuna_consulta.php`
- **Tabla `consultas`**: columnas `temperatura_c`, `fc_lpm`, `fr_rpm`, `cerrada_at` (timestamp nullable), `cerrada_por_id` (FK `users`, nullable). *Backfill*: todas las consultas existentes reciben `cerrada_at = COALESCE(updated_at, created_at)` para no dejar visitas “sin estado” en despliegues sobre datos reales.
- **Tabla `vacunas_aplicadas`**: `consulta_id` (UUID nullable, FK `consultas` con `nullOnDelete`) + índice por `consulta_id` y `aplicada_at`.
- **Materialización en legado**: `App\Tenancy\TenantSchemaMigrator::tenantMigrationIsMaterialized()` reconoce **t064** (columnas `cerrada_at` en `consultas` y `consulta_id` en `vacunas_aplicadas`) para schemas ya poblados sin fila en `migrations` del tenant.

**Operación**: tras desplegar código, ejecutar migraciones tenant en cada clínica (`php artisan vetsaas:tenant-migrate <slug>` o el flujo masivo que use el proyecto).

### Migraciones tenant `t066`–`t068` (recetas, laboratorio, cirugías)

| Migración | Tablas principales | Notas |
|-----------|-------------------|--------|
| **t066** — `2026_05_25_100000_t066_create_recetas_tables.php` | `recetas`, `recetas_lineas` | FK opcional `consulta_id` → `consultas`; `paciente_id`, `veterinario_id`, `sede_id`, `emitida_at`, `estado` (borrador / emitida / anulada). |
| **t067** — `2026_05_26_100000_t067_create_pedidos_laboratorio_tables.php` | `pedidos_laboratorio`, `pedidos_laboratorio_lineas` | Mismo patrón de vínculo opcional a consulta; `solicitado_at`, `estado`, destino y líneas de examen. |
| **t068** — `2026_05_27_100000_t068_create_cirugias_table.php` | `cirugias` | `programada_at`, `nombre_procedimiento`, `tipo_anestesia`, `estado`, FK opcional a consulta. |

### Modelos y validación

- **`App\Models\Consulta`**: vitales y cierre; relaciones `cerradaPor()`, `vacunasAplicadas()`, **`recetas()`**, **`pedidosLaboratorio()`**, **`cirugias()`** (además de `planTratamiento()`).
- **`App\Models\VacunaAplicada`**: `consulta_id`; relación `consulta()`.
- **`App\Models\Receta`** / **`RecetaLinea`**: prescripción por paciente; vínculo opcional a consulta; líneas con medicamento (producto o texto).
- **`App\Models\PedidoLaboratorio`** / **`PedidoLaboratorioLinea`**: pedido de análisis; `consulta_id` opcional.
- **`App\Models\Cirugia`**: procedimiento programado; `consulta_id` opcional.
- **Form requests**: reglas para vitales en consulta; en vacunas, `consulta_id` nullable con validación de paciente coherente; en **alta** no se permite vincular a consulta **cerrada**; en **update** se tolera mantener la misma consulta aunque esté cerrada.
- **Traducciones PHP**: `lang/{es,en}/historias-clinicas.php`, `lang/{es,en}/vacunaciones.php`, `lang/{es,en}/recetas.php`, `lang/{es,en}/laboratorio.php`, `lang/{es,en}/cirugia.php` (mensajes de validación y flashes, p. ej. plan bloqueado por consulta cerrada).

### Controladores y reglas de negocio

| Área | Comportamiento |
|------|----------------|
| **Historias clínicas** (`ConsultaHistoriaController`) | Alta de consulta en estado **abierto** (`cerrada_at` null). **Update** bloqueado si ya está cerrada. Endpoints **`cerrar`** / **`reabrir`** (POST). Prefill modal nueva consulta desde `?nuevo_para_paciente=`. Prop Inertia **`consulta_abrir_editar`** cuando `?editar_consulta=` (abre modal en el listado; requiere permiso de actualización de historias). |
| **Plan de tratamiento** (`ConsultaPlanTratamientoController`) | **Upsert** del plan y **alta de seguimiento** rechazados si la consulta está cerrada (mensaje coherente en sesión). |
| **Vacunaciones** (`VacunacionController`) | Listado con `consulta` en eager load. **`vacuna_prefill`** desde query (`prefill_paciente_id`, `prefill_consulta_id` validado: paciente activo, consulta del mismo paciente y **abierta**). **`vacuna_abrir_editar`**: si `?editar_vacuna_aplicada=<uuid>` y el usuario puede `vacunaciones.update`, se carga el registro, se fuerza el rango de fechas del listado al **mes de `aplicada_at`** de esa fila y se devuelve el modelo para abrir el modal de edición en React (mismo patrón que `editar_consulta` en historias). |
| **Recetas** (`RecetaController`) | CRUD tenant, líneas con productos **medicamento**, PDF opcional. Listado con rango por **`emitida_at`**, búsqueda, ordenación, filtro **`estado`** solo desde **query string** (no se mezcla body POST en redirects: `listIndexQuery` con `array_intersect_key` sobre `$request->query`). Deep link **`?editar_receta=<uuid>`** + prop Inertia **`receta_abrir_editar`** (modal edición si `recetas.update`). |
| **Laboratorio** (`LaboratorioController`) | CRUD de **pedidos** con líneas de examen; mismas convenciones de filtros/redirects; deep link **`?editar_pedido_laboratorio=<uuid>`** + **`pedido_abrir_editar`**. |
| **Cirugías** (`CirugiaController`) | CRUD de cirugías con fechas y estados; mismas convenciones; deep link **`?editar_cirugia=<uuid>`** + **`cirugia_abrir_editar`**. |
| **Paciente — historial** (`PacienteController::show`) | Requiere `pacientes.view`. Construye **`timeline`** mezclando **consultas** (si `historias-clinicas.view`) y **aplicaciones** (si `vacunaciones.view`), orden global por fecha. Las consultas cargan en **`detalle.vinculos`** las **recetas**, **pedidos de laboratorio** y **cirugías** cuya **`consulta_id`** coincide con esa visita (no son ítems aparte en la línea de tiempo: se muestran dentro del resumen colapsable **“Ver resumen”**). Cada vínculo incluye URL al módulo correspondiente (rango mensual acorde + parámetro `editar_*` si el usuario puede `update` en ese módulo). Resumen SOAP/vitales y enlaces a historias / vacunaciones sin cambio. Props **`links`** y **`permisos`** para CTAs. |

### Rutas web (tenant)

- `GET /clinica/pacientes/{paciente}` — vista **Historial clínico** (`clinica.pacientes.show`).
- `POST /clinica/historias-clinicas/consultas/{consulta}/cerrar` y `POST …/reabrir` — cierre operativo de la visita (además del CRUD de consultas y del plan ya existente).
- Rutas CRUD/Inertia de **recetas**, **laboratorio** y **cirugías** bajo el prefijo `clinica/` (permisos `recetas.*`, `laboratorio.*`, `cirugias.*`); ver `routes/web.php` en el grupo tenant.

Las rutas Wayfinder generadas en `resources/js/routes/clinica/…` incluyen `cerrar`, `reabrir` y `pacientes.show` donde corresponda.

### Frontend (páginas y piezas clave)

| Pieza | Ubicación / notas |
|------|-------------------|
| Historial del paciente | `resources/js/pages/clinica/pacientes/show.tsx` — rail temporal, badges **Abierta** / **Cerrada**, resumen colapsable (SOAP S/O/A/P + constantes o detalle de aplicación), bloque **“Vinculados a esta consulta”** (recetas / laboratorio / cirugías con permiso de vista), botones **Abrir consulta completa** / **Abrir registro completo**. i18n cruzado `pacientes` + `recetas` / `laboratorio` / `cirugia` para etiquetas de estado en ese bloque. |
| Recetas (listado) | `resources/js/pages/clinica/recetas/index.tsx` + modales / acciones — filtro **estado** + rango **`receta_desde` / `receta_hasta`** (`emitida_at`); efecto `receta_abrir_editar`. |
| Laboratorio (listado) | `resources/js/pages/clinica/laboratorio/index.tsx` — análogo; **`pedido_abrir_editar`**. |
| Cirugías (listado) | `resources/js/pages/clinica/cirugias/index.tsx` — análogo; **`cirugia_abrir_editar`**. |
| Listado + modal consulta | `resources/js/pages/clinica/historias-clinicas/index.tsx`, `components/consulta-form-modal.tsx` — vitales, cerrar/reabrir en pie del modal, columna estado, efectos URL. |
| Acciones por fila | `components/consulta-row-actions.tsx` — enlace a vacunaciones con `prefill_paciente_id` + `prefill_consulta_id` si la consulta está abierta y hay permiso de crear vacunas. |
| Plan por visita | `plan-tratamiento.tsx` — sin edición de plan ni notas de seguimiento si `cerrada_at` presente; aviso en UI. |
| Vacunaciones | `vacunaciones/index.tsx`, `components/vacuna-form-modal.tsx` — `consulta_id` en payload; prefill; efecto `vacuna_abrir_editar`; columna **Consulta**. |
| Navegación a historial | `pacientes/index.tsx`, `pacientes/components/paciente-row-actions.tsx`, `propietarios/components/mascota-tarjeta-ficha.tsx`, `propietarios/show.tsx` — enlaces a `clinica.pacientes.show`. |
| i18n | `resources/js/lang/{es,en}/pacientes.json` (bloque `historial`, incl. `vinculos_*`), `historias-clinicas.json`, `vacunaciones.json`, `recetas.json`, `laboratorio.json`, `cirugia.json` — claves alineadas donde aplica. |

### Deep links (query string)

| Parámetro | Destino | Efecto principal |
|-----------|---------|-------------------|
| `nuevo_para_paciente=<uuid>` | Historias clínicas | Modal **nueva consulta** con paciente preseleccionado. |
| `editar_consulta=<uuid>` + `atendido_desde` / `atendido_hasta` | Historias clínicas | Modal **editar** esa visita (si hay permiso de update). |
| `prefill_paciente_id` + opcional `prefill_consulta_id` | Vacunaciones | Modal **nueva aplicación** con datos (consulta solo si abierta y del paciente). |
| `editar_vacuna_aplicada=<uuid>` | Vacunaciones | Rango de fechas al mes del registro + modal **editar** (si hay permiso update). |
| `editar_receta=<uuid>` (+ `receta_desde` / `receta_hasta` ajustados por backend) | Recetas | Modal **editar** esa receta (si `recetas.update`). |
| `editar_pedido_laboratorio=<uuid>` (+ `pedido_desde` / `pedido_hasta`) | Laboratorio | Modal **editar** ese pedido (si `laboratorio.update`). |
| `editar_cirugia=<uuid>` (+ `programada_desde` / `programada_hasta`) | Cirugías | Modal **editar** esa cirugía (si `cirugias.update`). |

### Pendientes conscientes (no bloquean el flujo actual)

- **Tests automatizados** (Pest): existe `tests/Feature/Clinica/ClinicaHistorialCoreTest.php` (cierre/reapertura de consulta, rechazo de vacuna nueva contra consulta **cerrada**, alta de vacuna con consulta **abierta**, props Inertia `detalle.vinculos` en historial del paciente). **Requieren `DB_CONNECTION=pgsql`**; con el `sqlite` por defecto de `phpunit.xml` / `.env.testing` los cuatro casos se **omiten** (`markTestSkipped`). Ejemplo: `DB_CONNECTION=pgsql DB_DATABASE=<tu_bd_test> php artisan test tests/Feature/Clinica/ClinicaHistorialCoreTest.php`. Ampliar con más casos (plan bloqueado, solo lectura, etc.) según prioridad.
- **UX permisos solo-lectura**: textos/enlaces distintos para quien tiene `historias-clinicas.view` pero no `update` (hoy el enlace “completo” puede abrir listado sin modal de edición).
- **Laboratorio — adjuntos**: subir **PDF de resultados** por pedido/línea (mencionado en visión § 4.5; **no** implementado aún).
- **Vacunaciones en el resumen de consulta**: hoy las aplicaciones siguen como **ítems propios** en la línea de tiempo; valorar anidar en el resumen de la consulta las que tengan `consulta_id` (paridad con recetas/lab/cirugía).
- **Hospitalización** (`/clinica/hospitalizacion`): **hecho** (jun. 2026): `internamientos`, `internamiento_evoluciones`, CRUD, detalle con evolución diaria (signos vitales), cargos propios (`consulta_cargos.internamiento_id` t078) y cobro desde caja (`ventas/desde-internamiento/{internamiento}`). Ver [§ 4.5](#45-bloque-clínico-avanzado).
- **Roadmap tabular legacy** (tablas `signos_vitales`, `diagnosticos`, `prescripciones` separadas del § 4.2 genérico): **no** sustituyen al modelo actual basado en `consultas` SOAP + plan + vacunas.

<a id="hueco-cobro-consulta"></a>

### Hueco de producto: precio de la consulta e importe cobrado al cliente (parcialmente cerrado — jun. 2026)

Hoy el flujo **Clínica** cubre registro clínico (SOAP, cierre, plan, vacunas, **recetas**, **laboratorio** con adjuntos de resultado, **cirugías**, historial del paciente, **PDFs** de consulta/receta/carnet/historial). **Pre-cuenta** y **Caja** enlazados (consulta, internamiento, grooming, hotel). **FEL** operativo con **Lucode (APISUNAT)** (emisión + anulación/NC). **Siguen abiertos a nivel producto**: arancel fijo por tipo de consulta en la ficha de visita, pagos parciales, y políticas UX “¿cierre clínico exige cobro?”.

**Análisis rápido (qué sigue para “cerrar” del todo el vínculo visita↔caja)**

| Necesidad | Estado actual (jun. 2026) | Dirección típica |
|-----------|---------------------------|-------------------|
| Saber “cuánto cuesta esta consulta” | No hay campo único de arancel en `consultas`; el importe vive en **pre-cuenta** o se cobra “a mano” en grooming vía POS. | Catálogo de **servicios / tarifas** por tipo de consulta o sugerencia al abrir cargos. |
| Total cobrado / vínculo a venta | **Hecho** para pre-cuenta confirmada → venta (`t074`, `consulta_cargos.venta_id`). **Hecho** para internamiento con cargo confirmado. **Hecho** para grooming completado → `venta_id` en turno. | FEL + reportes; opcional: mostrar `venta` desde ficha consulta/turno. |
| Conciliación con FEL | Emisión + anulación Lucode desde venta. | Resúmenes diarios UI agregada / notas-baja menú Facturación (placeholders). |

**Opciones de diseño** (no excluyentes; conviene elegir una como MVP):

1. **Mínimo**: columnas en `consultas` (`importe_estimado`, `moneda`, opcional `notas_cobro`) — rápido pero no sustituye factura ni caja.
2. **Recomendado para escalar**: entidad **`cargo_consulta`** o **`venta_linea`** con `consulta_id`, cantidad, precio unitario, tipo (servicio/producto), enlace opcional a `movimiento_inventario` / producto — permite totalizar y luego enlazar a **comprobante**.
3. **Integración fuerte**: toda visita genera o adjunta un **documento de venta** (POS) y la consulta solo referencia `venta_id` — coherente con § 4.4 pero mayor esfuerzo.

**Dependencias transversales**: permisos `consulta-cargos.*`, `ventas.*`, `caja-sesiones.*`, `grooming.view` donde aplique; **integración datos** consulta↔venta **implementada** vía t074; pendiente **catálogo servicios en POS libre**, redondeo/documentación contable, política de visibilidad de importes (recepción vs veterinario), y **FEL**.

---

## Hoja de ruta restante

### ✅ Fase 1 — Tenancy en runtime (COMPLETADA)

- `TenantManager` singleton + `SubdomainResolver` + `TenantContext` DTO ✓
- Excepciones `TenantNotFoundException`, `TenantSuspendedException` ✓
- Helpers globales (`tenant()`, `current_tenant_id()`, etc.) ✓
- Middleware `ResolveTenant`, `EnsureTenant`, `EnsureNoTenant` ✓
- `TenancyServiceProvider` registrado ✓
- 5 capas de defensa para aislamiento de datos ✓

### ✅ Fase 2 — Routing y separación por subdominio (COMPLETADA)

- `routes/tenant.php` con dominio dinámico ✓
- Renderers Inertia para `TenantNotFoundException` y `TenantSuspendedException` ✓
- Tenant compartido con Inertia (`page.props.tenant`) ✓
- Landing pública del tenant (`tenant/welcome`) ✓
- 6 tests end-to-end de routing ✓

### ⚠️ Fase 2.5 — Auth con guard separado (REEMPLAZADA por 2.5-bis)

Se construyó completa (modelo `TenantUser`, guard `tenant`, login propio, sidebar propio, 8 tests) y luego se descartó porque la arquitectura "dos sistemas paralelos" duplicaba código sin valor. Se conserva el detalle en su sección histórica como registro.

### ✅ Fase 2.5-bis — Single-login + datos aislados (COMPLETADA)

- Migración `add_tenant_id_to_users` con índice único por (tenant_id, email) ✓
- Modelo `User` con relación `tenant()`, helpers `isCentral()` / `isTenantUser()` / `belongsToTenant()` ✓
- `Fortify::authenticateUsing` filtra por host (central ↔ tenant) ✓
- Middleware `MatchUserTenant` valida host ↔ user.tenant_id en cada request ✓
- `routes/web.php` accesible desde cualquier host (sin `tenant.none`) ✓
- `routes/tenant.php` reducido a la landing pública ✓
- `HandleInertiaRequests` unificado a guard `web` ✓
- `auth-split-layout` con branding contextual del tenant ✓
- `AppLayout` + `AppSidebar` únicos para todos (filtrado por permisos) ✓
- Comando `vetsaas:tenant-create-admin` reescrito (crea en `public.users` con tenant_id) ✓
- Borrados: `TenantUser`, guard `tenant`, login del tenant, sidebar del tenant, layouts del tenant ✓

### ✅ Fase 2.6 — Recuperación de contraseña + cambio obligatorio (COMPLETADA)

- Provider `tenant-eloquent` que scopea por `tenant_id` del host en `retrieveByCredentials` ✓
- Repositorio `TenantAwarePasswordTokenRepository` con índice composite `(tenant_id, email)` ✓
- Manager `TenantAwarePasswordBrokerManager` vía `$app->extend('auth.password')` ✓
- Notificación `PasswordResetLinkNotification` queueable + URL con subdominio correcto ✓
- Notificación `TenantAdminInvitationNotification` para el flujo de invitación ✓
- Middleware `EnsurePasswordIsChanged` con allowlist (form/update/logout) ✓
- Página Inertia `auth/change-password.tsx` con branding del tenant ✓
- Comando `vetsaas:tenant-create-admin` soporta modo "invitación por correo" (sin `--password`) ✓
- Migración: `users.must_change_password` + `password_reset_tokens.tenant_id` + drop email-PK ✓
- 23 tests verdes (forgot/reset contextual, must_change_password, aislamiento entre tenants) ✓

### ⏭️ Fase 3 — Provisionamiento automático (Orvae → VetSaaS)

> **Objetivo**: que cuando un cliente compre un plan en **Orvae PE**, su clínica nazca **sola** en VetSaaS — schema creado, migraciones corridas, admin sembrado, correo de bienvenida enviado — sin que nadie de soporte tenga que hacer nada manualmente.

#### 3.1 Pipeline de provisión (`App\Services\Tenancy\TenantProvisioner`)

Servicio único que orquesta los pasos. Idempotente: si algo falla a mitad, re-ejecutarlo no debe romper nada.

```php
$provisioner->provision(new ProvisionTenantPayload(
    slug:           'clinica-nueva',
    razonSocial:    'Clínica Nueva SAC',
    nombreComercial:'Clínica Nueva',
    ruc:            '20123456789',
    planCodigo:     'pro',
    adminEmail:     'admin@clinicanueva.com',
    adminNombre:    'María Quispe',
    adminTelefono:  '+51 999 999 999',
    trialDays:      14,
));
```

**Pasos secuenciales** (cada uno con su propio commit):

1. `Tenant::create(...)` en `public.tenants` (estado: `provisioning`).
2. `Subscription::create(...)` con plan + `trial_ends_at`.
3. `vetsaas:tenant-migrate <schema>` (CREATE SCHEMA + migraciones tenant).
4. Sembrar `cfg_clinic_settings` en el schema (RUC, razón social, etc.).
5. `vetsaas:tenant-create-admin` (rol `admin_clinica` + password temporal).
6. Disparar `WelcomeEmail` (con magic-link de primer login).
7. `Tenant::update(['estado' => 'active'])`.

#### 3.2 Job en cola: `ProvisionTenantJob`

Para que la respuesta HTTP del webhook sea < 200ms.

```php
ProvisionTenantJob::dispatch($payload)
    ->onQueue('provisioning')
    ->afterCommit();
```

Si falla cualquier paso, el job va a la tabla `failed_jobs` con todo el contexto y el tenant queda en estado `provisioning` → soporte ve un badge rojo en `/plataforma/tenants` y puede reintentar con un botón.

#### 3.3 Webhook de Orvae: `POST /api/orvae/webhooks`

| Header | Validación |
|---|---|
| `X-Orvae-Signature` | HMAC-SHA256 sobre el body con `ORVAE_WEBHOOK_SECRET` |
| `X-Orvae-Event` | `tenant.purchased`, `subscription.renewed`, `subscription.cancelled`, `payment.succeeded`, `payment.refunded` |

**Eventos soportados**:
- `tenant.purchased` → `ProvisionTenantJob` (crea todo desde cero).
- `subscription.renewed` → extender `current_period_end` + insertar `subscription_payments`.
- `subscription.cancelled` → marcar `cancelled`, NO borrar schema (gracia 30 días).
- `payment.succeeded` / `payment.refunded` → insertar/actualizar en `subscription_payments`.

Todo guardado en tabla `webhook_events` (idempotencia por `event_id`).

#### 3.4 Helpers para jobs y comandos

```php
TenantManager::runForSlug('clinica-rivera', function () {
    // Aquí Eloquent ya opera contra el schema de clinica-rivera.
    return Paciente::where('especie', 'canino')->count();
});

TenantManager::runForEach(function (TenantContext $ctx) {
    Artisan::call('reportes:generar-mensual');
});
```

Útil para reportes nocturnos, recordatorios masivos, etc.

#### 3.5 Auditoría con `tenant_id` en logs

Logger custom que enriquece automáticamente cada entrada con `tenant_id`, `tenant_slug`, `user_id`. Permite filtrar en Sentry/Loki por tenant para debugging de soporte.

#### 3.6 Tests pendientes

- `ProvisionTenantJobTest`: payload válido → tenant + admin + cfg_clinic existen.
- `OrvaeWebhookTest`: HMAC inválida → 401. Evento duplicado → 200 idempotente. `tenant.purchased` → encola job.
- `TenantAuthFlowTest` (E2E del single-login): login válido en central, login válido en subdominio, cruce host↔usuario rechazado, sidebar filtrado por rol.

### ⏭️ Fase 3.5 — Feature gating por plan

> **Objetivo**: que los límites del plan (`max_pacientes`, `max_usuarios`, módulos habilitados) se apliquen automáticamente en cada Policy del tenant, con UI que invite a hacer upgrade.

#### 3.5.1 `PlanFeatures` cached

Helper que cachea por request las features del plan del tenant actual:

```php
$features = tenant()->planFeatures();
$features->limit('max_pacientes');       // int|null (null = ilimitado)
$features->boolean('modulo_inventario'); // bool
$features->value('color_branding');      // string
```

Caché por tenant en `redis://tenant:{id}:features` (TTL 5 min, invalidado al cambiar plan).

#### 3.5.2 Trait `EnforcesPlanLimits` en Policies

```php
class PacientePolicy
{
    use EnforcesPlanLimits;
    
    public function create(User $user): bool|Response
    {
        if (! $user->can('pacientes.create')) {
            return Response::deny('Sin permiso.');
        }
        
        return $this->withinLimit(
            'max_pacientes',
            current: Paciente::count(),
            upgradeHint: 'Mejora a Plan Pro para registrar pacientes ilimitados.',
        );
    }
}
```

`Response::deny()` lleva código `PLAN_LIMIT_REACHED` y `upgrade_hint` que el frontend muestra como modal contextual.

#### 3.5.3 Inertia shared: `page.props.planLimits`

Snapshot por request con consumo actual vs límite. El frontend lo usa para:
- Deshabilitar botones de "Crear paciente" al 100%.
- Mostrar barra de progreso en el dashboard.
- Pintar tags "FREE" en items bloqueados del sidebar.

#### 3.5.4 Overrides manuales del soporte

UI en `/plataforma/tenants/<id>/features-override` para que soporte aumente puntualmente un límite (regalo de campaña, cliente en mudanza, etc.) sin tener que cambiarle de plan.

#### 3.5.5 Tests

- `PlanLimitsTest`: plan FREE con `max_pacientes=5` → crear 6 falla con código `PLAN_LIMIT_REACHED`.
- Plan PRO con override `max_pacientes=10` → crear 7 funciona.
- Cambio de plan FREE→PRO invalida caché y desbloquea inmediatamente.

### ⏭️ Fase 4 — App del tenant (módulos clínicos)

> **Objetivo**: que la clínica tenga algo que hacer cuando entra a su panel. Núcleo del producto.

#### 4.1 Bloque CONFIGURACIÓN (mínimo viable)

| Módulo | Tablas tenant | Estado |
|---|---|---|
| `configuracion/clinica` | `cfg_clinic_settings` | Migración existe, falta CRUD |
| `configuracion/sedes` | `cfg_sedes` | Ya construido en SaaS, replicar para tenant |
| `configuracion/usuarios` | `public.users` (filtrado por `tenant_id`) | Admin gestiona empleados de su clínica |
| `configuracion/roles` | `roles` (Spatie en `public`) | Solo lectura para tenant; soporte crea roles globales |

#### 4.2 Bloque PACIENTES Y PROPIETARIOS

| Módulo | Tablas / alcance | Estado en código (mayo 2026) |
|---|---|---|
| `propietarios` | `propietarios` (uuid, documento, contacto, dirección…) | **Hecho**: CRUD tenant, ficha con mascotas, enlaces. |
| `pacientes` | `pacientes` (uuid, fk propietario, especie, raza, foto…) | **Hecho**: listado, CRUD, carnet PDF vacunas, acceso **Historial clínico** (`pacientes.show`). |
| Historias clínicas (SOAP) | `historias_clinicas`, `consultas` (+ plan de tratamiento en evolución) | **Hecho**: listado por rango `atendido_at`, modal crear/editar, **cierre / reapertura** de consulta (`cerrada_at`, `cerrada_por_id`), constantes en consulta (`temperatura_c`, `fc_lpm`, `fr_rpm`), bloqueo de plan y seguimiento si consulta cerrada. Ver [§ Módulo clínico del tenant…](#módulo-clínico-del-tenant-historias-plan-vacunas-e-historial-del-paciente-mayo-2026). |
| Vacunaciones / aplicaciones | `vacunas_aplicadas` (+ vínculo opcional a `consultas`) | **Hecho**: registro con categoría, stock, **`consulta_id`** opcional (consulta abierta), prefill desde URL; listado con columna consulta. |
| Historial unificado del paciente | Timeline (consultas + aplicaciones) | **Hecho**: ruta `GET clinica/pacientes/{paciente}`, UI línea de tiempo con resumen SOAP/vitales y enlaces a historias / vacunaciones. **Recetas, laboratorio y cirugías** vinculados por `consulta_id` se listan **dentro del resumen de la consulta** (`detalle.vinculos`), no como entradas separadas del timeline. |
| Recetas | `recetas`, `recetas_lineas` | **Hecho**: CRUD, PDF, listado con filtros (búsqueda, rango `emitida_at`, estado solo query), deep link `editar_receta`. Registros **sin** `consulta_id` solo aparecen en el módulo Recetas, no en el bloque vinculado del historial del paciente. |
| Laboratorio (pedidos) | `pedidos_laboratorio`, líneas | **Hecho**: CRUD, deep link, **adjuntos de resultado** (PDF/imagen por línea, `resultado_archivo_*`). Integraciones lab externas: no. |
| Cirugías | `cirugias` | **Hecho**: CRUD y listado con filtros; `editar_cirugia`. |
| **Cobro en la visita** (precio consulta, total cobrado, vínculo venta/FEL) | **Avanzado** | Pre-cuenta → venta; internamiento/grooming/hotel → venta; **FEL Lucode** emitido/anulado. Pendiente: arancel fijo en ficha de visita, POS libre solo-servicio, pagos parciales. |
| Calendario sugerido por especie/edad | — | Pendiente de producto (no confundir con “próxima sugerida” por registro). |

#### 4.3 Bloque AGENDA

| Módulo | Tablas tenant nuevas | Estado en código (jul. 2026) |
|---|---|---|
| `citas` | `citas` (fk paciente, fk veterinario, fecha, estado, motivo, sede) | **Hecho**: migración **t065**, CRUD/listado, permisos. |
| `citas/calendario` | Vista mes + lista | **Hecho**: `CitasCalendar` en `clinica/citas` (vista calendario / lista) + **DnD** para reprogramar (solo `programada`/`confirmada`, permiso `citas.update`). |
| `citas/recordatorios` | Job WhatsApp/email | **Hecho (parcial)**: `AppointmentReminderScanner` + `vetsaas:reminders-scan` (cita 48h/2h y otros recordatorios del scheduler). |

#### 4.4 Bloque OPERACIONES

| Módulo | Notas |
|---|---|
| `inventario/productos` | Medicinas, vacunas, accesorios. Control de stock por sede. **Avance real**: CRUD + `stock_minimo`; ver [Módulo Inventario del tenant (mayo 2026)](#módulo-inventario-del-tenant-mayo-2026). |
| `inventario/proveedores` | **Hecho**: maestro de proveedores + consulta RUC SUNAT (apiperu.dev). |
| `inventario/compras` | **Hecho**: compras con líneas, stock, factura opcional, filtros, export, anulación; **lote/vencimiento** por línea. |
| `inventario/stock` | **Hecho**: existencias, ajuste, **dialog de lotes FEFO** por sede. |
| `inventario/alertas` | **Hecho**: stock mínimo + **lotes por vencer / vencidos**. |
| `inventario/movimientos` | Entradas, salidas, mermas, ajustes, **traslados entre sedes** (UI solo si hay ≥2 sedes activas; plan Clínica hasta 3). FEFO en origen + mismo lote/vencimiento en destino (`traslado_grupo_id`, **t110**). |
| `caja/sesiones` | **Hecho**: apertura/cierre por sede. |
| `caja/ventas` | **Hecho**: POS + prefills + ticket + **anulación** + **FEL Lucode**. Pendiente: pagos parciales, alta libre solo-servicio. |
| `caja/pagos` | **Placeholder** — cobranzas parciales. |
| `caja/descuentos` | **Placeholder** — cupones/promociones (promotions table existe; UI menú puede ser placeholder). |
| `caja/arqueos` | Cuadre al final del día (no implementado; cierre de sesión cubre efectivo). |
| `facturacion/*` | FEL Perú: **emisión + anulación/NC vía Lucode (APISUNAT)** desde venta. Series en panel. Pantallas agregadoras (resúmenes/notas-baja) pueden seguir en placeholder. |

#### 4.5 Bloque CLÍNICO AVANZADO

| Módulo | Estado en código (mayo 2026) |
|---|---|
| `cirugias` | **Hecho** (tenant): tabla `cirugias`, CRUD, permisos, listado con rango `programada_at`, filtro estado por query, deep link `editar_cirugia`. La visión de “protocolo pre-operatorio extendido” puede enriquecerse con más campos o plantillas. |
| `laboratorio` | **Hecho**: pedidos + **adjuntos de resultado** (PDF/imagen). Integraciones lab externas: no. |
| `internamiento/hospitalizacion` | **Hecho** (**t076–t078**): listado, evolución, cargos, cobro en caja. |
| `servicios/grooming` | **Hecho** (**t079–t083**, **t106** insumos): turnos, cobro, **tarifas + insumos** en Configuración › Tarifas. |
| `servicios/hotel` | **Hecho** (**t084–t087**): estancias, bitácora, cobro, **tarifas** en Configuración › Tarifas. |

#### 4.6 Bloque REPORTES Y COMUNICACIONES

- Reportes preformateados: ventas por veterinario, productos más vendidos, pacientes activos, ingresos por sede.
- Recordatorios automáticos: vacunas vencidas, controles pendientes, cumpleaños.
- Campañas masivas opt-in: promociones por especie/edad.

#### 4.7 Onboarding guiado para clínicas nuevas

Pantalla "Bienvenido a tu clínica" que aparece al primer login del admin con checklist:

```
□ Carga el logo y datos fiscales       (→ configuracion/clinica)
□ Crea tu primera sede                 (→ configuracion/sedes)
□ Invita a tu equipo                   (→ configuracion/usuarios)
□ Registra tu primer paciente          (→ pacientes/nuevo)
□ Agenda tu primera cita               (→ citas/nueva)
□ Conecta Lucode / APISUNAT (FEL)      (→ configuracion/general)
```

Estado persistido en `tenant_onboarding_progress` (schema del tenant). Al completarlo, se oculta el banner.

### ⏭️ Fase 5 — Producción (deploy)

> **Objetivo**: poner VetSaaS en `vetsaas.com` con todos sus tenants reales.

#### 5.1 Infraestructura

| Componente | Stack sugerido |
|---|---|
| App Laravel | Docker en VPS Hetzner CX42 o EC2 t3.medium |
| PostgreSQL | RDS PostgreSQL 16 (db.t4g.medium) o Supabase managed |
| Redis | Upstash Redis o ElastiCache (sessions + cache + queue) |
| Storage | S3 / Cloudflare R2 (logos, fotos de mascotas, PDFs FEL) |
| Workers de cola | 2× procesos Horizon (uno para `provisioning`, otro para `default`) |
| Cron | Supervisor que ejecuta `schedule:run` cada minuto |
| Frontend | Vite build estático servido por Nginx |

#### 5.2 DNS y SSL

- DNS: `vetsaas.com` (apex) + `*.vetsaas.com` (wildcard) apuntando al balanceador.
- SSL: certificado wildcard con Let's Encrypt vía `certbot --preferred-challenges dns-01` (DNS Cloudflare API).
- Header `Host` propagado correctamente al PHP-FPM (importante para `SubdomainResolver`).

#### 5.3 CI/CD (GitHub Actions)

```yaml
jobs:
  test:    # Pest contra Postgres real (service container)
  build:   # Docker multi-stage (composer + npm + php)
  deploy:  # Push a registry + ssh deploy.sh en el VPS
```

#### 5.4 Backups por tenant

**Implementado (MVP):**

- Comando: `php artisan vetsaas:backup-database`
- Schedule Laravel: diario **02:00** (`bootstrap/app.php`)
- Genera en `storage/app/backups/` (o `BACKUP_PATH`):
  - `full.dump` — recuperación de desastre
  - `public.dump` — catálogo SaaS
  - `vet_*.dump` — un archivo por clínica
  - `latest.json` — estado leído por **Plataforma › Operaciones**
- Retención local: `BACKUP_RETENTION_DAYS` (default 14)
- **Offsite S3/R2**: `BACKUP_REMOTE_ENABLED=true` + credenciales `AWS_*` (o `BACKUP_AWS_*`). Sube la carpeta del día a `{BACKUP_REMOTE_PREFIX}/{Y-m-d_His}/`. Disco `backups` en `config/filesystems.php`.
- UI: Operaciones muestra OK/atrasado/fallido + estado remoto + botón «Correr ahora»
- Script VPS opcional: `scripts/vetsaas-backup-db.sh`

**Requisitos VPS:** `pg_dump` en PATH, cron `* * * * * php artisan schedule:run`, worker de colas si usas «Correr ahora», paquete `league/flysystem-aws-s3-v3`, disco con espacio. En R2/S3 conviene lifecycle de 30 días.

**Restore por tenant:** `php artisan vetsaas:tenant-restore {slug} {folder?} {--force}` restaura solo el schema `vet_*` desde `{BACKUP_PATH}/{folder}/{schema}.dump` (si no hay folder, usa el más reciente). Antes del `DROP SCHEMA CASCADE` genera un safety dump en `{BACKUP_PATH}/_safety/`. No restaura `full`/`public`.

**Pendiente:** poda remota automática (hoy se recomienda lifecycle del bucket).
#### 5.5 Observabilidad

- **Sentry**: errores PHP y JS, con `tenant_id` como tag.
- **Métricas**: Prometheus exporter + Grafana (latencia por tenant, jobs en cola, tamaño del schema, etc.).
- **Healthchecks**: `/up` (Laravel) + `/health/db` + `/health/queue`.
- **Alertas**: cola atascada > 100 jobs, jobs `failed`, tenant en estado `provisioning` > 5 min.

#### 5.6 Impersonation para soporte

- **MVP implementado**: desde **Plataforma › Tenants**, menú de fila **«Entrar como soporte»** (permiso `plataforma-tenants.impersonate`). El backend genera un **token de un solo uso** en cache (~5 min), redirige al host `<slug>.<TENANT_ROOT_DOMAIN>/impersonate/accept?token=…`, establece sesión del superadmin en ese host y guarda `tenant_impersonation` en sesión. **`MatchUserTenant`** permite `tenant_id = null` en subdominio solo con esa bandera coherente al tenant del host.
- **Banner** arriba del contenido (layout app) + **«Salir del modo soporte»** (`POST /impersonate/leave`) invalida sesión y redirige con **`Inertia::location()`** al login del **`central_origin`** guardado al iniciar (fallback `APP_URL`).
- **Auditoría**: tabla central `impersonation_audit_logs` (`superadmin_id`, `tenant_id`, IP, `user_agent`, `central_origin`, `started_at`, `ended_at`). Se crea al aceptar el token; `ended_at` al salir.
- **Pendiente**: caducidad del token configurable, suplantar usuario admin de clínica en lugar del superadmin (opción producto), UI en Plataforma para consultar el log.

### ⏭️ Fase 6 — Portal del cliente y self-service

> **Objetivo**: que los **dueños de mascotas** tengan su propio acceso reducido a la clínica para ver historial, agendar citas y recibir recordatorios.

#### 6.1 Nuevo tipo de usuario: `cliente`

- Mismo modelo `User` con `tenant_id` y nuevo rol Spatie `cliente_dueno`.
- Solo permisos `cliente.*`: ver SUS mascotas, agendar citas, descargar historial PDF.
- Login en `<slug>.vetsaas.com/portal/login` (mismo Fortify, otro rol).

#### 6.2 Pantallas

- `/portal/mis-mascotas` (cards con foto, próxima vacuna, última visita).
- `/portal/agendar` (calendario público del veterinario).
- `/portal/historial/<paciente>` (timeline + descarga PDF).
- `/portal/perfil` (datos personales y método de pago para suscripciones futuras).

#### 6.3 Onboarding del cliente

- El staff envía invitación desde `pacientes/<id>` → "Invitar al dueño al portal".
- Email con magic-link de primer acceso.

---

## Recomendación: cuál sigue

Sin una restricción de negocio en contra, **el orden técnico recomendable es Fase 3 → 3.5 → 4 → 5 → 6**, porque:

1. **Fase 3 (provisión automática)** desbloquea el negocio: ya puedes vender en Orvae y que los tenants nazcan solos. Hoy el flujo manual con `vetsaas:tenant-migrate` + `vetsaas:tenant-create-admin` solo escala para pruebas y soporte.
2. **Fase 3.5 (feature gating)** es prerequisito de cualquier plan de pago real: sin límites por plan, no hay diferencia entre FREE y PRO.
3. **Fase 4 (módulos clínicos)** da valor al producto. Sin Fase 3, hacer Fase 4 antes implica seguir provisionando tenants a mano, pero **es válido invertir el orden** si la prioridad es **demos a clínicas** antes que onboarding automatizado.
4. **Fase 5 (producción)** se hace cuando hay algo que vender (mínimo: pacientes + citas + caja básica).
5. **Fase 6 (portal cliente)** es upsell, no MVP.

> **Si el objetivo a 30 días es lanzar piloto con 1-2 clínicas reales** → empieza por **Fase 4** (módulos clínicos mínimos) provisionando manualmente; postpone Fase 3 hasta tener tracción.
>
> **Si el objetivo es tener Orvae vendiendo solo** → empieza por **Fase 3** sí o sí.

---

## Comandos de operación frecuente

```bash
# RESET SOLO del tenant demo (seguro en production):
php artisan vetsaas:reset-demo --rebuild

# Setup local (desarrollo): migrate + seed + DemoTenantsSeeder
php artisan migrate --seed
php artisan db:seed --class=DemoTenantsSeeder

# Solo recrear los tenants demo sin tocar public:
php artisan db:seed --class=DemoTenantsSeeder --force

# Crear schema operativo + correr migraciones tenant
php artisan vetsaas:tenant-migrate vet_otra_clinica

# Crear admin de la clínica e ENVIAR INVITACIÓN por correo (recomendado)
php artisan vetsaas:tenant-create-admin otra-clinica --email=admin@otra.com --name="Admin Otra"

# Crear admin con password explícita (modo compatibilidad)
php artisan vetsaas:tenant-create-admin otra-clinica --email=admin@otra.com --password=secret123 --name="Admin Otra"

# Procesar la cola de correos (en dev/local)
php artisan queue:work --queue=mails

# Listar todos los tenants y su estado
php artisan vetsaas:tenant-list

# Ejecutar un comando dentro del contexto de un tenant
php artisan vetsaas:tenant-tinker otra-clinica
```

---

## Estado del producto (jul. 2026) — puntuación real

> Snapshot tras auditoría código vs doc. Escala **0–100** = listo para piloto comercial con clínicas reales en Perú (no “producto eterno perfecto”).

| Bloque | Puntos | Notas |
|--------|--------|-------|
| Clínica (HC, vacunas, recetas, lab, cirugías, hosp., PDFs) | **88** | PDFs carnet/receta/consulta/historial + adjuntos lab. |
| Agenda (citas + calendario + recordatorios) | **90** | Calendario + DnD reprogramar. |
| Inventario + FEFO/lotes | **92** | Lotes, FEFO, alertas, UI lotes, **traslados sede↔sede** (≥2 sedes). |
| Caja + anulación + tickets | **85** | Anulación + stock OK. Sin pagos parciales. |
| FEL Lucode (emitir + anular/NC) | **82** | Operativo; series NC obligatorias para fallback NC; menú Facturación parcial. |
| Servicios (grooming + hotel + tarifas) | **84** | Tarifas/insumos en panel. |
| Ops plataforma (backups, presencia, panel) | **92** | Backup + **`vetsaas:tenant-restore`**. |
| Adopción (onboarding + ayuda) | **80** | Wizard flaggeable + centro de ayuda. |
| SaaS scale (provisión Orvae) | **78** | API interna sí; portal dueño **no**. |
| **Core global (piloto)** | **88 / 100** | |

**Pendientes reales (no inventados):** pagos parciales, portal del cliente (Fase 6), UI agregada de resúmenes FEL, tests Pest ampliados.

---

> **Última actualización (jul. 2026)**: se alineó esta doc con el código. **FEL = Lucode/APISUNAT**. **Inventario FEFO/lotes + traslados** (**t108–t110**), **DnD calendario**, **`vetsaas:tenant-restore`**, anulación de ventas, PDFs clínicos, ops/backups y onboarding constan como hechos o parciales según tablas arriba.
>
> **Próximo hito sugerido**: (1) **piloto con 1–2 clínicas** (series FEL/NC + Lucode + checklist ops R2); (2) según dolor real: pagos parciales o portal dueño; (3) endurecer tests Pest caja/FEL Lucode.
