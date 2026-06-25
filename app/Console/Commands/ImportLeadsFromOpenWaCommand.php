<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SalesConversation;
use App\Services\OpenWa\OpenWaClient;
use App\Services\OpenWa\PlatformWhatsAppSessionSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Importa como leads fríos los chats de WhatsApp que aún no están
 * registrados en `sales_conversations`.
 *
 * Consulta la API de OpenWA para listar todos los chats de la sesión
 * de plataforma y filtra los que:
 *  - No son grupos (@g.us)
 *  - No están ya en la tabla sales_conversations
 *  - Contienen mensajes con keywords de VetSaaS (opcional con --all)
 *
 * Uso:
 *   php artisan vetsaas:import-leads-from-openwa           Solo chats con keywords VetSaaS
 *   php artisan vetsaas:import-leads-from-openwa --all     Todos los chats (tú decides)
 *   php artisan vetsaas:import-leads-from-openwa --dry-run Solo lista, no importa
 *   php artisan vetsaas:import-leads-from-openwa --days=6  Inactividad simulada
 */
final class ImportLeadsFromOpenWaCommand extends Command
{
    protected $signature = 'vetsaas:import-leads-from-openwa
        {--all     : Importar todos los chats sin filtrar por keywords}
        {--days=5  : Días de inactividad simulada para el scheduler}
        {--dry-run : Solo muestra qué se importaría, sin guardar nada}
        {--limit=100 : Máximo de chats a procesar}';

    protected $description = 'Importa chats existentes de OpenWA como leads fríos para reactivación';

    /** Keywords de VetSaaS para filtrar chats relevantes. */
    private const KEYWORDS = [
        'vetsaas', 'vet saas', 'veterinari', 'clinica vet', 'clínica vet',
        'sistema', 'software', 'gestión', 'gestion', 'historial', 'citas',
        'paciente', 'plan', 'precio', 'demo', 'prueba', 'orvae',
        'factura', 'comprobante', 'boleta',
    ];

    public function __construct(
        private readonly OpenWaClient $client,
        private readonly PlatformWhatsAppSessionSync $sync,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun  = (bool) $this->option('dry-run');
        $all     = (bool) $this->option('all');
        $days    = (int) $this->option('days');
        $limit   = (int) $this->option('limit');

        if (! $this->client->isConfigured()) {
            $this->error('OpenWA no está configurado. Verifica OPENWA_API_KEY y OPENWA_API_URL en .env');
            return self::FAILURE;
        }

        $this->info('🔌 Conectando con OpenWA...');

        // Obtener session ID activa.
        $session = $this->sync->ensure();
        if ($session === null || ! $session->isReady()) {
            $this->error('La sesión de OpenWA no está conectada. Verifica que el bot está activo.');
            return self::FAILURE;
        }

        $sessionId = trim((string) $session->openwa_session_id);
        $this->info("✅ Sesión activa: {$sessionId}");
        $this->info('📥 Obteniendo lista de chats...');

        // Llamar al endpoint de chats de OpenWA.
        try {
            $chats = $this->fetchChats($sessionId, $limit);
        } catch (\Throwable $e) {
            $this->error('Error al obtener chats de OpenWA: ' . $e->getMessage());
            $this->line('  → Asegúrate de que tu versión de OpenWA soporte GET /api/sessions/{id}/chats');
            return self::FAILURE;
        }

        if (empty($chats)) {
            $this->warn('No se encontraron chats en OpenWA.');
            return self::SUCCESS;
        }

        $this->info("📋 " . count($chats) . " chats encontrados.");
        $this->line('');

        $imported = 0;
        $skipped  = 0;
        $filtered = 0;

        foreach ($chats as $chat) {
            $chatId = (string) ($chat['id'] ?? $chat['chatId'] ?? '');

            // Ignorar grupos.
            if (str_ends_with($chatId, '@g.us') || str_ends_with($chatId, '@newsletter')) {
                continue;
            }

            $phone = preg_replace('/\D/', '', str_replace('@c.us', '', $chatId));
            if ($phone === '' || strlen($phone) < 8) {
                continue;
            }

            $name    = (string) ($chat['name'] ?? $chat['pushname'] ?? '');
            $lastMsg = (string) ($chat['lastMessage']['body'] ?? $chat['snippet'] ?? '');

            // Filtrar por keywords si no se usa --all.
            if (! $all && ! $this->hasVetSaaSKeyword($lastMsg . ' ' . $name)) {
                $filtered++;
                continue;
            }

            // Verificar duplicado.
            if (SalesConversation::query()->where('phone', $phone)->exists()) {
                $skipped++;
                continue;
            }

            $label = ($name !== '' ? $name : 'Sin nombre') . " [{$phone}]";
            $this->line('  ' . ($dryRun ? '👁️ ' : '✅ ') . $label . ($lastMsg ? " — \"" . mb_substr($lastMsg, 0, 60) . "\"" : ''));

            if (! $dryRun) {
                SalesConversation::query()->create([
                    'phone'              => $phone,
                    'wa_chat_id'         => $chatId,
                    'prospect_name'      => $name !== '' ? $name : null,
                    'messages'           => $lastMsg !== '' ? [['role' => 'user', 'content' => $lastMsg]] : [],
                    'turn_count'         => $lastMsg !== '' ? 1 : 0,
                    'bot_active'         => false,
                    'activation_trigger' => 'manual:openwa-import',
                    'last_message_at'    => now()->subDays($days),
                    'reactivation_count' => 0,
                    'converted'          => false,
                ]);
            }

            $imported++;
        }

        $this->line('');
        $this->info("📊 Resumen: {$imported} importados, {$skipped} duplicados omitidos, {$filtered} sin keywords VetSaaS.");

        if (! $dryRun && $imported > 0) {
            $this->line('');
            $this->line("Los {$imported} leads quedan listos para reactivación automática a las 10:00 AM.");
            $this->line("Para reactivarlos ahora:");
            $this->line("  php artisan vetsaas:reactivate-cold-leads");
        }

        if ($filtered > 0 && ! $all) {
            $this->line('');
            $this->line("Se filtraron {$filtered} chats sin keywords de VetSaaS.");
            $this->line("Usa --all para importar todos los chats independientemente del contenido.");
        }

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchChats(string $sessionId, int $limit): array
    {
        $apiKey = trim((string) config('openwa.api_key', ''));
        $apiUrl = rtrim((string) config('openwa.api_url', ''), '/');

        $response = Http::timeout(30)
            ->acceptJson()
            ->withHeaders(['X-API-Key' => $apiKey])
            ->get("{$apiUrl}/api/sessions/{$sessionId}/chats", ['limit' => $limit]);

        if (! $response->successful()) {
            throw new \RuntimeException('OpenWA HTTP ' . $response->status() . ': ' . $response->body());
        }

        $data = $response->json();

        // OpenWA puede devolver el array directamente o envuelto en { data: [...] }.
        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        }

        return is_array($data) ? $data : [];
    }

    private function hasVetSaaSKeyword(string $text): bool
    {
        $lower = mb_strtolower($text);
        foreach (self::KEYWORDS as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }
        return false;
    }
}
