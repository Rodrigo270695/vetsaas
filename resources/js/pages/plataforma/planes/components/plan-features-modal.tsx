import { router } from '@inertiajs/react';
import {
    ChevronDown,
    Infinity as InfinityIcon,
    Loader2,
    RotateCcw,
    Search,
    Sparkles,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { toastManager } from '@/lib/toast';
import planes from '@/routes/plataforma/planes';
import type {
    Plan,
    PlanFeatureCatalogEntry,
    PlanFeatureRow,
    PlanFeatureType,
} from '../types';

export type PlanFeaturesModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Plan al que se le están gestionando las features. */
    plan: Plan | null;
    /** Filas actuales de `plan_features` para ese plan. */
    initialFeatures: readonly PlanFeatureRow[];
    /** Catálogo de features conocidos (key + tipo + grupo). */
    catalog: readonly PlanFeatureCatalogEntry[];
};

/**
 * Valor "en memoria" mientras se edita el modal. Solo uno de los 3
 * campos se usa según el `type` de la feature; los otros 2 permanecen
 * en null hasta que se envía el form.
 */
type FeatureValue = {
    valor_int: number | null;
    valor_bool: boolean | null;
    valor_str: string | null;
};

/**
 * Modal de gestión de features de un plan.
 *
 * Hermano de `RolePermissionsModal` pero simplificado:
 *   - No hay árbol jerárquico (las features son flat dentro de su grupo).
 *   - Cada feature renderiza un input según su `type` declarado:
 *       - `bool`: checkbox.
 *       - `int`: input number + botón "ilimitado" (= null = no aplicar).
 *       - `str`: input text.
 *   - Búsqueda en vivo + filtro por grupo.
 *   - Submit hace PUT a `planes.update-features`.
 */
export function PlanFeaturesModal({
    open,
    onOpenChange,
    plan,
    initialFeatures,
    catalog,
}: PlanFeaturesModalProps) {
    const { t } = useTranslation(['planes', 'common']);

    /** Snapshot del estado al abrir, para detectar cambios. */
    const [initialSnapshot, setInitialSnapshot] = useState<
        Record<string, FeatureValue>
    >({});
    const [values, setValues] = useState<Record<string, FeatureValue>>({});
    const [query, setQuery] = useState('');
    const [expandedGroups, setExpandedGroups] = useState<Set<string>>(
        new Set(),
    );
    const [processing, setProcessing] = useState(false);

    /**
     * Agrupa el catálogo por `group` para renderizar secciones colapsables.
     */
    const groupedCatalog = useMemo(() => {
        const map = new Map<string, PlanFeatureCatalogEntry[]>();
        for (const entry of catalog) {
            const list = map.get(entry.group) ?? [];
            list.push(entry);
            map.set(entry.group, list);
        }
        return Array.from(map.entries()).map(([group, items]) => ({
            group,
            items,
        }));
    }, [catalog]);

    /**
     * Inicialización al abrir: poblamos `values` con los valores actuales
     * del plan; las features no presentes en `plan_features` quedan con
     * el `default` declarado en el catálogo (o `null` si es `bool`/`str`).
     */
    useEffect(() => {
        if (!open) return;

        const initial: Record<string, FeatureValue> = {};
        const currentMap = new Map<string, PlanFeatureRow>();
        for (const row of initialFeatures) {
            currentMap.set(row.feature, row);
        }

        for (const entry of catalog) {
            const current = currentMap.get(entry.feature);
            if (current) {
                initial[entry.feature] = {
                    valor_int: current.valor_int,
                    valor_bool: current.valor_bool,
                    valor_str: current.valor_str,
                };
            } else {
                // Si no está en plan_features, dejamos un placeholder
                // explícito (todo null) para que el usuario deba marcar.
                // El `default` del catálogo se sugiere en el input.
                initial[entry.feature] = {
                    valor_int: null,
                    valor_bool: null,
                    valor_str: null,
                };
            }
        }

        setInitialSnapshot(initial);
        setValues(initial);
        setQuery('');
        // Por defecto expandimos todos los grupos para que el superadmin
        // vea el catálogo completo de un vistazo.
        setExpandedGroups(new Set(groupedCatalog.map((g) => g.group)));
    }, [open, plan?.id, catalog, initialFeatures, groupedCatalog]);

    /**
     * Filtro por texto: filtra features cuyo nombre contenga el query.
     * Si un grupo queda vacío después de filtrar, se oculta.
     */
    const filteredGroups = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return groupedCatalog;
        return groupedCatalog
            .map((g) => ({
                ...g,
                items: g.items.filter((item) =>
                    item.feature.toLowerCase().includes(q),
                ),
            }))
            .filter((g) => g.items.length > 0);
    }, [groupedCatalog, query]);

    const toggleGroup = (group: string) => {
        setExpandedGroups((prev) => {
            const next = new Set(prev);
            if (next.has(group)) next.delete(group);
            else next.add(group);
            return next;
        });
    };

    const setFeatureValue = (
        feature: string,
        type: PlanFeatureType,
        partial: Partial<FeatureValue>,
    ) => {
        setValues((prev) => {
            // Cualquier cambio limpia los otros dos campos (defensa: solo
            // un campo se persiste por feature según su tipo).
            const next: FeatureValue = {
                valor_int: null,
                valor_bool: null,
                valor_str: null,
                ...partial,
            };
            void type;
            return { ...prev, [feature]: next };
        });
    };

    const clearFeature = (feature: string) => {
        setValues((prev) => ({
            ...prev,
            [feature]: {
                valor_int: null,
                valor_bool: null,
                valor_str: null,
            },
        }));
    };

    const applyDefault = (entry: PlanFeatureCatalogEntry) => {
        setValues((prev) => {
            const next: FeatureValue = {
                valor_int: null,
                valor_bool: null,
                valor_str: null,
            };
            if (entry.type === 'int' && typeof entry.default === 'number') {
                next.valor_int = entry.default;
            } else if (
                entry.type === 'bool' &&
                typeof entry.default === 'boolean'
            ) {
                next.valor_bool = entry.default;
            } else if (
                entry.type === 'str' &&
                typeof entry.default === 'string'
            ) {
                next.valor_str = entry.default;
            }
            return { ...prev, [entry.feature]: next };
        });
    };

    const isDirty = useMemo(() => {
        return JSON.stringify(initialSnapshot) !== JSON.stringify(values);
    }, [initialSnapshot, values]);

    const activeCount = useMemo(() => {
        return Object.values(values).filter(
            (v) =>
                v.valor_int !== null ||
                v.valor_bool !== null ||
                v.valor_str !== null,
        ).length;
    }, [values]);

    const confirmDiscard = (): boolean => {
        if (!isDirty) return true;
        return window.confirm(t('common:form.unsaved_changes'));
    };

    const handleClose = (next: boolean) => {
        if (!next && !confirmDiscard()) {
            return;
        }
        onOpenChange(next);
    };

    const onSave = () => {
        if (!plan) return;

        // Construimos el payload solo con features que tienen al menos
        // un valor no-null (el backend descarta el resto también, pero
        // esto reduce el payload y hace el diff más legible en logs).
        const features = Object.entries(values)
            .filter(
                ([, v]) =>
                    v.valor_int !== null ||
                    v.valor_bool !== null ||
                    v.valor_str !== null,
            )
            .map(([feature, v]) => ({
                feature,
                valor_int: v.valor_int,
                valor_bool: v.valor_bool,
                valor_str: v.valor_str,
            }));

        setProcessing(true);
        router.put(
            planes.updateFeatures(plan.id).url,
            { features },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => onOpenChange(false),
                onError: () => {
                    toastManager.error({
                        title: t('common:feedback.save_error'),
                    });
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="flex max-h-[85vh] flex-col gap-0 p-0 sm:max-w-2xl">
                <DialogHeader className="border-b border-border/60 px-5 pt-5 pb-3">
                    <div className="flex items-start gap-3">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Sparkles
                                className="size-4"
                                strokeWidth={2.5}
                                aria-hidden
                            />
                        </div>
                        <div className="min-w-0 flex-1">
                            <DialogTitle className="text-base font-semibold tracking-tight">
                                {t('planes:features_modal.title')}
                            </DialogTitle>
                            <DialogDescription className="text-xs text-muted-foreground">
                                {plan && (
                                    <span className="font-mono text-foreground/80">
                                        {plan.codigo}
                                    </span>
                                )}
                                {plan && ' · '}
                                {t('planes:features_modal.description')}
                                {' · '}
                                <span className="font-semibold text-primary">
                                    {t('planes:features_modal.active_count', {
                                        count: activeCount,
                                    })}
                                </span>
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div className="flex items-center gap-2 border-b border-border/60 bg-muted/30 px-5 py-3">
                    <div className="relative flex-1">
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            strokeWidth={2.25}
                            aria-hidden
                        />
                        <Input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder={t('planes:features_modal.search')}
                            className="pl-9"
                        />
                        {query && (
                            <button
                                type="button"
                                onClick={() => setQuery('')}
                                className="absolute top-1/2 right-3 -translate-y-1/2 cursor-pointer text-muted-foreground hover:text-foreground"
                                aria-label={t('common:actions.clear')}
                            >
                                <X className="size-4" strokeWidth={2.5} />
                            </button>
                        )}
                    </div>
                </div>

                <div className="min-h-0 flex-1 overflow-y-auto px-5 py-3">
                    {filteredGroups.length === 0 ? (
                        <div className="flex flex-col items-center justify-center gap-2 py-8 text-center text-sm text-muted-foreground">
                            <Search
                                className="size-6 opacity-50"
                                strokeWidth={2}
                                aria-hidden
                            />
                            {t('planes:features_modal.empty_search')}
                        </div>
                    ) : (
                        <div className="flex flex-col gap-3">
                            {filteredGroups.map(({ group, items }) => {
                                const isExpanded = expandedGroups.has(group);
                                const groupActive = items.filter((item) => {
                                    const v = values[item.feature];
                                    return (
                                        v &&
                                        (v.valor_int !== null ||
                                            v.valor_bool !== null ||
                                            v.valor_str !== null)
                                    );
                                }).length;

                                return (
                                    <section
                                        key={group}
                                        className="overflow-hidden rounded-lg border border-border/60"
                                    >
                                        <button
                                            type="button"
                                            onClick={() => toggleGroup(group)}
                                            className="flex w-full cursor-pointer items-center justify-between gap-2 bg-muted/30 px-3 py-2 text-left text-sm font-semibold transition hover:bg-muted/50"
                                        >
                                            <span className="flex items-center gap-2">
                                                <ChevronDown
                                                    className={cn(
                                                        'size-4 transition-transform',
                                                        !isExpanded &&
                                                            '-rotate-90',
                                                    )}
                                                    strokeWidth={2.5}
                                                    aria-hidden
                                                />
                                                {t(
                                                    `planes:features_modal.groups.${group}`,
                                                    { defaultValue: group },
                                                )}
                                            </span>
                                            <span className="text-xs font-medium text-muted-foreground">
                                                {groupActive}/{items.length}
                                            </span>
                                        </button>

                                        {isExpanded && (
                                            <div className="flex flex-col divide-y divide-border/40">
                                                {items.map((entry) => (
                                                    <FeatureRow
                                                        key={entry.feature}
                                                        entry={entry}
                                                        value={
                                                            values[
                                                                entry.feature
                                                            ] ?? {
                                                                valor_int:
                                                                    null,
                                                                valor_bool:
                                                                    null,
                                                                valor_str:
                                                                    null,
                                                            }
                                                        }
                                                        onChange={(partial) =>
                                                            setFeatureValue(
                                                                entry.feature,
                                                                entry.type,
                                                                partial,
                                                            )
                                                        }
                                                        onClear={() =>
                                                            clearFeature(
                                                                entry.feature,
                                                            )
                                                        }
                                                        onApplyDefault={() =>
                                                            applyDefault(entry)
                                                        }
                                                    />
                                                ))}
                                            </div>
                                        )}
                                    </section>
                                );
                            })}
                        </div>
                    )}
                </div>

                <DialogFooter className="border-t border-border/60 px-5 py-3">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => handleClose(false)}
                        disabled={processing}
                        className="cursor-pointer"
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="button"
                        onClick={onSave}
                        disabled={processing || !isDirty}
                        className="cursor-pointer gap-2 disabled:cursor-not-allowed"
                    >
                        {processing && (
                            <Loader2
                                className="size-4 animate-spin"
                                aria-hidden="true"
                            />
                        )}
                        {processing
                            ? t('planes:features_modal.saving')
                            : t('planes:features_modal.save')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Renderiza un input específico según el tipo de la feature.
 * Componente interno, no se exporta.
 */
function FeatureRow({
    entry,
    value,
    onChange,
    onClear,
    onApplyDefault,
}: {
    entry: PlanFeatureCatalogEntry;
    value: FeatureValue;
    onChange: (partial: Partial<FeatureValue>) => void;
    onClear: () => void;
    onApplyDefault: () => void;
}) {
    const { t } = useTranslation(['planes']);
    const isActive =
        value.valor_int !== null ||
        value.valor_bool !== null ||
        value.valor_str !== null;

    return (
        <div className="flex items-center justify-between gap-3 px-3 py-2.5">
            <div className="flex min-w-0 flex-1 flex-col leading-tight">
                <span className="truncate font-mono text-xs text-foreground/90">
                    {entry.feature}
                </span>
                <span className="truncate text-[11px] text-muted-foreground">
                    {t(`planes:features_modal.descriptions.${entry.feature}`, {
                        defaultValue:
                            t(`planes:features_modal.types.${entry.type}`) +
                            ` · default: ${String(entry.default)}`,
                    })}
                </span>
            </div>

            <div className="flex shrink-0 items-center gap-1.5">
                {entry.type === 'bool' && (
                    <label
                        htmlFor={`feature-${entry.feature}`}
                        className="flex h-9 cursor-pointer items-center gap-2 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <Checkbox
                            id={`feature-${entry.feature}`}
                            checked={value.valor_bool === true}
                            onCheckedChange={(checked) =>
                                onChange({ valor_bool: checked === true })
                            }
                        />
                        <span className="text-xs text-foreground/80">
                            {value.valor_bool === true
                                ? t('planes:features_modal.enabled')
                                : t('planes:features_modal.disabled')}
                        </span>
                    </label>
                )}

                {entry.type === 'int' && (
                    <div className="flex items-center gap-1">
                        <Input
                            type="number"
                            min="0"
                            value={value.valor_int ?? ''}
                            onChange={(e) => {
                                const raw = e.target.value;
                                onChange({
                                    valor_int:
                                        raw === '' ? null : Number(raw) || 0,
                                });
                            }}
                            placeholder="—"
                            aria-label={entry.feature}
                            className="h-9 w-24 font-mono"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => onChange({ valor_int: -1 })}
                            className="h-9 w-9 cursor-pointer text-amber-600 hover:text-amber-700 dark:text-amber-400"
                            aria-label={t('planes:features_modal.unlimited')}
                            title={t('planes:features_modal.unlimited')}
                        >
                            <InfinityIcon
                                className="size-4"
                                strokeWidth={2.5}
                            />
                        </Button>
                    </div>
                )}

                {entry.type === 'str' && (
                    <Input
                        type="text"
                        value={value.valor_str ?? ''}
                        onChange={(e) =>
                            onChange({
                                valor_str:
                                    e.target.value === '' ? null : e.target.value,
                            })
                        }
                        placeholder="—"
                        aria-label={entry.feature}
                        className="h-9 w-40"
                    />
                )}

                {isActive ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={onClear}
                        className="h-9 w-9 cursor-pointer text-muted-foreground hover:text-destructive"
                        aria-label={t('planes:features_modal.clear')}
                        title={t('planes:features_modal.clear')}
                    >
                        <X className="size-4" strokeWidth={2.5} />
                    </Button>
                ) : (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={onApplyDefault}
                        className="h-9 w-9 cursor-pointer text-muted-foreground hover:text-primary"
                        aria-label={t('planes:features_modal.apply_default')}
                        title={t('planes:features_modal.apply_default')}
                    >
                        <RotateCcw className="size-4" strokeWidth={2.5} />
                    </Button>
                )}
            </div>
        </div>
    );
}
