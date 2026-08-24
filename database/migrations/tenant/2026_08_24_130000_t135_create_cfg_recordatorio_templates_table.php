<?php

use App\Database\Migrations\TenantMigration;
use App\Support\Notifications\RecordatorioTemplateCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('cfg_recordatorio_templates')) {
                return;
            }

            Schema::create('cfg_recordatorio_templates', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tipo', 60);
                $table->string('grupo', 40);
                $table->string('canal', 20)->default('whatsapp');
                $table->text('cuerpo');
                $table->boolean('activo')->default(true);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->foreignUuid('updated_by_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestampsTz();

                $table->unique('tipo');
                $table->index(['grupo', 'orden']);
                $table->index('activo');
            });

            $now = now();
            $rows = [];
            foreach (RecordatorioTemplateCatalog::definitions() as $definition) {
                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'tipo' => $definition['tipo'],
                    'grupo' => $definition['grupo'],
                    'canal' => 'whatsapp',
                    'cuerpo' => $definition['cuerpo_default'],
                    'activo' => true,
                    'orden' => $definition['orden'],
                    'updated_by_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                DB::table('cfg_recordatorio_templates')->insert($rows);
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('cfg_recordatorio_templates');
        });
    }
};
