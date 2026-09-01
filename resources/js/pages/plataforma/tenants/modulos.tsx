import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, LayoutGrid, Save } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import tenants from '@/routes/plataforma/tenants';
import type { TenantModuleGroup } from '@/types/tenant-modules';
import { TenantModuleGroupsEditor } from './components/tenant-module-groups-editor';

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
    const disabledCount = Object.values(enabled).filter((isOn) => !isOn).length;
    const hasChanges = Object.entries(initialEnabled).some(
        ([key, value]) => enabled[key] !== value,
    );

    const handleSave = () => {
        const modulos_deshabilitados = Object.entries(enabled)
            .filter(([, isOn]) => !isOn)
            .map(([key]) => key);

        setProcessing(true);
        router.put(
            `/plataforma/tenants/${tenant.id}/modulos`,
            { modulos_deshabilitados },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
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

                <TenantModuleGroupsEditor
                    groups={module_groups}
                    enabled={enabled}
                    onToggleModule={(key, checked) =>
                        setEnabled((prev) => ({ ...prev, [key]: checked }))
                    }
                    onToggleGroup={(group, checked) => {
                        setEnabled((prev) => {
                            const next = { ...prev };
                            for (const mod of group.modules) {
                                next[mod.key] = checked;
                            }

                            return next;
                        });
                    }}
                />
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
