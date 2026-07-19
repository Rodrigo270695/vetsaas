<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('in_app_assistant_announcements')) {
            Schema::create('in_app_assistant_announcements', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('title', 160);
                $table->text('body');
                $table->json('features')->nullable();
                $table->boolean('is_active')->default(false);
                $table->unsignedInteger('version')->default(1);
                $table->timestamp('published_at')->nullable();
                $table->foreignUuid('created_by_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestamps();

                $table->index(['is_active', 'published_at']);
            });
        }

        if (Schema::hasTable('in_app_assistant_announcements')
            && DB::table('in_app_assistant_announcements')->count() === 0) {
            $active = true;
            $version = 1;
            $title = 'Nuevo Asistente IA en VetSaaS';
            $body = 'Ahora tienes ayuda y consultas de tu clínica sin salir del sistema. Está arriba a la derecha, junto a los breadcrumbs.';
            $features = [
                'Te orienta dónde está cada módulo y cómo usarlo',
                'Consulta pacientes, stock, alertas y más (solo lectura)',
                'Revisa citas de hoy o mañana por veterinario o sede',
                'Di «llévame a vacunaciones» y te lleva ahí',
            ];

            if (Schema::hasTable('platform_settings')) {
                $setting = DB::table('platform_settings')->orderBy('created_at')->first();
                if ($setting) {
                    if (isset($setting->in_app_assistant_announcement_active)) {
                        $active = (bool) $setting->in_app_assistant_announcement_active;
                    }
                    if (isset($setting->in_app_assistant_announcement_version)) {
                        $version = max(1, (int) $setting->in_app_assistant_announcement_version);
                    }
                    $customTitle = trim((string) ($setting->in_app_assistant_announcement_title ?? ''));
                    $customBody = trim((string) ($setting->in_app_assistant_announcement_body ?? ''));
                    if ($customTitle !== '') {
                        $title = $customTitle;
                    }
                    if ($customBody !== '') {
                        $body = $customBody;
                    }
                    $rawFeatures = $setting->in_app_assistant_announcement_features ?? null;
                    if (is_string($rawFeatures) && $rawFeatures !== '') {
                        $decoded = json_decode($rawFeatures, true);
                        if (is_array($decoded)) {
                            $cleaned = array_values(array_filter(
                                array_map(
                                    static fn ($item) => is_string($item) ? trim($item) : '',
                                    $decoded,
                                ),
                                static fn (string $item) => $item !== '',
                            ));
                            if ($cleaned !== []) {
                                $features = array_slice($cleaned, 0, 4);
                            }
                        }
                    }
                }
            }

            DB::table('in_app_assistant_announcements')->insert([
                'id' => (string) Str::uuid(),
                'title' => $title,
                'body' => $body,
                'features' => json_encode($features, JSON_UNESCAPED_UNICODE),
                'is_active' => $active,
                'version' => $version,
                'published_at' => now(),
                'created_by_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('in_app_assistant_announcements');
    }
};
