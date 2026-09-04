import { Head, useForm } from '@inertiajs/react';
import { FilePenLine, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
import { EmptyState, PageHeader } from '@/components/data-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { usePermission } from '@/hooks/use-permission';
import {
    DocumentoAutorizacionPlantillaFormModal,
    type PlantillaAutorizacion,
} from './components/plantilla-form-modal';
import { htmlToPlain } from './components/plantilla-cuerpo-editor';

type Props = {
    plantillas: readonly PlantillaAutorizacion[];
    cuerpo_default: string;
};

export default function Index({ plantillas, cuerpo_default }: Props) {
    const { t } = useTranslation(['documentos-autorizacion', 'common', 'nav']);
    const { can } = usePermission();
    const canUpdate = can('config-general.update');
    const [editing, setEditing] = useState<PlantillaAutorizacion | null | 'new'>(null);
    const destroyForm = useForm({});

    const newButton = canUpdate ? (
        <Button
            type="button"
            className="cursor-pointer gap-2"
            onClick={() => setEditing('new')}
        >
            <Plus className="size-4" />
            {t('new')}
        </Button>
    ) : null;

    return (
        <>
            <Head title={t('title')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    action={newButton}
                />

                {plantillas.length === 0 ? (
                    <EmptyState
                        icon={FilePenLine}
                        title={t('empty')}
                        description={t('empty_hint')}
                        action={newButton}
                    />
                ) : (
                    <div className="grid gap-3">
                        {plantillas.map((row) => (
                            <Card key={row.id}>
                                <CardContent className="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-medium">{row.nombre}</p>
                                            {row.activo ? (
                                                <Badge variant="secondary">{t('activo')}</Badge>
                                            ) : null}
                                        </div>
                                        {row.descripcion ? (
                                            <p className="text-sm text-muted-foreground">{row.descripcion}</p>
                                        ) : null}
                                        <p className="line-clamp-3 text-sm text-muted-foreground">
                                            {htmlToPlain(row.cuerpo)}
                                        </p>
                                    </div>
                                    <Can permission="config-general.update">
                                        <div className="flex shrink-0 gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="cursor-pointer gap-1"
                                                onClick={() => setEditing(row)}
                                            >
                                                <Pencil className="size-3.5" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="cursor-pointer gap-1 text-destructive"
                                                onClick={() => {
                                                    if (!window.confirm(t('delete_description', { nombre: row.nombre }))) {
                                                        return;
                                                    }
                                                    destroyForm.delete(
                                                        `/configuracion/documentos-autorizacion/${row.id}`,
                                                        { preserveScroll: true },
                                                    );
                                                }}
                                            >
                                                <Trash2 className="size-3.5" />
                                            </Button>
                                        </div>
                                    </Can>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <DocumentoAutorizacionPlantillaFormModal
                open={editing !== null}
                onOpenChange={(next) => {
                    if (!next) {
                        setEditing(null);
                    }
                }}
                plantilla={editing === 'new' || editing === null ? null : editing}
                cuerpoDefault={cuerpo_default}
            />
        </>
    );
}
