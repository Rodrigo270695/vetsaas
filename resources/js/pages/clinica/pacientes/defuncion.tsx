import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, FilePenLine, HeartOff, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { DocumentoAutorizacionSendDialog } from './components/documento-autorizacion-send-dialog';
import type { PlantillaAutorizacionOpcion } from './components/documento-autorizacion-send-dialog';

type Autorizacion = {
    estado: string;
    firmado_at: string | null;
    pdf_url: string | null;
    titulo: string;
};

type Registro = {
    id?: string;
    motivo: string | null;
    sitio: string | null;
    testigo: string | null;
    comentarios: string | null;
    veterinario: string | null;
    autorizacion: Autorizacion | null;
};

type Props = {
    paciente: { id: string; nombre: string; fallecido?: boolean };
    registro: Registro | null;
    ocurrido_at: string;
    puede_editar: boolean;
    guardar_url: string;
    method: 'post' | 'put';
    autorizar_url: string | null;
    plantillas: PlantillaAutorizacionOpcion[];
    plantilla_defuncion_id: string | null;
    telefono: string;
    email: string;
    onVolver: () => void;
    onSaved?: (url: string) => void;
};

const campoClass =
    'h-10 border-amber-100/80 bg-[#fbf6e4] shadow-none dark:border-amber-900/40 dark:bg-amber-950/25';

export function DefuncionForm({
    paciente,
    registro,
    ocurrido_at,
    puede_editar,
    guardar_url,
    method,
    autorizar_url,
    plantillas,
    plantilla_defuncion_id,
    telefono,
    email,
    onVolver,
    onSaved,
}: Props) {
    const { t } = useTranslation('pacientes');
    const [authOpen, setAuthOpen] = useState(false);
    const form = useForm({
        ocurrido_at,
        motivo: registro?.motivo ?? 'Certificado de Defunción',
        sitio: registro?.sitio ?? '',
        testigo: registro?.testigo ?? '',
        comentarios: registro?.comentarios ?? '',
    });

    const volver = () => {
        if (form.isDirty && !window.confirm(t('defuncion.confirmar_volver'))) {
            return;
        }

        onVolver();
    };

    const firmada = registro?.autorizacion?.estado === 'firmado';
    const pendiente = registro?.autorizacion?.estado === 'pendiente';

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="min-w-0">
                    <h2 className="flex items-center gap-2 text-lg font-semibold tracking-tight">
                        <span className="flex size-8 items-center justify-center rounded-lg bg-slate-500/15 text-slate-700 dark:text-slate-200">
                            <HeartOff className="size-4" />
                        </span>
                        {t('defuncion.title')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t('defuncion.subtitle', { nombre: paciente.nombre })}
                        {registro?.veterinario ? ` · ${t('defuncion.veterinario')}: ${registro.veterinario}` : ''}
                    </p>
                </div>
                <button
                    type="button"
                    onClick={volver}
                    className="inline-flex cursor-pointer items-center gap-1 px-1 text-sm font-medium text-primary underline-offset-4 hover:underline"
                >
                    <ArrowLeft className="size-3.5" />
                    {t('defuncion.volver')}
                </button>
            </div>

            <form
                className="flex flex-col gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    const options = {
                        preserveScroll: true,
                        onSuccess: (page) => {
                            const flash = page.props.flash as { defuncion_editar?: string | null } | null;
                            const url = flash?.defuncion_editar;

                            if (typeof url === 'string' && url !== '') {
                                onSaved?.(url);
                            }
                        },
                    };

                    if (method === 'post') {
                        form.post(guardar_url, options);

                        return;
                    }

                    form.put(guardar_url, options);
                }}
            >
                {form.hasErrors ? (
                    <p className="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                        {Object.values(form.errors)
                            .flatMap((value) => (Array.isArray(value) ? value : [value]))
                            .filter(Boolean)
                            .join(' ')}
                    </p>
                ) : null}

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-1.5">
                        <Label htmlFor="ocurrido_at">{t('defuncion.fecha')}</Label>
                        <Input
                            id="ocurrido_at"
                            type="datetime-local"
                            disabled={!puede_editar}
                            className={campoClass}
                            value={form.data.ocurrido_at}
                            onChange={(event) => form.setData('ocurrido_at', event.target.value)}
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="motivo">{t('defuncion.motivo')}</Label>
                        <Input
                            id="motivo"
                            disabled={!puede_editar}
                            className={campoClass}
                            value={form.data.motivo}
                            onChange={(event) => form.setData('motivo', event.target.value)}
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="sitio">{t('defuncion.sitio')}</Label>
                        <Input
                            id="sitio"
                            disabled={!puede_editar}
                            className={campoClass}
                            value={form.data.sitio}
                            onChange={(event) => form.setData('sitio', event.target.value)}
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="testigo">{t('defuncion.testigo')}</Label>
                        <Input
                            id="testigo"
                            disabled={!puede_editar}
                            className={campoClass}
                            value={form.data.testigo}
                            onChange={(event) => form.setData('testigo', event.target.value)}
                        />
                    </div>
                </div>

                <div className="flex flex-wrap items-center justify-end gap-3">
                    <p className="mr-auto text-xs text-rose-600 dark:text-rose-300">{t('defuncion.recordatorio')}</p>
                    <Button type="button" variant="outline" className="cursor-pointer" onClick={volver}>
                        {t('defuncion.cancelar')}
                    </Button>
                    <Button type="submit" disabled={!puede_editar || form.processing} className="cursor-pointer gap-2">
                        {form.processing ? <Loader2 className="size-4 animate-spin" /> : null}
                        {t('defuncion.guardar')}
                    </Button>
                </div>

                <section className="rounded-xl border border-border/70 bg-card">
                    <header className="border-b border-border/60 px-4 py-2.5 text-sm font-medium">
                        {t('defuncion.comentarios')}
                    </header>
                    <Textarea
                        rows={4}
                        disabled={!puede_editar}
                        className="resize-y rounded-none border-0 bg-transparent shadow-none focus-visible:ring-0"
                        value={form.data.comentarios}
                        onChange={(event) => form.setData('comentarios', event.target.value)}
                    />
                </section>
            </form>

            <section className="rounded-xl border border-border/70 bg-slate-50/80 p-4 dark:bg-slate-950/30">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0 space-y-1">
                        <h3 className="flex items-center gap-2 text-sm font-semibold">
                            <FilePenLine className="size-4 text-slate-600 dark:text-slate-300" />
                            {t('defuncion.autorizacion')}
                        </h3>
                        <p className="text-sm text-muted-foreground">{t('defuncion.autorizacion_hint')}</p>
                        {firmada ? (
                            <p className="text-sm font-medium text-slate-800 dark:text-slate-100">
                                {t('defuncion.autorizacion_firmada')}
                            </p>
                        ) : pendiente ? (
                            <p className="text-sm text-amber-800 dark:text-amber-200">{t('defuncion.autorizacion_pendiente')}</p>
                        ) : !registro?.id ? (
                            <p className="text-sm font-medium text-amber-800 dark:text-amber-200">
                                {t('defuncion.autorizacion_guardar')}
                            </p>
                        ) : null}
                        <a
                            href="/configuracion/documentos-autorizacion"
                            className="inline-flex text-xs font-medium text-primary underline-offset-4 hover:underline"
                        >
                            {t('defuncion.autorizacion_plantillas')}
                        </a>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {firmada && registro?.autorizacion?.pdf_url ? (
                            <a
                                href={registro.autorizacion.pdf_url}
                                className={cn(
                                    'inline-flex h-9 cursor-pointer items-center rounded-md border border-border bg-background px-3 text-sm font-medium',
                                )}
                            >
                                {t('defuncion.autorizacion_pdf')}
                            </a>
                        ) : null}
                        <Button
                            type="button"
                            variant="outline"
                            className="cursor-pointer gap-2"
                            disabled={!autorizar_url || !puede_editar || firmada}
                            onClick={() => setAuthOpen(true)}
                        >
                            <FilePenLine className="size-4" />
                            {t('defuncion.autorizacion_enviar')}
                        </Button>
                    </div>
                </div>
            </section>

            <DocumentoAutorizacionSendDialog
                open={authOpen}
                consultaId={null}
                actionUrl={autorizar_url}
                preferredPlantillaId={plantilla_defuncion_id}
                plantillas={plantillas}
                defaultPhone={telefono}
                defaultEmail={email}
                onOpenChange={setAuthOpen}
            />
        </div>
    );
}

type DefuncionCreateLinks = {
    store_url: string;
    ocurrido_at: string;
};

type DefuncionPayload = Omit<Props, 'onVolver' | 'onSaved'>;

function DefuncionRegistro({
    editUrl,
    onVolver,
    onSaved,
}: {
    editUrl: string;
    onVolver: () => void;
    onSaved?: (url: string) => void;
}) {
    const { t } = useTranslation('pacientes');
    const [payload, setPayload] = useState<DefuncionPayload | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancel = false;
        void fetch(editUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(async (res) => {
                if (!res.ok) {
                    throw new Error(t('defuncion.error_abrir'));
                }

                return (await res.json()) as DefuncionPayload;
            })
            .then((body) => {
                if (!cancel) {
                    setPayload(body);
                }
            })
            .catch((reason: unknown) => {
                if (!cancel) {
                    setError(reason instanceof Error ? reason.message : t('defuncion.error_abrir'));
                }
            });

        return () => {
            cancel = true;
        };
    }, [editUrl, t]);

    if (error) {
        return (
            <div className="flex flex-col items-start gap-3">
                <p className="text-sm text-destructive">{error}</p>
                <Button type="button" variant="outline" className="cursor-pointer" onClick={onVolver}>
                    <ArrowLeft className="size-4" />
                    {t('defuncion.volver')}
                </Button>
            </div>
        );
    }

    if (!payload) {
        return (
            <div className="flex justify-center py-12">
                <Loader2 className="size-5 animate-spin text-muted-foreground" />
            </div>
        );
    }

    return <DefuncionForm {...payload} onVolver={onVolver} onSaved={onSaved} />;
}

export function DefuncionEmbed({
    paciente,
    create,
    editUrl,
    plantillas,
    plantillaDefuncionId,
    telefono,
    email,
    onVolver,
    onSaved,
}: {
    paciente: { id: string; nombre: string; fallecido?: boolean };
    create: DefuncionCreateLinks | null;
    editUrl: string | null;
    plantillas: readonly PlantillaAutorizacionOpcion[];
    plantillaDefuncionId: string | null;
    telefono: string;
    email: string;
    onVolver: () => void;
    onSaved?: (url: string) => void;
}) {
    if (editUrl) {
        return <DefuncionRegistro key={editUrl} editUrl={editUrl} onVolver={onVolver} onSaved={onSaved} />;
    }

    if (!create) {
        return null;
    }

    return (
        <DefuncionForm
            paciente={paciente}
            registro={null}
            ocurrido_at={create.ocurrido_at}
            puede_editar
            guardar_url={create.store_url}
            method="post"
            autorizar_url={null}
            plantillas={[...plantillas]}
            plantilla_defuncion_id={plantillaDefuncionId}
            telefono={telefono}
            email={email}
            onVolver={onVolver}
            onSaved={onSaved}
        />
    );
}

type PageProps = Omit<Props, 'onVolver'> & { volver_url: string };

export default function DefuncionPage({ volver_url, ...props }: PageProps) {
    const { t } = useTranslation('pacientes');

    return (
        <>
            <Head title={t('defuncion.title')} />
            <div className="flex flex-1 flex-col p-4 sm:p-6">
                <DefuncionForm {...props} onVolver={() => router.visit(volver_url)} />
            </div>
        </>
    );
}
