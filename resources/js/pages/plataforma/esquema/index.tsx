import { Head, resetLayoutProps, router, setLayoutProps } from '@inertiajs/react';
import { Database, KeyRound, Link2, Minus, Plus } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type Column = {
    name: string;
    type: string;
    nullable: boolean;
    pk: boolean;
    fk: boolean;
};

type TableNode = {
    name: string;
    group: string;
    columns: Column[];
    column_total: number;
    hidden_columns: number;
    x: number;
    y: number;
    w: number;
    h: number;
};

type Edge = {
    from: string;
    to: string;
    from_column: string;
    to_column: string;
    x1: number;
    y1: number;
    x2: number;
    y2: number;
};

type Group = { id: string; label: string; count: number };

type SchemaOpt = { schema: string; label: string; kind: string };

type Diagram = {
    schema: string;
    groups: Group[];
    tables: TableNode[];
    edges: Edge[];
    layout: { width: number; height: number };
};

type Props = {
    schemas: SchemaOpt[];
    diagram: Diagram;
};

function curve(e: Edge): string {
    const dx = Math.max(48, Math.abs(e.x2 - e.x1) * 0.38);

    return `M ${e.x1} ${e.y1} C ${e.x1 + dx} ${e.y1}, ${e.x2 - dx} ${e.y2}, ${e.x2} ${e.y2}`;
}

export default function PlataformaEsquemaIndex({ schemas, diagram }: Props) {
    const { t } = useTranslation('plataforma-operaciones');
    const [search, setSearch] = useState('');
    const [group, setGroup] = useState<string>('all');
    const [active, setActive] = useState<string | null>(null);
    const [zoom, setZoom] = useState(1);

    useEffect(() => {
        setGroup('all');
        setSearch('');
        setActive(null);
    }, [diagram.schema]);

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: t('schema.title'), href: '/plataforma/esquema' },
            ],
        });
        return () => resetLayoutProps();
    }, [t]);

    const q = search.trim().toLowerCase();

    const visibleNames = useMemo(() => {
        const names = new Set<string>();
        for (const table of diagram.tables) {
            if (group !== 'all' && table.group !== group) {
                continue;
            }
            if (q !== '') {
                const hit =
                    table.name.includes(q) ||
                    table.columns.some((c) => c.name.toLowerCase().includes(q));
                if (!hit) {
                    continue;
                }
            }
            names.add(table.name);
        }
        return names;
    }, [diagram.tables, group, q]);

    const visibleTables = diagram.tables.filter((tb) => visibleNames.has(tb.name));
    const visibleEdges = diagram.edges.filter(
        (e) => visibleNames.has(e.from) && visibleNames.has(e.to),
    );
    const related = useMemo(() => {
        if (active === null) {
            return null;
        }
        const names = new Set<string>([active]);
        for (const e of diagram.edges) {
            if (e.from === active) {
                names.add(e.to);
            }
            if (e.to === active) {
                names.add(e.from);
            }
        }
        return names;
    }, [active, diagram.edges]);

    const schemaOptions = useMemo(
        () => schemas.map((s) => ({ value: s.schema, label: s.label })),
        [schemas],
    );

    const changeSchema = (schema: string | null) => {
        if (schema === null || schema === diagram.schema) {
            return;
        }

        router.get(
            '/plataforma/esquema',
            { schema },
            { preserveState: false, replace: true },
        );
    };

    return (
        <>
            <Head title={t('schema.title')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={t('schema.title')}
                    description={
                        <span className="inline-flex items-center gap-2">
                            <Database className="size-4 shrink-0 text-emerald-600" />
                            {t('schema.description')}
                        </span>
                    }
                />

                <div className="flex flex-col gap-3">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div className="w-full sm:max-w-md">
                            <label
                                htmlFor="plataforma-esquema-schema"
                                className="mb-1.5 block text-xs font-medium text-muted-foreground"
                            >
                                {t('schema.schema')}
                            </label>
                            <Combobox
                                id="plataforma-esquema-schema"
                                options={schemaOptions}
                                value={diagram.schema}
                                onChange={changeSchema}
                                placeholder={t('schema.schema')}
                                searchPlaceholder={t('schema.search_schema')}
                                emptyMessage={t('schema.empty_schema')}
                                clearable={false}
                            />
                        </div>
                        <div className="flex shrink-0 items-center gap-3">
                            <p className="text-xs text-muted-foreground">
                                {visibleTables.length} {t('schema.tables')} · {visibleEdges.length}{' '}
                                {t('schema.relations')}
                            </p>
                            <div className="flex items-center gap-1">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    className="size-8"
                                    onClick={() =>
                                        setZoom((z) => Math.max(0.4, Number((z - 0.1).toFixed(1))))
                                    }
                                    aria-label={t('schema.zoom_out')}
                                >
                                    <Minus className="size-3.5" />
                                </Button>
                                <span className="w-10 text-center text-xs tabular-nums text-muted-foreground">
                                    {Math.round(zoom * 100)}%
                                </span>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    className="size-8"
                                    onClick={() =>
                                        setZoom((z) => Math.min(1.6, Number((z + 0.1).toFixed(1))))
                                    }
                                    aria-label={t('schema.zoom_in')}
                                >
                                    <Plus className="size-3.5" />
                                </Button>
                            </div>
                        </div>
                    </div>

                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={t('schema.search')}
                        className="h-10 w-full sm:max-w-sm"
                    />

                    <div className="flex flex-wrap gap-1.5">
                        <button
                            type="button"
                            onClick={() => setGroup('all')}
                            className={cn(
                                'rounded-full border px-2.5 py-1 text-xs',
                                group === 'all'
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'border-border text-muted-foreground',
                            )}
                        >
                            {t('schema.all_groups')}
                        </button>
                        {diagram.groups.map((g) => (
                            <button
                                key={g.id}
                                type="button"
                                onClick={() => setGroup(g.id)}
                                className={cn(
                                    'rounded-full border px-2.5 py-1 text-xs',
                                    group === g.id
                                        ? 'border-primary bg-primary/10 text-primary'
                                        : 'border-border text-muted-foreground',
                                )}
                            >
                                {g.label} ({g.count})
                            </button>
                        ))}
                    </div>

                    <div className="flex flex-wrap gap-4 text-xs text-muted-foreground">
                        <span className="inline-flex items-center gap-1">
                            <KeyRound className="size-3.5 text-amber-600" /> {t('schema.legend_pk')}
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <Link2 className="size-3.5 text-sky-600" /> {t('schema.legend_fk')}
                        </span>
                    </div>
                </div>

                {visibleTables.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t('schema.empty')}</p>
                ) : (
                    <div className="relative h-[min(78vh,920px)] overflow-auto rounded-xl border border-border bg-muted/30">
                        <div
                            className="relative"
                            style={{
                                width: diagram.layout.width * zoom,
                                height: diagram.layout.height * zoom,
                            }}
                        >
                            <div
                                className="absolute left-0 top-0 origin-top-left"
                                style={{
                                    width: diagram.layout.width,
                                    height: diagram.layout.height,
                                    transform: `scale(${zoom})`,
                                }}
                            >
                            <svg
                                className="pointer-events-none absolute inset-0"
                                width={diagram.layout.width}
                                height={diagram.layout.height}
                            >
                                {visibleEdges.map((e, i) => {
                                    const hot =
                                        active !== null &&
                                        (e.from === active || e.to === active);
                                    return (
                                        <path
                                            key={`${e.from}-${e.from_column}-${e.to}-${i}`}
                                            d={curve(e)}
                                            fill="none"
                                            stroke={hot ? '#0369a1' : '#94a3b8'}
                                            strokeWidth={hot ? 2.2 : 1.15}
                                            opacity={active && !hot ? 0.18 : 0.85}
                                        />
                                    );
                                })}
                            </svg>

                            {visibleTables.map((table) => (
                                <article
                                    key={table.name}
                                    className={cn(
                                        'absolute overflow-hidden rounded-lg border bg-card shadow-sm',
                                        active === table.name
                                            ? 'z-10 border-sky-500 ring-2 ring-sky-400/40'
                                            : related && related.has(table.name)
                                              ? 'z-1 border-sky-300'
                                              : related
                                                ? 'border-border opacity-40'
                                                : 'border-border',
                                    )}
                                    style={{
                                        left: table.x,
                                        top: table.y,
                                        width: table.w,
                                        height: table.h,
                                    }}
                                    onMouseEnter={() => setActive(table.name)}
                                    onMouseLeave={() => setActive(null)}
                                >
                                    <header className="truncate bg-emerald-800 px-2 py-1.5 font-mono text-[11px] font-semibold text-white">
                                        {table.name}
                                    </header>
                                    <ul className="px-1.5 py-1">
                                        {table.columns.map((col) => (
                                            <li
                                                key={col.name}
                                                className="flex items-center gap-1 font-mono text-[10px] leading-4.5"
                                            >
                                                {col.pk ? (
                                                    <KeyRound className="size-3 shrink-0 text-amber-600" />
                                                ) : col.fk ? (
                                                    <Link2 className="size-3 shrink-0 text-sky-600" />
                                                ) : (
                                                    <span className="size-3 shrink-0" />
                                                )}
                                                <span
                                                    className={cn(
                                                        'min-w-0 truncate',
                                                        col.pk && 'font-semibold',
                                                    )}
                                                >
                                                    {col.name}
                                                </span>
                                                <span className="ml-auto shrink-0 text-muted-foreground">
                                                    {col.type}
                                                </span>
                                            </li>
                                        ))}
                                        {table.hidden_columns > 0 ? (
                                            <li className="px-3 text-[10px] text-muted-foreground">
                                                {t('schema.more_columns', {
                                                    count: table.hidden_columns,
                                                })}
                                            </li>
                                        ) : null}
                                    </ul>
                                </article>
                            ))}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}
