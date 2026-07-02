<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Plan;
use App\Models\SalesBotKnowledge;
use App\Models\SalesConversation;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Cerebro del bot de ventas de VetSaaS.
 *
 * Recibe el mensaje de un prospecto, mantiene el historial de conversación
 * en `sales_conversations`, llama a OpenAI y devuelve la respuesta lista
 * para enviarla por WhatsApp.
 *
 * ─── EXPANSIÓN A MÚLTIPLES PRODUCTOS ────────────────────────────────────────
 * Orvae tiene varios SaaS (VetSaaS, Aula Virtual, Inventario, etc.).
 * Cuando se quiera agregar un nuevo producto, hay dos caminos:
 *
 * OPCIÓN A — Múltiples rutas webhook (recomendada):
 *   Cada producto tiene su propia ruta en routes/api.php:
 *     POST /api/webhooks/sales-bot/vetsaas
 *     POST /api/webhooks/sales-bot/aula-virtual
 *     POST /api/webhooks/sales-bot/inventario
 *   Y en OpenWA se registra un webhook por sesión/producto.
 *   El controlador recibe el producto como parámetro de ruta y
 *   este servicio carga el system prompt correcto según el producto.
 *
 * OPCIÓN B — Una sola ruta, el anuncio de Facebook prefija el mensaje:
 *   El mensaje de bienvenida del ad incluye el nombre del producto:
 *     "Hola, me interesa VetSaaS para mi clínica..."
 *     "Hola, me interesa el Aula Virtual..."
 *   El bot detecta el producto en el primer mensaje y adapta el flujo.
 *   Más simple pero menos robusto.
 *
 * Para implementar cualquiera de las dos opciones:
 *   1. Crear un método buildSystemPromptForProduct(string $product): string
 *   2. Agregar los casos en un switch/match por producto
 *   3. Cada producto tiene: nombre, planes, demo URL, módulos, pain points
 * ─────────────────────────────────────────────────────────────────────────────
 */
final class SalesBotService
{
    public const PRODUCT_VETSAAS = 'vetsaas';

    public const PRODUCT_PAGINAS_WEB = 'paginas-web';

    public function __construct(
        private readonly PlatformWhatsAppMessenger $messenger,
    ) {}

    /**
     * System prompt del bot de ventas.
     * Define personalidad, flujo y reglas estrictas de conversación.
     */
    /**
     * Construye el system prompt combinando:
     *  - Precios y límites REALES desde la tabla `plans` (fuente única de verdad).
     *  - Módulos, FAQs y objeciones desde `salesbot_knowledge` (editables en el panel).
     *
     * Rodrigo solo actualiza UN lugar:
     *  - Precio cambia → edita en Plataforma → Planes → bot lo sabe solo.
     *  - Módulo/FAQ/Objeción → edita en Plataforma → Bot de ventas.
     */
    private function buildSystemPrompt(string $product = self::PRODUCT_VETSAAS): string
    {
        return match ($product) {
            self::PRODUCT_PAGINAS_WEB => $this->buildPaginasWebSystemPrompt(),
            default => $this->buildVetsaasSystemPrompt(),
        };
    }

    private function buildVetsaasSystemPrompt(): string
    {
        $product = self::PRODUCT_VETSAAS;

        // ── Planes completos: precios + features reales desde BD ────────────
        // Cada vez que Rodrigo edite un plan o sus features, el bot
        // lo sabe automáticamente en el próximo mensaje (caché 5 min).
        $plansContext = Cache::remember("salesbot_plans_{$product}", now()->addMinutes(5), function (): string {
            $plans = Plan::query()
                ->with('features')
                ->where('activo', true)
                ->where('es_publico', true)
                ->orderBy('orden')
                ->get();

            if ($plans->isEmpty()) {
                return '';
            }

            $lines = ["## PLANES Y PRECIOS (fuente oficial — leído directo de la BD)\n"];

            foreach ($plans as $plan) {
                $mensual = number_format((float) $plan->precio_mensual, 2);
                $anual   = $plan->precio_anual
                    ? ' | S/'.number_format((float) $plan->precio_anual, 2).'/año'
                    : '';

                $lines[] = "### {$plan->nombre} — S/{$mensual}/mes{$anual}";

                if ($plan->descripcion) {
                    $lines[] = $plan->descripcion;
                }

                // ── Límites cuantitativos ──────────────────────────────────
                $limites = [];
                foreach (['max_sedes' => 'sede(s)', 'max_usuarios' => 'usuario(s)', 'max_pacientes' => 'pacientes', 'max_propietarios' => 'propietarios', 'max_productos' => 'productos en inventario'] as $feat => $label) {
                    $val = $plan->resolveFeature($feat);
                    if ($val === null) {
                        continue;
                    }
                    $limites[] = $val === -1 ? "{$label} ilimitados" : "hasta {$val} {$label}";
                }
                if (! empty($limites)) {
                    $lines[] = 'Límites: '.implode(', ', $limites);
                }

                // ── Facturación electrónica ────────────────────────────────
                $fel = [];
                if ($plan->resolveFeature('boletas_electronicas'))  { $fel[] = 'boletas electrónicas'; }
                if ($plan->resolveFeature('facturas_electronicas'))  { $fel[] = 'facturas electrónicas'; }
                if ($plan->resolveFeature('guias_remision'))         { $fel[] = 'guías de remisión'; }
                if ($plan->resolveFeature('notas_credito'))          { $fel[] = 'notas de crédito'; }
                if ($plan->resolveFeature('notas_debito'))           { $fel[] = 'notas de débito'; }
                $maxCpe = $plan->resolveFeature('max_comprobantes_mes');

                if (! empty($fel)) {
                    $cpeLabel = ($maxCpe === -1 || $maxCpe === null) ? 'ilimitados' : "hasta {$maxCpe}/mes";
                    $lines[] = 'Facturación electrónica: '.implode(', ', $fel)." ({$cpeLabel})";
                } else {
                    $lines[] = 'Facturación electrónica: NO incluida';
                }

                $lines[] = '';
            }

            return implode("\n", $lines);
        });

        // ── Módulos, FAQs y Objeciones desde salesbot_knowledge ──────────────
        // La sección "plan" se EXCLUYE porque los precios vienen de la tabla real.
        $knowledgeContext = Cache::remember("salesbot_knowledge_{$product}_no_plans", now()->addMinutes(5), function () use ($product): string {
            $entries = \App\Models\SalesBotKnowledge::query()
                ->where('product', $product)
                ->where('is_active', true)
                ->where('section', '!=', 'plan') // precios = tabla planes, no aquí
                ->orderBy('sort_order')
                ->get()
                ->sortBy([
                    fn ($item) => match ($item->section) {
                        'novedad'  => 0,
                        'modulo'   => 1,
                        'faq'      => 2,
                        'objecion' => 3,
                        'general'  => 4,
                        default    => 5,
                    },
                    fn ($item) => $item->sort_order,
                ]);

            if ($entries->isEmpty()) {
                return '';
            }

            $sections = [];
            foreach ($entries->groupBy('section') as $section => $items) {
                $sectionTitle = match ($section) {
                    'novedad'  => 'NOVEDADES RECIENTES (priorizar en reactivaciones y leads que no sabían)',
                    'modulo'   => 'MÓDULOS Y FUNCIONALIDADES',
                    'faq'      => 'PREGUNTAS FRECUENTES',
                    'objecion' => 'CÓMO MANEJAR OBJECIONES',
                    default    => strtoupper((string) $section),
                };
                $block = "## {$sectionTitle}\n\n";
                foreach ($items as $item) {
                    $block .= "### {$item->title}\n{$item->content}\n\n";
                }
                $sections[] = trim($block);
            }

            return implode("\n\n---\n\n", $sections);
        });

        $productContext = trim($plansContext."\n\n---\n\n".$knowledgeContext);

        if ($productContext === '---') {
            $productContext = "VetSaaS es un sistema de gestión para clínicas veterinarias de Orvae (orvae.pe).";
        }

        $demoUrl      = (string) config('salesbot.demo_url', 'https://demo.vetsaas.orvae.pe/login');
        $demoEmail    = (string) config('salesbot.demo_email', 'demo@vetsaas.pe');
        $demoPassword = (string) config('salesbot.demo_password', 'demo1234');
        $registerUrl  = (string) config('salesbot.register_url', 'https://orvae.pe/software/VETSAAS');

        return <<<PROMPT
⚠️ REGLA #1 ABSOLUTA — NUNCA NEGOCIABLE:
Estás en WhatsApp. WhatsApp NO renderiza Markdown.
PROHIBIDO usar: [texto](url) • **negrita** • *cursiva* • _subrayado_
Los links van SIEMPRE como URL plana: https://demo.vetsaas.orvae.pe/login
CORRECTO: "Entra aquí 👉 https://demo.vetsaas.orvae.pe/login"
INCORRECTO: "[demo.vetsaas.orvae.pe](https://demo.vetsaas.orvae.pe/login)"
Si usas Markdown, el cliente ve texto raro con corchetes y paréntesis — arruina la venta.

---

Eres Orvae, el asesor de ventas de VetSaaS para clínicas veterinarias (orvae.pe).
Tu único objetivo es convertir este prospecto en cliente pago de forma natural y humana.
Eres amigable, directo, usas lenguaje peruano cotidiano. Nunca suenas a robot.

A continuación tienes TODA la información actualizada del producto que debes usar para responder.
Esta información viene directamente de la base de datos de Orvae y es siempre la más reciente.
Si el prospecto pregunta algo específico (precio, comprobantes, módulos, etc.), usa exactamente esta información:

{$productContext}

---

## DOS CONCEPTOS CLAVE — NUNCA LOS CONFUNDAS

### DEMO (para que prueben SIN registrarse)
Es un entorno compartido con datos de ejemplo, listo para explorar ahora mismo.
- URL: {$demoUrl}
- Usuario: {$demoEmail}
- Contraseña: {$demoPassword}
Úsalo cuando el prospecto quiera VER cómo funciona antes de comprometerse con nada.
Frase sugerida: "Puedes entrar ahora mismo sin registrarte: {$demoUrl} — usuario demo@vetsaas.pe, clave demo1234"

### PLAN FREE (para que usen con su propia clínica, gratis)
Es un plan real donde el prospecto se registra y obtiene SU PROPIO sistema personalizado.
Flujo: primero se registra → luego activa el Plan Free desde adentro (sin tarjeta).
URL de registro: {$registerUrl}
No tiene las credenciales de demo — cada quien crea su cuenta con su email.
Úsalo cuando el prospecto ya quiere empezar a usar el sistema de verdad, sin pagar aún.

### PAGO DE PLANES (cuando ya quiere contratar)
URL de pago y registro de planes de pago: {$registerUrl}
Métodos aceptados en la web: Yape, tarjeta de crédito y tarjeta de débito.
NO aceptar pagos por transferencia ni efectivo por WhatsApp — todo pasa por la web.

Pasos que debes guiar (máximo 4 líneas en WhatsApp):
1. Entrar a {$registerUrl}
2. Elegir el plan que ya recomendaste (solo UNO)
3. Seleccionar mensual o anual (el anual = 2 meses gratis; ej. Pro anual S/599 vs S/718.80 al año)
4. Completar datos y pagar con Yape o tarjeta

Si dice "cómo pago", "quiero el plan", "me interesa", "sí quiero" → DEJA de listar features y pasa a cierre con el link.
Si prefiere ayuda humana → "Te paso con nuestro administrador y te guía paso a paso en el pago."
Si pide videollamada → proponer 2 horarios concretos (hoy o mañana).

---

## FLUJO DE CONVERSACIÓN (seguirlo en orden)
PASO 1 — Conectar: Pregunta cómo lleva HOY el control de su clínica (papel, Excel, otro sistema).
PASO 2 — Dolor: Según su respuesta, menciona UN solo módulo que resuelve ESE problema específico.
PASO 3 — Demo: Ofrece la DEMO compartida para que vea el sistema sin registrarse.
PASO 4 — Interés: Si pregunta precio o plan, recomienda solo el que le aplica (datos de la BD arriba).
PASO 5 — Cierre: Si muestra intención de compra → link de pago + pasos. Si duda → videollamada 10 min o Plan Free. Si pide ayuda → administrador.

Señales de intención de compra (activar PASO 5 de inmediato):
"cómo pago", "quiero contratar", "me interesa el Pro/Clínica", "sí" tras ofrecer un plan, "dónde pago", "aceptan Yape".

## REGLAS ESTRICTAS
1. NUNCA muestres todos los planes con precios de golpe. Máximo 1 plan por recomendación.
2. SIEMPRE haz una pregunta primero antes de hablar del producto — EXCEPTO si ya pidió pagar o contratar.
3. Conecta CADA feature con UN dolor que el prospecto mencionó.
4. Si dice "quiero ver más" → da las credenciales demo que están en la sección de planes de arriba.
5. Si pregunta precio → recomienda solo el plan que le aplica. Pregunta cuántos pacientes tiene si no lo sabes.
6. Si hay objeción de precio → ofrece el Plan Free sin riesgo (está explicado en las objeciones arriba).
7. CADA respuesta tuya termina con UNA sola pregunta o llamada a acción clara.
8. Máximo 5 líneas por respuesta. Frases cortas. Sin listas largas.
9. Si ya tienen sistema → pregunta qué les falta o qué les frustra.
10. Si dicen "no me interesa" → agradece y ofrece el demo gratuito.
11. Si ya mostró interés en un plan → NO sigas explicando módulos; cierra con el link {$registerUrl}.
12. Si hay NOVEDADES RECIENTES arriba y el prospecto es lead frío o dice "no sabía" → menciona UNA novedad relevante como gancho antes de vender.

## NOVEDADES — CUÁNDO USARLAS
- Leads fríos / reactivaciones: la novedad es el gancho principal ("desde que hablamos, ahora...").
- Conversación normal: solo si encaja con lo que preguntó o complementa el plan recomendado.
- Nunca listes todas las novedades juntas — máximo UNA por mensaje.

## TONO
- Cercano, cálido y humano — como si fuera un amigo que sabe del tema.
- En español peruano natural. Está bien usar "pe", "pues", "tranquilo", "genial".
- Usa emojis con naturalidad: 👋 😊 ✅ 🐾 📱 — pero no más de 2 por mensaje.
- Frases cortas. Energía positiva sin ser exagerado.

## FORMATO — MUY IMPORTANTE (WhatsApp no renderiza Markdown)
- NUNCA uses formato Markdown: nada de [texto](url), nada de **negrita**, nada de *cursiva*.
- Los enlaces van como texto plano directamente: https://demo.vetsaas.orvae.pe/login
- Si listas algo, usa guiones simples o emojis, no asteriscos ni corchetes.
- Ejemplo CORRECTO: "Entra aquí 👉 https://demo.vetsaas.orvae.pe/login"
- Ejemplo INCORRECTO: "[demo.vetsaas.orvae.pe](https://demo.vetsaas.orvae.pe/login)"

## NUNCA HAGAS ESTO
- Usar Markdown de ningún tipo — WhatsApp lo muestra como texto raro con corchetes.
- Enviar todos los planes con precios juntos.
- Responder con más de 5 líneas sin hacer una pregunta.
- Mencionar límites o restricciones antes de que pregunten.
- Sonar robótico: nada de "¡Claro que sí!", "Por supuesto", "Con mucho gusto".

## PREGUNTAS FUERA DE TEMA — MUY IMPORTANTE
Si el prospecto pregunta algo que NO está relacionado con VetSaaS, su clínica veterinaria,
sus pacientes, la gestión de su negocio o los planes/precios:
- NO respondas la pregunta (no eres un ChatGPT de uso general).
- Redirígelo amablemente hacia VetSaaS con UNA sola frase corta.
- Ejemplo: "Jaja eso está fuera de mi zona 😄 Pero cuéntame, ¿cómo llevas hoy el control de tu clínica?"
- Ejemplo: "Solo soy experto en clínicas veterinarias 🐾 ¿Tienes alguna duda sobre VetSaaS?"

## AUTO-PAUSA — CRÍTICO
Si el prospecto hace 3 o más preguntas seguidas que NO son sobre VetSaaS ni su clínica,
responde exactamente esto y nada más:
"Parece que no es el mejor momento 😊 Si luego tienes dudas sobre VetSaaS, escríbeme y con gusto te ayudo. ¡Hasta pronto!"
Después de ese mensaje NO respondas nada más en esta conversación.
PROMPT;
    }

    private function buildPaginasWebSystemPrompt(): string
    {
        return <<<'PROMPT'
⚠️ REGLA #1 — WhatsApp NO renderiza Markdown.
PROHIBIDO: [texto](url) • **negrita** • *cursiva*
Los enlaces van como URL plana si hace falta: https://orvae.pe

---

Eres el asesor digital de Orvae Software Development (orvae.pe).
Actúas como consultor de negocio, analista de sistemas y desarrollador senior.
Tu objetivo es entender el proyecto del cliente, recomendar el plan correcto y dejar todo listo para cerrar la venta.

Tono: cercano, profesional, español peruano natural. Máximo 2 emojis por mensaje.

## PLANES OFICIALES (usa estos precios y beneficios exactos)

PLAN 1 — Landing Page — S/ 519 pago único
- Página web informativa profesional
- Dominio .com propio (1 año)
- Hosting rápido y seguro (1 año)
- Correos corporativos ilimitados (1 año), ej. contacto@tunegocio.com
- Certificado SSL HTTPS (1 año)
- Diseño moderno adaptado a móvil
- Soporte post-entrega incluido
Ideal para: negocios que quieren presencia online profesional rápido.

PLAN 2 — Web Administrable — S/ 719 pago único
- Todo lo del Plan 1, más:
- Panel de administración propio
- Cambia imágenes desde el celular
- Edita textos y contenidos tú mismo
- Sin depender de nosotros para updates
- Acceso desde cualquier dispositivo
Ideal para: negocios que quieren actualizar su web sin contratar desarrollador.

PLAN 3 — Software a Medida — cotización personalizada
- Sistema desarrollado 100% para el cliente
- Gestión de inventario, ventas, clientes (según necesidad)
- Automatización de procesos internos
- Integraciones con otros sistemas
- Base de datos propia y segura
- Panel de reportes y estadísticas
- Soporte y mantenimiento continuo
Ideal para: empresas que necesitan digitalizar procesos y control total.

Pagos Plan 1 y 2: Yape o transferencia (el administrador confirma datos al cerrar).
Plan 3: cotización tras levantar requerimientos.

## FLUJO DE CONVERSACIÓN

PASO 1 — Si es el primer mensaje o piden info/planes: presenta los 3 planes de forma clara (puede ser hasta 12 líneas en ese mensaje). Cierra preguntando con cuál se identifican más.

PASO 2 — Según el plan elegido, haz preguntas de descubrimiento UNA a la vez:

Plan 1 o 2 (web):
- Nombre del negocio y rubro
- Si ya tienen dominio o hay que registrar uno
- Qué secciones necesitan (inicio, servicios, contacto, etc.)
- Si tienen logo, fotos y textos listos
- Plazo deseado

Plan 3 (software a medida):
- Nombre de la empresa y rubro
- Problema principal que quieren resolver
- Procesos a digitalizar (ventas, inventario, clientes, reportes…)
- Cuántos usuarios usarían el sistema
- Si necesitan integraciones (Excel, facturación, WhatsApp, etc.)
- Si ya usan algún sistema o todo es manual

PASO 3 — Resume en 4-6 líneas: plan, alcance acordado, entregables y precio. Pregunta si está de acuerdo para avanzar.

PASO 4 — CIERRE Y HANDOFF (solo cuando ya tengas plan + alcance + confirmación del cliente):
Responde con UNA sola vez esta frase (puedes variar ligeramente el inicio pero DEBE incluir "Te paso con Rodrigo, nuestro administrador"):
"Perfecto 🙌 Ya tengo claro tu proyecto. Te paso con Rodrigo, nuestro administrador, para cerrar los detalles finales y coordinar el pago."
Después de ese mensaje NO hagas más preguntas ni sigas la conversación.

## REGLAS
1. No inventes precios ni features fuera de los 3 planes.
2. Si no saben qué plan elegir, orienta con 1 pregunta sobre su negocio y recomienda UN solo plan.
3. Para Plan 3, profundiza como analista: entiende el proceso actual antes de proponer módulos.
4. Si preguntan por VetSaaS o clínicas veterinarias, di que este chat es para desarrollo web/software Orvae y ofrece ayuda con esos planes.
5. Si preguntan algo totalmente ajeno (chistes, tareas escolares, etc.), redirige al proyecto en 1 frase.
6. Cada respuesta (excepto el mensaje de planes inicial) máximo 6 líneas y termina con UNA pregunta, salvo en el mensaje final de handoff.

## NUNCA
- Prometer fechas exactas sin consultar al administrador (di "coordinamos el plazo al cerrar").
- Dar descuentos no autorizados.
- Seguir escribiendo después del mensaje de handoff a Rodrigo.
PROMPT;
    }

    public function resolveProductFromTrigger(string $trigger): string
    {
        $trigger = mb_strtolower(trim($trigger));

        if (
            $trigger === self::PRODUCT_PAGINAS_WEB
            || str_starts_with($trigger, self::PRODUCT_PAGINAS_WEB.':')
            || str_starts_with($trigger, 'facebook:'.self::PRODUCT_PAGINAS_WEB)
            || str_contains($trigger, self::PRODUCT_PAGINAS_WEB)
        ) {
            return self::PRODUCT_PAGINAS_WEB;
        }

        return self::PRODUCT_VETSAAS;
    }

    public function resolveConversationProduct(SalesConversation $conversation): string
    {
        $stored = trim((string) ($conversation->product ?? ''));

        if ($stored !== '') {
            return $stored;
        }

        return $this->resolveProductFromTrigger((string) ($conversation->activation_trigger ?? ''));
    }

    public function shouldPauseForAdminHandoff(string $reply, string $product): bool
    {
        if ($product !== self::PRODUCT_PAGINAS_WEB) {
            return false;
        }

        $lower = mb_strtolower($reply);

        foreach ([
            'te paso con rodrigo',
            'te conecto con rodrigo',
            'paso con rodrigo',
        ] as $marker) {
            if (str_contains($lower, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Procesa un mensaje entrante y devuelve la respuesta del bot.
     *
     * @throws RuntimeException si OpenAI falla o no está configurado.
     */
    public function reply(SalesConversation $conversation, string $incomingMessage): string
    {
        $apiKey = (string) config('salesbot.openai_api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY no está configurada.');
        }

        // 1. Agregar mensaje del usuario al historial.
        $conversation->pushMessage('user', $incomingMessage);

        $product = $this->resolveConversationProduct($conversation);

        // 2. Construir el payload para OpenAI.
        $messages = array_merge(
            [['role' => 'system', 'content' => $this->buildSystemPrompt($product)]],
            $conversation->getOpenAiMessages(),
        );

        $maxTokens = $product === self::PRODUCT_PAGINAS_WEB
            ? (int) config('salesbot.max_tokens_paginas_web', 550)
            : (int) config('salesbot.max_tokens', 300);

        // 3. Llamar a la API de OpenAI.
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type'  => 'application/json',
        ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
            'model'       => (string) config('salesbot.openai_model', 'gpt-4o-mini'),
            'messages'    => $messages,
            'max_tokens'  => $maxTokens,
            'temperature' => (float) config('salesbot.temperature', 0.7),
        ]);

        if (! $response->successful()) {
            Log::error('SalesBot OpenAI error', [
                'status' => $response->status(),
                'body'   => $response->body(),
                'phone'  => $conversation->phone,
            ]);
            throw new RuntimeException('OpenAI respondió con HTTP '.$response->status());
        }

        $botReply = (string) ($response->json('choices.0.message.content') ?? '');

        if (trim($botReply) === '') {
            throw new RuntimeException('OpenAI devolvió una respuesta vacía.');
        }

        // 4. Guardar respuesta del bot en el historial.
        $conversation->pushMessage('assistant', $botReply);
        $conversation->save();

        return $botReply;
    }

    /**
     * Busca una conversación existente por teléfono o por chat ID de WhatsApp.
     */
    public function findExistingConversation(string $phone, ?string $waChatId = null): ?SalesConversation
    {
        /** @var SalesConversation|null */
        $conversation = SalesConversation::query()->where('phone', $phone)->first();

        if ($conversation !== null) {
            return $conversation;
        }

        if (str_starts_with($phone, 'lid:')) {
            $digits = substr($phone, 4);
            /** @var SalesConversation|null */
            $conversation = SalesConversation::query()->where('phone', $digits)->first();

            if ($conversation !== null) {
                return $conversation;
            }
        } elseif ($this->phoneLooksLikeLid($phone)) {
            /** @var SalesConversation|null */
            $conversation = SalesConversation::query()->where('phone', 'lid:'.$phone)->first();

            if ($conversation !== null) {
                return $conversation;
            }
        }

        if ($waChatId !== null && $waChatId !== '') {
            /** @var SalesConversation|null */
            return SalesConversation::query()->where('wa_chat_id', $waChatId)->first();
        }

        return null;
    }

    private function phoneLooksLikeLid(string $phone): bool
    {
        if (str_starts_with($phone, 'lid:')) {
            return true;
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';
        $len    = strlen($digits);

        if ($len === 11 && str_starts_with($digits, '51')) {
            return false;
        }

        if ($len === 9 && str_starts_with($digits, '9')) {
            return false;
        }

        return $len >= 13;
    }

    /**
     * Actualiza teléfono/nombre cuando OpenWA nos da datos mejores (ej. resolver @lid).
     */
    public function syncContactMetadata(
        SalesConversation $conversation,
        string $phone,
        string $waChatId,
        ?string $prospectName,
    ): void {
        $dirty = false;

        if ($conversation->wa_chat_id !== $waChatId) {
            $conversation->wa_chat_id = $waChatId;
            $dirty = true;
        }

        $currentPhone = $conversation->phone;
        $hasRealPhone = ! str_starts_with($phone, 'lid:');
        $currentIsLid = str_starts_with($currentPhone, 'lid:')
            || $this->phoneLooksLikeLid($currentPhone);

        if ($hasRealPhone && $currentIsLid) {
            $conversation->phone = $phone;
            $dirty = true;
        } elseif (str_starts_with($phone, 'lid:') && $currentIsLid && $conversation->phone !== $phone) {
            $conversation->phone = $phone;
            $dirty = true;
        }

        if ($prospectName !== null && ($conversation->prospect_name === null || $conversation->prospect_name === '')) {
            $conversation->prospect_name = $prospectName;
            $dirty = true;
        }

        if ($dirty) {
            $conversation->save();
        }
    }

    /**
     * Normaliza teléfono peruano a formato 519XXXXXXXX.
     */
    public function normalizeLeadPhone(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            return '51'.$digits;
        }

        return $digits;
    }

    /**
     * Activa el bot, genera respuesta IA y la envía por WhatsApp.
     *
     * @return array{reply: string, sent: bool, conversation: SalesConversation}
     */
    public function engageConversation(
        SalesConversation $conversation,
        string $incomingMessage,
        bool $sendWhatsApp = true,
    ): array {
        if (! config('salesbot.enabled')) {
            throw new RuntimeException('El bot de ventas está desactivado (SALESBOT_ENABLED=false).');
        }

        $message = trim($incomingMessage);
        if ($message === '') {
            $message = 'Hola, quisiera información sobre VetSaaS y los costos.';
        }

        $conversation->resumeBot();
        if (! str_starts_with((string) ($conversation->activation_trigger ?? ''), 'facebook:')) {
            $conversation->activation_trigger = 'manual:engage';
        }
        $conversation->save();

        $reply = $this->reply($conversation, $message);

        $sent = false;
        if ($sendWhatsApp) {
            if (! $this->messenger->isReady()) {
                throw new RuntimeException('OpenWA no está conectado. La respuesta quedó guardada pero no se envió.');
            }

            $this->messenger->sendText($conversation->wa_chat_id, $reply);
            $sent = true;
        }

        return [
            'reply'        => $reply,
            'sent'         => $sent,
            'conversation' => $conversation->fresh(),
        ];
    }

    /**
     * @return array{reply: string, sent: bool, conversation: SalesConversation}
     */
    public function engagePhone(
        string $rawPhone,
        string $incomingMessage,
        ?string $prospectName = null,
        bool $sendWhatsApp = true,
    ): array {
        $phone = $this->normalizeLeadPhone($rawPhone);

        if ($phone === '' || strlen($phone) < 8) {
            throw new RuntimeException('Número de teléfono inválido.');
        }

        $waChatId     = $phone.'@c.us';
        $conversation = $this->findExistingConversation($phone, $waChatId);

        if ($conversation === null) {
            $conversation = $this->createConversation(
                phone: $phone,
                waChatId: $waChatId,
                prospectName: $prospectName,
                trigger: 'manual:engage',
            );
        } elseif ($prospectName !== null && ($conversation->prospect_name === null || $conversation->prospect_name === '')) {
            $conversation->prospect_name = $prospectName;
            $conversation->save();
        }

        return $this->engageConversation($conversation, $incomingMessage, $sendWhatsApp);
    }

    /**
     * Crea una conversación nueva ya activada con el trigger detectado.
     */
    public function createConversation(
        string $phone,
        string $waChatId,
        ?string $prospectName,
        string $trigger,
        string $product = self::PRODUCT_VETSAAS,
    ): SalesConversation {
        /** @var SalesConversation */
        return SalesConversation::query()->create([
            'phone'              => $phone,
            'wa_chat_id'         => $waChatId,
            'prospect_name'      => $prospectName,
            'messages'           => [],
            'turn_count'         => 0,
            'bot_active'         => true,
            'activation_trigger' => $trigger,
            'product'            => $product,
        ]);
    }

    /**
     * Transcribe un archivo de audio a texto usando la API Whisper de OpenAI.
     *
     * El audio puede ser cualquier formato soportado por Whisper:
     * mp3, mp4, mpeg, mpga, m4a, wav, webm, ogg.
     *
     * OpenWA envía los audios de WhatsApp en formato ogg/opus.
     *
     * @param  string  $audioContent  Contenido binario del archivo de audio.
     * @param  string  $filename      Nombre de archivo con extensión (para que Whisper detecte el formato).
     * @return string  Texto transcrito.
     *
     * @throws RuntimeException si la transcripción falla.
     */
    public function transcribeAudio(string $audioContent, string $filename = 'audio.ogg'): string
    {
        $apiKey = (string) config('salesbot.openai_api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY no está configurada.');
        }

        if (! (bool) config('salesbot.audio_enabled', true)) {
            throw new RuntimeException('El soporte de audio está deshabilitado (SALESBOT_AUDIO_ENABLED).');
        }

        // Guardar el audio en un temp file para poder enviarlo como multipart.
        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'salesbot_' . uniqid() . '_' . $filename;
        file_put_contents($tmpPath, $audioContent);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])->timeout(60)->attach(
                'file',
                fopen($tmpPath, 'r'),
                $filename,
            )->post('https://api.openai.com/v1/audio/transcriptions', [
                'model'    => (string) config('salesbot.whisper_model', 'whisper-1'),
                'language' => (string) config('salesbot.whisper_lang', 'es'),
            ]);
        } finally {
            @unlink($tmpPath);
        }

        if (! $response->successful()) {
            Log::error('SalesBot Whisper error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new RuntimeException('Whisper respondió con HTTP ' . $response->status());
        }

        $text = trim((string) ($response->json('text') ?? ''));

        if ($text === '') {
            throw new RuntimeException('Whisper devolvió una transcripción vacía.');
        }

        return $text;
    }

    /**
     * Convierte texto a audio (nota de voz) usando OpenAI TTS.
     *
     * Devuelve el contenido binario del archivo de audio en formato opus/ogg,
     * listo para enviarlo por WhatsApp como nota de voz.
     *
     * @return string  Contenido binario del audio (ogg/opus).
     * @throws RuntimeException si TTS falla o no está habilitado.
     */
    public function textToSpeech(string $text): string
    {
        $apiKey = (string) config('salesbot.openai_api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY no está configurada.');
        }

        if (! (bool) config('salesbot.tts_enabled', true)) {
            throw new RuntimeException('TTS deshabilitado (SALESBOT_TTS_ENABLED=false).');
        }

        // Limpiar el texto: quitar emojis y caracteres que suenan raro en voz.
        $cleanText = preg_replace('/[\x{1F000}-\x{1FFFF}]/u', '', $text);
        $cleanText = preg_replace('/\*+([^*]+)\*+/', '$1', (string) $cleanText); // quitar **negrita**
        $cleanText = trim((string) $cleanText);

        if ($cleanText === '') {
            throw new RuntimeException('Texto vacío después de limpiar emojis.');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])->timeout(30)->post('https://api.openai.com/v1/audio/speech', [
            'model'           => (string) config('salesbot.tts_model', 'tts-1'),
            'voice'           => (string) config('salesbot.tts_voice', 'nova'),
            'input'           => $cleanText,
            'response_format' => 'opus', // ogg/opus — formato nativo de WhatsApp voz
        ]);

        if (! $response->successful()) {
            Log::error('SalesBot TTS error', [
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 200),
            ]);
            throw new RuntimeException('OpenAI TTS respondió con HTTP ' . $response->status());
        }

        $audioContent = (string) $response->body();

        if (strlen($audioContent) < 100) {
            throw new RuntimeException('TTS devolvió un audio demasiado pequeño.');
        }

        return $audioContent;
    }

    /**
     * Elige una novedad activa para incluir en mensajes de reactivación.
     * Rota por conversación para no repetir siempre la misma entre leads distintos.
     *
     * @return array{title: string, content: string}|null
     */
    private function pickReactivationNovelty(SalesConversation $conversation): ?array
    {
        /** @var list<array{title: string, content: string}> $novelties */
        $novelties = Cache::remember('salesbot_novedades_vetsaas_v2', now()->addMinutes(5), function (): array {
            return SalesBotKnowledge::query()
                ->where('product', 'vetsaas')
                ->where('section', 'novedad')
                ->where('is_active', true)
                ->orderByDesc('sort_order')
                ->orderByDesc('updated_at')
                ->get(['title', 'content'])
                ->map(fn (SalesBotKnowledge $row): array => [
                    'title' => (string) $row->title,
                    'content' => (string) $row->content,
                ])
                ->values()
                ->all();
        });

        if ($novelties === []) {
            return null;
        }

        $index = self::noveltyIndexForConversation((string) $conversation->id, count($novelties));

        return $novelties[$index] ?? null;
    }

    /**
     * Índice estable para rotar novedades (el id de conversación es UUID, no entero).
     */
    public static function noveltyIndexForConversation(string $conversationId, int $count): int
    {
        if ($count <= 0) {
            return 0;
        }

        return abs(crc32($conversationId)) % $count;
    }

    /**
     * Envía un mensaje de reactivación proactivo al prospecto frío.
     *
     * Genera el mensaje usando OpenAI con un prompt especial de reactivación,
     * lo envía por WhatsApp y registra el intento en la conversación.
     *
     * @throws RuntimeException si OpenAI o el messenger fallan.
     */
    public function sendReactivationMessage(SalesConversation $conversation): string
    {
        $apiKey = (string) config('salesbot.openai_api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY no está configurada.');
        }

        $attemptNumber = ($conversation->reactivation_count ?? 0) + 1;
        $name          = $conversation->prospect_name ?? 'amigo';
        $novelty       = $this->pickReactivationNovelty($conversation);
        $noveltyHint   = $novelty !== null
            ? "\n\nOBLIGATORIO — incluye esta novedad como gancho (el prospecto NO la sabía cuando escribió antes):\n"
              ."{$novelty['title']}\n"
              .Str::limit(trim(strtok($novelty['content'], "\n") ?: $novelty['content']), 220)
            : '';

        // Prompt específico para reactivación — diferente tono según el intento.
        $reactivationPrompt = $attemptNumber === 1
            ? "Escribe UN mensaje corto y amigable para recontactar a {$name} que preguntó sobre VetSaaS pero no siguió. "
              ."Si hay novedad abajo, úsala como gancho principal en la primera frase. "
              ."Sé natural, curioso, sin presionar. Máximo 3 líneas. Termina con una pregunta simple. No digas que eres IA."
              .$noveltyHint
            : "Escribe UN último mensaje breve para {$name} que preguntó sobre VetSaaS hace días. "
              ."Ofrece el Plan Free sin costo como alternativa sin riesgo. Máximo 2 líneas. No menciones que es el último intento."
              .($novelty !== null ? "\n\nOpcional: si cabe en 1 frase extra, menciona: {$novelty['title']}" : '');

        $systemPrompt = $this->buildSystemPrompt();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type'  => 'application/json',
        ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
            'model'       => (string) config('salesbot.openai_model', 'gpt-4o-mini'),
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $reactivationPrompt],
            ],
            'max_tokens'  => 150,
            'temperature' => 0.8,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI respondió con HTTP '.$response->status());
        }

        $reactivationMsg = trim((string) ($response->json('choices.0.message.content') ?? ''));

        if ($reactivationMsg === '') {
            throw new RuntimeException('OpenAI devolvió una respuesta vacía.');
        }

        // Enviar por WhatsApp.
        if ($this->messenger->isReady()) {
            $this->messenger->sendText($conversation->wa_chat_id, $reactivationMsg);
        } else {
            throw new RuntimeException('El messenger OpenWA no está listo.');
        }

        // Registrar el intento en la conversación y reactivar el bot para recibir la respuesta.
        $conversation->pushMessage('assistant', "[reactivación #{$attemptNumber}] {$reactivationMsg}");
        $conversation->reactivation_count    = $attemptNumber;
        $conversation->last_reactivation_at  = now();

        if (! $conversation->isManuallyPaused()) {
            $conversation->bot_active = true;
        }

        $conversation->save();

        return $reactivationMsg;
    }

    /**
     * Saludo automático del anuncio de Facebook (mensaje saliente fromMe).
     */
    public function isFacebookWelcomeMessage(string $body): bool
    {
        return $this->detectFacebookWelcomeProduct($body) !== null;
    }

    public function detectFacebookWelcomeProduct(string $body): ?string
    {
        if (trim($body) === '') {
            return null;
        }

        $lower = mb_strtolower($body);

        foreach ([
            'página web',
            'pagina web',
            'páginas web',
            'paginas web',
            'planes de página',
            'planes de pagina',
            'diseño web',
            'diseno web',
            'sitio web',
            'desarrollo web',
        ] as $signal) {
            if (str_contains($lower, $signal)) {
                return self::PRODUCT_PAGINAS_WEB;
            }
        }

        foreach ([
            'vetsaas',
            'vet saas',
            'gracias por escribir',
            'saludo automático',
            'saludo automatico',
            'clínica vet',
            'clinica vet',
        ] as $signal) {
            if (str_contains($lower, $signal)) {
                return self::PRODUCT_VETSAAS;
            }
        }

        return null;
    }

    public function isFacebookLeadConversation(?SalesConversation $conversation): bool
    {
        if ($conversation === null) {
            return false;
        }

        return str_starts_with((string) ($conversation->activation_trigger ?? ''), 'facebook:');
    }

    /**
     * Prepara el bot para responder cuando el lead conteste al saludo del anuncio.
     */
    public function armConversationFromWelcome(
        string $phone,
        string $waChatId,
        ?string $prospectName,
        string $welcomeBody,
    ): SalesConversation {
        $product      = $this->detectFacebookWelcomeProduct($welcomeBody) ?? self::PRODUCT_VETSAAS;
        $trigger      = $product === self::PRODUCT_PAGINAS_WEB
            ? 'facebook:'.self::PRODUCT_PAGINAS_WEB
            : 'facebook:welcome';
        $conversation = $this->findExistingConversation($phone, $waChatId);

        if ($conversation !== null) {
            // Pausa manual desde el panel: no rearmar con el saludo de Meta.
            if ($conversation->isManuallyPaused()) {
                return $conversation;
            }

            $this->syncContactMetadata($conversation, $phone, $waChatId, $prospectName);
            $conversation->resumeBot();
            $conversation->activation_trigger = $trigger;
            $conversation->product            = $product;
        } else {
            $conversation = $this->createConversation(
                phone: $phone,
                waChatId: $waChatId,
                prospectName: $prospectName,
                trigger: $trigger,
                product: $product,
            );
        }

        $messages = $conversation->messages ?? [];
        $last     = count($messages) > 0 ? end($messages) : null;

        if (! is_array($last) || ($last['content'] ?? '') !== $welcomeBody) {
            $conversation->pushMessage('assistant', $welcomeBody);
        }

        $conversation->save();

        return $conversation;
    }

    /**
     * Detecta si un mensaje contiene palabras clave de ventas de VetSaaS.
     *
     * Devuelve el trigger encontrado o null si no es un prospecto.
     *
     * Estas keywords deben coincidir con los mensajes de bienvenida
     * configurados en los anuncios de Facebook Ads de Orvae.
     *
     * TODO — Para agregar otros productos (Aula Virtual, Inventario):
     *   Agregar sus propias keywords aquí y devolver el slug del producto.
     *   El controlador luego cargará el system prompt correspondiente.
     */
    public function detectSalesTrigger(string $message): ?string
    {
        $lower = mb_strtolower($message);

        $triggers = [
            // Orvae — páginas web y software (ANTES que keywords genéricas)
            'planes de página web'   => self::PRODUCT_PAGINAS_WEB,
            'planes de pagina web'   => self::PRODUCT_PAGINAS_WEB,
            'info sobre sus planes'  => self::PRODUCT_PAGINAS_WEB,
            'página administrable'   => self::PRODUCT_PAGINAS_WEB,
            'pagina administrable'   => self::PRODUCT_PAGINAS_WEB,
            'web administrable'      => self::PRODUCT_PAGINAS_WEB,
            'landing page'           => self::PRODUCT_PAGINAS_WEB,
            'landingpage'            => self::PRODUCT_PAGINAS_WEB,
            'páginas web'            => self::PRODUCT_PAGINAS_WEB,
            'paginas web'            => self::PRODUCT_PAGINAS_WEB,
            'página web'             => self::PRODUCT_PAGINAS_WEB,
            'pagina web'             => self::PRODUCT_PAGINAS_WEB,
            'sitio web'              => self::PRODUCT_PAGINAS_WEB,
            'diseño web'             => self::PRODUCT_PAGINAS_WEB,
            'diseno web'             => self::PRODUCT_PAGINAS_WEB,
            'desarrollo web'         => self::PRODUCT_PAGINAS_WEB,
            'software a medida'      => self::PRODUCT_PAGINAS_WEB,
            'sistema a medida'       => self::PRODUCT_PAGINAS_WEB,
            'hosting y dominio'      => self::PRODUCT_PAGINAS_WEB,
            // VetSaaS — menciones directas al producto
            'vetsaas'                => self::PRODUCT_VETSAAS,
            'vet saas'               => self::PRODUCT_VETSAAS,
            // Contexto veterinario
            'veterinari'             => 'veterinaria',
            'clinica vet'            => 'veterinaria',
            'clínica vet'            => 'veterinaria',
            'mascotas'               => 'veterinaria',
            'pacientes vet'          => 'veterinaria',
            // Intención de compra / información
            'me interesa'            => 'interes',
            'quiero info'            => 'interes',
            'más informaci'          => 'interes',
            'mas informaci'          => 'interes',
            'informaci'              => 'interes',
            'quiero saber'           => 'interes',
            'cómo funciona'          => 'interes',
            'como funciona'          => 'interes',
            // Demo / precio
            'demo'                   => 'demo',
            'prueba'                 => 'demo',
            'precio'                 => 'precio',
            'precios'                => 'precio',
            'costos'                 => 'precio',
            'costo'                  => 'precio',
            'tarifa'                 => 'precio',
            'tarifas'                => 'precio',
            'cotiz'                  => 'precio',
            'cuánto cuesta'          => 'precio',
            'cuanto cuesta'          => 'precio',
            'cuánto vale'            => 'precio',
            'cuanto vale'            => 'precio',
            'cuánto es'              => 'precio',
            'cuanto es'              => 'precio',
        ];

        foreach ($triggers as $keyword => $trigger) {
            if (str_contains($lower, $keyword)) {
                return $trigger;
            }
        }

        return null;
    }

    /**
     * Detecta mensajes que indican conversación manual (no ventas VetSaaS).
     * Ej: cliente envía datos de registro, habla con Rodrigo, proyecto a medida.
     */
    public function isHumanHandoffMessage(string $message, ?string $product = null): bool
    {
        $lower = mb_strtolower($message);

        if (preg_match('/\brodrigo\b/u', $lower)) {
            return true;
        }

        $signals = [
            'dni:',
            'ruc:',
            'empresa:',
            'razón social',
            'razon social',
            'dominio a registrar',
            'corporacion',
            'corporación',
            'eirl',
            's.a.c.',
            'sac ',
        ];

        if ($product !== self::PRODUCT_PAGINAS_WEB) {
            $signals[] = 'software a medida';
            $signals[] = 'a medida';
        }

        $matchCount = 0;
        foreach ($signals as $signal) {
            if (str_contains($lower, $signal)) {
                $matchCount++;
            }
        }

        // Formulario con varios campos (Nombre + Teléfono + Email, etc.)
        if ($matchCount >= 2) {
            return true;
        }

        if (str_contains($lower, 'nombre:') && (str_contains($lower, 'teléfono:') || str_contains($lower, 'telefono:'))) {
            return true;
        }

        return false;
    }
}
