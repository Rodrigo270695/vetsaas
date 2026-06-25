# Comandos Artisan — VetSaaS / Bot de ventas

Referencia rápida para ejecutar tareas manualmente en el VPS.

**Ruta habitual en el servidor:**

```bash
cd /var/www/vetsaas
php artisan <comando>
```

**Antes de probar en producción**, usa `--dry-run` cuando el comando lo soporte: lista qué haría sin enviar mensajes ni guardar cambios.

---

## Panel web (sin terminal)

Muchas acciones ya están en **Plataforma → Conversaciones bot**:

| Acción | Dónde |
|--------|--------|
| Pausar / reanudar bot por lead | Botón en cada fila |
| Responder con IA (lead nuevo o sin respuesta) | Botón **Responder con IA** en la barra de filtros |
| Reactivar lead frío (un lead) | Icono ✉ en la fila |
| Importar CSV | Botón **Importar CSV** |
| Marcar convertido | Icono ✓ verde |

Los comandos de abajo son el **plan B** cuando no tienes el panel a mano o necesitas automatizar/scriptear.

---

## Bot de ventas y leads

### `salesbot:pause` — Pausar o reanudar el bot por teléfono

Para que **tú escribas manualmente** en WhatsApp sin que la IA interfiera.

```bash
# Pausar bot para un número
php artisan salesbot:pause 51986709811

# Reactivar bot (mismo comando con --resume)
php artisan salesbot:pause 51986709811 --resume

# Atajo equivalente
php artisan salesbot:resume 51986709811

# Ver todas las conversaciones y su estado
php artisan salesbot:pause --list
```

---

### `salesbot:engage` — Forzar respuesta de la IA

Cuando el lead escribió pero el bot **no entró** (Facebook Ads, keywords, etc.). Crea la conversación si no existe, genera respuesta con OpenAI y la envía por WhatsApp.

```bash
# Básico
php artisan salesbot:engage 51961777549

# Con el mensaje real del lead como contexto
php artisan salesbot:engage 51961777549 --message="Buenos días, información de costos"

# Con nombre (si no está en la BD)
php artisan salesbot:engage 51961777549 --name="Beatriz Moscol"

# Solo ver qué respondería la IA, sin enviar
php artisan salesbot:engage 51961777549 --dry-run
```

Acepta número corto peruano (`961777549`) o con código país (`51961777549`).

---

### `vetsaas:reactivate-cold-leads` — Recordatorio a leads fríos

Envía mensaje de **reactivación con IA** a leads que hablaron con el bot hace varios días y no convirtieron.

**Reglas:**
- Máximo **2 intentos** por lead
- Al menos **3 días** entre intentos
- Leads **convertidos** o **perdidos** se excluyen
- Tras 2 intentos sin respuesta → se marcan como **perdidos** automáticamente

```bash
# Ejecutar reactivación real (como el scheduler)
php artisan vetsaas:reactivate-cold-leads

# Solo ver quién calificaría, sin enviar
php artisan vetsaas:reactivate-cold-leads --dry-run

# Leads inactivos 5+ días (default: 3)
php artisan vetsaas:reactivate-cold-leads --days=5

# Máximo de mensajes por corrida (default: 15, máx recomendado: 20/día)
php artisan vetsaas:reactivate-cold-leads --limit=10

# Segundos entre envíos (default: 15, mínimo recomendado: 10)
php artisan vetsaas:reactivate-cold-leads --delay=20

# Combinado — prueba segura
php artisan vetsaas:reactivate-cold-leads --dry-run --days=5 --limit=20
```

**Scheduler automático** (ya configurado en `bootstrap/app.php`):

| Hora | Comando |
|------|---------|
| 10:00 | `vetsaas:reactivate-cold-leads --limit=10 --delay=15` |
| 15:00 | `vetsaas:reactivate-cold-leads --limit=10 --delay=15` |

---

### `vetsaas:import-leads` — Importar leads desde CSV

Carga leads históricos para que el scheduler los reactivé. Entran con **bot pausado** (`manual:csv-import`).

```bash
# Generar plantilla en storage/app/leads_template.csv
php artisan vetsaas:import-leads --template

# Importar archivo
php artisan vetsaas:import-leads /ruta/leads.csv

# Simular inactividad de 6 días (para que cuenten como fríos antes)
php artisan vetsaas:import-leads leads.csv --days=6

# Solo previsualizar
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

Lee chats de la sesión WhatsApp de plataforma y los registra como leads fríos. Útil para migrar conversaciones antiguas.

```bash
# Solo chats con palabras clave de VetSaaS
php artisan vetsaas:import-leads-from-openwa

# Todos los chats (sin filtro)
php artisan vetsaas:import-leads-from-openwa --all

# Solo listar, no guardar
php artisan vetsaas:import-leads-from-openwa --dry-run

# Días de inactividad simulada
php artisan vetsaas:import-leads-from-openwa --days=6 --limit=50
```

> **Nota:** Depende del endpoint `/chats` de tu versión de OpenWA. Si devuelve 404, usa importación CSV.

---

### `vetsaas:resolve-lid-leads` — Corregir teléfonos @lid

WhatsApp a veces envía un **ID privado** (`@lid`) en vez del número real. Este comando intenta resolver teléfono y nombre vía API de OpenWA.

```bash
php artisan vetsaas:resolve-lid-leads --dry-run
php artisan vetsaas:resolve-lid-leads
```

---

### `vetsaas:sync-bot-knowledge` — Sincronizar base de conocimiento del bot

Actualiza FAQs y módulos en `salesbot_knowledge` desde los **planes reales** de la BD. No sobrescribe entradas editadas manualmente en el panel.

```bash
php artisan vetsaas:sync-bot-knowledge

# Forzar sobrescritura incluso de entradas manuales
php artisan vetsaas:sync-bot-knowledge --force
```

**Scheduler:** todos los días a las **03:30**.

---

## Scheduler automático (resumen)

| Hora | Comando | Para qué |
|------|---------|----------|
| 03:00 | `vetsaas:reset-demo` | Reset entorno demo |
| 03:30 | `vetsaas:sync-bot-knowledge` | FAQs del bot desde BD |
| 06:00 | `vetsaas:billing-supervisor` | Supervisión de cobros |
| 09:00 | `vetsaas:subscription-renewal-reminders` | Avisos vencimiento suscripción |
| 10:00 | `vetsaas:reactivate-cold-leads` | Reactivar leads fríos (mañana) |
| 15:00 | `vetsaas:reactivate-cold-leads` | Reactivar leads fríos (tarde) |
| Cada 15 min | `vetsaas:reminders-scan` | Recordatorios clínicas |
| Cada 5 min | `vetsaas:notifications-dispatch` | Cola de notificaciones |
| Cada hora | `vetsaas:whatsapp-sync-sessions` | Estado sesiones OpenWA |

Verificar que el cron de Laravel esté activo:

```bash
* * * * * cd /var/www/vetsaas && php artisan schedule:run >> /dev/null 2>&1
```

---

## WhatsApp y suscripciones (plataforma)

### `vetsaas:subscription-renewal-reminders`

Avisos de **vencimiento de suscripción** a clínicas (no es el bot de ventas).

```bash
php artisan vetsaas:subscription-renewal-reminders --dry-run
php artisan vetsaas:subscription-renewal-reminders --report
php artisan vetsaas:subscription-renewal-reminders
```

### `vetsaas:whatsapp-sync-sessions`

Sincroniza estado de sesiones OpenWA (plataforma y tenants).

```bash
php artisan vetsaas:whatsapp-sync-sessions
```

---

## Comandos útiles de mantenimiento

```bash
# Limpiar caché tras deploy
php artisan cache:clear && php artisan optimize:clear

# Ver todos los comandos registrados
php artisan list

# Solo comandos vetsaas / salesbot
php artisan list vetsaas
php artisan list salesbot

# Logs del bot en tiempo real
tail -f storage/logs/laravel.log | grep -iE "SalesBot|reactivat|engage|OpenWA"
```

---

## Flujos frecuentes

### Lead de Facebook no respondió

1. Panel → **Responder con IA** (recomendado)  
2. O: `php artisan salesbot:engage 519XXXXXXXX --message="..."`

### Quiero escribir yo sin que el bot moleste

1. Panel → **Pausar** en ese lead  
2. O: `php artisan salesbot:pause 519XXXXXXXX`

### Enviar recordatorios a leads fríos ahora (sin esperar 10:00 / 15:00)

```bash
php artisan vetsaas:reactivate-cold-leads --dry-run   # revisar lista
php artisan vetsaas:reactivate-cold-leads --limit=10  # enviar
```

### Importar ~157 leads históricos y reactivarlos

```bash
php artisan vetsaas:import-leads leads_historicos.csv --days=5
php artisan vetsaas:reactivate-cold-leads --dry-run
```

---

*Última actualización: junio 2026 — VetSaaS sales bot.*
