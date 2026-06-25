# Plan de Ventas VetSaaS — De 2 clientes a 20+

> **Situación actual:** S/1,129.24 gastados en Facebook Ads → 159 conversaciones → 2 clientes Pro.
> Costo por cliente: S/564. Conversión: 1.2%. Esto es un problema de funnel, no de producto.
>
> **Objetivo:** Bajar el costo por cliente a menos de S/100 y subir la conversión al 15-20%
> sin aumentar el presupuesto publicitario.

---

## Tabla de contenidos

1. [Diagnóstico: por qué no estás convirtiendo](#diagnóstico)
2. [Fase 1 — Demo Tenant (técnico)](#fase-1--demo-tenant)
3. [Fase 2 — Funnel WhatsApp](#fase-2--funnel-whatsapp)
4. [Fase 3 — Facebook Ads optimizado](#fase-3--facebook-ads-optimizado)
5. [Fase 4 — Cierre y seguimiento](#fase-4--cierre-y-seguimiento)
6. [Fase 5 — Escalado](#fase-5--escalado)
7. [Métricas y semáforos](#métricas-y-semáforos)

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
El problema está en los primeros 30 segundos de conversación.

### Los 3 errores que matan la venta

| Error | Por qué mata la venta |
|---|---|
| Mostrar todos los precios de golpe | El cerebro ve "S/99.90" antes de entender el valor → fuga inmediata |
| No hacer preguntas primero | Sin saber el dolor del prospecto, no puedes conectar features con problemas reales |
| Mandar al Free sin pelear | Los prospectos leen "Free" y sienten que no vale la pena pagar. Nunca hacen upgrade |

---

## Fase 1 — Demo Tenant

**Tiempo estimado:** 2-3 horas | **Costo:** S/0

### 1.1 Qué necesitas crear

Un tenant con slug `demo` que tenga credenciales públicas y datos realistas.
El prospecto entra y ve una **clínica que ya funciona**, no un sistema vacío.

**Credenciales públicas a publicar:**
```
URL:      demo.orvae.pe (o demo.vetsaas.pe)
Usuario:  demo@vetsaas.pe
Clave:    demo1234
```

**Plan del tenant demo:** Pro (para mostrar todos los módulos al prospecto).
> Si el tenant demo está en Free, el prospecto ve un sistema limitado y piensa
> que así funciona siempre. Muéstrale el mejor escenario posible.

### 1.2 Agregar al DemoTenantsSeeder

En `database/seeders/DemoTenantsSeeder.php`, agregar esta entrada al array `TENANTS`:

```php
[
    'slug'             => 'demo',
    'nombre_comercial' => 'Clínica Veterinaria Demo',
    'razon_social'     => 'VetSaaS Demo SAC',
    'ruc'              => '20999999999',
    'color_primario'   => '#1F6F43',
    'color_secundario' => '#94C7A8',
    'email_admin'      => 'demo@vetsaas.pe',
    'password'         => 'demo1234',
    'must_change_password' => false,
],
```

> El seeder ya tiene la lógica de `updateOrCreate`: si corres el seeder
> de nuevo, no duplica — solo actualiza.

### 1.3 Datos que tiene que tener el tenant demo

Estos datos hacen que el prospecto se imagine su propia clínica:

#### Pacientes (mínimo 20)
```
Max        — Golden Retriever, 3 años, propietario: Carlos Ríos
Luna       — Gato Persa, 2 años,      propietario: María Torres
Rocky      — Bulldog Francés, 1 año,  propietario: José Ramírez
Toby       — Beagle, 5 años,          propietario: Ana Flores
Mia        — Siames, 4 años,          propietario: Luis Mendoza
Pelusa     — Cocker Spaniel, 2 años,  propietario: Carmen Vega
Bruno      — Labrador, 6 años,        propietario: Pedro Castillo
Nala       — Shih Tzu, 1 año,         propietario: Sofía Paredes
... (completar hasta 20)
```

#### Historial clínico (por lo menos 3 consultas cerradas)
```
Max — Consulta de control + vacuna rabia + receta antiparasitario
Luna — Castración + seguimiento post-op + alta
Rocky — Consulta dermatológica + plan tratamiento 2 semanas
```

#### Citas agendadas (esta semana)
```
Lunes 09:00  — Toby / Control anual
Lunes 11:30  — Mia / Esterilización
Martes 10:00 — Pelusa / Vacuna múltiple
Miércoles 15:00 — Bruno / Corte de uñas (Grooming)
Jueves 09:30 — Nala / Primera consulta
```

#### Caja (últimos 7 días)
```
Consultas: S/480
Vacunas:   S/180
Grooming:  S/120
Medicamentos: S/95
Total semana: S/875
```

#### Stock (10-15 productos)
```
Vacuna Rabia 1ml         — Stock: 24 unidades
Antiparasitario Drontal  — Stock: 18 unidades
Amoxicilina 250mg        — Stock: 30 unidades
Ivermectina 1%           — Stock: 12 unidades
Guantes descartables     — Stock: 50 unidades
...
```

### 1.4 Comandos para correr los seeders

```bash
# 1. Crear el tenant demo (estructura + usuario demo@vetsaas.pe)
php artisan db:seed --class=DemoTenantsSeeder

# 2. Poblar con datos clínicos realistas
php artisan db:seed --class=DemoDataSeeder
```

Para resetear el demo en cualquier momento (útil para limpieza periódica):

```bash
# Solo recarga la data clínica (pacientes, citas, caja...) sin tocar estructura
php artisan db:seed --class=DemoDataSeeder
```

### 1.5 Checklist antes de publicar las credenciales

- [ ] El tenant demo existe en producción
- [ ] Puedes entrar con `demo@vetsaas.pe` / `demo1234`
- [ ] Se ven pacientes, citas, historial y caja con datos
- [ ] La URL pública funciona: `demo.vetsaas.pe` o como la tengas configurada
- [ ] Tienes un job/comando que resetea los datos del demo cada 24h
      (para que nadie borre todo y lo deje vacío para el próximo visitante)

---

## Fase 2 — Funnel WhatsApp

**Tiempo estimado:** 1 hora | **Costo:** S/0

### 2.1 El flujo correcto (4 pasos)

```
LEAD escribe "Hola quiero info"
        ↓
PASO 1: Tú preguntas su situación actual (nunca mandas precios)
        ↓
PASO 2: Conectas UNA feature con SU dolor específico
        ↓
PASO 3: Ofreces el demo (credenciales o videollamada 10 min)
        ↓
PASO 4: Cierras con plan específico o con seguimiento al día siguiente
```

### 2.2 Respuestas rápidas en WhatsApp Business

Ve a **Ajustes → Herramientas de negocio → Respuestas rápidas**.
Crea estas 6 plantillas (se activan escribiendo `/` + el nombre):

---

**`/bienvenida`** — Primer mensaje siempre
```
Hola [nombre] 👋 gracias por escribir sobre VetSaaS.

Cuéntame, ¿cómo llevas hoy el control de tu clínica?
¿En papel, Excel u otro sistema?
```

---

**`/dolor-papel`** — Si dice "en papel" o "manual"
```
Entiendo, la mayoría empieza así. El problema es que
en papel no puedes ver el historial completo de un
paciente en segundos ni saber cuántos pacientes
vienen esta semana.

¿Quieres verlo funcionando en vivo? Te muestro 10 min.
```

---

**`/dolor-excel`** — Si dice "en Excel"
```
El Excel funciona al principio, pero cuando empiezas
a crecer se vuelve caótico: archivos perdidos, sin
acceso desde el celular, sin historial de vacunas.

¿Te muestro cómo VetSaaS resuelve eso en 10 minutos?
```

---

**`/demo`** — Cuando aceptan ver el sistema
```
Perfecto. Puedes entrar ahora mismo con estas credenciales:

🌐 demo.orvae.pe
👤 demo@vetsaas.pe
🔑 demo1234

Entra y cuéntame: ¿qué módulo te llama más la atención?
```

---

**`/precio`** — Solo cuando preguntan precio
```
Depende de tu clínica. La mayoría empieza con el
plan Starter a S/39.90/mes: historial clínico,
citas y caja desde el primer día, 1 sede, 150 pacientes.

¿Tienes más de 150 pacientes al mes?
```

---

**`/cierre`** — Para agendar videollamada
```
¿Quieres que te lo muestre en vivo? Son 10 minutos
por videollamada de WhatsApp y ves todo funcionando real.

¿Cuándo tienes un momento hoy o mañana?
```

---

### 2.3 Reglas de oro en cada conversación

1. **Nunca menciones precios antes del mensaje 4** — Sin conocer su dolor, el precio es solo un número que asusta
2. **Siempre termina con una pregunta** — Sin pregunta, la conversación muere
3. **Cuando digan "demo" o "ver el sistema"** → manda `/demo` + llámalo por WhatsApp en ese momento
4. **Cuando digan "Free"** → no lo dejes ir, di: *"El Free es para conocer el sistema. ¿Cuántos pacientes atiendes al mes? Quizás el Starter te funciona mejor y cuesta menos que un almuerzo al mes."*
5. **Si no responden en 24h** → manda: *"Hola [nombre], ¿pudiste entrar al demo? Cuéntame qué te pareció."*

---

## Fase 3 — Facebook Ads optimizado

**Tiempo estimado:** 30 min | **Costo:** el que ya tienes**

### 3.1 Pausa el ad actual

**HOY**, antes de hacer cualquier otra cosa, baja el presupuesto a S/10/día.
No tiene sentido seguir pagando S/7.10 por conversación con el funnel roto.
Cuando el funnel esté listo, lo vuelves a subir.

### 3.2 Cambiar el mensaje de bienvenida del anuncio

En **Meta Ads Manager → tu campaña → Mensaje instantáneo de bienvenida**, cambia todo el bloque de precios por esto:

```
Hola 👋 gracias por escribir.

Una pregunta rápida: ¿cómo llevas hoy el control
de tu clínica veterinaria? ¿En papel, Excel
u otro sistema?
```

Eso es todo. Sin planes, sin precios, sin emojis de cohetes.

### 3.3 Agregar botones de respuesta rápida en el ad

En la configuración del mensaje instantáneo, activa las **respuestas sugeridas**:

```
Botón 1: "En papel o cuaderno"
Botón 2: "En Excel"
Botón 3: "Ya uso un sistema"
Botón 4: "Ver demo gratis"
```

Cada botón que tocan te dice exactamente qué decirles. Sin adivinar.

### 3.4 Segmentación del ad (revisar)

**Audience actual:** revisar que esté apuntando a:
- Perú (Lima principalmente, luego expandir)
- Edad: 28-55
- Intereses: veterinaria, mascotas, clínica veterinaria, negocio, emprendimiento
- **Excluir:** empleados de veterinaria (quieres dueños, no empleados)

**Objetivo de campaña:** cambiar de "Conversaciones" a **"Leads"** si empiezas a usar formulario.
Por ahora, mantener conversaciones pero con el mensaje correcto.

### 3.5 Copys de anuncio que convierten mejor

**Versión A (dolor):**
```
¿Sigues llevando el historial de tus pacientes
en papel o Excel?

Cada vez que un paciente llega de emergencia
y no encuentras su historial, pierdes tiempo,
confianza y dinero.

VetSaaS centraliza todo: historial, citas,
caja y WhatsApp automático desde S/0.

👇 Escríbenos y te mostramos cómo en 10 minutos.
```

**Versión B (beneficio):**
```
Las clínicas veterinarias que usan VetSaaS
atienden 40% más pacientes con el mismo equipo.

¿Por qué? Porque no pierden tiempo buscando
historiales, anotando citas a mano ni calculando
la caja al final del día.

Todo está en una pantalla.

👇 Prueba gratis, sin tarjeta.
```

**Versión C (social proof — cuando tengas más clientes):**
```
"Antes anotaba todo en un cuaderno. Ahora
veo el historial de cualquier paciente
en 5 segundos desde el celular."
— [Nombre], Clínica Veterinaria [Ciudad]

VetSaaS: el sistema que usan las clínicas
veterinarias que quieren crecer sin caos.

👇 Pruébalo gratis hoy.
```

---

## Fase 4 — Cierre y seguimiento

**Tiempo estimado:** 15 min/día | **Costo:** S/0

### 4.1 Los 157 leads que ya tienes (reactivación)

Tienes 157 conversaciones que no convirtieron. No las pierdas.
Esta semana, manda este mensaje a todos:

```
Hola [nombre], hace unos días me escribiste
sobre VetSaaS.

¿Pudiste ver el sistema? Te comparto acceso
de prueba para que lo explores tú mismo:

🌐 demo.orvae.pe
👤 demo@vetsaas.pe
🔑 demo1234

¿Qué te pareció?
```

Con 157 leads, si el 10% responde = 15 conversaciones nuevas.
Si el 20% de esas convierte = 3 clientes más sin gastar un sol en ads.

### 4.2 Secuencia de seguimiento (los 5 días del cierre)

| Día | Qué haces |
|---|---|
| Día 1 | Manda el demo, pregunta qué módulo le llamó la atención |
| Día 2 | Si no respondió: *"¿Pudiste entrar al demo?"* |
| Día 3 | Si sí respondió: agendar videollamada de 10 min |
| Día 5 | Videollamada → cierre con propuesta específica |
| Día 7 | Si no cerró: *"El plan Free sigue disponible para que lo uses sin presión."* |

### 4.3 Cómo cerrar en la videollamada de 10 minutos

**Minuto 0-2:** Escuchar. "Cuéntame cómo funciona tu clínica hoy."

**Minuto 2-5:** Mostrar solo los módulos que resuelven SU dolor específico.
Si dijo "me cuesta organizar las citas" → muéstrale el módulo de citas.
Si dijo "no sé cuánto gano al mes" → muéstrale la caja y reportes.

**Minuto 5-8:** Hacer la pregunta del cierre:
*"¿Ves cómo esto resuelve [su dolor específico]? ¿Qué te falta para empezar hoy mismo?"*

**Minuto 8-10:** Manejar la objeción que dé:
- "Es caro" → *"¿Cuánto pierdes al mes por no tener esto organizado? El Starter son S/39.90."*
- "Déjame pensarlo" → *"Entiendo. ¿Qué información te falta para decidir?"*
- "Voy a consultar" → *"¿Con quién? Puedo hablar con esa persona también si quieres."*

---

## Fase 5 — Escalado

**Cuando tengas 10+ clientes pagos**, hacer esto:

### 5.1 Testimonios (el activo más valioso)

Pídele a cada cliente que te mande un audio o texto corto:
*"¿Cómo era tu clínica antes de VetSaaS y cómo es ahora?"*

Eso se convierte en:
- Video testimonial para Facebook Ads (versión C del copy)
- Historia de Instagram
- Caso de éxito en el landing page

### 5.2 Referidos (el canal más barato)

Ofrece a tus clientes actuales: **1 mes gratis por cada referido que pague.**
Un cliente satisfecho en una red de veterinarios puede traerte 5 clientes más.

### 5.3 Comunidades veterinarias en Perú

Grupos de Facebook, foros de veterinarios, asociaciones gremiales.
Entra, participa con valor (no solo con publicidad), y cuando alguien mencione
un problema que VetSaaS resuelve → ahí entras a dar la solución.

### 5.4 SEO de largo plazo

Crear contenido en el blog/landing sobre:
- "Cómo organizar una clínica veterinaria en Perú"
- "Software para veterinarias gratis en Perú"
- "Historial clínico veterinario digital"

Tráfico orgánico = leads a costo S/0.

---

## Métricas y semáforos

Mide esto cada semana para saber si vas bien:

| Métrica | Rojo | Amarillo | Verde |
|---|---|---|---|
| Tasa de respuesta al primer mensaje | < 20% | 20-40% | > 40% |
| Tasa de conversación → demo | < 5% | 5-15% | > 15% |
| Tasa de demo → videollamada | < 10% | 10-25% | > 25% |
| Tasa de videollamada → pago | < 20% | 20-40% | > 40% |
| Costo por cliente | > S/300 | S/100-300 | < S/100 |

**Benchmark actual:**
- Conversación → cliente: 1.2% (rojo)
- Costo por cliente: ~S/565 (rojo)

**Objetivo a 60 días:**
- Conversación → cliente: 15%+ (verde)
- Costo por cliente: < S/100 (verde)

---

## Orden de ejecución (priorizado)

```
SEMANA 1 — Base
  ✅ Pausar o bajar el ad a S/10/día
  ✅ Crear tenant demo con datos realistas
  ✅ Configurar credenciales demo@vetsaas.pe / demo1234
  ✅ Crear las 6 respuestas rápidas en WhatsApp Business
  ✅ Cambiar el mensaje de bienvenida del ad en Meta

SEMANA 1 — Reactivación
  ✅ Enviar mensaje de reactivación a las 157 conversaciones muertas
  ✅ Hacer seguimiento a los que respondan (secuencia de 5 días)

SEMANA 2 — Optimizar
  ✅ Agregar botones de respuesta al mensaje del ad
  ✅ Probar los 3 copys del anuncio (A/B test)
  ✅ Agendar y hacer videollamadas de demo con leads calientes

SEMANA 3-4 — Escalar
  ✅ Subir presupuesto del ad cuando la conversión sea > 10%
  ✅ Pedir primer testimonio a cliente satisfecho
  ✅ Lanzar programa de referidos
```

---

## Técnico: DemoDataSeeder (pendiente de crear)

Archivo a crear: `database/seeders/DemoDataSeeder.php`

Responsabilidades:
- Conectarse al schema `vet_demo`
- Insertar propietarios y pacientes con datos realistas
- Insertar consultas cerradas con historial clínico
- Insertar citas de la semana actual
- Insertar movimientos de caja de los últimos 7 días
- Insertar productos en stock
- Ser **idempotente**: si se corre de nuevo, primero limpia y recarga
- Ser llamado por un `schedule` diario para mantener el demo fresco

```bash
# Comando para regenerar el demo cuando quieras
php artisan vetsaas:reset-demo
```

---

*Última actualización: junio 2026*
*Estado: en ejecución — Fase 1 en progreso*
