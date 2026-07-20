import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Gauge, Save } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import tenants from '@/routes/plataforma/tenants';

type TenantRef = {
    id: string;
    slug: string;
    razon_social: string;
    nombre_comercial: string | null;
    estado: string;
};

type PlanRef = {
    id: string;
    codigo: string;
    nombre: string;
} | null;

type FeatureRow = {
    feature: string;
    base: number | null;
    extra: number;
    override: number | null;
    motivo: string | null;
    expires_at: string | null;
    effective: number | null;
    unlimited_base: boolean;
};

type Props = {
    tenant: TenantRef;
    plan: PlanRef;
    features: FeatureRow[];
};

type DraftRow = {
    feature: string;
    extra: string;
    override: string;
    motivo: string;
    expires_at: string;
};

function toDraft(rows: FeatureRow[]): DraftRow[] {
    return rows.map((r) => ({
        feature: r.feature,
        extra: String(r.extra ?? 0),
        override: r.override === null || r.override === undefined ? '' : String(r.override),
        motivo: r.motivo ?? '',
        expires_at: r.expires_at ?? '',
    }));
}

function formatLimit(value: number | null, unlimited: boolean, t: (k: string) => string): string {
    if (unlimited || value === null) {
        return t('tenants:limits.unlimited');
    }

    return String(value);
}

export default function TenantLimites({ tenant, plan, features }: Props) {
    const { t } = useTranslation(['tenants', 'common']);
    const [processing, setProcessing] = useState(false);
    const [draft, setDraft] = useState<DraftRow[]>(() => toDraft(features));

    const tenantLabel = tenant.nombre_comercial?.trim() || tenant.razon_social;
    const initial = useMemo(() => toDraft(features), [features]);

    const hasChanges = useMemo(() => {
        return draft.some((row, i) => {
            const base = initial[i];
            if (!base) {
                return true;
            }

            return (
                row.extra !== base.extra ||
                row.override !== base.override ||
                row.motivo !== base.motivo ||
                row.expires_at !== base.expires_at
            );
        });
    }, [draft, initial]);

    const updateRow = (feature: string, patch: Partial<DraftRow>) => {
        setDraft((prev) =>
            prev.map((row) => (row.feature === feature ? { ...row, ...patch } : row)),
        );
    };

    const handleSave = () => {
        setProcessing(true);
        router.put(
            `/plataforma/tenants/${tenant.id}/limites`,
            {
                overrides: draft.map((row) => ({
                    feature: row.feature,
                    extra: Number.parseInt(row.extra || '0', 10) || 0,
                    override:
                        row.override.trim() === ''
                            ? null
                            : Number.parseInt(row.override, 10),
                    motivo: row.motivo.trim() || null,
                    expires_at: row.expires_at.trim() || null,
                })),
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <>
            <Head title={t('tenants:limits.page_title', { name: tenantLabel })} />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={t('tenants:limits.page_title', { name: tenantLabel })}
                    description={t('tenants:limits.page_description')}
                    action={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                className="cursor-pointer gap-2"
                                asChild
                            >
                                <Link href={tenants.index().url}>
                                    <ArrowLeft className="size-4" />
                                    {t('tenants:limits.back')}
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

                <div className="rounded-xl border border-border/60 bg-muted/20 px-4 py-3 text-sm">
                    <p className="font-medium text-foreground">
                        {t('tenants:limits.plan_label')}:{' '}
                        {plan
                            ? `${plan.nombre} (${plan.codigo})`
                            : t('tenants:limits.plan_none')}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {t('tenants:limits.hint')}
                    </p>
                    {hasChanges ? (
                        <p className="mt-1 text-xs font-medium text-amber-700 dark:text-amber-400">
                            {t('tenants:limits.unsaved')}
                        </p>
                    ) : null}
                </div>

                <div className="grid gap-3">
                    {features.map((feature, index) => {
                        const row = draft[index];
                        if (!row) {
                            return null;
                        }

                        const extraNum = Number.parseInt(row.extra || '0', 10) || 0;
                        const overrideNum =
                            row.override.trim() === ''
                                ? null
                                : Number.parseInt(row.override, 10);
                        const preview =
                            overrideNum !== null && !Number.isNaN(overrideNum)
                                ? overrideNum < 0
                                    ? null
                                    : overrideNum
                                : feature.unlimited_base
                                  ? null
                                  : (feature.base ?? 0) + extraNum;

                        return (
                            <div
                                key={feature.feature}
                                className="rounded-xl border border-border/60 bg-card p-4 shadow-sm"
                            >
                                <div className="mb-3 flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p className="flex items-center gap-2 font-semibold text-foreground">
                                            <Gauge className="size-4 text-muted-foreground" />
                                            {t(`tenants:limits.features.${feature.feature}`)}
                                        </p>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            {t('tenants:limits.base')}:{' '}
                                            {formatLimit(
                                                feature.base,
                                                feature.unlimited_base,
                                                t,
                                            )}
                                            {' · '}
                                            {t('tenants:limits.effective')}:{' '}
                                            <span className="font-medium text-foreground">
                                                {formatLimit(
                                                    preview,
                                                    preview === null,
                                                    t,
                                                )}
                                            </span>
                                        </p>
                                    </div>
                                </div>

                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    <div className="grid gap-1.5">
                                        <Label htmlFor={`${feature.feature}-extra`}>
                                            {t('tenants:limits.extra')}
                                        </Label>
                                        <Input
                                            id={`${feature.feature}-extra`}
                                            type="number"
                                            min={0}
                                            inputMode="numeric"
                                            value={row.extra}
                                            disabled={row.override.trim() !== ''}
                                            onChange={(e) =>
                                                updateRow(feature.feature, {
                                                    extra: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor={`${feature.feature}-override`}>
                                            {t('tenants:limits.override')}
                                        </Label>
                                        <Input
                                            id={`${feature.feature}-override`}
                                            type="number"
                                            min={-1}
                                            inputMode="numeric"
                                            placeholder={t(
                                                'tenants:limits.override_placeholder',
                                            )}
                                            value={row.override}
                                            onChange={(e) =>
                                                updateRow(feature.feature, {
                                                    override: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor={`${feature.feature}-expires`}>
                                            {t('tenants:limits.expires_at')}
                                        </Label>
                                        <Input
                                            id={`${feature.feature}-expires`}
                                            type="date"
                                            value={row.expires_at}
                                            onChange={(e) =>
                                                updateRow(feature.feature, {
                                                    expires_at: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1.5 sm:col-span-2 lg:col-span-1">
                                        <Label htmlFor={`${feature.feature}-motivo`}>
                                            {t('tenants:limits.motivo')}
                                        </Label>
                                        <Input
                                            id={`${feature.feature}-motivo`}
                                            value={row.motivo}
                                            placeholder={t(
                                                'tenants:limits.motivo_placeholder',
                                            )}
                                            onChange={(e) =>
                                                updateRow(feature.feature, {
                                                    motivo: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </>
    );
}

TenantLimites.layout = {
    breadcrumbs: [
        { title: 'Plataforma', href: '/plataforma/tenants' },
        { title: 'Tenants', href: '/plataforma/tenants' },
        { title: 'Límites', href: '#' },
    ],
};
