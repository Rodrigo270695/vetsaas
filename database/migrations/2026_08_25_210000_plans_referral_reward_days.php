<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedSmallInteger('referral_reward_days')
                ->default(0)
                ->after('trial_days');
        });

        // Defaults pedidos: starter 14, pro 21, clinica 30 (1 mes).
        $defaults = [
            'starter' => 14,
            'pro' => 21,
            'clinica' => 30,
            'free' => 0,
        ];

        foreach ($defaults as $codigo => $days) {
            DB::table('plans')->where('codigo', $codigo)->update([
                'referral_reward_days' => $days,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('referral_reward_days');
        });
    }
};
