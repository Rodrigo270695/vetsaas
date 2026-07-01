<?php

declare(strict_types=1);

namespace App\Services\ClinicBot;

use App\Models\ClinicBotConversation;
use App\Models\ClinicBotKnowledge;
use App\Models\ClinicSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Asistente IA de clínica: responde consultas de clientes por WhatsApp.
 */
final class ClinicBotService
{
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

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
            'model' => (string) config('bot-ia.openai_model', 'gpt-4o-mini'),
            'messages' => $messages,
            'max_tokens' => (int) config('bot-ia.max_tokens', 350),
            'temperature' => (float) config('bot-ia.temperature', 0.5),
        ]);

        if (! $response->successful()) {
            Log::error('ClinicBot OpenAI error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'phone' => $conversation->phone,
            ]);

            throw new RuntimeException('OpenAI respondió con HTTP '.$response->status());
        }

        $botReply = trim((string) ($response->json('choices.0.message.content') ?? ''));

        if ($botReply === '') {
            throw new RuntimeException('OpenAI devolvió una respuesta vacía.');
        }

        $conversation->pushMessage('assistant', $botReply);
        $conversation->save();

        return $botReply;
    }

    private function buildSystemPrompt(): string
    {
        $clinic = ClinicSetting::query()->first();
        $clinicName = trim((string) ($clinic?->nombre_comercial ?? $clinic?->razon_social ?? ''));
        if ($clinicName === '') {
            $clinicName = 'la clínica veterinaria';
        }

        $knowledge = ClinicBotKnowledge::buildContext();

        $knowledgeBlock = $knowledge !== ''
            ? "BASE DE CONOCIMIENTO DE LA CLÍNICA:\n\n{$knowledge}"
            : 'BASE DE CONOCIMIENTO: aún vacía. Indica amablemente que un asistente humano puede ayudar en horario de atención.';

        return <<<PROMPT
Eres el asistente virtual de WhatsApp de {$clinicName}.
Responde en español, tono amable y profesional. Mensajes cortos (máximo 4-5 líneas), aptos para WhatsApp.
Usa SOLO la información de la base de conocimiento. Si no tienes el dato, dilo con honestidad y sugiere contactar a la clínica o visitar en horario de atención.
No inventes precios, horarios ni políticas. No des diagnósticos veterinarios: solo orientación general y logística.

{$knowledgeBlock}
PROMPT;
    }
}
