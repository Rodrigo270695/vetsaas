<?php

use App\Database\Migrations\TenantMigration;
use App\Models\FelDocument;
use App\Support\Fel\FelDocumentApisunatModeResolver;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('fel_documents') || ! Schema::hasColumn('fel_documents', 'apisunat_mode')) {
                return;
            }

            FelDocument::query()
                ->whereNull('apisunat_mode')
                ->orderBy('id')
                ->chunkById(100, function ($documents): void {
                    foreach ($documents as $document) {
                        FelDocumentApisunatModeResolver::resolveAndPersist($document);
                    }
                });
        });
    }

    public function down(): void
    {
        // Sin reversión: el backfill no debe borrarse al hacer rollback de schema.
    }
};
