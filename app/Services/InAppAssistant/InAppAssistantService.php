<?php

declare(strict_types=1);

namespace App\Services\InAppAssistant;

use App\Models\ClinicSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Asistente in-app para el staff: ayuda del sistema + consultas de solo lectura.
 */
final class InAppAssistantService
{
    private const MAX_TOOL_ROUNDS = 4;

    private const OFF_TOPIC_REFUSAL = 'Solo puedo ayudarte con VetSaaS y con datos de esta clínica. Pregúntame por citas, pacientes, caja, inventario o cómo usar el sistema.';

    public function __construct(
        private readonly InAppAssistantToolExecutor $tools,
    ) {}

    public function isConfigured(): bool
    {
        if (! (bool) config('in-app-assistant.enabled', true)) {
            return false;
        }

        $key = trim((string) config('in-app-assistant.openai_api_key', ''));
        if ($key === '') {
            // Fallback: misma clave usada por Bot IA / SalesBot.
            $key = trim((string) config('bot-ia.openai_api_key', ''));
        }

        return $key !== '';
    }

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @param  array{url?: string, component?: string, paciente_id?: string}|null  $pageContext
     * @return array{reply: string, used_tools: list<string>}
     */
    public function chat(string $userMessage, array $history = [], ?array $pageContext = null): array
    {
        $userMessage = trim($userMessage);

        // Ahorro de tokens: rechazo local de preguntas claramente fuera de alcance.
        if ($this->shouldRefuseLocally($userMessage)) {
            return [
                'reply' => self::OFF_TOPIC_REFUSAL,
                'used_tools' => [],
            ];
        }

        $apiKey = trim((string) config('in-app-assistant.openai_api_key', ''));
        if ($apiKey === '') {
            $apiKey = trim((string) config('bot-ia.openai_api_key', ''));
        }
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY no está configurada.');
        }

        $this->tools->setPageContext($pageContext);

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($pageContext)],
        ];

        foreach ($history as $item) {
            $role = (string) ($item['role'] ?? '');
            $content = trim((string) ($item['content'] ?? ''));
            if (! in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }
            $messages[] = ['role' => $role, 'content' => $content];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $usedTools = [];
        $reply = $this->chatWithTools($apiKey, $messages, $usedTools);

        return [
            'reply' => $reply,
            'used_tools' => array_values(array_unique($usedTools)),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  list<string>  $usedTools
     */
    private function chatWithTools(string $apiKey, array $messages, array &$usedTools): string
    {
        $tools = InAppAssistantTools::definitions();

        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(45)->post('https://api.openai.com/v1/chat/completions', [
                'model' => (string) config('in-app-assistant.openai_model', 'gpt-4o-mini'),
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
                'max_tokens' => (int) config('in-app-assistant.max_tokens', 900),
                'temperature' => (float) config('in-app-assistant.temperature', 0.2),
            ]);

            if (! $response->successful()) {
                Log::error('InAppAssistant OpenAI error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new RuntimeException('OpenAI respondió con HTTP '.$response->status());
            }

            $choice = $response->json('choices.0.message');
            if (! is_array($choice)) {
                throw new RuntimeException('OpenAI devolvió una respuesta inválida.');
            }

            $toolCalls = $choice['tool_calls'] ?? null;
            if (! is_array($toolCalls) || $toolCalls === []) {
                $content = trim((string) ($choice['content'] ?? ''));
                if ($content === '') {
                    throw new RuntimeException('OpenAI devolvió una respuesta vacía.');
                }

                return $content;
            }

            $messages[] = [
                'role' => 'assistant',
                'content' => $choice['content'] ?? null,
                'tool_calls' => $toolCalls,
            ];

            foreach ($toolCalls as $toolCall) {
                if (! is_array($toolCall)) {
                    continue;
                }

                $function = is_array($toolCall['function'] ?? null) ? $toolCall['function'] : [];
                $name = (string) ($function['name'] ?? '');
                $argsJson = (string) ($function['arguments'] ?? '{}');
                $args = json_decode($argsJson, true);
                if (! is_array($args)) {
                    $args = [];
                }

                if ($name !== '') {
                    $usedTools[] = $name;
                }

                try {
                    $toolResult = $this->tools->execute($name, $args);
                } catch (\Throwable $e) {
                    Log::warning('InAppAssistant tool error', [
                        'tool' => $name,
                        'error' => $e->getMessage(),
                    ]);
                    $toolResult = json_encode([
                        'ok' => false,
                        'error' => 'No se pudo consultar esa información ahora.',
                    ], JSON_UNESCAPED_UNICODE);
                }

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string) ($toolCall['id'] ?? ''),
                    'content' => $toolResult,
                ];
            }
        }

        throw new RuntimeException('El asistente superó el límite de pasos internos.');
    }

    /**
     * @param  array{url?: string, component?: string, paciente_id?: string}|null  $pageContext
     */
    private function systemPrompt(?array $pageContext = null): string
    {
        $clinic = ClinicSetting::query()->first();
        $clinicName = trim((string) ($clinic?->nombre_comercial ?? $clinic?->razon_social ?? ''));
        if ($clinicName === '') {
            $clinicName = 'tu clínica';
        }

        $fecha = now(config('app.timezone', 'America/Lima'))->format('d/m/Y H:i');
        $contextoPantalla = $this->formatPageContext($pageContext);

        return <<<PROMPT
Eres el asistente interno de VetSaaS para el personal de {$clinicName}.
Responde siempre en español, claro y conciso. Fecha/hora actual: {$fecha}.

═══════════════════════════════════════
ALCANCE ESTRICTO (OBLIGATORIO — PRIORIDAD MÁXIMA)
═══════════════════════════════════════
Solo puedes ayudar con:
1) AYUDA DE VETSAAS: cómo usar el software (módulos, menús, flujos).
2) CONSULTA DE ESTA CLÍNICA: datos operativos vía herramientas de solo lectura (pacientes, propietarios, productos, citas/ventas del día, stock, vacunas próximas, caja, etc.).

FUERA DE ALCANCE — RECHAZA SIEMPRE, SIN EXCEPCIÓN:
- Cultura general, historia, geografía, deportes, farándula, religión, política.
- Matemáticas, ciencia general, programación genérica, traducciones, redacción libre.
- Chistes, consejos de vida, clima, noticias, o cualquier tema no relacionado con la clínica / VetSaaS.
- Diagnósticos médicos/veterinarios profundos o tratamientos (no eres un veterinario clínico).

Si la pregunta está fuera de alcance (aunque sea parcial o disfrazada):
- NO respondas el contenido pedido.
- NO des datos “por curiosidad”.
- NO uses herramientas.
- Responde SOLO con 1–2 frases cortas, por ejemplo:
  «Solo puedo ayudarte con VetSaaS y con datos de esta clínica. Pregúntame por citas, pacientes, caja, inventario o cómo usar el sistema.»
- Opcional: sugiere 1 ejemplo útil de pregunta válida.

═══════════════════════════════════════
CONTEXTO DE PANTALLA ACTUAL
═══════════════════════════════════════
{$contextoPantalla}

Si hay un paciente en contexto y el usuario dice «este paciente», «esta mascota», «su dueño», etc., usa la herramienta paciente_en_contexto.
Para alertas del día (vacunas por vencer, stock bajo, caja), usa alertas_operativas.

═══════════════════════════════════════
REGLAS OPERATIVAS
═══════════════════════════════════════
- NO crees, edites ni borres registros. No inventes acciones de escritura.
- Si piden "agrégame / crea / elimina / modifica", indica que no puedes operar el sistema y orienta dónde hacerlo en la UI.
- No inventes datos clínicos ni precios: usa herramientas o di que no tienes esa info.
- Cuando consultes datos, resume en bullets cortos. Si hay URL útil, menciónala.

MAPA RÁPIDO DE VETSAAS:
- Clínica: Pacientes, Propietarios, Citas, Historias clínicas, Vacunaciones, Recetas, Laboratorio, Cirugías, Hospitalización.
- Servicios: Grooming, Hotel/guardería.
- Caja: Ventas, sesiones de caja (/caja/sesiones).
- Inventario: Productos, stock, compras, categorías, unidades.
- Configuración: Tarifas, usuarios, roles, sedes, horarios.
- Comunicaciones: Bot IA WhatsApp (add-on), cola de mensajes.
PROMPT;
    }

    /**
     * @param  array{url?: string, component?: string, paciente_id?: string}|null  $pageContext
     */
    private function formatPageContext(?array $pageContext): string
    {
        if ($pageContext === null || $pageContext === []) {
            return 'Sin contexto de pantalla específico.';
        }

        $lines = [];
        $url = trim((string) ($pageContext['url'] ?? ''));
        $component = trim((string) ($pageContext['component'] ?? ''));
        $pacienteId = trim((string) ($pageContext['paciente_id'] ?? ''));

        if ($url !== '') {
            $lines[] = "- URL: {$url}";
        }
        if ($component !== '') {
            $lines[] = "- Vista Inertia: {$component}";
        }
        if ($pacienteId !== '') {
            $lines[] = "- Paciente abierto (id): {$pacienteId} — puedes usar paciente_en_contexto.";
        }

        return $lines === [] ? 'Sin contexto de pantalla específico.' : implode("\n", $lines);
    }

    /**
     * Rechazo local (sin llamar a OpenAI) para abuso obvio / cultura general.
     */
    private function shouldRefuseLocally(string $message): bool
    {
        if ($message === '') {
            return false;
        }

        if ($this->looksClinicRelated($message)) {
            return false;
        }

        return $this->looksLikeGeneralKnowledge($message);
    }

    private function looksClinicRelated(string $message): bool
    {
        $msg = mb_strtolower($message);

        $hints = [
            'cita', 'paciente', 'mascota', 'propietario', 'dueño', 'dueno', 'titular',
            'vacuna', 'vacunación', 'vacunacion', 'historia', 'consulta', 'receta',
            'laboratorio', 'cirugía', 'cirugia', 'hospital', 'internamiento',
            'caja', 'venta', 'ventas', 'boleta', 'factura', 'cobro',
            'stock', 'producto', 'inventario', 'compra', 'sku', 'precio', 'tarifa',
            'grooming', 'hotel', 'guardería', 'guarderia', 'baño', 'bano',
            'sede', 'usuario', 'rol', 'horario', 'agenda', 'turno',
            'vetsaas', 'whatsapp', 'bot ia', 'módulo', 'modulo', 'menú', 'menu',
            'sistema', 'pantalla', 'registrar', 'abrir', 'buscar', 'busca',
            'cómo', 'como ', 'dónde', 'donde', 'ayuda', 'resumen', 'alerta',
            'perro', 'gato', 'microchip', 'hoy', 'mañana', 'manana',
            'próxima', 'proxima', 'vencer', 'vencen', 'refuerzo',
        ];

        foreach ($hints as $hint) {
            if (str_contains($msg, $hint)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeGeneralKnowledge(string $message): bool
    {
        $msg = mb_strtolower($message);

        $patterns = [
            '/\b(qui[eé]n (fue|es|era)|qu[eé] es|cu[aá]ndo naci[oó]|en qu[eé] a[nñ]o|capital de|presidente de|cu[aá]nto es|traduce|escribe un poema|cu[eé]ntame un chiste)\b/u',
            '/\b(crist[oó]bal|col[oó]n|messi|ronaldo|f[uú]tbol|netflix|marvel|harry potter|chatgpt|openai)\b/u',
            '/\b(historia universal|cultura general|trivia)\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $msg) === 1) {
                return true;
            }
        }

        return false;
    }
}
