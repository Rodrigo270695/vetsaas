<?php

namespace App\Tenancy;

use App\Models\Tenant;
use App\Providers\TenancyServiceProvider;
use App\Tenancy\Exceptions\TenantNotFoundException;
use App\Tenancy\Exceptions\TenantSuspendedException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Orquesta el "tenant activo" del request actual.
 *
 * Responsabilidades:
 *   1. Cargar un tenant por slug o id (con cache configurable).
 *   2. Validar que esté en un estado permitido (allowed_states).
 *   3. Aplicar `SET search_path TO "<schema>", public` a la conexión
 *      Postgres por defecto. ESTE es el mecanismo que garantiza el
 *      aislamiento físico: una vez fijado el search_path, ningún
 *      `SELECT * FROM owners` puede ver datos de otro schema.
 *   4. Exponer el {@see TenantContext} resuelto al resto de la app.
 *   5. Invalidar la cache cuando se modifica un tenant desde el panel SaaS.
 *
 * Se registra como singleton en {@see TenancyServiceProvider}.
 *
 * Importante:
 *   - NUNCA aceptes el `schema_name` desde la request: siempre se lee
 *     desde la fila `tenants` correspondiente al slug del subdominio.
 *   - El nombre se sanitiza (`/[^a-z0-9_]/i`) antes de inyectarlo en
 *     la sentencia `SET search_path`, como defensa en profundidad pese
 *     a que ya validamos que cumpla el patrón en migración.
 *   - `forget()` revierte el search_path a `public`. Útil en jobs o
 *     comandos que deban operar contra el SaaS después de un tenant.
 */
class TenantManager
{
    protected ?TenantContext $current = null;

    public function current(): ?TenantContext
    {
        return $this->current;
    }

    public function check(): bool
    {
        return $this->current !== null;
    }

    public function id(): ?string
    {
        return $this->current?->id();
    }

    public function schema(): ?string
    {
        return $this->current?->schema;
    }

    public function slug(): ?string
    {
        return $this->current?->slug;
    }

    /**
     * Resuelve un tenant por su slug (subdominio).
     *
     * @throws TenantNotFoundException si no existe o tiene schema inválido.
     * @throws TenantSuspendedException si su estado no está permitido.
     */
    public function resolveBySlug(string $slug, ?ConnectionInterface $connection = null): TenantContext
    {
        $tenant = $this->findBySlug($slug);

        if (! $tenant) {
            throw new TenantNotFoundException($slug);
        }

        return $this->bootstrap($tenant, $connection);
    }

    /**
     * Resuelve un tenant por su UUID (útil para jobs en cola que sólo
     * tienen el id).
     *
     * @throws TenantNotFoundException
     * @throws TenantSuspendedException
     */
    public function resolveById(string $id, ?ConnectionInterface $connection = null): TenantContext
    {
        $tenant = $this->findById($id);

        if (! $tenant) {
            throw new TenantNotFoundException($id);
        }

        return $this->bootstrap($tenant, $connection);
    }

    /**
     * Aplica el tenant al request actual:
     *   1. Verifica que el estado esté en `allowed_states`.
     *   2. Sanea y aplica `SET search_path` a la conexión.
     *   3. Guarda el {@see TenantContext} en memoria.
     */
    protected function bootstrap(Tenant $tenant, ?ConnectionInterface $connection = null): TenantContext
    {
        $allowed = (array) config('tenant.allowed_states', ['active', 'trial', 'grace']);

        if (! in_array($tenant->estado, $allowed, true)) {
            throw new TenantSuspendedException($tenant);
        }

        $schema = $this->safeSchemaName($tenant);

        if ($schema === null) {
            throw new TenantNotFoundException((string) ($tenant->slug ?? $tenant->getKey()));
        }

        $this->applySearchPath($schema, $connection);

        $this->current = new TenantContext(
            tenant: $tenant,
            schema: $schema,
            slug: (string) ($tenant->slug ?? ''),
        );

        return $this->current;
    }

    /**
     * Limpia el tenant activo y restaura `search_path` a `public`.
     *
     * Útil al final de un job, en tests, o cuando un comando Artisan
     * necesita volver al contexto central después de procesar un tenant.
     */
    public function forget(?ConnectionInterface $connection = null): void
    {
        $conn = $connection ?? DB::connection();

        if ($conn->getDriverName() === 'pgsql') {
            $conn->statement('SET search_path TO public');
        }

        $this->current = null;
    }

    /**
     * Ejecuta un callback con un tenant temporal "montado".
     *
     * Garantiza que aunque la callback lance excepción, el search_path
     * se restaure correctamente. Es la API recomendada para jobs en
     * cola o tareas batch que iteran varios tenants.
     *
     * @template T
     *
     * @param  callable(TenantContext): T  $callback
     * @return T
     */
    public function runForSlug(string $slug, callable $callback): mixed
    {
        $previous = $this->current;
        $context = $this->resolveBySlug($slug);

        try {
            return $callback($context);
        } finally {
            if ($previous !== null) {
                $this->applySearchPath($previous->schema);
                $this->current = $previous;
            } else {
                $this->forget();
            }
        }
    }

    /**
     * Invalida la cache de resolución de un tenant. El TenantController
     * debe llamarlo desde suspend/resume/update/destroy para que el
     * cambio de estado se propague inmediatamente sin esperar al TTL.
     */
    public function flushCacheFor(Tenant $tenant): void
    {
        if (is_string($tenant->slug) && $tenant->slug !== '') {
            Cache::forget("tenant:slug:{$tenant->slug}");
        }

        Cache::forget('tenant:id:'.$tenant->getKey());
    }

    /**
     * Sanea el nombre del schema antes de inyectarlo en `SET search_path`.
     *
     * Aunque las migraciones ya validan el formato, esta es la última
     * línea de defensa: incluso si una fila en `tenants` quedara con
     * un valor extraño (por edición directa en BD, por ejemplo), no
     * llegamos a ejecutar SQL malicioso.
     *
     * Reglas:
     *   - Solo letras minúsculas, dígitos y guion bajo.
     *   - Debe respetar `schema_prefix` si está configurado.
     *   - Longitud máxima 63 (límite de PostgreSQL).
     */
    protected function safeSchemaName(Tenant $tenant): ?string
    {
        $raw = (string) ($tenant->schema_name ?? '');

        if ($raw === '') {
            return null;
        }

        $clean = strtolower($raw);
        $clean = preg_replace('/[^a-z0-9_]/', '', $clean) ?? '';

        if ($clean === '' || strlen($clean) > 63) {
            return null;
        }

        if (! preg_match('/^[a-z_][a-z0-9_]*$/', $clean)) {
            return null;
        }

        $prefix = (string) config('tenant.schema_prefix', '');
        if ($prefix !== '' && ! str_starts_with($clean, $prefix)) {
            return null;
        }

        return $clean;
    }

    protected function applySearchPath(string $schema, ?ConnectionInterface $connection = null): void
    {
        $conn = $connection ?? DB::connection();

        if ($conn->getDriverName() !== 'pgsql') {
            return;
        }

        $conn->statement('SET search_path TO "'.$schema.'", public');
    }

    protected function findBySlug(string $slug): ?Tenant
    {
        $ttl = (int) config('tenant.cache_ttl', 60);

        $loader = fn (): ?Tenant => Tenant::query()->where('slug', $slug)->first();

        if ($ttl <= 0) {
            return $loader();
        }

        return Cache::remember("tenant:slug:{$slug}", $ttl, $loader);
    }

    protected function findById(string $id): ?Tenant
    {
        $ttl = (int) config('tenant.cache_ttl', 60);

        $loader = fn (): ?Tenant => Tenant::query()->whereKey($id)->first();

        if ($ttl <= 0) {
            return $loader();
        }

        return Cache::remember("tenant:id:{$id}", $ttl, $loader);
    }
}
