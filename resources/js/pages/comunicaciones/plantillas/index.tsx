import { Head } from '@inertiajs/react';
import { MessageSquareText, Pencil } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
import { PageHeader } from '@/components/data-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { usePermission } from '@/hooks/use-permission';
import { PlantillaFormModal } from './components/plantilla-form-modal';
import type { PlantillaItem, PlantillasPageProps } from './types';

export default function Index({
    groups = {},
    groupOrder = [],
}: PlantillasPageProps) {
    const { t } = useTranslation(['comunicaciones', 'common']);
    const { can } = usePermission();
    const canUpdate = can('plantillas.update');
    const [editing, setEditing] = useState<PlantillaItem | null>(null);

    const orderedGroups = useMemo(() => {
        const keys =
            groupOrder.length > 0 ? groupOrder : Object.keys(groups);
        return keys
            .filter((key) => (groups[key]?.length ?? 0) > 0)
            .map((key) => ({
                key,
                items: groups[key] ?? [],
            }));
    }, [groupOrder, groups]);

    return (
        <>
            <Head title={t('plantillas.title')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('plantillas.title')}
                    description={t('plantillas.description')}
                />

                {orderedGroups.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-2 py-12 text-center text-muted-foreground">
                            <MessageSquareText className="size-8 opacity-50" />
                            <p>{t('plantillas.empty')}</p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-6">
                        {orderedGroups.map((group) => (
                            <Card key={group.key}>
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-base">
                                        {t(`plantillas.grupos.${group.key}`, {
                                            defaultValue: group.key,
                                        })}
                                    </CardTitle>
                                    <CardDescription>
                                        {t(
                                            `plantillas.grupos.${group.key}_hint`,
                                            {
                                                defaultValue: '',
                                            },
                                        )}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {group.items.map((item) => (
                                        <div
                                            key={item.id}
                                            className="flex flex-col gap-3 rounded-lg border border-border p-3 sm:flex-row sm:items-start sm:justify-between"
                                        >
                                            <div className="min-w-0 flex-1 space-y-2">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="font-medium text-foreground">
                                                        {t(
                                                            `plantillas.tipos.${item.tipo}`,
                                                            {
                                                                defaultValue:
                                                                    item.tipo,
                                                            },
                                                        )}
                                                    </p>
                                                    {item.cuerpo !==
                                                    item.cuerpo_default ? (
                                                        <Badge variant="default">
                                                            {t(
                                                                'plantillas.badge_modified',
                                                            )}
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="secondary">
                                                            {t(
                                                                'plantillas.badge_stock',
                                                            )}
                                                        </Badge>
                                                    )}
                                                    {!item.activo ? (
                                                        <Badge variant="outline">
                                                            {t(
                                                                'plantillas.badge_factory',
                                                            )}
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                                <pre className="max-h-28 overflow-hidden whitespace-pre-wrap break-words font-sans text-sm text-muted-foreground">
                                                    {item.preview}
                                                </pre>
                                            </div>
                                            <Can permission="plantillas.update">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className="shrink-0"
                                                    onClick={() =>
                                                        setEditing(item)
                                                    }
                                                >
                                                    <Pencil className="size-4" />
                                                    {t('plantillas.edit')}
                                                </Button>
                                            </Can>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {canUpdate ? (
                <PlantillaFormModal
                    open={editing !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setEditing(null);
                        }
                    }}
                    plantilla={editing}
                />
            ) : null}
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Comunicaciones', href: '#' },
        { title: 'Plantillas', href: '/comunicaciones/plantillas' },
    ],
};
