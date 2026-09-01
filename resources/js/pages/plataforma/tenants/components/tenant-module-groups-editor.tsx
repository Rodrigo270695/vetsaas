import { useTranslation } from 'react-i18next';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { navLabelKeyForModule } from '@/config/tenant-module-labels';
import { cn } from '@/lib/utils';
import type { TenantModuleGroup } from '@/types/tenant-modules';

type Props = {
    groups: TenantModuleGroup[];
    enabled: Record<string, boolean>;
    onToggleModule: (key: string, checked: boolean) => void;
    onToggleGroup: (group: TenantModuleGroup, checked: boolean) => void;
};

export function TenantModuleGroupsEditor({
    groups,
    enabled,
    onToggleModule,
    onToggleGroup,
}: Props) {
    const { t } = useTranslation(['tenants', 'nav']);

    return (
        <div className="grid gap-3 sm:grid-cols-2">
            {groups.map((group) => {
                const groupKeys = group.modules.map((m) => m.key);
                const allOn = groupKeys.every((key) => enabled[key] !== false);
                const someOn = groupKeys.some((key) => enabled[key] !== false);
                const groupOff = groupKeys.filter((key) => enabled[key] === false).length;

                return (
                    <section
                        key={group.group}
                        className="rounded-lg border border-border/60 bg-card"
                    >
                        <div className="flex items-center justify-between gap-2 border-b border-border/50 px-3 py-2">
                            <div className="min-w-0">
                                <h2 className="truncate text-sm font-semibold">
                                    {t(`nav:groups.${group.group}`)}
                                </h2>
                                {groupOff > 0 ? (
                                    <p className="text-[11px] text-muted-foreground">
                                        {t('tenants:modules.hidden_count', {
                                            count: groupOff,
                                        })}
                                    </p>
                                ) : null}
                            </div>
                            <div className="flex shrink-0 items-center gap-1.5">
                                <Checkbox
                                    id={`group-${group.group}`}
                                    checked={allOn ? true : someOn ? 'indeterminate' : false}
                                    onCheckedChange={(value) =>
                                        onToggleGroup(group, value === true)
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
                                                    onToggleModule(mod.key, value === true)
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
    );
}
