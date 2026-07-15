# Comandos Artisan — VetSaaS

Referencia de comandos propios (`vetsaas:*` y `salesbot:*`) para el VPS y entornos locales.

**Ruta habitual en el servidor:**

```bash
cd /var/www/vetsaas
php artisan <comando>
```

**Antes de probar en producción**, usa `--dry-run` cuando el comando lo soporte: lista qué haría sin enviar mensajes ni guardar cambios.

```bash
# Ver todos los comandos registrados
php artisan list

# Solo namespace vetsaas / salesbot
php artisan list vetsaas
php artisan list salesbot
```

---

## Índice rápido

| Grupo | Comandos |
|-------|----------|
| **Tenants** | `tenant-diagnose`, `tenant-migrate`, `tenant-migrate-all`, `tenant-create-admin`, `tenant-restore`, `onboarding-reset` |
| **Backups** | `backup-database`, `tenant-restore` |
| **Cobros / suscripciones** | `billing-supervisor`, `subscription-renewal-reminders`, `sync-tenants-from-subscriptions` |
| **WhatsApp / notificaciones clínicas** | `whatsapp-sync-sessions`, `reminders-scan`, `notifications-dispatch`, `clinic-bot-register-webhooks` |
| **Bot de ventas / leads** | `salesbot:*`, `reactivate-cold-leads`, `import-leads`, `import-leads-from-openwa`, `resolve-lid-leads`, `sync-bot-knowledge` |
| **Demo / mantenimiento** | `reset-demo`, `fresh-demo`, `geo-fix-encoding`, `nubefact-diagnose`, `test-password-reset-mail` |

---

## Panel web (sin terminal)

| Acción | Dónde |
|--------|--------|
| Pausar / reanudar bot por lead | **Plataforma → Conversaciones bot** |
| Responder con IA | Mismo panel → **Responder con IA** |
| Reactivar lead frío (uno) | Icono ✉ en la fila |
| Importar CSV de leads | Botón **Importar CSV** |
| Estado de backups / «Correr ahora» | **Plataforma → Operaciones** |
| Sesiones WhatsApp clínicas | **Plataforma → Operaciones** (radar OpenWA) |

Los comandos de abajo son el **plan B** (SSH, scripts, recuperación).

> **Restore de BD:** no hay UI de restauración. Solo CLI (`vetsaas:tenant-restore`). A propósito: es destructivo (`DROP SCHEMA CASCADE`).

---

## Tenants (schemas, migraciones, admin)

### `vetsaas:tenant-diagnose` — Diagnóstico rápido

Comprueba registro central, existencia del schema PostgreSQL y URL de login.

```bash
php artisan vetsaas:tenant-diagnose mi-clinica
```

---

### `vetsaas:tenant-migrate` — Migrar un schema

Ejecuta `database/migrations/tenant` en el schema indicado.

```bash
php artisan vetsaas:tenant-migrate vet_xxxxxxxx

# Desarrollo: borrar historial tenant en public.migrations y reaplicar
php artisan vetsaas:tenant-migrate vet_xxxxxxxx --replay

# Schema a medias: DROP SCHEMA CASCADE + recrear + migrar
php artisan vetsaas:tenant-migrate vet_xxxxxxxx --wipe
```

> `--wipe` / `--replay` son peligrosos en producción.

---

### `vetsaas:tenant-migrate-all` — Migrar todos (o uno)

Aplica migraciones tenant **pendientes** a todos los tenants registrados.

```bash
# Todos
php artisan vetsaas:tenant-migrate-all

# Solo uno
php artisan vetsaas:tenant-migrate-all --slug=mi-clinica
php artisan vetsaas:tenant-migrate-all --schema=vet_xxxxxxxx

# Solo listar qué se migraría
php artisan vetsaas:tenant-migrate-all --dry-run

# Parar al primer fallo (por defecto continúa con el resto)
php artisan vetsaas:tenant-migrate-all --stop-on-error
```

**Tras deploy con migraciones nuevas (ej. t109/t110 lotes/traslados):**

```bash
php artisan vetsaas:tenant-migrate-all
```

---

### `vetsaas:tenant-create-admin` — Crear / actualizar admin de clínica

Por defecto genera contraseña e **envía invitación por correo**.

```bash
php artisan vetsaas:tenant-create-admin mi-clinica \
  --email=admin@clinica.com \
  --name="María Quispe"

# Contraseña explícita
php artisan vetsaas:tenant-create-admin mi-clinica \
  --email=admin@clinica.com \
  --password='Secreta123!' \
  --force

# Sin correo (tests / seeds)
php artisan vetsaas:tenant-create-admin mi-clinica \
  --email=admin@clinica.com \
  --no-invite \
  --no-force-change
```

---

### `vetsaas:tenant-restore` — Restaurar schema desde backup

Restaura **solo** un schema `vet_*` desde un dump local (`pg_restore`).  
Antes del `DROP SCHEMA CASCADE` genera un **safety dump** en `{BACKUP_PATH}/_safety/`.

**No restaura** `full.dump` ni `public.dump`.

```bash
# Usa el dump más reciente que exista para ese schema
php artisan vetsaas:tenant-restore mi-clinica

# Carpeta concreta (nombre = Y-m-d_His bajo BACKUP_PATH)
php artisan vetsaas:tenant-restore mi-clinica 2026-07-12_020015

# Sin pregunta interactiva
php artisan vetsaas:tenant-restore mi-clinica 2026-07-12_020015 --force

# Restaurar TODOS los schemas faltantes (tras un DROP masivo)
php artisan vetsaas:tenant-restore-all --force
php artisan vetsaas:tenant-restore-all --dry-run   # solo lista
```

**Requisitos:** dumps `vet_*.dump` en `BACKUP_PATH` (default `storage/app/backups`).

---

### `vetsaas:onboarding-reset` — Reiniciar wizard de onboarding

```bash
php artisan vetsaas:onboarding-reset mi-clinica
```

---

## Backups de PostgreSQL

### `vetsaas:backup-database` — Dump diario

Genera en `{BACKUP_PATH}/{Y-m-d_His}/`:

| Archivo | Contenido |
|---------|-----------|
| `full.dump` | BD completa (desastre) |
| `public.dump` | Catálogo SaaS |
| `vet_*.dump` | Un archivo por clínica |
| `latest.json` | Estado leído por **Operaciones** |

Si `BACKUP_REMOTE_ENABLED=true`, sube la carpeta a S3/R2.

```bash
php artisan vetsaas:backup-database
```

**Scheduler:** diario **02:00**. También se puede disparar desde **Plataforma → Operaciones → Correr ahora**.

**Env útiles:** `BACKUP_ENABLED`, `BACKUP_PATH`, `BACKUP_RETENTION_DAYS`, `BACKUP_PG_DUMP`, `BACKUP_PG_RESTORE`, `BACKUP_REMOTE_*`.

---

## Cobros y suscripciones

### `vetsaas:billing-supervisor`

Aplica grace / suspended a suscripciones con cobro o trial vencido sin pago.

```bash
php artisan vetsaas:billing-supervisor
```

**Scheduler:** diario **06:00**.

---

### `vetsaas:subscription-renewal-reminders`

Avisos WhatsApp de **vencimiento de suscripción** (plataforma → clínica). No es el bot de ventas.

```bash
php artisan vetsaas:subscription-renewal-reminders --dry-run
php artisan vetsaas:subscription-renewal-reminders --report
php artisan vetsaas:subscription-renewal-reminders
```

**Scheduler:** diario **09:00**.

---

### `vetsaas:sync-tenants-from-subscriptions`

Alinea `estado` / trial de tenants con su suscripción viva.

```bash
php artisan vetsaas:sync-tenants-from-subscriptions
```

---

## WhatsApp clínico, recordatorios y notificaciones

### `vetsaas:whatsapp-sync-sessions`

Crea / sincroniza sesiones OpenWA por tenant (`slug` = nombre de sesión). Es lo que alimenta el radar de Operaciones (`created`, `qr_ready`, `ready`, etc.).

```bash
php artisan vetsaas:whatsapp-sync-sessions
```

**Scheduler:** **cada hora**.

---

### `vetsaas:reminders-scan`

Encola recordatorios automáticos (citas, vacunas, cumpleaños) por tenant.

```bash
php artisan vetsaas:reminders-scan
```

**Scheduler:** cada **15 minutos**.

---

### `vetsaas:notifications-dispatch`

Envía mensajes pendientes de la cola vía OpenWA.

```bash
php artisan vetsaas:notifications-dispatch
php artisan vetsaas:notifications-dispatch --limit=50
```

**Scheduler:** cada **5 minutos**.

---

### `vetsaas:clinic-bot-register-webhooks`

Registra el webhook del asistente IA en sesiones OpenWA conectadas.

```bash
php artisan vetsaas:clinic-bot-register-webhooks --dry-run
php artisan vetsaas:clinic-bot-register-webhooks
php artisan vetsaas:clinic-bot-register-webhooks --slug=mi-clinica
```

---

## Bot de ventas y leads

### Panel web (recomendado)

Muchas acciones ya están en **Plataforma → Conversaciones bot** (pausar, responder con IA, reactivar, importar CSV, marcar convertido).

---

### `salesbot:pause` / `salesbot:resume` — Pausar o reanudar el bot por teléfono

Para escribir manualmente en WhatsApp sin que la IA interfiera.

```bash
php artisan salesbot:pause 51986709811
php artisan salesbot:pause 51986709811 --resume
php artisan salesbot:resume 51986709811
php artisan salesbot:pause --list
```

---

### `salesbot:engage` — Forzar respuesta de la IA

Cuando el lead escribió pero el bot no entró (Facebook Ads, keywords, etc.).

```bash
php artisan salesbot:engage 51961777549
php artisan salesbot:engage 51961777549 --message="Buenos días, información de costos"
php artisan salesbot:engage 51961777549 --name="Beatriz Moscol"
php artisan salesbot:engage 51961777549 --dry-run
```

Acepta número corto peruano (`961777549`) o con código país (`51961777549`).

---

### `vetsaas:reactivate-cold-leads` — Recordatorio a leads fríos

Envía reactivación con IA a leads que hablaron hace varios días y no convirtieron.

**Reglas:** máx. **2** intentos por lead · ≥ **3** días entre intentos · excluye convertidos/perdidos · tras 2 intentos sin respuesta → **perdidos**.

```bash
php artisan vetsaas:reactivate-cold-leads
php artisan vetsaas:reactivate-cold-leads --dry-run
php artisan vetsaas:reactivate-cold-leads --days=5
php artisan vetsaas:reactivate-cold-leads --limit=10
php artisan vetsaas:reactivate-cold-leads --delay=20
php artisan vetsaas:reactivate-cold-leads --dry-run --days=5 --limit=20
```

**Scheduler:** **10:00** y **15:00** (`--limit=10 --delay=15`).

---

### `vetsaas:import-leads` — Importar leads desde CSV

Entran con bot pausado (`manual:csv-import`).

```bash
php artisan vetsaas:import-leads --template
php artisan vetsaas:import-leads /ruta/leads.csv
php artisan vetsaas:import-leads leads.csv --days=6
php artisan vetsaas:import-leads leads.csv --dry-run
```

**Formato CSV:**

```csv
phone,name,note
51987654321,José Rosales,Preguntó por precio Starter
51993897841,,
```

---

### `vetsaas:import-leads-from-openwa` — Importar chats desde OpenWA

```bash
php artisan vetsaas:import-leads-from-openwa
php artisan vetsaas:import-leads-from-openwa --all
php artisan vetsaas:import-leads-from-openwa --dry-run
php artisan vetsaas:import-leads-from-openwa --days=6 --limit=50
```

> Depende del endpoint `/chats` de tu versión de OpenWA. Si devuelve 404, usa importación CSV.

---

### `vetsaas:resolve-lid-leads` — Corregir teléfonos `@lid`

```bash
php artisan vetsaas:resolve-lid-leads --dry-run
php artisan vetsaas:resolve-lid-leads
```

---

### `vetsaas:sync-bot-knowledge` — Sincronizar base de conocimiento del bot

Actualiza FAQs/módulos en `salesbot_knowledge` desde los planes reales de la BD. No sobrescribe entradas editadas a mano (salvo `--force`).

```bash
php artisan vetsaas:sync-bot-knowledge
php artisan vetsaas:sync-bot-knowledge --force
```

**Scheduler:** diario **03:30**.

---

## Demo, geo y diagnósticos

### `vetsaas:reset-demo`

Resetea **solo** el tenant `demo` (datos + contraseña `demo1234`).

```bash
# Reset datos (nocturno / habitual)
php artisan vetsaas:reset-demo

# Si el schema quedó roto, sin permisos o tras un accidente:
php artisan vetsaas:reset-demo --rebuild
```

**Scheduler:** diario **03:00** (sin `--rebuild`).

Login: `https://demo.<TENANT_ROOT_DOMAIN>/login` → `demo@vetsaas.pe` / `demo1234`

---

### `vetsaas:fresh-demo`

`migrate:fresh` + seed + tenant demo. **Solo local/staging.**  
En **producción está bloqueado** (antes podía dropear todos los `vet_*` y dejar clínicas sin schema).

```bash
# Solo desarrollo
php artisan vetsaas:fresh-demo --force
```

En producción, para la demo usa **`vetsaas:reset-demo --rebuild`**.  
Si necesitas recuperar otra clínica: `vetsaas:tenant-restore {slug} {carpeta} --force`.

---

### `vetsaas:geo-fix-encoding`

Repara tildes/eñes corruptas en el catálogo ubigeo.

```bash
php artisan vetsaas:geo-fix-encoding --dry-run
php artisan vetsaas:geo-fix-encoding
php artisan vetsaas:geo-fix-encoding --sync-denormalized
```

---

### `vetsaas:nubefact-diagnose`

Diagnóstico legacy de credenciales Nubefact / series SUNAT (error código 21).  
La emisión/anulación FEL actual es **Lucode/APISUNAT**; este comando sigue útil si queda config Nubefact.

```bash
php artisan vetsaas:nubefact-diagnose mi-clinica
```

---

### `vetsaas:test-password-reset-mail`

Envía un correo de prueba de reset de contraseña.

```bash
php artisan vetsaas:test-password-reset-mail admin@clinica.com --slug=mi-clinica
```

---

## Scheduler automático (resumen)

Definido en `bootstrap/app.php`:

| Cuándo | Comando |
|--------|---------|
| 02:00 | `vetsaas:backup-database` |
| 03:00 | `vetsaas:reset-demo` |
| 03:30 | `vetsaas:sync-bot-knowledge` |
| 06:00 | `vetsaas:billing-supervisor` |
| 09:00 | `vetsaas:subscription-renewal-reminders` |
| 10:00 | `vetsaas:reactivate-cold-leads --limit=10 --delay=15` |
| 15:00 | `vetsaas:reactivate-cold-leads --limit=10 --delay=15` |
| Cada 15 min | `vetsaas:reminders-scan` |
| Cada 5 min | `vetsaas:notifications-dispatch` |
| Cada hora | `vetsaas:whatsapp-sync-sessions` |

Cron del VPS:

```bash
* * * * * cd /var/www/vetsaas && php artisan schedule:run >> /dev/null 2>&1
```

---

## Mantenimiento post-deploy

```bash
cd /var/www/vetsaas

# Caché
php artisan cache:clear && php artisan optimize:clear

# Migraciones central (public)
php artisan migrate --force

# Migraciones tenant (todos)
php artisan vetsaas:tenant-migrate-all

# Verificar un tenant
php artisan vetsaas:tenant-diagnose mi-clinica

# Backup manual
php artisan vetsaas:backup-database
```

Logs útiles:

```bash
tail -f storage/logs/laravel.log | grep -iE "SalesBot|reactivat|engage|OpenWA|backup|tenant-restore|Fel|Apisunat"
```

---

## Flujos frecuentes

### Deploy con migraciones tenant nuevas (lotes, traslados, etc.)

```bash
php artisan migrate --force
php artisan vetsaas:tenant-migrate-all
php artisan vetsaas:tenant-diagnose mi-clinica
```

### Recuperar una clínica desde backup

```bash
# Ver dumps locales (en el VPS)
ls -la /var/backups/vetsaas   # o storage/app/backups según BACKUP_PATH

php artisan vetsaas:tenant-restore mi-clinica                  # última carpeta
php artisan vetsaas:tenant-restore mi-clinica 2026-07-12_020015 --force
```

### Lead de Facebook no respondió

1. Panel → **Responder con IA** (recomendado)  
2. O: `php artisan salesbot:engage 519XXXXXXXX --message="..."`

### Escribir yo sin que el bot moleste

1. Panel → **Pausar** en ese lead  
2. O: `php artisan salesbot:pause 519XXXXXXXX`

### Enviar recordatorios a leads fríos ahora

```bash
php artisan vetsaas:reactivate-cold-leads --dry-run
php artisan vetsaas:reactivate-cold-leads --limit=10
```

### Importar leads históricos y reactivarlos

```bash
php artisan vetsaas:import-leads leads_historicos.csv --days=5
php artisan vetsaas:reactivate-cold-leads --dry-run
```

### WhatsApp de clínicas “created” en Operaciones

Es normal: el cron horario provisiona sesiones OpenWA. Pasan a **Lista** (`ready`) cuando la clínica escanea el QR en Comunicaciones. No indica usuarios logueados.

```bash
php artisan vetsaas:whatsapp-sync-sessions
```

---

*Última actualización: julio 2026 — inventario completo `vetsaas:*` / `salesbot:*` (incluye `tenant-restore`, backups, migraciones tenant).*
