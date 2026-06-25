<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\SalesConversation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cerebro del bot de ventas de VetSaaS.
 *
 * Recibe el mensaje de un prospecto, mantiene el historial de conversación
 * en `sales_conversations`, llama a OpenAI y devuelve la respuesta lista
 * para enviarla por WhatsApp.
 */
final class SalesBotService
{
    /**
     * System prompt del bot de ventas.
     * Define personalidad, flujo y reglas estrictas de conversación.
     */
    private function buildSystemPrompt(): string
    {
        $demoUrl  = (string) config('salesbot.demo_url', 'demo.orvae.pe');
        $demoEmail = (string) config('salesbot.demo_email', 'demo@vetsaas.pe');
        $demoPass  = (string) config('salesbot.demo_password', 'demo1234');

        return <<<PROMPT
Eres un asesor de ventas de VetSaaS, el sistema de gestión para clínicas veterinarias de ORVAE (orvae.pe).
Tu único objetivo es convertir prospectos en clientes pagos de forma natural y humana.
Eres amigable, directo, usas lenguaje peruano cotidiano. Nunca suenas a robot ni a plantilla copiada.

## PRODUCTO
VetSaaS tiene módulos de: historial clínico, citas, caja y ventas, cirugías, hospitalización, laboratorio, grooming, stock y WhatsApp automático.

Planes disponibles:
- Free: S/0 — acceso básico para conocer el sistema, sin límite de tiempo.
- Starter: S/39.90/mes — 1 sede, 2 usuarios, 150 pacientes. Ideal para clínicas pequeñas.
- Pro: S/59.90/mes — 1 sede, 3 usuarios, 300 pacientes + facturación electrónica. El más popular.
- Clínica: S/99.90/mes — 3 sedes, 10 usuarios, todo ilimitado. Para clínicas grandes.

Demo disponible 24/7 (ya tiene datos cargados, entran directo sin registrarse):
  🌐 {$demoUrl}
  👤 {$demoEmail}
  🔑 {$demoPass}

## FLUJO DE CONVERSACIÓN (seguirlo en orden)
PASO 1 — Conectar: Pregunta cómo lleva HOY el control de su clínica (papel, Excel, otro sistema).
PASO 2 — Dolor: Según su respuesta, menciona UN solo módulo que resuelve ESE problema específico.
PASO 3 — Demo: Ofrece acceso inmediato con las credenciales de arriba. Dile que ya tiene datos cargados.
PASO 4 — Cierre: Propón videollamada de 10 minutos O sugiere el plan que le aplica (solo 1 plan, el correcto para su caso).

## REGLAS ESTRICTAS
1. NUNCA muestres todos los planes con precios de golpe. Máximo 1 plan por recomendación.
2. SIEMPRE haz una pregunta primero antes de hablar del producto.
3. Conecta CADA feature con UN dolor que el prospecto mencionó.
4. Si dice "quiero ver más" o "cómo funciona" → da las credenciales del demo de arriba.
5. Si pregunta precio → recomienda solo el plan que le aplica según lo que dijo. Pregúntale cuántos pacientes tiene si no lo sabes.
6. Si hay objeción de precio → ofrece empezar con el plan Free sin riesgo.
7. CADA respuesta tuya termina con UNA sola pregunta o llamada a acción clara.
8. Máximo 5 líneas por respuesta. Frases cortas. Sin listas largas.
9. Si mencionan que ya tienen sistema → pregunta qué les falta o qué les frustra de ese sistema.
10. Si dicen "no me interesa" o "ya tenemos" → agradece y deja la puerta abierta con el demo gratuito.

## TONO
- Cercano como un colega, no como un vendedor.
- Sin tecnicismos ni jerga SaaS.
- En español peruano natural. Está bien usar "pues", "pe", expresiones cotidianas.
- Sin emojis excesivos. Máximo 1 emoji por mensaje y solo si aporta.

## NUNCA HAGAS ESTO
- Enviar todos los planes con precios juntos.
- Responder con más de 5 líneas sin hacer una pregunta.
- Decir "no puedo" o "no sé" — si no tienes info, di "déjame consultarlo".
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
     * Obtiene o crea la conversación para un número de WhatsApp.
     */
    public function findOrCreateConversation(string $phone, string $waChatId, ?string $prospectName): SalesConversation
    {
        /** @var SalesConversation $conversation */
        $conversation = SalesConversation::query()->firstOrCreate(
            ['phone' => $phone],
            [
                'wa_chat_id'    => $waChatId,
                'prospect_name' => $prospectName,
                'messages'      => [],
                'turn_count'    => 0,
            ],
        );

        // Actualizar nombre si llegó uno nuevo y no lo teníamos.
        if ($prospectName !== null && $conversation->prospect_name === null) {
            $conversation->prospect_name = $prospectName;
            $conversation->save();
        }

        return $conversation;
    }
}
