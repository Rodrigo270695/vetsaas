import { AlertCircle, CheckCircle2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { FormModal } from '@/components/forms';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import { ApiPeruResultViewer } from './apiperu-result-viewer';
import { ApiPeruSwipeTabs } from './apiperu-swipe-tabs';
import type { ApiPeruPerfilPayload } from '../types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    payload: ApiPeruPerfilPayload | null;
};

export function ApiPeruDetailModal({ open, onOpenChange, payload }: Props) {
    const keys = useMemo(() => {
        if (!payload) {
            return [];
        }

        return Object.keys(payload.results);
    }, [payload]);

    const firstOk = useMemo(() => {
        if (!payload) {
            return keys[0] ?? '';
        }

        const okKey = keys.find((k) => payload.results[k]?.ok);

        return okKey ?? keys[0] ?? '';
    }, [keys, payload]);

    const [tab, setTab] = useState(firstOk);

    useEffect(() => {
        setTab(firstOk);
    }, [firstOk, payload?.subject, payload?.profile]);

    const activeTab = keys.includes(tab) ? tab : firstOk;

    useEffect(() => {
        if (!activeTab) {
            return;
        }

        const trigger = document.querySelector<HTMLElement>(
            `[data-slot="tabs-trigger"][data-state="active"]`,
        );
        trigger?.scrollIntoView({ inline: 'nearest', block: 'nearest', behavior: 'smooth' });
    }, [activeTab]);

    if (!payload) {
        return null;
    }

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            size="xl"
            blockDismiss={false}
            title={payload.subject ? `${payload.label} · ${payload.subject}` : payload.label}
            description={`${payload.ok_count} ok · ${payload.fail_count} con error · ${keys.length} fuentes`}
            footer={
                <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                    Cerrar
                </Button>
            }
        >
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap gap-2">
                    <Badge variant="default" className="font-normal">
                        {payload.ok_count} correctas
                    </Badge>
                    {payload.fail_count > 0 ? (
                        <Badge variant="secondary" className="font-normal">
                            {payload.fail_count} sin datos / error
                        </Badge>
                    ) : null}
                </div>

                <Tabs value={activeTab} onValueChange={setTab} className="gap-3">
                    <ApiPeruSwipeTabs remountKey={`${payload.profile}:${payload.subject ?? ''}:${keys.length}`}>
                        <TabsList className="h-auto w-max justify-start gap-1 bg-muted/70 p-1">
                            {keys.map((key) => {
                                const item = payload.results[key];
                                if (!item) {
                                    return null;
                                }

                                return (
                                    <TabsTrigger
                                        key={key}
                                        value={key}
                                        className={cn(
                                            'h-9 shrink-0 gap-1.5 px-3 text-xs sm:text-sm',
                                            'data-[state=active]:bg-background data-[state=active]:text-primary',
                                        )}
                                    >
                                        {item.ok ? (
                                            <CheckCircle2
                                                className="size-3.5 text-emerald-600"
                                                aria-hidden
                                            />
                                        ) : (
                                            <AlertCircle
                                                className="size-3.5 text-amber-600"
                                                aria-hidden
                                            />
                                        )}
                                        <span className="whitespace-nowrap">{item.label}</span>
                                    </TabsTrigger>
                                );
                            })}
                        </TabsList>
                    </ApiPeruSwipeTabs>

                    {keys.map((key) => {
                        const item = payload.results[key];
                        if (!item) {
                            return null;
                        }

                        return (
                            <TabsContent key={key} value={key} className="outline-none">
                                {item.ok ? (
                                    <ApiPeruResultViewer
                                        data={item.data}
                                        timeMs={item.time}
                                        endpointKey={key}
                                    />
                                ) : (
                                    <div className="rounded-lg border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-sm">
                                        <p className="font-medium text-foreground">{item.label}</p>
                                        <p className="mt-1 text-muted-foreground">
                                            {item.message ?? 'Sin datos para esta fuente.'}
                                        </p>
                                        {item.code ? (
                                            <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                código: {item.code}
                                            </p>
                                        ) : null}
                                    </div>
                                )}
                            </TabsContent>
                        );
                    })}
                </Tabs>
            </div>
        </FormModal>
    );
}
