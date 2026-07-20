import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Boxes,
    Building2,
    Gauge,
    PawPrint,
    ReceiptText,
    Save,
    UserRound,
    Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/data-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
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

const FEATURE_ICONS: Record<
    string,
    React.ComponentType<{ className?: string }>
> = {
    max_sedes: Building2,
    max_usuarios: Users,
    max_pacientes: PawPrint,
    max_propietarios: UserRound,
    max_productos: Boxes,
    max_comprobantes_mes: ReceiptText,
};

function toDraft(rows: FeatureRow[]): DraftRow[] {
    return rows.map((r) => ({
        feature: r.feature,
        extra: String(r.extra ?? 0),
        override:
            r.override === null || r.override === undefined
                ? ''
                : String(r.override),
        motivo: r.motivo ?? '',
        expires_at: r.expires_at ?? '',
    }));
}

function formatLimit(
    value: number | null,
    unlimited: boolean,
    t: (k: string) => string,
): string {
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

    const activeExtras = useMemo(
        () =>
            draft.filter((row) => {
                const extra = Number.parseInt(row.extra || '0', 10) || 0;
                return extra > 0 || row.override.trim() !== '';
            }).length,
        [draft],
    );

    const updateRow = (feature: string, patch: Partial<DraftRow>) => {
        setDraft((prev) =>
            prev.map((row) =>
                row.feature === feature ? { ...row, ...patch } : row,
            ),
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

            <div className="flex flex-col gap-5 p-4 md:p-6">
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

                <div className="flex flex-wrap items-center gap-3 rounded-2xl border border-border/60 bg-linear-to-r from-muted/40 to-card px-4 py-3.5">
                    <div className="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <Gauge className="size-5" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="text-sm font-semibold text-foreground">
                            {t('tenants:limits.plan_label')}:{' '}
                            {plan
                                ? `${plan.nombre} (${plan.codigo})`
                                : t('tenants:limits.plan_none')}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {t('tenants:limits.hint')}
                        </p>
                    </div>
                    <Badge variant="outline" className="tabular-nums">
                        {t('tenants:limits.active_extras', {
                            count: activeExtras,
                        })}
                    </Badge>
                    {hasChanges ? (
                        <Badge className="bg-amber-500/15 text-amber-800 dark:text-amber-300">
                            {t('tenants:limits.unsaved')}
                        </Badge>
                    ) : null}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    {features.map((feature, index) => {
                        const row = draft[index];
                        if (!row) {
                            return null;
                        }

                        const Icon = FEATURE_ICONS[feature.feature] ?? Gauge;
                        const extraNum =
                            Number.parseInt(row.extra || '0', 10) || 0;
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
                        const hasBoost =
                            extraNum > 0 ||
                            (overrideNum !== null && !Number.isNaN(overrideNum));

                        return (
                            <div
                                key={feature.feature}
                                className={cn(
                                    'overflow-hidden rounded-2xl border bg-card shadow-sm transition-colors',
                                    hasBoost
                                        ? 'border-emerald-500/30 ring-1 ring-emerald-500/15'
                                        : 'border-border/60',
                                )}
                            >
                                <div className="flex items-start justify-between gap-3 border-b border-border/50 bg-muted/20 px-4 py-3">
                                    <div className="flex min-w-0 items-start gap-3">
                                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-background shadow-sm ring-1 ring-border/60">
                                            <Icon className="size-4 text-primary" />
                                        </div>
                                        <div className="min-w-0">
                                            <p className="font-semibold text-foreground">
                                                {t(
                                                    `tenants:limits.features.${feature.feature}`,
                                                )}
                                            </p>
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                {t('tenants:limits.base')}:{' '}
                                                <span className="font-medium text-foreground/80">
                                                    {formatLimit(
                                                        feature.base,
                                                        feature.unlimited_base,
                                                        t,
                                                    )}
                                                </span>
                                                {' → '}
                                                {t('tenants:limits.effective')}:{' '}
                                                <span className="font-semibold text-foreground">
                                                    {formatLimit(
                                                        preview,
                                                        preview === null,
                                                        t,
                                                    )}
                                                </span>
                                            </p>
                                            {feature.feature ===
                                            'max_comprobantes_mes' ? (
                                                <p className="mt-1 text-[11px] leading-snug text-muted-foreground">
                                                    {t(
                                                        'tenants:limits.features_hint.max_comprobantes_mes',
                                                    )}
                                                </p>
                                            ) : null}
                                        </div>
                                    </div>
                                    {hasBoost ? (
                                        <Badge className="shrink-0 bg-emerald-500/15 text-emerald-800 dark:text-emerald-300">
                                            {overrideNum !== null
                                                ? t('tenants:limits.badge_override')
                                                : t('tenants:limits.badge_extra', {
                                                      count: extraNum,
                                                  })}
                                        </Badge>
                                    ) : null}
                                </div>

                                <div className="grid gap-3 p-4 sm:grid-cols-2">
                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor={`${feature.feature}-extra`}
                                        >
                                            {t('tenants:limits.extra')}
                                        </Label>
                                        <Input
                                            id={`${feature.feature}-extra`}
                                            type="number"
                                            min={0}
                                            inputMode="numeric"
                                            className="h-9"
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
                                        <Label
                                            htmlFor={`${feature.feature}-override`}
                                        >
                                            {t('tenants:limits.override')}
                                        </Label>
                                        <Input
                                            id={`${feature.feature}-override`}
                                            type="number"
                                            min={-1}
                                            inputMode="numeric"
                                            className="h-9"
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
                                        <Label
                                            htmlFor={`${feature.feature}-expires`}
                                        >
                                            {t('tenants:limits.expires_at')}
                                        </Label>
                                        <Input
                                            id={`${feature.feature}-expires`}
                                            type="date"
                                            className="h-9"
                                            value={row.expires_at}
                                            onChange={(e) =>
                                                updateRow(feature.feature, {
                                                    expires_at: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor={`${feature.feature}-motivo`}
                                        >
                                            {t('tenants:limits.motivo')}
                                        </Label>
                                        <Input
                                            id={`${feature.feature}-motivo`}
                                            className="h-9"
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
