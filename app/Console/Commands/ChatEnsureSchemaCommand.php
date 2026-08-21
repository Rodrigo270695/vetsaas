<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Repara schemas tenant: crea chat_message_reactions si falta (misma estructura que t133).
 */
final class ChatEnsureSchemaCommand extends Command
{
    protected $signature = 'vetsaas:chat-ensure-schema
        {--tenant= : Solo este tenant (slug)}
        {--dry-run : Solo listar qué se crearía}';

    protected $description = 'Asegura chat_message_reactions en tenants que ya tienen chat_messages';

    public function handle(TenantManager $tenants): int
    {
        $only = trim((string) $this->option('tenant'));
        $dry = (bool) $this->option('dry-run');

        $query = Tenant::query()->orderBy('slug');
        if ($only !== '') {
            $query->where('slug', $only);
        }

        $created = 0;
        $skipped = 0;

        foreach ($query->cursor() as $tenant) {
            try {
                $tenants->run($tenant, function () use ($tenant, $dry, &$created, &$skipped): void {
                    if (! Schema::hasTable('chat_messages')) {
                        $this->line("[{$tenant->slug}] sin chat_messages — omitido");
                        $skipped++;

                        return;
                    }

                    if (Schema::hasTable('chat_message_reactions')) {
                        $this->line("[{$tenant->slug}] chat_message_reactions ya existe");
                        $skipped++;

                        return;
                    }

                    if ($dry) {
                        $this->warn("[{$tenant->slug}] crearía chat_message_reactions (dry-run)");
                        $created++;

                        return;
                    }

                    Schema::create('chat_message_reactions', function (Blueprint $table): void {
                        $table->uuid('id')->primary();
                        $table->foreignUuid('message_id')
                            ->constrained('chat_messages')
                            ->cascadeOnDelete();
                        $table->uuid('user_id');
                        $table->string('emoji', 16);
                        $table->timestampTz('created_at')->useCurrent();

                        $table->unique(['message_id', 'user_id']);
                        $table->index('message_id');
                        $table->index('user_id');
                    });

                    $this->info("[{$tenant->slug}] creada chat_message_reactions");
                    $created++;
                });
            } catch (Throwable $e) {
                $this->error("[{$tenant->slug}] ".$e->getMessage());
            }
        }

        $this->info($dry
            ? "Dry-run: {$created} pendiente(s), {$skipped} omitido(s)"
            : "Listo: {$created} creada(s), {$skipped} omitido(s)");

        return self::SUCCESS;
    }
}
