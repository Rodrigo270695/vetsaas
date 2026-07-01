<?php

declare(strict_types=1);

namespace App\Services\ClinicBot;

use App\Models\ClinicBotConversation;
use App\Models\ClinicBotKnowledge;
use App\Models\ClinicSetting;
use App\Support\ClinicBot\ClinicBotPeruClock;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Asistente IA de clínica: responde consultas de clientes por WhatsApp.
 */
final class ClinicBotService
{
    private const MAX_TOOL_ROUNDS = 5;

    public function __construct(
        private readonly ClinicBotCatalogService $catalog,
        private readonly ClinicBotToolExecutor $toolExecutor,
    ) {}

    public function findConversation(string $phone, ?string $waChatId = null): ?ClinicBotConversation
    {
        /** @var ClinicBotConversation|null */
        $conversation = ClinicBotConversation::query()->where('phone', $phone)->first();

        if ($conversation !== null) {
            return $conversation;
        }

        if ($waChatId !== null && $waChatId !== '') {
            /** @var ClinicBotConversation|null */
            return ClinicBotConversation::query()->where('wa_chat_id', $waChatId)->first();
        }

        return null;
    }

    public function findOrCreateConversation(
        string $phone,
        string $waChatId,
        ?string $clientName,
    ): ClinicBotConversation {
        $existing = $this->findConversation($phone, $waChatId);

        if ($existing !== null) {
            if ($clientName !== null && ($existing->client_name === null || $existing->client_name === '')) {
                $existing->client_name = $clientName;
                $existing->save();
            }

            return $existing;
        }

        /** @var ClinicBotConversation */
        return ClinicBotConversation::query()->create([
            'phone' => $phone,
            'wa_chat_id' => $waChatId,
            'client_name' => $clientName,
            'messages' => [],
            'turn_count' => 0,
            'bot_active' => true,
            'bot_paused_manually' => false,
        ]);
    }

    public function syncContactMetadata(
        ClinicBotConversation $conversation,
        string $phone,
        string $waChatId,
        ?string $clientName,
    ): void {
        $dirty = false;

        if ($conversation->wa_chat_id !== $waChatId) {
            $conversation->wa_chat_id = $waChatId;
            $dirty = true;
        }

        if ($conversation->phone !== $phone && $phone !== '') {
            $conversation->phone = $phone;
            $dirty = true;
        }

        if ($clientName !== null && ($conversation->client_name === null || $conversation->client_name === '')) {
            $conversation->client_name = $clientName;
            $dirty = true;
        }

        if ($dirty) {
            $conversation->save();
        }
    }

    public function reply(ClinicBotConversation $conversation, string $incomingMessage): string
    {
        $apiKey = (string) config('bot-ia.openai_api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY no está configurada.');
        }

        $conversation->pushMessage('user', $incomingMessage);

        $messages = array_merge(
            [['role' => 'system', 'content' => $this->buildSystemPrompt()]],
            $conversation->getOpenAiMessages(),
        );

        $botReply = $this->chatWithTools(
            $apiKey,
            $messages,
            $conversation->phone,
            $conversation->client_name,
        );

        $conversation->pushMessage('assistant', $botReply);
        $conversation->save();

        return $botReply;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    private function chatWithTools(
        string $apiKey,
        array $messages,
        string $clientPhone,
        ?string $clientName = null,
    ): string {
        $tools = ClinicBotTools::definitions();

        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(45)->post('https://api.openai.com/v1/chat/completions', [
                'model' => (string) config('bot-ia.openai_model', 'gpt-4o-mini'),
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
                'max_tokens' => (int) config('bot-ia.max_tokens', 500),
                'temperature' => (float) config('bot-ia.temperature', 0.5),
            ]);

            if (! $response->successful()) {
                Log::error('ClinicBot OpenAI error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'phone' => $clientPhone,
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

                try {
                    $toolResult = $this->toolExecutor->execute($name, $args, $clientPhone, $clientName);
                } catch (\Throwable $e) {
                    Log::warning('ClinicBot tool error', [
                        'tool' => $name,
                        'error' => $e->getMessage(),
                    ]);
                    $toolResult = json_encode([
                        'ok' => false,
                        'error' => 'No se pudo ejecutar la acción solicitada.',
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

    private function buildSystemPrompt(): string
    {
        $clinic = ClinicSetting::query()->first();
        $clinicName = trim((string) ($clinic?->nombre_comercial ?? $clinic?->razon_social ?? ''));
        if ($clinicName === '') {
            $clinicName = 'la clínica veterinaria';
        }

        $knowledge = ClinicBotKnowledge::buildContext();
        $catalog = $this->catalog->buildPromptCatalogSummary();
        $fechaActual = ClinicBotPeruClock::promptReference();

        $knowledgeBlock = $knowledge !== ''
            ? "BASE DE CONOCIMIENTO DE LA CLÍNICA:\n\n{$knowledge}"
            : 'BASE DE CONOCIMIENTO: aún vacía. Indica amablemente que un asistente humano puede ayudar en horario de atención.';

        $catalogBlock = $catalog !== ''
            ? "CATÁLOGO OPERATIVO DE ESTA CLÍNICA (solo datos reales del sistema):\n\n{$catalog}"
            : 'CATÁLOGO: no hay productos ni servicios de grooming activos cargados en el sistema.';

        return <<<PROMPT
Eres el asistente virtual de WhatsApp de {$clinicName}.
Responde en español, tono amable y profesional. Mensajes cortos (máximo 4-5 líneas), aptos para WhatsApp.

FECHA Y HORA ACTUAL EN PERÚ: {$fechaActual}
Siempre interpreta "hoy", "mañana", "pasado mañana" y días de la semana respecto a esa referencia (zona horaria America/Lima).
Antes de agendar, confirma mascota, fecha, hora y tipo de servicio. Usa las herramientas para consultar catálogo, mascotas, registrar clientes y agendar citas.

REGISTRO DE CLIENTES NUEVOS:
- Si listar_mascotas_cliente viene vacío, puedes registrar al tutor con registrar_propietario y la mascota con registrar_mascota.
- registrar_mascota crea al propietario automáticamente si falta (usa el nombre de WhatsApp o pide nombres básicos).
- Tras registrar la mascota, usa registrar_cita con el paciente_id devuelto.
- Solo pide datos básicos: nombre del tutor, nombre de la mascota, especie, raza y edad aproximada.

Para precios y servicios usa SOLO el catálogo del sistema o las herramientas listar_productos / listar_servicios_grooming.
No inventes precios, horarios ni políticas. No des diagnósticos veterinarios: solo orientación general y logística.

{$knowledgeBlock}

{$catalogBlock}
PROMPT;
    }
}
