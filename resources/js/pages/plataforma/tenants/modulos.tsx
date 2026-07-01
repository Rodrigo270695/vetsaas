import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, LayoutGrid, Save } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { navLabelKeyForModule } from '@/config/tenant-module-labels';
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

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('tenants:modules.page_title', { name: tenantLabel })}
                    description={t('tenants:modules.page_description')}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button variant="outline" className="cursor-pointer gap-2" asChild>
                                <Link href={tenants.index().url}>
                                    <ArrowLeft className="size-4" />
                                    {t('tenants:modules.back')}
                                </Link>
                            </Button>
                            <Button
                                type="button"
                                className="cursor-pointer gap-2"
                                disabled={processing}
                                onClick={handleSave}
                            >
                                <Save className="size-4" />
                                {t('common:actions.save')}
                            </Button>
                        </div>
                    }
                />

                <div className="rounded-xl border border-border/70 bg-card p-4 shadow-sm md:p-6">
                    <div className="mb-6 flex items-start gap-3 text-sm text-muted-foreground">
                        <LayoutGrid className="mt-0.5 size-4 shrink-0 text-primary" />
                        <p>{t('tenants:modules.hint')}</p>
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        {module_groups.map((group) => {
                            const groupKeys = group.modules.map((m) => m.key);
                            const allOn = groupKeys.every((key) => enabled[key] !== false);
                            const someOn = groupKeys.some((key) => enabled[key] !== false);

                            return (
                                <section
                                    key={group.group}
                                    className="rounded-lg border border-border/60 bg-muted/20 p-4"
                                >
                                    <div className="mb-4 flex items-center justify-between gap-3 border-b border-border/50 pb-3">
                                        <h2 className="text-sm font-semibold tracking-tight">
                                            {t(`nav:groups.${group.group}`)}
                                        </h2>
                                        <div className="flex items-center gap-2">
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
                                                className="cursor-pointer text-xs text-muted-foreground"
                                            >
                                                {t('tenants:modules.toggle_group')}
                                            </Label>
                                        </div>
                                    </div>

                                    <ul className="space-y-2">
                                        {group.modules.map((mod) => (
                                            <li
                                                key={mod.key}
                                                className="flex items-center gap-3 rounded-md px-2 py-1.5 hover:bg-background/80"
                                            >
                                                <Checkbox
                                                    id={`mod-${mod.key}`}
                                                    checked={enabled[mod.key] !== false}
                                                    onCheckedChange={(value) =>
                                                        toggleModule(mod.key, value === true)
                                                    }
                                                    className="cursor-pointer"
                                                />
                                                <Label
                                                    htmlFor={`mod-${mod.key}`}
                                                    className="flex-1 cursor-pointer text-sm font-normal"
                                                >
                                                    {t(`nav:items.${navLabelKeyForModule(mod.key)}`, {
                                                        defaultValue: mod.key,
                                                    })}
                                                </Label>
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            );
                        })}
                    </div>
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
