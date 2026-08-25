<?php

declare(strict_types=1);

namespace App\Services\WhatsAppCloud;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente Graph API para WhatsApp Cloud (Meta) — solo envío de plantillas.
 */
final class WhatsAppCloudClient
{
    public function isEnabled(): bool
    {
        return (bool) config('whatsapp_cloud.enabled', false);
    }

    public function isConfigured(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $token = trim((string) config('whatsapp_cloud.token', ''));
        $phoneNumberId = trim((string) config('whatsapp_cloud.phone_number_id', ''));
        $template = trim((string) config('whatsapp_cloud.template_renewal', ''));

        return $token !== '' && $phoneNumberId !== '' && $template !== '';
    }

    public function renewalTemplateName(): string
    {
        return trim((string) config('whatsapp_cloud.template_renewal', 'vetsaas_renewal_reminder'));
    }

    public function renewalTemplateLang(): string
    {
        $lang = trim((string) config('whatsapp_cloud.template_lang', 'es'));

        return $lang !== '' ? $lang : 'es';
    }

    /**
     * Envía plantilla Utility de renovación.
     *
     * @param  list<string>  $bodyParams  Variables {{1}}…{{n}} del body
     * @return array<string, mixed>
     */
    public function sendRenewalTemplate(string $toPhoneOrChatId, array $bodyParams): array
    {
        return $this->sendTemplate(
            $toPhoneOrChatId,
            $this->renewalTemplateName(),
            $this->renewalTemplateLang(),
            $bodyParams,
        );
    }

    /**
     * @param  list<string>  $bodyParams
     * @return array<string, mixed>
     */
    public function sendTemplate(
        string $toPhoneOrChatId,
        string $templateName,
        string $languageCode,
        array $bodyParams = [],
    ): array {
        if (! $this->isConfigured()) {
            throw new RuntimeException('WhatsApp Cloud API no está configurado (WHATSAPP_CLOUD_*).');
        }

        $to = self::normalizeRecipient($toPhoneOrChatId);
        if ($to === null) {
            throw new RuntimeException('Destinatario WhatsApp Cloud inválido.');
        }

        $phoneNumberId = trim((string) config('whatsapp_cloud.phone_number_id'));
        $version = trim((string) config('whatsapp_cloud.api_version', 'v21.0')) ?: 'v21.0';
        $timeout = max(5, (int) config('whatsapp_cloud.timeout_seconds', 30));

        $components = [];
        if ($bodyParams !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(
                    static fn (string $text): array => [
                        'type' => 'text',
                        'text' => self::sanitizeParam($text),
                    ],
                    array_values($bodyParams),
                ),
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $languageCode],
                'components' => $components,
            ],
        ];

        try {
            $response = Http::withToken((string) config('whatsapp_cloud.token'))
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", $payload)
                ->throw();
        } catch (RequestException $e) {
            $body = $e->response?->json();
            $message = is_array($body)
                ? (string) data_get($body, 'error.message', $e->getMessage())
                : $e->getMessage();

            throw new RuntimeException('WhatsApp Cloud API: '.$message, previous: $e);
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return $json;
    }

    public static function normalizeRecipient(string $phoneOrChatId): ?string
    {
        $digits = preg_replace('/\D+/', '', $phoneOrChatId) ?? '';
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            $digits = '51'.$digits;
        }

        if (strlen($digits) < 10) {
            return null;
        }

        return $digits;
    }

    private static function sanitizeParam(string $text): string
    {
        // Meta rechaza saltos de línea y tabs en parámetros de plantilla.
        $clean = preg_replace('/[\r\n\t]+/', ' ', $text) ?? $text;
        $clean = trim(preg_replace('/\s{2,}/', ' ', $clean) ?? $clean);

        return $clean !== '' ? $clean : '-';
    }
}
