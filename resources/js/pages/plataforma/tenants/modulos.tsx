import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, LayoutGrid, Save } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { navLabelKeyForModule } from '@/config/tenant-module-labels';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import tenants from '@/routes/plataforma/tenants';
import type { TenantModuleGroup } from '@/types/tenant-modules';

type TenantRef = {
    id: string;
    slug: string;
    razon_social: string;
    nombre_comercial: string | null;
    estado: string;
};

type TenantModulosProps = {
    tenant: TenantRef;
    module_groups: TenantModuleGroup[];
};

export default function TenantModulos({ tenant, module_groups }: TenantModulosProps) {
    const { t } = useTranslation(['tenants', 'nav', 'common']);
    const [processing, setProcessing] = useState(false);

    const initialEnabled = useMemo(() => {
        const map: Record<string, boolean> = {};

        for (const group of module_groups) {
            for (const mod of group.modules) {
                map[mod.key] = mod.enabled;
            }
        }

        return map;
    }, [module_groups]);

    const [enabled, setEnabled] = useState(initialEnabled);

    const tenantLabel = tenant.nombre_comercial?.trim() || tenant.razon_social;

    const disabledCount = useMemo(
        () => Object.values(enabled).filter((isOn) => !isOn).length,
        [enabled],
    );

    const hasChanges = useMemo(() => {
        return Object.entries(initialEnabled).some(([key, value]) => enabled[key] !== value);
    }, [enabled, initialEnabled]);

    const toggleModule = (key: string, checked: boolean) => {
        setEnabled((prev) => ({ ...prev, [key]: checked }));
    };

    const toggleGroup = (group: TenantModuleGroup, checked: boolean) => {
        setEnabled((prev) => {
            const next = { ...prev };
            for (const mod of group.modules) {
                next[mod.key] = checked;
            }
            return next;
        });
    };

    const handleSave = () => {
        const modulos_deshabilitados = Object.entries(enabled)
            .filter(([, isOn]) => !isOn)
            .map(([key]) => key);

        setProcessing(true);
        router.put(`/plataforma/tenants/${tenant.id}/modulos`, { modulos_deshabilitados }, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <>
            <Head title={t('tenants:modules.page_title', { name: tenantLabel })} />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={t('tenants:modules.page_title', { name: tenantLabel })}
                    description={t('tenants:modules.page_description')}
                    action={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button variant="outline" size="sm" className="cursor-pointer gap-2" asChild>
                                <Link href={tenants.index().url}>
                                    <ArrowLeft className="size-4" />
                                    {t('tenants:modules.back')}
                                </Link>
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                className="cursor-pointer gap-2"
                                disabled={processing || !hasChanges}
                                onClick={handleSave}
                            >
                                <Save className="size-4" />
                                {t('common:actions.save')}
                            </Button>
                        </div>
                    }
                />

                <p className="-mt-2 text-xs text-muted-foreground">
                    {disabledCount === 0
                        ? t('tenants:modules.all_visible')
                        : t('tenants:modules.disabled_summary', { count: disabledCount })}
                    {hasChanges ? (
                        <span className="ml-1 font-medium text-amber-700 dark:text-amber-400">
                            · {t('tenants:modules.unsaved')}
                        </span>
                    ) : null}
                </p>

                <div className="flex items-start gap-2 rounded-lg border border-border/60 bg-muted/30 px-3 py-2.5 text-xs leading-relaxed text-muted-foreground">
                    <LayoutGrid className="mt-0.5 size-3.5 shrink-0 text-primary" />
                    <p>{t('tenants:modules.hint')}</p>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {module_groups.map((group) => {
                        const groupKeys = group.modules.map((m) => m.key);
                        const allOn = groupKeys.every((key) => enabled[key] !== false);
                        const someOn = groupKeys.some((key) => enabled[key] !== false);
                        const groupOff = groupKeys.filter((key) => enabled[key] === false).length;

                        return (
                            <section
                                key={group.group}
                                className="rounded-lg border border-border/60 bg-card shadow-sm"
                            >
                                <div className="flex items-center justify-between gap-2 border-b border-border/50 px-3 py-2">
                                    <div className="min-w-0">
                                        <h2 className="truncate text-sm font-semibold">
                                            {t(`nav:groups.${group.group}`)}
                                        </h2>
                                        {groupOff > 0 ? (
                                            <p className="text-[11px] text-muted-foreground">
                                                {t('tenants:modules.hidden_count', { count: groupOff })}
                                            </p>
                                        ) : null}
                                    </div>
                                    <div className="flex shrink-0 items-center gap-1.5">
                                        <Checkbox
                                            id={`group-${group.group}`}
                                            checked={allOn ? true : someOn ? 'indeterminate' : false}
                                            onCheckedChange={(value) =>
                                                toggleGroup(group, value === true)
                                            }
                                            className="cursor-pointer"
                                        />
                                        <Label
                                            htmlFor={`group-${group.group}`}
                                            className="cursor-pointer text-[11px] text-muted-foreground"
                                        >
                                            {t('tenants:modules.toggle_group')}
                                        </Label>
                                    </div>
                                </div>

                                <ul className="grid grid-cols-1 gap-0.5 p-2 sm:grid-cols-2">
                                    {group.modules.map((mod) => {
                                        const isOn = enabled[mod.key] !== false;

                                        return (
                                            <li key={mod.key}>
                                                <label
                                                    htmlFor={`mod-${mod.key}`}
                                                    className={cn(
                                                        'flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-muted/60',
                                                        !isOn && 'text-muted-foreground',
                                                    )}
                                                >
                                                    <Checkbox
                                                        id={`mod-${mod.key}`}
                                                        checked={isOn}
                                                        onCheckedChange={(value) =>
                                                            toggleModule(mod.key, value === true)
                                                        }
                                                        className="cursor-pointer"
                                                    />
                                                    <span className="truncate leading-tight">
                                                        {t(
                                                            `nav:items.${navLabelKeyForModule(mod.key)}`,
                                                            { defaultValue: mod.key },
                                                        )}
                                                    </span>
                                                </label>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </section>
                        );
                    })}
                </div>
            </div>
        </>
    );
}

TenantModulos.layout = {
    breadcrumbs: [
        { title: 'Plataforma', href: '#' },
        { title: 'Tenants', href: tenants.index().url },
        { title: 'Módulos', href: '#' },
    ],
};
