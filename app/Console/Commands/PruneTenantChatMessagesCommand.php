<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ClinicSetting;
use App\Models\Tenant;
use App\Services\Chat\TenantChatService;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class PruneTenantChatMessagesCommand extends Command
{
    protected $signature = 'vetsaas:chat-prune
        {--days= : Días de retención (override; por defecto ClinicSetting o 90)}
        {--tenant= : Solo este tenant (slug)}
        {--dry-run : Contar sin borrar}';

    protected $description = 'Borra mensajes de chat interno más antiguos que la retención configurada';

    public function handle(TenantManager $tenants, TenantChatService $chat): int
    {
        $only = trim((string) $this->option('tenant'));
        $overrideDays = $this->option('days');
        $dry = (bool) $this->option('dry-run');

        $query = Tenant::query()->orderBy('slug');
        if ($only !== '') {
            $query->where('slug', $only);
        }

        $total = 0;

        foreach ($query->cursor() as $tenant) {
            try {
                $tenants->runForTenant($tenant, function () use ($chat, $overrideDays, $dry, $tenant, &$total): void {
                    if (! Schema::hasTable('chat_messages')) {
                        return;
                    }

                    $days = $overrideDays !== null && $overrideDays !== ''
                        ? (int) $overrideDays
                        : (int) (ClinicSetting::query()->value('chat_retention_days') ?? 90);

                    if ($days < 7) {
                        $this->warn("[{$tenant->slug}] retención inválida ({$days}), se omite.");

                        return;
                    }

                    if ($dry) {
                        $cutoff = now()->subDays($days);
                        $count = \App\Models\ChatMessage::query()
                            ->where('created_at', '<', $cutoff)
                            ->count();
                        $this->line("[{$tenant->slug}] {$count} mensajes > {$days} días");
                        $total += $count;

                        return;
                    }

                    $deleted = $chat->pruneOlderThan($days);
                    $this->info("[{$tenant->slug}] eliminados: {$deleted} (retención {$days}d)");
                    $total += $deleted;
                }, enforceAccess: false);
            } catch (Throwable $e) {
                $this->error("[{$tenant->slug}] ".$e->getMessage());
            }
        }

        $this->info($dry ? "Dry-run total: {$total}" : "Total eliminados: {$total}");

        return self::SUCCESS;
    }
}
