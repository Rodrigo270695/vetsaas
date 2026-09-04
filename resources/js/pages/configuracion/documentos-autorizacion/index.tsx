import { Head, useForm } from '@inertiajs/react';
import { FilePenLine, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
import { EmptyState, PageHeader } from '@/components/data-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/hooks/use-permission';
import {
    DocumentoAutorizacionPlantillaFormModal,
    type PlantillaAutorizacion,
} from './components/plantilla-form-modal';

type Props = {
    plantillas: readonly PlantillaAutorizacion[];
    cuerpo_default: string;
    clinic_logo_url?: string | null;
};

export default function Index({ plantillas, cuerpo_default, clinic_logo_url = null }: Props) {
    const { t } = useTranslation(['documentos-autorizacion', 'common', 'nav']);
    const { can } = usePermission();
    const canUpdate = can('config-general.update');
    const [editing, setEditing] = useState<PlantillaAutorizacion | null | 'new'>(null);
    const destroyForm = useForm({});

    const newButton = canUpdate ? (
        <Button type="button" className="cursor-pointer gap-2 shadow-sm" onClick={() => setEditing('new')}>
            <Plus className="size-4" />
            {t('new')}
        </Button>
    ) : null;

    return (
        <>
            <Head title={t('title')} />
            <div className="flex flex-1 flex-col gap-6 bg-linear-to-b from-primary/6 via-transparent to-transparent p-4 md:p-6">
                <PageHeader title={t('title')} description={t('description')} action={newButton} />

                {plantillas.length === 0 ? (
                    <EmptyState
                        icon={FilePenLine}
                        title={t('empty')}
                        description={t('empty_hint')}
                        action={newButton}
                    />
                ) : (
                    <div className="grid gap-5 lg:grid-cols-2">
                        {plantillas.map((row) => (
                            <article
                                key={row.id}
                                className="overflow-hidden rounded-xl border border-border/70 bg-card shadow-sm ring-1 ring-black/4"
                            >
                                <div className="flex items-start justify-between gap-3 border-b border-border/60 bg-primary/8 px-4 py-3">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h2 className="truncate font-semibold">{row.nombre}</h2>
                                            {row.activo ? (
                                                <Badge className="bg-emerald-600 text-white hover:bg-emerald-600">
                                                    {t('activo')}
                                                </Badge>
                                            ) : null}
                                        </div>
                                        {row.descripcion ? (
                                            <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                                {row.descripcion}
                                            </p>
                                        ) : null}
                                    </div>
                                    <Can permission="config-general.update">
                                        <div className="flex shrink-0 gap-1.5">
                                            <Button
                                                type="button"
                                                variant="secondary"
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
                                                    if (
                                                        !window.confirm(
                                                            t('delete_description', { nombre: row.nombre }),
                                                        )
                                                    ) {
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
                                </div>
                                <div className="bg-[#efe9dc] p-3 sm:p-4">
                                    <div className="auth-doc-body max-h-56 overflow-hidden rounded-sm bg-white px-4 py-5 leading-relaxed text-stone-800 shadow-md ring-1 ring-black/8">
                                        <div
                                            className="line-clamp-8"
                                            dangerouslySetInnerHTML={{
                                                __html: row.cuerpo_preview ?? row.cuerpo,
                                            }}
                                        />
                                    </div>
                                </div>
                            </article>
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
                clinicLogoUrl={clinic_logo_url}
            />
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Configuración' },
        { title: 'Autorizaciones', href: '/configuracion/documentos-autorizacion' },
    ],
};
