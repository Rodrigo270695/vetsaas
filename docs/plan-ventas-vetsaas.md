# Plan de Ventas VetSaaS — De 2 clientes a 20+

> **Situación inicial:** S/1,129.24 gastados en Facebook Ads → 159 conversaciones → 2 clientes Pro.
> Costo por cliente: S/564. Conversión: 1.2%. Problema de funnel, no de producto.
>
> **Objetivo:** Bajar el costo por cliente a menos de S/100 y subir la conversión al 15-20%
> sin aumentar el presupuesto publicitario.

---

## Estado actual (junio 2026)

| Componente | Estado |
|---|---|
| Bot IA respondiendo en WhatsApp | ✅ Activo — gpt-4o-mini vía OpenWA |
| Webhook OpenWA registrado | ✅ Sesión `vetsaas-platform` |
| Base de conocimiento editable | ✅ Plataforma → Bot de ventas |
| Panel de conversaciones con pausa/resume | ✅ Plataforma → Conversaciones bot |
| Auto-refresh del panel cada 15s | ✅ Funciona desde celular |
| Bot se reactiva si lead vuelve a preguntar | ✅ Detección de trigger keywords |
| Tenant demo con datos reales | ✅ demo.orvae.pe — demo@vetsaas.pe / demo1234 |
| Reset automático del demo cada noche | ✅ `vetsaas:reset-demo` a las 3:00 a.m. |
| Campaña Facebook Ads activa | ✅ S/25/día — Interacción → WhatsApp |
| Reactivación automática de leads fríos | ✅ Scheduler 10:00 y 15:00 — máx 20/día |
| Auto-cierre de leads sin respuesta (2 intentos) | ✅ Marcados como "perdidos" automáticamente |
| Importación de leads desde CSV | ✅ Panel web + CLI `vetsaas:import-leads` |
| Marcar lead como convertido desde el panel | ✅ Excluye de reactivaciones futuras |
| Verificación HMAC webhook | ⚠️ Deshabilitada temporalmente |

---

## Tabla de contenidos

1. [Diagnóstico: por qué no estás convirtiendo](#diagnóstico)
2. [Fase 1 — Demo Tenant](#fase-1--demo-tenant)
3. [Fase 2 — Funnel WhatsApp + Bot IA](#fase-2--funnel-whatsapp--bot-ia)
4. [Fase 3 — Facebook Ads optimizado](#fase-3--facebook-ads-optimizado)
5. [Fase 4 — Cierre y seguimiento](#fase-4--cierre-y-seguimiento)
6. [Fase 5 — Escalado](#fase-5--escalado)
7. [Métricas y semáforos](#métricas-y-semáforos)
8. [Expansión a múltiples productos](#expansión-a-múltiples-productos-futuro)

---

## Diagnóstico

### El problema real (no es el ad)

```
LEAD entra al WhatsApp
        ↓
Bot/tú manda bloque con 4 planes + precios + límites + emojis
        ↓
Lead se abruma → cierra WhatsApp
        ↓
Nunca más responde
```

El anuncio **sí funciona**: S/7.10 por conversación es barato para SaaS B2B.
El problema estaba en los primeros 30 segundos de conversación.

### Los 3 errores que mataban la venta

| Error | Por qué mataba la venta |
|---|---|
| Mostrar todos los precios de golpe | El cerebro ve "S/99.90" antes de entender el valor → fuga inmediata |
| No hacer preguntas primero | Sin saber el dolor del prospecto, no puedes conectar features con problemas reales |
| Mandar al Free sin pelear | Los prospectos leen "Free" y sienten que no vale la pena pagar. Nunca hacen upgrade |

---

## Fase 1 — Demo Tenant

**Estado: ✅ COMPLETO**

### Lo que se hizo

- Tenant `demo` creado en producción con slug `demo`
- URL pública: `demo.orvae.pe`
- Credenciales públicas: `demo@vetsaas.pe` / `demo1234`
- Plan Pro (muestra todos los módulos al prospecto)
- 12 pacientes reales (Max, Luna, Rocky, Toby, Mia, Pelusa, Bruno, Nala, Simba, Kira, Titán, Cleo)
- 10 propietarios con datos peruanos
- Historial clínico con consultas cerradas
- Citas programadas para la semana actual
- Caja con ventas de los últimos 7 días
- Stock de productos

### Reset automático

```bash
# Corre solo cada noche a las 3:00 a.m. (scheduler activo)
php artisan vetsaas:reset-demo

# También restaura la contraseña a "demo1234" si alguien la cambió
```

### Seeder (solo si necesitas recrear el tenant desde cero)

```bash
php artisan db:seed --class=DemoTenantsSeeder  # crea estructura
php artisan db:seed --class=DemoDataSeeder      # carga datos clínicos
```

---

## Fase 2 — Funnel WhatsApp + Bot IA

**Estado: ✅ COMPLETO**

### El flujo correcto (automatizado con IA)

```
LEAD escribe desde el anuncio de Facebook
        ↓
Mensaje de bienvenida automático de Meta:
"¿Cómo llevas hoy el control de tu clínica?"
        ↓
LEAD responde → OpenWA recibe → Bot IA detecta trigger de VetSaaS
        ↓
Bot hace preguntas, conecta dolor con feature, ofrece demo
        ↓
Si LEAD pregunta precio → Bot recomienda solo el plan que aplica
        ↓
Si LEAD quiere demo → Bot da credenciales de demo.orvae.pe
        ↓
Rodrigo ve la conversación en Plataforma → Conversaciones bot
        ↓
Si quiere tomar el control → Pausa el bot con un clic
```

### Componentes técnicos

| Componente | Descripción |
|---|---|
| `SalesBotWebhookController` | Recibe mensajes de OpenWA, detecta triggers |
| `SalesBotService` | Genera respuestas con gpt-4o-mini |
| `SalesBotKnowledge` | Base de conocimiento editable desde la plataforma |
| `SalesConversation` | Registro de cada lead con `bot_active` flag |
| Panel de conversaciones | Vista web con pausa/resume por lead, auto-refresh 15s |

### Lógica de activación del bot

| Situación | Qué hace el bot |
|---|---|
| Lead nuevo escribe sobre VetSaaS | Se activa y responde |
| Lead nuevo escribe algo sin relación | Silencio |
| Bot activo, lead escribe cualquier cosa | Responde normalmente |
| Rodrigo pausa el bot, lead escribe "ok gracias" | Silencio |
| Rodrigo pausa el bot, lead vuelve a preguntar sobre VetSaaS | **Bot se reactiva solo** |

### Cómo pausar/reanudar el bot

1. Entra a **Plataforma → Conversaciones bot** (funciona desde el celular)
2. Busca el lead por nombre o teléfono
3. Clic en **Pausar** → tú escribes manualmente en WhatsApp
4. Clic en **Reanudar** → el bot retoma la conversación

### Base de conocimiento

Editable desde **Plataforma → Bot de ventas**:
- Módulos y features del sistema
- FAQs frecuentes
- Manejo de objeciones
- Los precios se leen automáticamente desde la tabla de Planes (no hay que duplicarlos)

### Respuestas rápidas en WhatsApp Business (para uso manual)

Úsalas cuando el bot esté pausado y tú escribas directamente:

**`/bienvenida`**
```
Hola 👋 gracias por escribir sobre VetSaaS.
Cuéntame, ¿cómo llevas hoy el control de tu clínica? ¿En papel, Excel u otro sistema?
```

**`/dolor-papel`**
```
Entiendo, la mayoría empieza así. El problema es que en papel no puedes ver el historial completo de un paciente en segundos ni saber cuántos pacientes vienen esta semana.
¿Quieres verlo funcionando en vivo? Te muestro 10 min.
```

**`/dolor-excel`**
```
El Excel funciona al principio, pero cuando empiezas a crecer se vuelve caótico: archivos perdidos, sin acceso desde el celular, sin historial de vacunas.
¿Te muestro cómo VetSaaS resuelve eso en 10 minutos?
```

**`/demo`**
```
Perfecto. Puedes entrar ahora mismo con estas credenciales:
🌐 demo.orvae.pe
👤 demo@vetsaas.pe
🔑 demo1234
Entra y cuéntame: ¿qué módulo te llama más la atención?
```

**`/precio`**
```
Depende de tu clínica. La mayoría empieza con el plan Starter a S/39.90/mes: historial clínico, citas y caja desde el primer día.
¿Tienes más de 150 pacientes al mes?
```

**`/cierre`**
```
¿Quieres que te lo muestre en vivo? Son 10 minutos por videollamada de WhatsApp y ves todo funcionando real.
¿Cuándo tienes un momento hoy o mañana?
```

---

## Fase 3 — Facebook Ads optimizado

**Estado: ✅ ACTIVO**

### Configuración actual de la campaña

| Parámetro | Valor |
|---|---|
| Nombre | VetSaaS — WhatsApp Leads Perú |
| Objetivo | Interacción → Mensajes WhatsApp |
| Presupuesto | S/25/día |
| País | Perú |
| Edad | 28+ |
| Intereses | Veterinary physician, Veterinaria (servicios para mascotas), Mascotas |
| Público | Amplio con Advantage+ |
| Imagen | WhatsApp I... 1280×1600 |

### Texto del anuncio

```
¿Sigues llevando el historial de tus pacientes en papel o Excel?

Cuando llega una emergencia y no encuentras el historial del paciente,
pierdes tiempo, credibilidad y dinero.

VetSaaS centraliza todo: historial clínico, citas, caja y WhatsApp
automático — desde S/0, sin contrato.

👇 Escríbenos y te mostramos cómo en 10 min.
```

### Mensaje de bienvenida automático (configurado en Meta)

```
Hola 👋 gracias por escribir sobre VetSaaS.
```

### Mensaje predefinido que el lead envía

```
¿Cómo llevas hoy el control de tu clínica veterinaria? ¿En papel, Excel u otro sistema?
```

### Proyección con funnel correcto

| | Antes | Con el bot |
|---|---|---|
| Gasto mensual | S/600 | S/750 |
| Conversaciones | ~85 | ~105 |
| Conversión | 1.2% | ~15% |
| Clientes/mes | 1 | ~15 |
| Costo por cliente | S/564 | ~S/50 |

### Cuándo subir el presupuesto

Sube a S/40/día cuando la tasa conversación→cliente supere el 10%.
Mide a los 14 días de la campaña activa.

---

## Fase 4 — Cierre y seguimiento

**Estado: ⏳ PENDIENTE**

### Los 157 leads que ya tienes (reactivación)

Tienes 157 conversaciones que no convirtieron. No las pierdas.
Esta semana, manda este mensaje manualmente a todos desde WhatsApp:

```
Hola [nombre], hace unos días me escribiste sobre VetSaaS.

Puedes probar el sistema ahora mismo:

🌐 demo.orvae.pe
👤 demo@vetsaas.pe
🔑 demo1234

¿Qué te parece?
```

Con 157 leads, si el 10% responde = 15 conversaciones nuevas.
Si el 20% de esas convierte = 3 clientes más **sin gastar un sol en ads**.

### Secuencia de seguimiento (los 5 días del cierre)

| Día | Qué haces |
|---|---|
| Día 1 | Manda el demo, pregunta qué módulo le llamó la atención |
| Día 2 | Si no respondió: *"¿Pudiste entrar al demo?"* |
| Día 3 | Si sí respondió: agendar videollamada de 10 min |
| Día 5 | Videollamada → cierre con propuesta específica |
| Día 7 | Si no cerró: *"El plan Free sigue disponible para que lo uses sin presión."* |

### Cómo cerrar en la videollamada de 10 minutos

**Minuto 0-2:** Escuchar. "Cuéntame cómo funciona tu clínica hoy."

**Minuto 2-5:** Mostrar solo los módulos que resuelven SU dolor.
Si dijo "me cuesta organizar las citas" → módulo de citas.
Si dijo "no sé cuánto gano al mes" → caja y reportes.

**Minuto 5-8:** Hacer la pregunta del cierre:
*"¿Ves cómo esto resuelve [su dolor]? ¿Qué te falta para empezar hoy mismo?"*

**Minuto 8-10:** Manejar la objeción:
- "Es caro" → *"¿Cuánto pierdes al mes por no tener esto organizado? El Starter son S/39.90."*
- "Déjame pensarlo" → *"Entiendo. ¿Qué información te falta para decidir?"*
- "Voy a consultar" → *"¿Con quién? Puedo hablar con esa persona también si quieres."*

---

## Fase 5 — Escalado

**Cuando tengas 10+ clientes pagos**, hacer esto:

### Testimonios (el activo más valioso)

Pídele a cada cliente:
*"¿Cómo era tu clínica antes de VetSaaS y cómo es ahora?"*

Eso se convierte en:
- Video testimonial para Facebook Ads (versión C del copy)
- Historia de Instagram
- Caso de éxito en el landing page

### Referidos (el canal más barato)

Ofrece a tus clientes actuales: **1 mes gratis por cada referido que pague.**

### Comunidades veterinarias en Perú

Grupos de Facebook, foros de veterinarios, asociaciones gremiales.
Entra, participa con valor, y cuando alguien mencione un problema que VetSaaS resuelve → ahí entras.

### SEO de largo plazo

Crear contenido en el blog/landing sobre:
- "Cómo organizar una clínica veterinaria en Perú"
- "Software para veterinarias gratis en Perú"
- "Historial clínico veterinario digital"

---

## Métricas y semáforos

Mide esto cada semana:

| Métrica | Rojo | Amarillo | Verde |
|---|---|---|---|
| Tasa de respuesta al primer mensaje | < 20% | 20-40% | > 40% |
| Tasa de conversación → demo | < 5% | 5-15% | > 15% |
| Tasa de demo → videollamada | < 10% | 10-25% | > 25% |
| Tasa de videollamada → pago | < 20% | 20-40% | > 40% |
| Costo por cliente | > S/300 | S/100-300 | < S/100 |

**Benchmark inicial (antes del bot):**
- Conversación → cliente: 1.2% (rojo)
- Costo por cliente: ~S/565 (rojo)

**Objetivo a 60 días:**
- Conversación → cliente: 15%+ (verde)
- Costo por cliente: < S/100 (verde)

---

## Pendientes inmediatos

- [x] ~~Enviar mensaje de reactivación a los 157 leads muertos~~ → Sistema automático activo
- [ ] Importar los ~157 leads históricos al CSV y subirlos (máx 20/día se reactivarán solos)
- [ ] Medir resultados del ad a los 14 días y ajustar si la conversión < 10%
- [ ] Reactivar verificación HMAC cuando OpenWA confirme el header que usa
- [ ] Subir presupuesto del ad a S/40/día cuando conversión > 10%
- [ ] Recolectar primer testimonio de cliente cuando llegues a 5 clientes pagos (Fase 5)

---

## Expansión a múltiples productos (futuro)

Orvae tiene varios SaaS. El bot actual solo cubre VetSaaS.
Para agregar nuevos productos sin múltiples números de celular:

### Opción A — Múltiples rutas webhook (recomendada)

```
POST /api/webhooks/sales-bot               → VetSaaS (activo)
POST /api/webhooks/sales-bot/aula-virtual  → futuro
POST /api/webhooks/sales-bot/inventario    → futuro
```

### Cómo agregar un nuevo producto

1. Agregar la ruta en `routes/api.php` (ver comentarios TODO)
2. Agregar el system prompt en `SalesBotService::buildSystemPrompt()`
3. Registrar el webhook en OpenWA apuntando a la nueva ruta
4. Configurar el mensaje de bienvenida del ad de Facebook con el producto

---

*Última actualización: junio 2026*
*Estado: Sistema completo activo en producción — bot IA + demo + ads corriendo*
