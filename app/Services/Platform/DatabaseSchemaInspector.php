<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo visual (MER) de un schema Postgres: tablas, columnas y FKs.
 */
final class DatabaseSchemaInspector
{
    private const CARD_W = 248;

    private const COL_H = 18;

    private const HEADER_H = 32;

    private const PAD = 8;

    private const MAX_COLS = 16;

    private const GAP_X = 56;

    private const GAP_Y = 28;

    /**
     * @return list<array{schema: string, label: string, kind: string}>
     */
    public function allowedSchemas(): array
    {
        $list = [[
            'schema' => 'public',
            'label' => 'public (plataforma)',
            'kind' => 'public',
        ]];

        if (! Schema::hasTable('tenants')) {
            return $list;
        }

        $tenants = Tenant::query()
            ->orderBy('slug')
            ->get(['slug', 'schema_name', 'nombre_comercial', 'razon_social']);

        foreach ($tenants as $tenant) {
            $schema = trim((string) $tenant->schema_name);
            if ($schema === '' || $schema === 'public') {
                continue;
            }
            $name = trim((string) ($tenant->nombre_comercial ?: $tenant->razon_social ?: $tenant->slug));
            $list[] = [
                'schema' => $schema,
                'label' => $tenant->slug.' · '.$name,
                'kind' => 'tenant',
            ];
        }

        return $list;
    }

    public function isAllowedSchema(string $schema): bool
    {
        foreach ($this->allowedSchemas() as $row) {
            if ($row['schema'] === $schema) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{
     *     schema: string,
     *     groups: list<array{id: string, label: string, count: int}>,
     *     tables: list<array<string, mixed>>,
     *     edges: list<array<string, mixed>>,
     *     layout: array{width: int, height: int}
     * }
     */
    public function inspect(string $schema): array
    {
        if (DB::getDriverName() !== 'pgsql') {
            return $this->empty($schema);
        }

        if (! $this->isAllowedSchema($schema) || ! $this->schemaExists($schema)) {
            return $this->empty($schema);
        }

        return Cache::remember('vetsaas.schema-er.'.$schema, 45, fn (): array => $this->build($schema));
    }

    public function forgetCache(?string $schema = null): void
    {
        if ($schema !== null) {
            Cache::forget('vetsaas.schema-er.'.$schema);

            return;
        }

        Cache::forget('vetsaas.schema-er.public');
    }

    /**
     * @return array<string, mixed>
     */
    private function build(string $schema): array
    {
        $tableNames = $this->tableNames($schema);
        $columns = $this->columnsByTable($schema);
        $pks = $this->primaryKeys($schema);
        $fks = $this->foreignKeys($schema);

        $tables = [];
        foreach ($tableNames as $name) {
            $group = $this->groupFor($name);
            $pkCols = [];
            $fkCols = [];
            $otherCols = [];
            foreach ($columns[$name] ?? [] as $col) {
                $colName = $col['name'];
                $row = [
                    'name' => $colName,
                    'type' => $col['type'],
                    'nullable' => $col['nullable'],
                    'pk' => in_array($colName, $pks[$name] ?? [], true),
                    'fk' => isset($fks['by_from'][$name][$colName]),
                ];
                if ($row['pk']) {
                    $pkCols[] = $row;
                } elseif ($row['fk']) {
                    $fkCols[] = $row;
                } else {
                    $otherCols[] = $row;
                }
            }
            $cols = array_merge($pkCols, $fkCols, $otherCols);

            $visible = array_slice($cols, 0, self::MAX_COLS);
            $hidden = max(0, count($cols) - self::MAX_COLS);
            $h = self::HEADER_H + self::PAD + (count($visible) * self::COL_H) + ($hidden > 0 ? self::COL_H : 0) + 6;

            $tables[] = [
                'name' => $name,
                'group' => $group,
                'columns' => $visible,
                'column_total' => count($cols),
                'hidden_columns' => $hidden,
                'w' => self::CARD_W,
                'h' => $h,
            ];
        }

        $grouped = [];
        foreach ($tables as $table) {
            $grouped[$table['group']][] = $table;
        }
        ksort($grouped);

        $x = 24;
        $maxH = 0;
        $placed = [];
        foreach ($grouped as $groupTables) {
            $y = 24;
            foreach ($groupTables as $table) {
                $table['x'] = $x;
                $table['y'] = $y;
                $placed[] = $table;
                $y += $table['h'] + self::GAP_Y;
            }
            $maxH = max($maxH, $y);
            $x += self::CARD_W + self::GAP_X;
        }

        $pos = [];
        foreach ($placed as $table) {
            $pos[$table['name']] = $table;
        }

        $edges = [];
        foreach ($fks['list'] as $fk) {
            $from = $pos[$fk['from_table']] ?? null;
            $to = $pos[$fk['to_table']] ?? null;
            if ($from === null || $to === null) {
                continue;
            }
            $edges[] = [
                'from' => $fk['from_table'],
                'to' => $fk['to_table'],
                'from_column' => $fk['from_column'],
                'to_column' => $fk['to_column'],
                'x1' => $from['x'] + $from['w'],
                'y1' => $from['y'] + self::HEADER_H / 2,
                'x2' => $to['x'],
                'y2' => $to['y'] + self::HEADER_H / 2,
            ];
        }

        $groups = [];
        foreach ($grouped as $id => $items) {
            $groups[] = [
                'id' => $id,
                'label' => $this->groupLabel($id),
                'count' => count($items),
            ];
        }

        return [
            'schema' => $schema,
            'groups' => $groups,
            'tables' => $placed,
            'edges' => $edges,
            'layout' => [
                'width' => max(800, $x),
                'height' => max(600, $maxH + 24),
            ],
        ];
    }

    public function groupFor(string $table): string
    {
        $exact = [
            'users' => 'identidad',
            'tenants' => 'tenancy',
            'plans' => 'tenancy',
            'plan_features' => 'tenancy',
            'subscriptions' => 'tenancy',
            'subscription_payments' => 'tenancy',
            'roles' => 'identidad',
            'permissions' => 'identidad',
            'model_has_roles' => 'identidad',
            'model_has_permissions' => 'identidad',
            'role_has_permissions' => 'identidad',
            'jobs' => 'sistema',
            'failed_jobs' => 'sistema',
            'job_batches' => 'sistema',
            'cache' => 'sistema',
            'cache_locks' => 'sistema',
            'sessions' => 'sistema',
            'migrations' => 'sistema',
            'password_reset_tokens' => 'sistema',
        ];
        if (isset($exact[$table])) {
            return $exact[$table];
        }

        return match (true) {
            str_starts_with($table, 'fel_') => 'fel',
            str_starts_with($table, 'venta') || str_starts_with($table, 'caja') => 'caja',
            str_starts_with($table, 'grooming') => 'grooming',
            str_starts_with($table, 'hotel') => 'hotel',
            str_starts_with($table, 'chat_') || str_starts_with($table, 'platform_support') => 'chat',
            str_starts_with($table, 'whatsapp') || str_starts_with($table, 'openwa') || str_contains($table, 'whatsapp') => 'whatsapp',
            str_starts_with($table, 'sales_') || str_starts_with($table, 'salesbot') || str_starts_with($table, 'veterinaria_prospecto') => 'ventas_saas',
            str_starts_with($table, 'paciente') || str_starts_with($table, 'propietario') || str_starts_with($table, 'cita') || str_starts_with($table, 'consulta') || str_starts_with($table, 'historia') || str_starts_with($table, 'vacun') || str_starts_with($table, 'receta') || str_starts_with($table, 'cirug') || str_starts_with($table, 'hospital') || str_starts_with($table, 'laboratorio') => 'clinica',
            str_starts_with($table, 'producto') || str_starts_with($table, 'categoria') || str_starts_with($table, 'stock') || str_starts_with($table, 'movimiento') || str_starts_with($table, 'compra') || str_starts_with($table, 'proveedor') => 'inventario',
            default => 'otros',
        };
    }

    public function groupLabel(string $id): string
    {
        return match ($id) {
            'tenancy' => 'Tenancy / planes',
            'identidad' => 'Usuarios y roles',
            'clinica' => 'Clínica',
            'caja' => 'Caja y ventas',
            'fel' => 'Facturación SUNAT',
            'inventario' => 'Inventario',
            'grooming' => 'Grooming',
            'hotel' => 'Hotel',
            'chat' => 'Chat',
            'whatsapp' => 'WhatsApp',
            'ventas_saas' => 'Ventas SaaS',
            'sistema' => 'Sistema Laravel',
            default => 'Otros',
        };
    }

    /**
     * @return list<string>
     */
    private function tableNames(string $schema): array
    {
        $rows = DB::select(
            'SELECT table_name
             FROM information_schema.tables
             WHERE table_schema = ? AND table_type = \'BASE TABLE\'
             ORDER BY table_name',
            [$schema],
        );

        return array_values(array_map(static fn ($r): string => (string) $r->table_name, $rows));
    }

    /**
     * @return array<string, list<array{name: string, type: string, nullable: bool}>>
     */
    private function columnsByTable(string $schema): array
    {
        $rows = DB::select(
            'SELECT table_name, column_name, data_type, is_nullable, udt_name
             FROM information_schema.columns
             WHERE table_schema = ?
             ORDER BY table_name, ordinal_position',
            [$schema],
        );

        $out = [];
        foreach ($rows as $row) {
            $type = (string) $row->udt_name;
            if ($type === '') {
                $type = (string) $row->data_type;
            }
            $out[(string) $row->table_name][] = [
                'name' => (string) $row->column_name,
                'type' => $type,
                'nullable' => $row->is_nullable === 'YES',
            ];
        }

        return $out;
    }

    /**
     * @return array<string, list<string>>
     */
    private function primaryKeys(string $schema): array
    {
        $rows = DB::select(
            'SELECT kcu.table_name, kcu.column_name
             FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu
               ON tc.constraint_name = kcu.constraint_name
              AND tc.table_schema = kcu.table_schema
             WHERE tc.constraint_type = \'PRIMARY KEY\'
               AND tc.table_schema = ?',
            [$schema],
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->table_name][] = (string) $row->column_name;
        }

        return $out;
    }

    /**
     * @return array{
     *     list: list<array{from_table: string, from_column: string, to_table: string, to_column: string}>,
     *     by_from: array<string, array<string, true>>
     * }
     */
    private function foreignKeys(string $schema): array
    {
        $rows = DB::select(
            'SELECT
                kcu.table_name AS from_table,
                kcu.column_name AS from_column,
                ccu.table_name AS to_table,
                ccu.column_name AS to_column
             FROM information_schema.table_constraints AS tc
             JOIN information_schema.key_column_usage AS kcu
               ON tc.constraint_name = kcu.constraint_name
              AND tc.table_schema = kcu.table_schema
             JOIN information_schema.constraint_column_usage AS ccu
               ON ccu.constraint_name = tc.constraint_name
              AND ccu.table_schema = tc.table_schema
             WHERE tc.constraint_type = \'FOREIGN KEY\'
               AND tc.table_schema = ?',
            [$schema],
        );

        $list = [];
        $byFrom = [];
        foreach ($rows as $row) {
            $from = (string) $row->from_table;
            $col = (string) $row->from_column;
            $list[] = [
                'from_table' => $from,
                'from_column' => $col,
                'to_table' => (string) $row->to_table,
                'to_column' => (string) $row->to_column,
            ];
            $byFrom[$from][$col] = true;
        }

        return ['list' => $list, 'by_from' => $byFrom];
    }

    private function schemaExists(string $schema): bool
    {
        $row = DB::selectOne(
            'SELECT 1 AS ok FROM information_schema.schemata WHERE schema_name = ?',
            [$schema],
        );

        return $row !== null;
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(string $schema): array
    {
        return [
            'schema' => $schema,
            'groups' => [],
            'tables' => [],
            'edges' => [],
            'layout' => ['width' => 800, 'height' => 400],
        ];
    }
}
