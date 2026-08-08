import { Head } from '@inertiajs/react';
import {
    Building2,
    CarFront,
    ExternalLink,
    FileText,
    IdCard,
    Landmark,
    MapPinned,
    WalletCards,
} from 'lucide-react';
import type { LucideIcon, ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { PageHeader } from '@/components/data-page';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { ComprobantesTab } from './components/tabs/comprobantes-tab';
import { FinanzasTab } from './components/tabs/finanzas-tab';
import { IdentidadTab } from './components/tabs/identidad-tab';
import { RucDetalleTab } from './components/tabs/ruc-detalle-tab';
import { UbicacionTab } from './components/tabs/ubicacion-tab';
import { VehiculosTab } from './components/tabs/vehiculos-tab';
import type { ApiPeruGroup, ApiPeruIndexProps } from './types';

const TAB_ICONS: Record<string, LucideIcon> = {
    identidad: IdCard,
    ruc_detalle: Building2,
    finanzas: WalletCards,
    comprobantes: FileText,
    vehiculos: CarFront,
    ubicacion: MapPinned,
};

function findGroup(groups: ApiPeruGroup[], id: string): ApiPeruGroup | null {
    return groups.find((g) => g.id === id) ?? null;
}

export default function PlataformaApiPeruIndex({ groups, meta }: ApiPeruIndexProps) {
    const { t } = useTranslation(['plataforma-apiperu', 'common']);
    const [tab, setTab] = useState(groups[0]?.id ?? 'identidad');
    const consultarUrl = '/plataforma/apiperu/consultar';
    const disabled = !meta.token_configured;

    const endpointCount = useMemo(
        () => groups.reduce((acc, g) => acc + g.endpoints.length, 0),
        [groups],
    );

    const identidad = findGroup(groups, 'identidad');
    const rucDetalle = findGroup(groups, 'ruc_detalle');
    const finanzas = findGroup(groups, 'finanzas');
    const comprobantes = findGroup(groups, 'comprobantes');
    const vehiculos = findGroup(groups, 'vehiculos');
    const ubicacion = findGroup(groups, 'ubicacion');

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    stats={[
                        {
                            label: t('stats.endpoints'),
                            value: endpointCount,
                            variant: 'primary',
                        },
                        {
                            label: t('stats.groups'),
                            value: groups.length,
                            variant: 'muted',
                        },
                    ]}
                    action={
                        <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                            <Badge
                                variant={meta.token_configured ? 'default' : 'secondary'}
                                className="h-8 justify-center px-3 font-normal"
                            >
                                {meta.token_configured
                                    ? t('meta.token_ok')
                                    : t('meta.token_missing')}
                            </Badge>
                            <Button asChild variant="outline" className="h-10 gap-2">
                                <a href={meta.docs_url} target="_blank" rel="noreferrer">
                                    <ExternalLink className="size-4 opacity-70" aria-hidden />
                                    {t('meta.docs')}
                                </a>
                            </Button>
                        </div>
                    }
                />

                {!meta.token_configured ? (
                    <Alert className="border-amber-500/30 bg-amber-500/5">
                        <Landmark className="size-4" />
                        <AlertTitle>{t('alert.token_title')}</AlertTitle>
                        <AlertDescription>
                            {t('alert.token_body', { env: 'APIPERU_TOKEN' })}
                            <span className="mt-1 block font-mono text-xs text-muted-foreground">
                                {meta.base_url}
                            </span>
                        </AlertDescription>
                    </Alert>
                ) : (
                    <Alert className="border-primary/20 bg-primary/5">
                        <Landmark className="size-4 text-primary" />
                        <AlertTitle>{t('alert.ready_title')}</AlertTitle>
                        <AlertDescription className="text-muted-foreground">
                            {t('alert.ready_body')}
                            <span className="mt-1 block font-mono text-xs">{meta.base_url}</span>
                        </AlertDescription>
                    </Alert>
                )}

                <Tabs value={tab} onValueChange={setTab} className="gap-4">
                    <div className="-mx-1 overflow-x-auto px-1 pb-1">
                        <TabsList className="h-auto w-max min-w-full flex-wrap justify-start gap-1 bg-muted/70 p-1 sm:min-w-0 sm:w-full">
                            {groups.map((group) => {
                                const Icon = TAB_ICONS[group.id] ?? IdCard;

                                return (
                                    <TabsTrigger
                                        key={group.id}
                                        value={group.id}
                                        className={cn(
                                            'h-9 shrink-0 gap-1.5 px-3 text-xs sm:text-sm',
                                            'data-[state=active]:bg-background data-[state=active]:text-primary',
                                        )}
                                    >
                                        <Icon className="size-3.5 shrink-0" aria-hidden />
                                        <span>{group.label}</span>
                                        <span className="rounded bg-muted-foreground/15 px-1.5 py-0.5 text-[10px] tabular-nums text-muted-foreground">
                                            {group.endpoints.length}
                                        </span>
                                    </TabsTrigger>
                                );
                            })}
                        </TabsList>
                    </div>

                    {identidad ? (
                        <TabsContent value="identidad">
                            <IdentidadTab
                                group={identidad}
                                consultarUrl={consultarUrl}
                                disabled={disabled}
                            />
                        </TabsContent>
                    ) : null}

                    {rucDetalle ? (
                        <TabsContent value="ruc_detalle">
                            <RucDetalleTab
                                group={rucDetalle}
                                consultarUrl={consultarUrl}
                                disabled={disabled}
                            />
                        </TabsContent>
                    ) : null}

                    {finanzas ? (
                        <TabsContent value="finanzas">
                            <FinanzasTab
                                group={finanzas}
                                consultarUrl={consultarUrl}
                                disabled={disabled}
                            />
                        </TabsContent>
                    ) : null}

                    {comprobantes ? (
                        <TabsContent value="comprobantes">
                            <ComprobantesTab
                                group={comprobantes}
                                consultarUrl={consultarUrl}
                                disabled={disabled}
                            />
                        </TabsContent>
                    ) : null}

                    {vehiculos ? (
                        <TabsContent value="vehiculos">
                            <VehiculosTab
                                group={vehiculos}
                                consultarUrl={consultarUrl}
                                disabled={disabled}
                            />
                        </TabsContent>
                    ) : null}

                    {ubicacion ? (
                        <TabsContent value="ubicacion">
                            <UbicacionTab
                                group={ubicacion}
                                consultarUrl={consultarUrl}
                                disabled={disabled}
                            />
                        </TabsContent>
                    ) : null}
                </Tabs>
            </div>
        </>
    );
}

PlataformaApiPeruIndex.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma' },
            { title: 'ApiPerú', href: '/plataforma/apiperu' },
        ]}
    >
        {page}
    </AppLayout>
);
