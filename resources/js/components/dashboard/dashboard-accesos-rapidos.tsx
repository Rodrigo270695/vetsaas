import { Link, usePage } from '@inertiajs/react';
import { Settings2 } from 'lucide-react';
import { useEffect, useMemo, useState, type ComponentType } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavConfig } from '@/components/app-sidebar';
import { FormModal } from '@/components/forms/form-modal';
import { useVisibleNavigation } from '@/components/nav-main-collapsible';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const DEFAULT_HREFS = [
    '/clinica/pacientes',
    '/clinica/citas',
    '/clinica/sala-espera',
    '/clinica/historias-clinicas',
    '/caja/ventas',
    '/inventario/productos',
    '/servicios/grooming',
    '/clinica/hospitalizacion',
    '/clinica/vacunaciones',
    '/inventario/alertas',
];

const CENTRAL_DEFAULT_HREFS = [
    '/plataforma/operaciones',
    '/plataforma/tenants',
    '/plataforma/planes',
    '/plataforma/suscripciones',
    '/plataforma/cobros',
    '/plataforma/reportes',
    '/plataforma/chat-soporte',
    '/plataforma/salesbot-conversations',
];

const TONES = [
    'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300',
    'bg-orange-50 text-orange-600 dark:bg-orange-950/40 dark:text-orange-300',
    'bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-300',
    'bg-teal-50 text-teal-600 dark:bg-teal-950/40 dark:text-teal-300',
    'bg-violet-50 text-violet-600 dark:bg-violet-950/40 dark:text-violet-300',
    'bg-sky-50 text-sky-600 dark:bg-sky-950/40 dark:text-sky-300',
    'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
    'bg-fuchsia-50 text-fuchsia-600 dark:bg-fuchsia-950/40 dark:text-fuchsia-300',
    'bg-lime-50 text-lime-700 dark:bg-lime-950/40 dark:text-lime-300',
    'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-300',
];

type Acceso = {
    href: string;
    title: string;
    group: string;
    icon?: ComponentType<{ className?: string }>;
};

function storageKey(userId: string): string {
    return `vetsaas.accesos-rapidos.${userId}`;
}

function readSaved(userId: string): string[] | null {
    try {
        const raw = window.localStorage.getItem(storageKey(userId));
        if (!raw) {
            return null;
        }
        const parsed = JSON.parse(raw) as unknown;
        if (!Array.isArray(parsed)) {
            return null;
        }

        return parsed.filter((item): item is string => typeof item === 'string');
    } catch {
        return null;
    }
}

export function DashboardAccesosRapidos() {
    const { t } = useTranslation('dashboard');
    const userId = String(usePage().props.auth?.user?.id ?? 'local');
    const { singles, groups } = useNavConfig();
    const { visibleSingles, visibleGroups } = useVisibleNavigation(singles, groups);
    const [open, setOpen] = useState(false);
    const [saved, setSaved] = useState<string[] | null>(() => readSaved(userId));
    const [draft, setDraft] = useState<string[]>([]);

    const catalog = useMemo((): Acceso[] => {
        const items: Acceso[] = [];
        for (const item of visibleSingles) {
            const href = typeof item.href === 'string' ? item.href : '';
            if (href === '' || href === '/') {
                continue;
            }
            items.push({ href, title: item.title, group: t('accesos.grupo_inicio'), icon: item.icon ?? undefined });
        }
        for (const group of visibleGroups) {
            for (const item of group.items) {
                const href = typeof item.href === 'string' ? item.href : '';
                if (href === '') {
                    continue;
                }
                items.push({ href, title: item.title, group: group.title, icon: item.icon ?? undefined });
            }
        }

        const seen = new Set<string>();

        return items.filter((item) => {
            if (seen.has(item.href)) {
                return false;
            }
            seen.add(item.href);

            return true;
        });
    }, [t, visibleGroups, visibleSingles]);

    useEffect(() => {
        setSaved(readSaved(userId));
    }, [userId]);

    const preferredDefaults = catalog.some((item) => item.href.startsWith('/plataforma/'))
        ? CENTRAL_DEFAULT_HREFS
        : DEFAULT_HREFS;
    const defaults = preferredDefaults.filter((href) => catalog.some((item) => item.href === href));
    const selectedHrefs = saved ?? (defaults.length > 0 ? defaults : catalog.slice(0, 8).map((item) => item.href));
    const shown = catalog.filter((item) => selectedHrefs.includes(item.href));

    const groupsInCatalog = useMemo(() => {
        const map = new Map<string, Acceso[]>();
        for (const item of catalog) {
            const list = map.get(item.group) ?? [];
            list.push(item);
            map.set(item.group, list);
        }

        return [...map.entries()];
    }, [catalog]);

    if (catalog.length === 0) {
        return null;
    }

    return (
        <section className="rounded-xl border border-border/70 bg-card px-3 py-3 shadow-sm">
            <div className="mb-2 flex items-center justify-between gap-2">
                <h2 className="text-sm font-semibold text-foreground">{t('accesos.title')}</h2>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="h-8 cursor-pointer gap-1.5 text-xs text-muted-foreground"
                    onClick={() => {
                        setDraft(shown.map((item) => item.href));
                        setOpen(true);
                    }}
                >
                    <Settings2 className="size-3.5" />
                    {t('accesos.personalizar')}
                </Button>
            </div>
            {shown.length === 0 ? (
                <p className="px-1 py-3 text-sm text-muted-foreground">{t('accesos.vacio')}</p>
            ) : (
            <div className="flex gap-1 overflow-x-auto pb-1">
                {shown.map((item, index) => {
                    const Icon = item.icon;

                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            className="flex w-[4.6rem] shrink-0 cursor-pointer flex-col items-center gap-1.5 rounded-lg px-1 py-1.5 text-center transition-colors hover:bg-muted/60"
                        >
                            <span
                                className={cn(
                                    'flex size-10 items-center justify-center rounded-xl',
                                    TONES[index % TONES.length],
                                )}
                            >
                                {Icon ? <Icon className="size-5" /> : null}
                            </span>
                            <span className="line-clamp-2 text-[11px] leading-tight font-medium text-muted-foreground">
                                {item.title}
                            </span>
                        </Link>
                    );
                })}
            </div>
            )}

            <FormModal
                open={open}
                onOpenChange={setOpen}
                title={t('accesos.personalizar')}
                description={t('accesos.hint')}
                size="md"
                onSubmit={(event) => {
                    event.preventDefault();
                    const next = draft.filter((href) => catalog.some((item) => item.href === href));
                    window.localStorage.setItem(storageKey(userId), JSON.stringify(next));
                    setSaved(next);
                    setOpen(false);
                }}
                footer={
                    <Button type="submit" className="cursor-pointer">
                        {t('accesos.guardar')}
                    </Button>
                }
            >
                <div className="space-y-4 pb-1">
                    {groupsInCatalog.map(([group, items]) => (
                        <div key={group}>
                            <p className="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                {group}
                            </p>
                            <div className="flex flex-wrap gap-1.5">
                                {items.map((item) => {
                                    const active = draft.includes(item.href);

                                    return (
                                        <button
                                            key={item.href}
                                            type="button"
                                            className={cn(
                                                'cursor-pointer rounded-full border px-2.5 py-1 text-xs font-medium',
                                                active
                                                    ? 'border-primary bg-primary/10 text-primary'
                                                    : 'border-border text-muted-foreground hover:bg-muted',
                                            )}
                                            onClick={() =>
                                                setDraft((current) =>
                                                    current.includes(item.href)
                                                        ? current.filter((href) => href !== item.href)
                                                        : [...current, item.href],
                                                )
                                            }
                                        >
                                            {item.title}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </div>
            </FormModal>
        </section>
    );
}
