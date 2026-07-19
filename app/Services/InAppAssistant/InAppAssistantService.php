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

    public function __construct(
        private readonly InAppAssistantToolExecutor $tools,
    ) {}

    public function isConfigured(): bool
    {
        if (! (bool) config('in-app-assistant.enabled', true)) {
            return false;
        }

        return trim((string) config('in-app-assistant.openai_api_key', '')) !== '';
    }

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @return array{reply: string, used_tools: list<string>}
     */
    public function chat(string $userMessage, array $history = []): array
    {
        $apiKey = trim((string) config('in-app-assistant.openai_api_key', ''));
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY no está configurada.');
        }

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
        ];

        foreach ($history as $item) {
            $role = (string) ($item['role'] ?? '');
            $content = trim((string) ($item['content'] ?? ''));
            if (! in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }
            $messages[] = ['role' => $role, 'content' => $content];
        }

        $messages[] = ['role' => 'user', 'content' => trim($userMessage)];

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
                'temperature' => (float) config('in-app-assistant.temperature', 0.4),
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

    private function systemPrompt(): string
    {
        $clinic = ClinicSetting::query()->first();
        $clinicName = trim((string) ($clinic?->nombre_comercial ?? $clinic?->razon_social ?? ''));
        if ($clinicName === '') {
            $clinicName = 'tu clínica';
        }

        $fecha = now(config('app.timezone', 'America/Lima'))->format('d/m/Y H:i');

        return <<<PROMPT
Eres el asistente interno de VetSaaS para el personal de {$clinicName}.
Responde siempre en español, claro y conciso. Fecha/hora actual: {$fecha}.

TU ROL:
1) AYUDA: explicar cómo usar VetSaaS (dónde está cada módulo, flujos típicos).
2) CONSULTA: responder preguntas sobre datos de ESTA clínica usando herramientas de solo lectura.

LÍMITES IMPORTANTES:
- NO crees, edites ni borres registros. No inventes acciones de escritura.
- Si piden "agrégame / crea / elimina / modifica", indica que aún no puedes operar el sistema y orienta dónde hacerlo en la UI.
- No inventes datos clínicos ni precios: usa herramientas o di que no tienes esa info.
- No des diagnósticos veterinarios; solo orientación operativa del software.

MAPA RÁPIDO DE VETSAAS:
- Clínica: Pacientes, Propietarios, Citas, Historias clínicas, Vacunaciones, Recetas, Laboratorio, Cirugías, Hospitalización.
- Servicios: Grooming, Hotel/guardería.
- Caja: Ventas, sesiones de caja.
- Inventario: Productos, stock, compras, categorías, unidades.
- Configuración: Tarifas (servicios clínicos / grooming / hotel), usuarios, roles, sedes, horarios.
- Comunicaciones: Bot IA WhatsApp (add-on), cola de mensajes.
- En historial del paciente se puede compartir vista pública por WhatsApp (enlace firmado).

Cuando consultes datos, resume en bullets cortos y menciona si hay pocos resultados.
PROMPT;
    }
}
