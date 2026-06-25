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
    private function buildSystemPrompt(string $product = 'vetsaas'): string
    {
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
                ->orderBy('section')
                ->orderBy('sort_order')
                ->get();

            if ($entries->isEmpty()) {
                return '';
            }

            $sections = [];
            foreach ($entries->groupBy('section') as $section => $items) {
                $sectionTitle = match ($section) {
                    'modulo'   => 'MÓDULOS Y FUNCIONALIDADES',
                    'faq'      => 'PREGUNTAS FRECUENTES',
                    'objecion' => 'CÓMO MANEJAR OBJECIONES',
                    default    => strtoupper($section),
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

---

## FLUJO DE CONVERSACIÓN (seguirlo en orden)
PASO 1 — Conectar: Pregunta cómo lleva HOY el control de su clínica (papel, Excel, otro sistema).
PASO 2 — Dolor: Según su respuesta, menciona UN solo módulo que resuelve ESE problema específico.
PASO 3 — Demo: Ofrece la DEMO compartida para que vea el sistema sin registrarse.
PASO 4 — Cierre: Propón videollamada de 10 minutos O sugiere que se registre en el Plan Free o el plan que le aplica.

## REGLAS ESTRICTAS
1. NUNCA muestres todos los planes con precios de golpe. Máximo 1 plan por recomendación.
2. SIEMPRE haz una pregunta primero antes de hablar del producto.
3. Conecta CADA feature con UN dolor que el prospecto mencionó.
4. Si dice "quiero ver más" → da las credenciales demo que están en la sección de planes de arriba.
5. Si pregunta precio → recomienda solo el plan que le aplica. Pregunta cuántos pacientes tiene si no lo sabes.
6. Si hay objeción de precio → ofrece el Plan Free sin riesgo (está explicado en las objeciones arriba).
7. CADA respuesta tuya termina con UNA sola pregunta o llamada a acción clara.
8. Máximo 5 líneas por respuesta. Frases cortas. Sin listas largas.
9. Si ya tienen sistema → pregunta qué les falta o qué les frustra.
10. Si dicen "no me interesa" → agradece y ofrece el demo gratuito.

## TONO
- Cercano como un colega, no como un vendedor.
- Sin tecnicismos ni jerga SaaS.
- En español peruano natural. Está bien usar "pues", "pe", expresiones cotidianas.
- Sin emojis excesivos. Máximo 1 emoji por mensaje y solo si aporta.

## NUNCA HAGAS ESTO
- Enviar todos los planes con precios juntos.
- Responder con más de 5 líneas sin hacer una pregunta.
- Decir "no puedo" o "no sé". Usa siempre la info del contexto de arriba para responder.
- Mencionar límites o restricciones antes de que pregunten.
- Sonar robótico o usar frases como "¡Claro que sí!" o "Por supuesto".
PROMPT;
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

        // 2. Construir el payload para OpenAI.
        $messages = array_merge(
            [['role' => 'system', 'content' => $this->buildSystemPrompt()]],
            $conversation->getOpenAiMessages(),
        );

        // 3. Llamar a la API de OpenAI.
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type'  => 'application/json',
        ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
            'model'       => (string) config('salesbot.openai_model', 'gpt-4o-mini'),
            'messages'    => $messages,
            'max_tokens'  => (int) config('salesbot.max_tokens', 300),
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
     * Busca una conversación existente por número de teléfono.
     * Devuelve null si no existe (prospecto nuevo).
     */
    public function findExistingConversation(string $phone): ?SalesConversation
    {
        /** @var SalesConversation|null */
        return SalesConversation::query()->where('phone', $phone)->first();
    }

    /**
     * Crea una conversación nueva ya activada con el trigger detectado.
     */
    public function createConversation(
        string $phone,
        string $waChatId,
        ?string $prospectName,
        string $trigger,
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
        ]);
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

        // Prompt específico para reactivación — diferente tono según el intento.
        $reactivationPrompt = $attemptNumber === 1
            ? "Escribe UN mensaje corto y amigable para recontactar a {$name} que preguntó sobre VetSaaS pero no siguió. Sé natural, curioso, sin presionar. Máximo 3 líneas. Termina con una pregunta simple. No digas que eres IA."
            : "Escribe UN último mensaje breve para {$name} que preguntó sobre VetSaaS hace días. Ofrece el Plan Free sin costo como alternativa sin riesgo. Máximo 2 líneas. No menciones que es el último intento.";

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
        $conversation->bot_active            = true;
        $conversation->save();

        return $reactivationMsg;
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
            // Menciones directas al producto
            'vetsaas'       => 'vetsaas',
            'vet saas'      => 'vetsaas',
            // Contexto veterinario
            'veterinari'    => 'veterinaria',
            'clinica vet'   => 'veterinaria',
            'clínica vet'   => 'veterinaria',
            // Intención de compra / información
            'me interesa'   => 'interes',
            'quiero info'   => 'interes',
            'más informaci' => 'interes',
            'mas informaci' => 'interes',
            'quiero saber'  => 'interes',
            'cómo funciona' => 'interes',
            'como funciona' => 'interes',
            // Demo / precio
            'demo'          => 'demo',
            'prueba'        => 'demo',
            'precio'        => 'precio',
            'cuánto cuesta' => 'precio',
            'cuanto cuesta' => 'precio',
            'plan'          => 'plan',
            // Sistema / software
            'sistema'       => 'sistema',
            'software'      => 'sistema',
            'gestión'       => 'sistema',
            'gestion'       => 'sistema',
        ];

        foreach ($triggers as $keyword => $trigger) {
            if (str_contains($lower, $keyword)) {
                return $trigger;
            }
        }

        return null;
    }
}
