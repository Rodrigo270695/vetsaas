<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_support_threads', function (Blueprint $table): void {
            if (! Schema::hasColumn('platform_support_threads', 'assigned_agent_id')) {
                $table->uuid('assigned_agent_id')->nullable()->after('support_user_id');
                $table->foreign('assigned_agent_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('platform_support_threads', 'clinic_waiting_since')) {
                $table->timestamp('clinic_waiting_since')->nullable()->after('from_clinic');
            }
            if (! Schema::hasColumn('platform_support_threads', 'first_response_at')) {
                $table->timestamp('first_response_at')->nullable()->after('clinic_waiting_since');
            }
            if (! Schema::hasColumn('platform_support_threads', 'muted_at')) {
                $table->timestamp('muted_at')->nullable()->after('platform_last_read_at');
            }
        });

        if (! Schema::hasTable('platform_support_notes')) {
            Schema::create('platform_support_notes', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('user_id');
                $table->text('body');
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->index(['tenant_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('platform_support_templates')) {
            Schema::create('platform_support_templates', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('label', 120);
                $table->text('body');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->uuid('created_by')->nullable();
                $table->timestamps();

                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                $table->index(['is_active', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_support_templates');
        Schema::dropIfExists('platform_support_notes');

        Schema::table('platform_support_threads', function (Blueprint $table): void {
            if (Schema::hasColumn('platform_support_threads', 'assigned_agent_id')) {
                $table->dropForeign(['assigned_agent_id']);
                $table->dropColumn('assigned_agent_id');
            }
            foreach (['clinic_waiting_since', 'first_response_at', 'muted_at'] as $col) {
                if (Schema::hasColumn('platform_support_threads', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
