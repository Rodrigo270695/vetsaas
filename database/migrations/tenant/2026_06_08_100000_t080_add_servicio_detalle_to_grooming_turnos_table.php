<?php

use App\Database\Migrations\TenantMigration;
use App\Grooming\GroomingCatalogoServicio;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('grooming_turnos', function (Blueprint $table) {
                $table->string('servicio_detalle', 500)->nullable()->after('servicio');
            });

            $slugs = GroomingCatalogoServicio::slugs();

            DB::table('grooming_turnos')
                ->orderBy('id')
                ->chunk(200, function ($rows) use ($slugs): void {
                    foreach ($rows as $row) {
                        $servicio = (string) $row->servicio;

                        if ($servicio === '' || in_array($servicio, $slugs, true)) {
                            continue;
                        }

                        DB::table('grooming_turnos')
                            ->where('id', $row->id)
                            ->update([
                                'servicio_detalle' => mb_substr($servicio, 0, 500),
                                'servicio' => GroomingCatalogoServicio::OTRO_PERSONALIZADO,
                            ]);
                    }
                });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('grooming_turnos', function (Blueprint $table) {
                $table->dropColumn('servicio_detalle');
            });
        });
    }
};
