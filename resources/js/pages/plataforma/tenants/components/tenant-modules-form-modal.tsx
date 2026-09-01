import { router } from '@inertiajs/react';
import { Loader2, Save, X } from 'lucide-react';
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormModal } from '@/components/forms';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { TenantModuleGroup } from '@/types/tenant-modules';
import { TenantModuleGroupsEditor } from './tenant-module-groups-editor';

type TenantRef = {
    id: string;
    nombre: string;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    tenant: TenantRef | null;
    groups: TenantModuleGroup[];
};

function enabledMap(groups: TenantModuleGroup[]): Record<string, boolean> {
    const map: Record<string, boolean> = {};
    for (const group of groups) {
        for (const mod of group.modules) {
            map[mod.key] = mod.enabled;
        }
    }

    return map;
}

export function TenantModulesFormModal({
    open,
    onOpenChange,
    tenant,
    groups,
}: Props) {
    const { t } = useTranslation(['tenants', 'common']);
    const [processing, setProcessing] = useState(false);
    const initial = useMemo(() => enabledMap(groups), [groups]);
    const [enabled, setEnabled] = useState(initial);

    useEffect(() => {
        if (open) {
            setEnabled(enabledMap(groups));
        }
    }, [open, groups, tenant?.id]);

    const disabledCount = Object.values(enabled).filter((isOn) => !isOn).length;
    const hasChanges = Object.entries(initial).some(
        ([key, value]) => enabled[key] !== value,
    );

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

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();
        if (!tenant || !hasChanges) {
            return;
        }

        const modulos_deshabilitados = Object.entries(enabled)
            .filter(([, isOn]) => !isOn)
            .map(([key]) => key);

        setProcessing(true);
        router.put(
            `/plataforma/tenants/${tenant.id}/modulos`,
            { modulos_deshabilitados },
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={
                tenant
                    ? t('tenants:modules.page_title', { name: tenant.nombre })
                    : t('tenants:row.modules')
            }
            description={t('tenants:modules.page_description')}
            size="xl"
            onSubmit={handleSubmit}
            footer={
                <>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                className="size-9 cursor-pointer"
                                disabled={processing}
                                onClick={() => onOpenChange(false)}
                                aria-label={t('common:actions.cancel')}
                            >
                                <X className="size-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{t('common:actions.cancel')}</TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                type="submit"
                                size="icon"
                                className="size-9 cursor-pointer disabled:cursor-not-allowed"
                                disabled={processing || !hasChanges}
                                aria-label={t('common:actions.save')}
                            >
                                {processing ? (
                                    <Loader2 className="size-4 animate-spin" />
                                ) : (
                                    <Save className="size-4" />
                                )}
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{t('common:actions.save')}</TooltipContent>
                    </Tooltip>
                </>
            }
        >
            <div className="flex flex-col gap-3">
                <p className="text-xs text-muted-foreground">
                    {disabledCount === 0
                        ? t('tenants:modules.all_visible')
                        : t('tenants:modules.disabled_summary', {
                              count: disabledCount,
                          })}
                    {hasChanges ? (
                        <span className="ml-1 font-medium text-amber-700 dark:text-amber-400">
                            · {t('tenants:modules.unsaved')}
                        </span>
                    ) : null}
                </p>
                <p className="text-xs leading-relaxed text-muted-foreground">
                    {t('tenants:modules.hint')}
                </p>
                <TenantModuleGroupsEditor
                    groups={groups}
                    enabled={enabled}
                    onToggleModule={toggleModule}
                    onToggleGroup={toggleGroup}
                />
            </div>
        </FormModal>
    );
}
