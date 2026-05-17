import { router } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckSquare,
    ChevronDown,
    KeyRound,
    Loader2,
    Minus,
    Search,
    Square,
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
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import roles from '@/routes/configuracion/roles';
import { toastManager } from '@/lib/toast';
import type {
    CatalogPermission,
    PermissionGroup,
    PermissionsCatalog,
    Role,
} from '../types';

export type RolePermissionsModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Rol al que se le están gestionando los permisos. */
    role: Role | null;
    /** Catálogo agrupado (mismo formato que el index). */
    catalog: PermissionsCatalog;
};

/**
 * Modal compacto para gestionar permisos de un rol en formato árbol.
 *
 * Diferencias vs el viejo picker dentro del modal de edición:
 *  - Tamaño `md` (más pequeño que el form modal).
 *  - Tree-view real: cada módulo es un nodo expandible con sus hojas.
 *  - Indentación visual con guía vertical para enfatizar la jerarquía.
 *  - Buscador en vivo + acciones rápidas globales (expandir/colapsar todo
 *    y seleccionar todo / limpiar todo).
 *  - Submit independiente del modal de edición de metadata: hace PUT
 *    contra `roles.update-permissions` (Spatie sync interno).
 *  - Roles del sistema (ej. `superadmin`): editables pero con banner
 *    de advertencia + confirm previo al guardar. La protección dura
 *    queda únicamente en el endpoint de metadata/eliminación.
 */
export function RolePermissionsModal({
    open,
    onOpenChange,
    role,
    catalog,
}: RolePermissionsModalProps) {
    const { t } = useTranslation(['roles', 'common']);
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const [query, setQuery] = useState('');
    const [expanded, setExpanded] = useState<Set<string>>(new Set());
    const [processing, setProcessing] = useState(false);
    const [initialSnapshot, setInitialSnapshot] = useState<Set<string>>(
        new Set(),
    );

    const isSystem = role?.is_system === true;

    // Cuando se abre el modal, hidratamos el set de seleccionados desde el
    // rol. También colapsamos todos los módulos por defecto para no
    // abrumar y abrimos sólo los que tienen permisos asignados.
    useEffect(() => {
        if (!open || !role) {
            return;
        }
        const initial = new Set(role.permissions.map((p) => p.name));
        setSelected(initial);
        setInitialSnapshot(initial);
        setQuery('');

        // Auto-expandir los módulos que ya tienen al menos un permiso.
        const modulesWithSelection = new Set<string>();
        for (const group of catalog) {
            if (group.permissions.some((p) => initial.has(p.name))) {
                modulesWithSelection.add(group.module);
            }
        }
        setExpanded(modulesWithSelection);
    }, [open, role, catalog]);

    /** Cuenta total de permisos del catálogo (para el badge global). */
    const totalPermissions = useMemo(
        () => catalog.reduce((acc, g) => acc + g.permissions.length, 0),
        [catalog],
    );

    /** Filtra el catálogo según el texto de búsqueda. */
    const filteredGroups = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (q === '') {
            return catalog;
        }

        const result: PermissionGroup[] = [];
        for (const group of catalog) {
            const moduleLabel = t(
                `roles:modules.${group.module}`,
                group.module,
            ).toLowerCase();
            const moduleMatches = moduleLabel.includes(q);

            const matchedPerms = group.permissions.filter((perm) => {
                if (moduleMatches) return true;
                const actionLabel = t(
                    `roles:permission_actions.${perm.action}`,
                    perm.action,
                ).toLowerCase();
                return (
                    perm.name.toLowerCase().includes(q) ||
                    actionLabel.includes(q)
                );
            });

            if (matchedPerms.length > 0) {
                result.push({
                    module: group.module,
                    permissions: matchedPerms,
                });
            }
        }

        return result;
    }, [catalog, query, t]);

    /*
     * Cuando hay búsqueda activa, expandimos automáticamente los grupos
     * con matches para que el usuario vea las hojas sin tener que abrir
     * uno por uno. Cuando se limpia, restauramos al estado del usuario.
     */
    const expandedSet = useMemo(() => {
        if (query.trim() !== '') {
            return new Set(filteredGroups.map((g) => g.module));
        }
        return expanded;
    }, [expanded, filteredGroups, query]);

    const toggleModule = (module: string) => {
        setExpanded((prev) => {
            const next = new Set(prev);
            if (next.has(module)) {
                next.delete(module);
            } else {
                next.add(module);
            }
            return next;
        });
    };

    const togglePermission = (perm: CatalogPermission) => {
        setSelected((prev) => {
            const next = new Set(prev);
            if (next.has(perm.name)) {
                next.delete(perm.name);
            } else {
                next.add(perm.name);
            }
            return next;
        });
    };

    const toggleGroup = (group: PermissionGroup, select: boolean) => {
        setSelected((prev) => {
            const next = new Set(prev);
            for (const perm of group.permissions) {
                if (select) {
                    next.add(perm.name);
                } else {
                    next.delete(perm.name);
                }
            }
            return next;
        });
    };

    const expandAll = () =>
        setExpanded(new Set(catalog.map((g) => g.module)));
    const collapseAll = () => setExpanded(new Set());

    const selectAll = () => {
        const next = new Set<string>();
        for (const group of catalog) {
            for (const perm of group.permissions) {
                next.add(perm.name);
            }
        }
        setSelected(next);
    };
    const clearAll = () => {
        setSelected(new Set());
    };

    const isDirty = useMemo(() => {
        if (selected.size !== initialSnapshot.size) return true;
        for (const name of selected) {
            if (!initialSnapshot.has(name)) return true;
        }
        return false;
    }, [selected, initialSnapshot]);

    const handleClose = (next: boolean) => {
        if (!next && isDirty) {
            if (!window.confirm(t('common:form.unsaved_changes'))) {
                return;
            }
        }
        onOpenChange(next);
    };

    const onSave = () => {
        if (!role) return;

        // Para roles del sistema pedimos un confirm explícito: editar
        // su set de permisos puede dejar al dueño de la plataforma sin
        // acceso a partes críticas si se desmarca, p.ej. `roles.update`.
        if (isSystem) {
            const ok = window.confirm(
                t('roles:permissions_modal.system_confirm', {
                    role: role.name,
                }),
            );
            if (!ok) return;
        }

        setProcessing(true);
        router.put(
            roles.updatePermissions(role.id).url,
            { permissions: Array.from(selected) },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => onOpenChange(false),
                onError: (errors) => {
                    // Inertia pone los errores de validación bajo la clave
                    // del campo. Para `updatePermissions` el backend usa
                    // `permissions`. Si existe, lo mostramos tal cual; si no,
                    // caemos al texto genérico.
                    const specific =
                        (errors as Record<string, string | undefined>)
                            ?.permissions ?? undefined;
                    toastManager.error({
                        title: specific ?? t('common:feedback.save_error'),
                    });
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent
                className={cn(
                    'flex max-h-[calc(100dvh-2rem)] flex-col gap-0 overflow-hidden p-0 sm:max-w-lg',
                    'shadow-2xl shadow-foreground/15 ring-1 ring-border/40',
                    'data-[state=open]:duration-400 data-[state=open]:ease-[cubic-bezier(0.16,1,0.3,1)]',
                    'data-[state=open]:slide-in-from-bottom-6 data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95',
                    'data-[state=closed]:duration-200 data-[state=closed]:ease-in',
                )}
                onPointerDownOutside={(e) => e.preventDefault()}
                onInteractOutside={(e) => e.preventDefault()}
            >
                <DialogHeader className="border-b border-border/60 px-5 pt-5 pb-3">
                    <div className="flex items-start gap-3">
                        <div
                            className={cn(
                                'flex size-9 shrink-0 items-center justify-center rounded-lg',
                                isSystem
                                    ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                    : 'bg-primary/10 text-primary',
                            )}
                        >
                            <KeyRound className="size-4" strokeWidth={2.5} />
                        </div>
                        <div className="min-w-0 flex-1">
                            <DialogTitle className="text-base font-semibold tracking-tight">
                                {t('roles:permissions_modal.title')}
                            </DialogTitle>
                            <DialogDescription className="text-xs text-muted-foreground">
                                {role && (
                                    <span className="font-mono text-foreground/80">
                                        {role.name}
                                    </span>
                                )}
                                {role && ' · '}
                                {t('roles:permissions_modal.description')}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                {/*
                 * Banner de advertencia para roles del sistema.
                 * Mantenemos el rol editable pero recordamos al usuario
                 * el riesgo (lockout) antes de modificar.
                 */}
                {isSystem && (
                    <div className="border-b border-amber-500/40 bg-amber-500/10 px-5 py-3 text-xs">
                        <div className="flex items-start gap-2 text-amber-700 dark:text-amber-300">
                            <AlertTriangle
                                className="mt-0.5 size-4 shrink-0"
                                strokeWidth={2.5}
                                aria-hidden
                            />
                            <div className="flex flex-col gap-1">
                                <p className="font-semibold">
                                    {t(
                                        'roles:permissions_modal.system_banner_title',
                                    )}
                                </p>
                                <p className="text-amber-700/90 dark:text-amber-300/90">
                                    {t(
                                        'roles:permissions_modal.system_banner_body',
                                    )}
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {/* Toolbar: buscador + acciones rápidas */}
                <div className="flex flex-col gap-2 border-b border-border/60 bg-muted/20 px-5 py-3">
                    <div className="relative">
                        <Search
                            aria-hidden
                            className="pointer-events-none absolute top-1/2 left-3 z-10 size-3.5 -translate-y-1/2 text-muted-foreground/90"
                            strokeWidth={2.25}
                        />
                        <Input
                            type="search"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder={t(
                                'roles:permissions_modal.search_placeholder',
                            )}
                            className="h-8 pl-9 text-xs"
                        />
                        {query.length > 0 && (
                            <button
                                type="button"
                                onClick={() => setQuery('')}
                                aria-label={t('common:actions.clear')}
                                className="absolute top-1/2 right-2 z-10 -translate-y-1/2 rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                            >
                                <X className="size-3" strokeWidth={2.5} />
                            </button>
                        )}
                    </div>

                    <div className="flex items-center justify-between gap-2">
                        <span className="text-[0.7rem] font-medium text-muted-foreground">
                            {t('roles:permissions_modal.selected_count', {
                                selected: selected.size,
                                total: totalPermissions,
                            })}
                        </span>
                        <div className="flex items-center gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={expanded.size === 0 ? expandAll : collapseAll}
                                disabled={filteredGroups.length === 0}
                                className="h-7 cursor-pointer px-2 text-[0.7rem]"
                            >
                                {expanded.size === 0
                                    ? t(
                                          'roles:permissions_modal.expand_all',
                                      )
                                    : t(
                                          'roles:permissions_modal.collapse_all',
                                      )}
                            </Button>
                            <span aria-hidden className="text-muted-foreground/40">
                                |
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={
                                    selected.size === totalPermissions
                                        ? clearAll
                                        : selectAll
                                }
                                className="h-7 cursor-pointer px-2 text-[0.7rem]"
                            >
                                {selected.size === totalPermissions
                                    ? t('roles:permissions_modal.clear_all')
                                    : t('roles:permissions_modal.select_all')}
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Tree body — editable también para roles del sistema. */}
                <div className="scrollbar-hidden flex-1 overflow-y-auto px-3 py-2">
                    {filteredGroups.length === 0 ? (
                        <div className="rounded-lg border border-dashed border-border/60 bg-muted/20 px-4 py-8 text-center text-xs text-muted-foreground">
                            {t('roles:form.permissions.no_match')}
                        </div>
                    ) : (
                        <ul className="flex flex-col">
                            {filteredGroups.map((group) => (
                                <TreeModuleNode
                                    key={group.module}
                                    group={group}
                                    expanded={expandedSet.has(group.module)}
                                    onToggleModule={() =>
                                        toggleModule(group.module)
                                    }
                                    selected={selected}
                                    onTogglePermission={togglePermission}
                                    onToggleGroup={(select) =>
                                        toggleGroup(group, select)
                                    }
                                    disabled={false}
                                />
                            ))}
                        </ul>
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
                        className={cn(
                            'cursor-pointer gap-2 disabled:cursor-not-allowed',
                            // Cuando es sistema enfatizamos visualmente el
                            // riesgo del guardar (color amber). El handler
                            // exige confirm explícito antes de enviar.
                            isSystem &&
                                'bg-amber-600 text-white hover:bg-amber-700 focus-visible:ring-amber-500/40',
                        )}
                    >
                        {processing && (
                            <Loader2
                                className="size-4 animate-spin"
                                aria-hidden="true"
                            />
                        )}
                        {processing
                            ? t('roles:permissions_modal.saving')
                            : t('roles:permissions_modal.save')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/* -------------------------------------------------------------------------- *
 *  Subcomponentes internos del tree                                          *
 * -------------------------------------------------------------------------- */

type TreeModuleNodeProps = {
    group: PermissionGroup;
    expanded: boolean;
    onToggleModule: () => void;
    selected: Set<string>;
    onTogglePermission: (perm: CatalogPermission) => void;
    onToggleGroup: (select: boolean) => void;
    disabled: boolean;
};

function TreeModuleNode({
    group,
    expanded,
    onToggleModule,
    selected,
    onTogglePermission,
    onToggleGroup,
    disabled,
}: TreeModuleNodeProps) {
    const { t } = useTranslation('roles');

    const total = group.permissions.length;
    const selectedInGroup = group.permissions.filter((p) =>
        selected.has(p.name),
    ).length;
    const state: 'all' | 'some' | 'none' =
        selectedInGroup === total
            ? 'all'
            : selectedInGroup > 0
              ? 'some'
              : 'none';

    return (
        <li className="flex flex-col">
            <div
                className={cn(
                    'group/node flex items-center gap-1.5 rounded-md px-1.5 py-1 transition-colors',
                    'hover:bg-muted/60',
                )}
            >
                <button
                    type="button"
                    onClick={onToggleModule}
                    aria-label={
                        expanded
                            ? t('permissions_modal.collapse_module')
                            : t('permissions_modal.expand_module')
                    }
                    className="flex size-5 shrink-0 cursor-pointer items-center justify-center rounded text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                >
                    <ChevronDown
                        className={cn(
                            'size-3.5 transition-transform duration-200',
                            !expanded && '-rotate-90',
                        )}
                        strokeWidth={2.5}
                    />
                </button>

                <TreeCheckbox
                    state={state}
                    onClick={() => onToggleGroup(state !== 'all')}
                    disabled={disabled}
                    ariaLabel={t('permissions_modal.toggle_module', {
                        module: t(`modules.${group.module}`, group.module),
                    })}
                />

                <span className="flex-1 truncate text-xs font-medium text-foreground">
                    {t(`modules.${group.module}`, group.module)}
                </span>
                <span className="shrink-0 font-mono text-[0.65rem] tabular-nums text-muted-foreground">
                    {selectedInGroup}/{total}
                </span>
            </div>

            {expanded && (
                <ul className="relative ml-3 flex flex-col border-l border-border/50 pl-3">
                    {group.permissions.map((perm) => (
                        <li key={perm.id}>
                            <button
                                type="button"
                                onClick={() => onTogglePermission(perm)}
                                disabled={disabled}
                                className={cn(
                                    'relative flex w-full items-center gap-2 rounded-md px-1.5 py-1 text-left transition-colors',
                                    'hover:bg-muted/60',
                                    'disabled:cursor-not-allowed disabled:opacity-50',
                                    selected.has(perm.name) && 'bg-primary/5',
                                )}
                            >
                                {/* Línea horizontal del tree */}
                                <span
                                    aria-hidden
                                    className="absolute top-1/2 -left-3 h-px w-2.5 bg-border/50"
                                />
                                <TreeCheckbox
                                    state={
                                        selected.has(perm.name) ? 'all' : 'none'
                                    }
                                    onClick={() => onTogglePermission(perm)}
                                    disabled={disabled}
                                    ariaLabel={perm.name}
                                />
                                <div className="flex min-w-0 flex-1 flex-col leading-tight">
                                    <span className="truncate text-xs text-foreground">
                                        {t(
                                            `permission_actions.${perm.action}`,
                                            perm.action,
                                        )}
                                    </span>
                                    <span className="truncate font-mono text-[0.65rem] text-muted-foreground">
                                        {perm.name}
                                    </span>
                                </div>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </li>
    );
}

type TreeCheckboxProps = {
    state: 'all' | 'some' | 'none';
    onClick: () => void;
    disabled?: boolean;
    ariaLabel: string;
};

/**
 * Checkbox visual ligero (no usa el de Radix) para evitar el coste y
 * tener total control del look. Soporta los 3 estados (none/some/all).
 *
 * - Click → propaga al handler externo (sin stopPropagation, deja al
 *   wrapper también recibirlo si quisiera).
 * - Atajos de teclado: el botón ya es focuseable y dispara con Enter/Space.
 */
function TreeCheckbox({
    state,
    onClick,
    disabled,
    ariaLabel,
}: TreeCheckboxProps) {
    const Icon = state === 'all' ? CheckSquare : state === 'some' ? Minus : Square;

    return (
        <button
            type="button"
            role="checkbox"
            aria-checked={state === 'all' ? true : state === 'some' ? 'mixed' : false}
            aria-label={ariaLabel}
            disabled={disabled}
            onClick={(e) => {
                e.stopPropagation();
                onClick();
            }}
            className={cn(
                'flex size-4 shrink-0 cursor-pointer items-center justify-center rounded text-muted-foreground transition-colors',
                'hover:text-foreground',
                state !== 'none' && 'text-primary',
                disabled && 'cursor-not-allowed opacity-50',
            )}
        >
            <Icon className="size-3.5" strokeWidth={2.5} aria-hidden />
        </button>
    );
}
