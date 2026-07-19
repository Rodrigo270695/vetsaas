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

    private const OFF_TOPIC_REFUSAL_PLATFORM = 'Solo puedo ayudarte con operaciones del SaaS VetSaaS: cobros, suscripciones, Bot IA, OpenWA, leads fríos y el panel de plataforma.';

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
     * @param  array{url?: string, component?: string, paciente_id?: string, scope?: string}|null  $pageContext
     * @return array{reply: string, used_tools: list<string>, actions: list<array{type: string, url: string, label: string}>}
     */
    public function chat(string $userMessage, array $history = [], ?array $pageContext = null): array
    {
        $userMessage = trim($userMessage);
        $scope = ($pageContext['scope'] ?? null) === 'platform' ? 'platform' : 'clinic';

        // Ahorro de tokens: rechazo local de preguntas claramente fuera de alcance.
        if ($this->shouldRefuseLocally($userMessage, $scope)) {
            return [
                'reply' => $scope === 'platform' ? self::OFF_TOPIC_REFUSAL_PLATFORM : self::OFF_TOPIC_REFUSAL,
                'used_tools' => [],
                'actions' => [],
            ];
        }

        $this->tools->setPageContext($pageContext);

        if ($scope === 'clinic') {
            // Navegación directa sin OpenAI (ahorra tokens).
            if (InAppAssistantNavigation::looksLikeNavigationRequest($userMessage)) {
                $resolved = InAppAssistantNavigation::resolve($userMessage);
                if ($resolved !== null) {
                    return [
                        'reply' => "Listo. Puedes ir a «{$resolved['label']}» con el botón de abajo.",
                        'used_tools' => ['resolver_navegacion'],
                        'actions' => [[
                            'type' => 'navigate',
                            'url' => $resolved['url'],
                            'label' => $resolved['label'],
                        ]],
                    ];
                }
            }

            // Resumen de historia del paciente en pantalla, sin OpenAI si el pedido es claro.
            $historyLocal = $this->tryLocalHistorySummary($userMessage, $pageContext);
            if ($historyLocal !== null) {
                return $historyLocal;
            }

            // Agenda de citas (hoy/mañana + vet/sede) sin OpenAI cuando el pedido es claro.
            $agendaLocal = $this->tryLocalAgenda($userMessage);
            if ($agendaLocal !== null) {
                return $agendaLocal;
            }

            $screenLocal = $this->tryLocalExplainScreen($userMessage, $pageContext);
            if ($screenLocal !== null) {
                return $screenLocal;
            }

            $opsLocal = $this->tryLocalClinicOps($userMessage);
            if ($opsLocal !== null) {
                return $opsLocal;
            }
        } else {
            // Atajo local: pendientes de pago / cobros.
            $platformLocal = $this->tryLocalPlatformQuery($userMessage);
            if ($platformLocal !== null) {
                return $platformLocal;
            }

            $screenLocal = $this->tryLocalExplainScreen($userMessage, $pageContext);
            if ($screenLocal !== null) {
                return $screenLocal;
            }

            $platformOps = $this->tryLocalPlatformOps($userMessage);
            if ($platformOps !== null) {
                return $platformOps;
            }
        }

        $apiKey = trim((string) config('in-app-assistant.openai_api_key', ''));
        if ($apiKey === '') {
            $apiKey = trim((string) config('bot-ia.openai_api_key', ''));
        }
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY no está configurada.');
        }

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($pageContext, $scope)],
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
        $reply = $this->chatWithTools($apiKey, $messages, $usedTools, $scope);

        return [
            'reply' => $reply,
            'used_tools' => array_values(array_unique($usedTools)),
            'actions' => $this->tools->pullUiActions(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  list<string>  $usedTools
     */
    private function chatWithTools(string $apiKey, array $messages, array &$usedTools, string $scope = 'clinic'): string
    {
        $tools = InAppAssistantTools::definitions($scope);

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
     * @param  array{url?: string, component?: string, paciente_id?: string, scope?: string}|null  $pageContext
     */
    private function systemPrompt(?array $pageContext = null, string $scope = 'clinic'): string
    {
        if ($scope === 'platform') {
            return $this->platformSystemPrompt($pageContext);
        }

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
Para resumen de historial (consultas, vacunas, labs), usa resumen_historia_paciente.
Para alertas del día (vacunas por vencer, stock bajo, caducidades, caja), usa alertas_operativas.
Para caducidades/lotes por vencer, usa caducidades_proximas.
Para caja del día (apertura, ventas, cierres), usa caja_del_dia.
Para buscar boleta/venta por número, usa buscar_venta.
Para «quién atiende hoy» / veterinarios con agenda, usa quien_atiende_hoy.
Para agenda («citas de hoy», «citas de María mañana», «citas de la sede Centro»), usa agenda_citas.
Si piden «explica esta pantalla» o «qué hago aquí», usa explicar_pantalla.
Si piden ir a un módulo («llévame a vacunaciones», «abre caja»), usa resolver_navegacion y menciona la URL/botón.

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
     * @param  array{url?: string, component?: string, scope?: string}|null  $pageContext
     */
    private function platformSystemPrompt(?array $pageContext = null): string
    {
        $fecha = now(config('app.timezone', 'America/Lima'))->format('d/m/Y H:i');
        $contextoPantalla = $this->formatPageContext($pageContext);

        return <<<PROMPT
Eres el asistente operativo del panel central de VetSaaS (superadmin).
Responde siempre en español, claro y conciso. Fecha/hora actual: {$fecha}.

═══════════════════════════════════════
ALCANCE
═══════════════════════════════════════
Solo ayudas con operaciones del SaaS:
1) Cobros/pagos de suscripción (pendientes, fallidos).
2) Suscripciones en riesgo (grace, suspended, próximo cobro) y tenants por vencer.
3) Uso del Bot IA (activo/inactivo por clínica).
4) Estado OpenWA / WhatsApp y jobs fallidos.
5) Leads fríos elegibles para reactivación.
6) Búsqueda de clínicas (tenants) y navegación del panel plataforma.
7) Explicar la pantalla actual.

FUERA DE ALCANCE: cultura general, datos clínicos de una clínica concreta (pacientes/citas), o acciones de escritura.
Si está fuera de alcance, rechaza en 1–2 frases.

═══════════════════════════════════════
CONTEXTO DE PANTALLA
═══════════════════════════════════════
{$contextoPantalla}

Herramientas clave:
- «quiénes están pendientes de pago» → cobros_pendientes
- cobros fallidos → cobros_fallidos
- clínicas en grace / suspended / próximo cobro → suscripciones_en_riesgo
- quiénes vencen en X días → tenants_por_vencer
- Bot IA activo/inactivo → uso_bot_ia
- OpenWA / sesiones rotas / cola → estado_whatsapp_openwa
- leads fríos → leads_frios
- panorama general → resumen_plataforma
- buscar clínica por nombre → buscar_clinicas
- explicar esta pantalla → explicar_pantalla
- ir a un módulo → resolver_navegacion_plataforma

REGLAS:
- Solo lectura. No marques reembolsos, no cambies estados, no escribas datos.
- Resume en bullets. Incluye nombres de clínicas y montos cuando existan.
- Menciona la URL / botón «Ir a…» cuando la herramienta lo aporte.
PROMPT;
    }

    /**
     * Atajos locales (sin OpenAI) para preguntas típicas de plataforma.
     *
     * @return array{reply: string, used_tools: list<string>, actions: list<array{type: string, url: string, label: string}>}|null
     */
    private function tryLocalPlatformQuery(string $message): ?array
    {
        $q = mb_strtolower(trim($message));
        if ($q === '') {
            return null;
        }

        $asksPending = (bool) preg_match(
            '/pendiente(s)?\s+de\s+pago|cobros?\s+pendiente|quién(es)?\s+(está|estan|están)\s+pendiente|quien(es)?\s+debe(n)?\s+pagar|pagos?\s+pendiente/u',
            $q,
        );

        if (! $asksPending) {
            return null;
        }

        $json = $this->tools->execute('cobros_pendientes', ['limite' => 20]);
        $data = json_decode($json, true);
        if (! is_array($data) || ($data['ok'] ?? false) !== true) {
            return null;
        }

        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $count = (int) ($data['count'] ?? count($items));

        if ($count === 0) {
            return [
                'reply' => 'No hay cobros en estado pendiente ahora mismo.',
                'used_tools' => ['cobros_pendientes'],
                'actions' => $this->tools->pullUiActions(),
            ];
        }

        $lines = ["Hay **{$count}** cobro(s) pendiente(s):"];
        foreach (array_slice($items, 0, 12) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $clinica = (string) ($item['clinica'] ?? 'Clínica');
            $total = $item['total'] ?? null;
            $moneda = (string) ($item['moneda'] ?? 'PEN');
            $plan = (string) ($item['plan'] ?? '');
            $monto = $total !== null ? " — {$moneda} {$total}" : '';
            $planBit = $plan !== '' ? " ({$plan})" : '';
            $lines[] = "- {$clinica}{$planBit}{$monto}";
        }
        if ($count > 12) {
            $lines[] = '- …y más. Abre Cobros para ver el listado completo.';
        }

        return [
            'reply' => implode("\n", $lines),
            'used_tools' => ['cobros_pendientes'],
            'actions' => $this->tools->pullUiActions(),
        ];
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
    private function shouldRefuseLocally(string $message, string $scope = 'clinic'): bool
    {
        if ($message === '') {
            return false;
        }

        if ($scope === 'platform') {
            if ($this->looksPlatformRelated($message)) {
                return false;
            }
        } elseif ($this->looksClinicRelated($message)) {
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
            'llévame', 'llevame', 'abre', 'abrir', 'historial',
            'agenda', 'veterinario', 'vet ', 'caduc', 'lote', 'boleta',
            'atiende', 'quién atiende', 'quien atiende', 'pantalla', 'explica',
        ];

        foreach ($hints as $hint) {
            if (str_contains($msg, $hint)) {
                return true;
            }
        }

        return false;
    }

    private function looksPlatformRelated(string $message): bool
    {
        $msg = mb_strtolower($message);

        $hints = [
            'cobro', 'pago', 'pendiente', 'fallido', 'reembolso', 'factura',
            'suscripci', 'grace', 'suspended', 'suspendid', 'plan', 'tenant',
            'clínica', 'clinica', 'veterinaria', 'próximo cobro', 'proximo cobro',
            'plataforma', 'operaciones', 'saas', 'vetsaas', 'tenant',
            'quién', 'quien', 'cuánt', 'cuant', 'resumen', 'alerta',
            'llévame', 'llevame', 'abre', 'abrir', 'buscar', 'busca',
            'cómo', 'como ', 'dónde', 'donde', 'ayuda',
            'bot ia', 'whatsapp', 'openwa', 'lead', 'frío', 'frio',
            'vencen', 'vencer', 'pantalla', 'explica', 'cola', 'job',
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

    /**
     * @param  array{url?: string, component?: string, paciente_id?: string}|null  $pageContext
     * @return array{reply: string, used_tools: list<string>, actions: list<array{type: string, url: string, label: string}>}|null
     */
    private function tryLocalHistorySummary(string $message, ?array $pageContext): ?array
    {
        $msg = mb_strtolower($message);
        $wantsSummary = (bool) preg_match(
            '/\b(res[uú]me|resumen|historia|historial)\b/u',
            $msg,
        ) && (bool) preg_match(
            '/\b(paciente|mascota|historial|historia|este|esta)\b/u',
            $msg,
        );

        if (! $wantsSummary) {
            return null;
        }

        $pacienteId = trim((string) ($pageContext['paciente_id'] ?? ''));
        if ($pacienteId === '') {
            return null;
        }

        $raw = $this->tools->execute('resumen_historia_paciente', [
            'paciente_id' => $pacienteId,
            'limite' => 5,
        ]);
        $data = json_decode($raw, true);
        if (! is_array($data) || ($data['ok'] ?? false) !== true) {
            return null;
        }

        return [
            'reply' => $this->formatHistorySummaryReply($data),
            'used_tools' => ['resumen_historia_paciente'],
            'actions' => [[
                'type' => 'navigate',
                'url' => (string) ($data['paciente']['url'] ?? '/clinica/pacientes/'.$pacienteId),
                'label' => 'Ver historial',
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatHistorySummaryReply(array $data): string
    {
        $p = is_array($data['paciente'] ?? null) ? $data['paciente'] : [];
        $nombre = (string) ($p['nombre'] ?? 'Paciente');
        $especie = trim((string) ($p['especie'] ?? ''));
        $titular = trim((string) ($p['titular'] ?? ''));

        $lines = ["**{$nombre}**".($especie !== '' ? " ({$especie})" : '')];
        if ($titular !== '') {
            $lines[] = "Titular: {$titular}";
        }

        $consultas = is_array($data['consultas'] ?? null) ? $data['consultas'] : [];
        $lines[] = '';
        $lines[] = 'Consultas recientes:';
        if ($consultas === []) {
            $lines[] = '• Sin consultas registradas.';
        } else {
            foreach ($consultas as $c) {
                if (! is_array($c)) {
                    continue;
                }
                $fecha = (string) ($c['fecha'] ?? '—');
                $motivo = trim((string) ($c['motivo'] ?? 'Sin motivo')) ?: 'Sin motivo';
                $lines[] = "• {$fecha}: {$motivo}";
            }
        }

        $apps = is_array($data['aplicaciones'] ?? null) ? $data['aplicaciones'] : [];
        $lines[] = '';
        $lines[] = 'Vacunas / aplicaciones:';
        if ($apps === []) {
            $lines[] = '• Sin aplicaciones registradas.';
        } else {
            foreach ($apps as $a) {
                if (! is_array($a)) {
                    continue;
                }
                $fecha = (string) ($a['fecha'] ?? '—');
                $vac = (string) ($a['nombre'] ?? 'Aplicación');
                $prox = trim((string) ($a['proxima'] ?? ''));
                $extra = $prox !== '' ? " (próx. {$prox})" : '';
                $lines[] = "• {$fecha}: {$vac}{$extra}";
            }
        }

        $labs = is_array($data['laboratorio'] ?? null) ? $data['laboratorio'] : [];
        $lines[] = '';
        $lines[] = 'Laboratorio:';
        if ($labs === []) {
            $lines[] = '• Sin pedidos registrados.';
        } else {
            foreach ($labs as $lab) {
                if (! is_array($lab)) {
                    continue;
                }
                $fecha = (string) ($lab['fecha'] ?? '—');
                $estado = (string) ($lab['estado'] ?? '');
                $examenes = is_array($lab['examenes'] ?? null)
                    ? implode(', ', array_map('strval', $lab['examenes']))
                    : '';
                $detail = $examenes !== '' ? $examenes : 'Pedido';
                $lines[] = "• {$fecha}: {$detail}".($estado !== '' ? " [{$estado}]" : '');
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{reply: string, used_tools: list<string>, actions: list<array{type: string, url: string, label: string}>}|null
     */
    private function tryLocalAgenda(string $message): ?array
    {
        $msg = mb_strtolower(trim($message));
        if (! preg_match('/\bcitas?\b/u', $msg)) {
            return null;
        }

        // Evitar capturar "dónde están citas" (navegación) — eso lo resuelve navegación.
        if (InAppAssistantNavigation::looksLikeNavigationRequest($message)) {
            return null;
        }

        $fecha = 'hoy';
        if (preg_match('/\b(mañana|manana|tomorrow)\b/u', $msg) === 1) {
            $fecha = 'mañana';
        } elseif (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/u', $msg, $m) === 1) {
            $fecha = $m[1];
        }

        $veterinario = null;
        if (preg_match('/\b(?:de|del|dra?\.?)\s+([a-záéíóúñü][\wáéíóúñü\.\s]{1,40}?)(?:\s+(?:mañana|manana|hoy|tomorrow|en|de la|sede)\b|$)/ui', $msg, $m) === 1) {
            $candidate = trim($m[1]);
            $skip = ['hoy', 'mañana', 'manana', 'la', 'las', 'el', 'los', 'sede', 'esta', 'este'];
            if ($candidate !== '' && ! in_array(mb_strtolower($candidate), $skip, true)) {
                $veterinario = $candidate;
            }
        }

        $sede = null;
        if (preg_match('/\bsede\s+([a-záéíóúñü][\wáéíóúñü\.\s-]{1,40})$/ui', $msg, $m) === 1
            || preg_match('/\bsede\s+([a-záéíóúñü][\wáéíóúñü\.\s-]{1,40})\b/ui', $msg, $m) === 1) {
            $sede = trim($m[1]);
        }

        $raw = $this->tools->execute('agenda_citas', array_filter([
            'fecha' => $fecha,
            'veterinario' => $veterinario,
            'sede' => $sede,
        ], static fn ($v) => $v !== null && $v !== ''));

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return null;
        }

        if (($data['ok'] ?? false) !== true) {
            return [
                'reply' => (string) ($data['error'] ?? 'No pude consultar la agenda.'),
                'used_tools' => ['agenda_citas'],
                'actions' => [[
                    'type' => 'navigate',
                    'url' => '/clinica/citas',
                    'label' => 'Citas',
                ]],
            ];
        }

        return [
            'reply' => $this->formatAgendaReply($data),
            'used_tools' => ['agenda_citas'],
            'actions' => $this->tools->pullUiActions(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatAgendaReply(array $data): string
    {
        $fecha = (string) ($data['fecha'] ?? '');
        $vet = trim((string) ($data['veterinario'] ?? ''));
        $sede = trim((string) ($data['sede'] ?? ''));
        $count = (int) ($data['count'] ?? 0);

        $header = "**Agenda {$fecha}**";
        if ($vet !== '') {
            $header .= " · {$vet}";
        }
        if ($sede !== '') {
            $header .= " · sede {$sede}";
        }

        $lines = [$header, ''];
        if ($count === 0) {
            $lines[] = 'No hay citas para ese filtro.';

            return implode("\n", $lines);
        }

        $lines[] = "{$count} cita(s):";
        $citas = is_array($data['citas'] ?? null) ? $data['citas'] : [];
        foreach ($citas as $c) {
            if (! is_array($c)) {
                continue;
            }
            $hora = (string) ($c['hora'] ?? '—');
            $pac = (string) ($c['paciente'] ?? 'Paciente');
            $v = trim((string) ($c['veterinario'] ?? ''));
            $s = trim((string) ($c['sede'] ?? ''));
            $estado = (string) ($c['estado'] ?? '');
            $extra = trim(($v !== '' ? $v : '').($s !== '' ? ($v !== '' ? ' · ' : '').$s : ''));
            $lines[] = "• {$hora} — {$pac}".($extra !== '' ? " ({$extra})" : '').($estado !== '' ? " [{$estado}]" : '');
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array{url?: string, component?: string, paciente_id?: string, scope?: string}|null  $pageContext
     * @return array{reply: string, used_tools: list<string>, actions: list<array{type: string, url: string, label: string}>}|null
     */
    private function tryLocalExplainScreen(string $message, ?array $pageContext): ?array
    {
        $msg = mb_strtolower(trim($message));
        if (! preg_match('/\b(explica|explicar|qu[eé]\s+(es|hace|hago)|para\s+qu[eé]\s+sirve).*(pantalla|aqu[ií]|esto|esta\s+vista)|explicar\s+esta\s+pantalla|qu[eé]\s+hago\s+aqu[ií]\b/u', $msg)) {
            return null;
        }

        $raw = $this->tools->execute('explicar_pantalla', []);
        $data = json_decode($raw, true);
        if (! is_array($data) || ($data['ok'] ?? false) !== true) {
            return null;
        }

        $titulo = (string) ($data['titulo'] ?? 'Pantalla actual');
        $resumen = (string) ($data['resumen'] ?? '');
        $acciones = is_array($data['acciones_tipicas'] ?? null) ? $data['acciones_tipicas'] : [];

        $lines = ["**{$titulo}**", '', $resumen];
        if ($acciones !== []) {
            $lines[] = '';
            $lines[] = 'Acciones típicas:';
            foreach ($acciones as $accion) {
                $lines[] = '• '.(string) $accion;
            }
        }

        $actions = [];
        $url = trim((string) ($data['url'] ?? ''));
        if ($url !== '') {
            $actions[] = [
                'type' => 'navigate',
                'url' => $url,
                'label' => $titulo,
            ];
        }

        return [
            'reply' => implode("\n", $lines),
            'used_tools' => ['explicar_pantalla'],
            'actions' => $actions,
        ];
    }

    /**
     * @return array{reply: string, used_tools: list<string>, actions: list<array{type: string, url: string, label: string}>}|null
     */
    private function tryLocalClinicOps(string $message): ?array
    {
        $msg = mb_strtolower(trim($message));

        if (preg_match('/\b(qui[eé]n|quienes|quiénes)\s+atiende|veterinari[oa]s?\s+(hoy|de\s+hoy)|qui[eé]n\s+est[aá]\s+en\s+agenda\b/u', $msg) === 1) {
            $fecha = preg_match('/\b(mañana|manana)\b/u', $msg) === 1 ? 'mañana' : 'hoy';
            $raw = $this->tools->execute('quien_atiende_hoy', ['fecha' => $fecha]);
            $data = json_decode($raw, true);
            if (! is_array($data) || ($data['ok'] ?? false) !== true) {
                return null;
            }

            $items = is_array($data['items'] ?? null) ? $data['items'] : [];
            $lines = ['**Quién atiende ('.(string) ($data['fecha'] ?? $fecha).')**', ''];
            if ($items === []) {
                $lines[] = 'No hay veterinarios con citas para esa fecha.';
            } else {
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $name = (string) ($item['veterinario'] ?? '—');
                    $count = (int) ($item['citas_count'] ?? 0);
                    $hora = trim((string) ($item['proxima_hora'] ?? ''));
                    $lines[] = "• {$name}: {$count} cita(s)".($hora !== '' ? " (próxima {$hora})" : '');
                }
            }
            $sin = (int) ($data['citas_sin_veterinario'] ?? 0);
            if ($sin > 0) {
                $lines[] = '';
                $lines[] = "Además hay {$sin} cita(s) sin veterinario asignado.";
            }

            return [
                'reply' => implode("\n", $lines),
                'used_tools' => ['quien_atiende_hoy'],
                'actions' => $this->tools->pullUiActions(),
            ];
        }

        if (preg_match('/\b(caduc|vencen|por\s+vencer|lotes?\s+venc)/u', $msg) === 1) {
            $raw = $this->tools->execute('caducidades_proximas', ['dias' => 30, 'limite' => 12]);
            $data = json_decode($raw, true);
            if (! is_array($data) || ($data['ok'] ?? false) !== true) {
                return null;
            }

            $lines = [
                '**Caducidades**',
                '',
                'Vencidos: '.(int) ($data['vencidos_count'] ?? 0),
                'Por vencer (30 días): '.(int) ($data['por_vencer_count'] ?? 0),
                '',
            ];
            $items = is_array($data['items'] ?? null) ? $data['items'] : [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $prod = (string) ($item['producto'] ?? 'Producto');
                $vence = (string) ($item['vence'] ?? '—');
                $estado = (string) ($item['estado'] ?? '');
                $lines[] = "• {$prod} · vence {$vence}".($estado !== '' ? " [{$estado}]" : '');
            }

            return [
                'reply' => implode("\n", $lines),
                'used_tools' => ['caducidades_proximas'],
                'actions' => $this->tools->pullUiActions(),
            ];
        }

        if (preg_match('/\bcaja\s+del\s+d[ií]a|sesi[oó]n(es)?\s+de\s+caja|c[oó]mo\s+va\s+la\s+caja|estado\s+de\s+caja\b/u', $msg) === 1) {
            $raw = $this->tools->execute('caja_del_dia', []);
            $data = json_decode($raw, true);
            if (! is_array($data) || ($data['ok'] ?? false) !== true) {
                return null;
            }

            $ventas = is_array($data['ventas_hoy'] ?? null) ? $data['ventas_hoy'] : [];
            $lines = [
                '**Caja del día ('.(string) ($data['fecha'] ?? '').')**',
                '',
                'Sesiones abiertas: '.(int) ($data['abiertas_count'] ?? 0),
            ];
            $mi = is_array($data['mi_sesion'] ?? null) ? $data['mi_sesion'] : null;
            if ($mi !== null) {
                $sede = (string) ($mi['sede'] ?? '—');
                $lines[] = "Tu sesión: abierta en {$sede}";
            } else {
                $lines[] = 'No tienes una sesión de caja abierta.';
            }
            if ($ventas !== []) {
                $lines[] = 'Ventas hoy: '.(int) ($ventas['cantidad'] ?? 0).' · total '.(string) ($ventas['total'] ?? '0');
            }

            return [
                'reply' => implode("\n", $lines),
                'used_tools' => ['caja_del_dia'],
                'actions' => $this->tools->pullUiActions(),
            ];
        }

        if (preg_match('/\b(busca|buscar|encuentra)\b.*\b(venta|boleta|factura|comprobante)\b|\b(venta|boleta|factura)\b.*\b(n[uú]mero|nro|#)\b/u', $msg) === 1) {
            $q = null;
            if (preg_match('/\b([A-Z0-9]{1,6}-\d{1,8})\b/i', $message, $m) === 1) {
                $q = $m[1];
            } elseif (preg_match('/\b(\d{3,})\b/u', $message, $m) === 1) {
                $q = $m[1];
            }
            if ($q === null) {
                return null;
            }

            $raw = $this->tools->execute('buscar_venta', ['q' => $q]);
            $data = json_decode($raw, true);
            if (! is_array($data) || ($data['ok'] ?? false) !== true) {
                return null;
            }

            $items = is_array($data['items'] ?? null) ? $data['items'] : [];
            $lines = ['**Ventas encontradas ('.$q.')**', ''];
            if ($items === []) {
                $lines[] = 'No encontré ventas con ese número.';
            } else {
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $num = (string) ($item['numero_display'] ?? $item['numero'] ?? '—');
                    $total = (string) ($item['total'] ?? '0');
                    $titular = trim((string) ($item['titular'] ?? ''));
                    $lines[] = "• {$num} · {$total}".($titular !== '' ? " · {$titular}" : '');
                }
            }

            return [
                'reply' => implode("\n", $lines),
                'used_tools' => ['buscar_venta'],
                'actions' => $this->tools->pullUiActions(),
            ];
        }

        return null;
    }

    /**
     * @return array{reply: string, used_tools: list<string>, actions: list<array{type: string, url: string, label: string}>}|null
     */
    private function tryLocalPlatformOps(string $message): ?array
    {
        $msg = mb_strtolower(trim($message));

        if (preg_match('/\b(bot\s*ia|addon\s*bot|qui[eé]n(es)?\s+tiene(n)?\s+bot)\b/u', $msg) === 1) {
            $raw = $this->tools->execute('uso_bot_ia', ['limite' => 15]);
            $data = json_decode($raw, true);
            if (! is_array($data) || ($data['ok'] ?? false) !== true) {
                return null;
            }

            $lines = [
                '**Bot IA**',
                '',
                'Activos: '.(int) ($data['activos_count'] ?? 0),
                'Inactivos (billable): '.(int) ($data['inactivos_count'] ?? 0),
                '',
                'Activos:',
            ];
            $activos = is_array($data['activos'] ?? null) ? $data['activos'] : [];
            if ($activos === []) {
                $lines[] = '• Ninguno';
            } else {
                foreach ($activos as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $lines[] = '• '.(string) ($row['clinica'] ?? '—').' ('.(string) ($row['plan'] ?? '').')';
                }
            }

            return [
                'reply' => implode("\n", $lines),
                'used_tools' => ['uso_bot_ia'],
                'actions' => $this->tools->pullUiActions(),
            ];
        }

        if (preg_match('/\b(openwa|whatsapp|sesiones?\s+rota|fallos?\s+de\s+(cola|jobs?)|jobs?\s+fallid)\b/u', $msg) === 1) {
            $raw = $this->tools->execute('estado_whatsapp_openwa', []);
            $data = json_decode($raw, true);
            if (! is_array($data) || ($data['ok'] ?? false) !== true) {
                return null;
            }

            $platform = is_array($data['platform'] ?? null) ? $data['platform'] : [];
            $ready = ($platform['ready'] ?? false) === true ? 'listo' : 'no listo';
            $lines = [
                '**OpenWA / WhatsApp**',
                '',
                'Configurado: '.(($data['openwa_configured'] ?? false) ? 'sí' : 'no'),
                'Sesión plataforma: '.$ready.($platform['phone'] ? ' · '.(string) $platform['phone'] : ''),
                'Tenants ready: '.(int) ($data['tenants_ready'] ?? 0),
                'Con error: '.(int) ($data['tenants_with_error'] ?? 0),
                'Jobs fallidos: '.(int) ($data['failed_jobs_total'] ?? 0),
            ];
            if (! empty($platform['last_error'])) {
                $lines[] = 'Último error plataforma: '.(string) $platform['last_error'];
            }

            return [
                'reply' => implode("\n", $lines),
                'used_tools' => ['estado_whatsapp_openwa'],
                'actions' => $this->tools->pullUiActions(),
            ];
        }

        if (preg_match('/\b(leads?\s+fr[ií]os?|fr[ií]os?\s+elegibles|reactivaci[oó]n)\b/u', $msg) === 1) {
            $raw = $this->tools->execute('leads_frios', []);
            $data = json_decode($raw, true);
            if (! is_array($data) || ($data['ok'] ?? false) !== true) {
                return null;
            }

            $lines = [
                '**Leads fríos**',
                '',
                'Elegibles hoy: '.(int) ($data['elegibles_hoy'] ?? 0),
                'Pool bruto: '.(int) ($data['pool_bruto'] ?? 0),
                '',
            ];
            $muestra = is_array($data['muestra'] ?? null) ? $data['muestra'] : [];
            foreach ($muestra as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $phone = (string) ($row['phone'] ?? '—');
                $name = trim((string) ($row['name'] ?? ''));
                $lines[] = '• '.$phone.($name !== '' ? " · {$name}" : '');
            }

            return [
                'reply' => implode("\n", $lines),
                'used_tools' => ['leads_frios'],
                'actions' => [],
            ];
        }

        if (preg_match('/\b(vencen|por\s+vencer|pr[oó]ximo\s+cobro|qui[eé]n(es)?\s+vence)\b/u', $msg) === 1) {
            $dias = 7;
            if (preg_match('/\b(\d{1,2})\s*d[ií]as?\b/u', $msg, $m) === 1) {
                $dias = (int) $m[1];
            }
            $raw = $this->tools->execute('tenants_por_vencer', ['dias' => $dias]);
            $data = json_decode($raw, true);
            if (! is_array($data) || ($data['ok'] ?? false) !== true) {
                return null;
            }

            $items = is_array($data['items'] ?? null) ? $data['items'] : [];
            $lines = [
                '**Vencen en '.(int) ($data['dias'] ?? $dias).' días**',
                '',
                'Total: '.(int) ($data['count'] ?? 0),
                '',
            ];
            foreach ($items as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $clinica = (string) ($row['clinica'] ?? '—');
                $vence = (string) ($row['vence_at'] ?? '—');
                $lines[] = "• {$clinica} · {$vence}";
            }

            return [
                'reply' => implode("\n", $lines),
                'used_tools' => ['tenants_por_vencer'],
                'actions' => $this->tools->pullUiActions(),
            ];
        }

        return null;
    }
}
