import { router } from '@inertiajs/react';
import {
    Bot,
    Clock,
    ImagePlus,
    Info,
    Loader2,
    MessageSquareText,
    ShieldCheck,
} from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { FormField, FormModal, FormSection } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';

export type OutreachSettings = {
    whatsapp_listo: boolean;
    automatico_activo: boolean;
    mensajes_por_corrida: number;
    hora_envio: string;
    enviar_con_imagen: boolean;
    imagen_url: string;
    imagen_personalizada: boolean;
    ultima_corrida_at: string | null;
    /** Cantidad de elegibles que calzan con los filtros ACTUALES de la tabla. */
    elegibles: number;
    /** True si hay algún filtro (búsqueda, estado, ubicación, fechas) activo. */
    filtros_aplicados: boolean;
};

const MIN_MENSAJES = 1;
const MAX_MENSAJES = 20;

function formatFechaHora(iso: string | null): string {
    if (!iso) return 'nunca';
    return new Date(iso).toLocaleString('es-PE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

/**
 * Modal de configuración del envío automático diario de mensajes de
 * contacto (IA + WhatsApp) a prospectos veterinarios nuevos.
 *
 * El tiempo de espera ENTRE cada mensaje no es configurable aquí a
 * propósito: lo decide el backend con un jitter aleatorio para simular
 * un envío humano y evitar bloqueos de WhatsApp.
 */
export function OutreachConfigModal({
    open,
    onOpenChange,
    settings,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    settings: OutreachSettings;
}) {
    const [activo, setActivo] = useState(settings.automatico_activo);
    const [cantidad, setCantidad] = useState(settings.mensajes_por_corrida);
    const [hora, setHora] = useState(settings.hora_envio);
    const [conImagen, setConImagen] = useState(settings.enviar_con_imagen);
    const [imagenFile, setImagenFile] = useState<File | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [quitarImagen, setQuitarImagen] = useState(false);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        setActivo(settings.automatico_activo);
        setCantidad(settings.mensajes_por_corrida);
        setHora(settings.hora_envio);
        setConImagen(settings.enviar_con_imagen);
        setImagenFile(null);
        setQuitarImagen(false);
        setPreviewUrl(null);
    }, [open, settings]);

    useEffect(() => {
        if (!imagenFile) {
            return;
        }

        const url = URL.createObjectURL(imagenFile);
        setPreviewUrl(url);

        return () => URL.revokeObjectURL(url);
    }, [imagenFile]);

    const imagenVisible = previewUrl
        ?? (quitarImagen ? '/images/vetsaas-hero-pets.png' : settings.imagen_url);

    const handleSubmit = (e?: FormEvent) => {
        e?.preventDefault();
        setSaving(true);
        router.post(
            '/plataforma/prospectos-veterinarias/outreach-config',
            {
                automatico_activo: activo,
                mensajes_por_corrida: cantidad,
                hora_envio: hora,
                enviar_con_imagen: conImagen,
                imagen: imagenFile ?? undefined,
                quitar_imagen: quitarImagen,
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title="Configurar envío automático con IA"
            description="Cada día, a la hora que elijas, el sistema le manda un mensaje de presentación a los prospectos nuevos que aún no han sido contactados."
            size="md"
            onSubmit={handleSubmit}
            footer={
                <div className="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={saving} className="gap-2">
                        {saving && <Loader2 className="size-4 animate-spin" />}
                        {saving ? 'Guardando…' : 'Guardar configuración'}
                    </Button>
                </div>
            }
        >
            <FormSection title="Envío automático diario" icon={Bot}>
                <label
                    htmlFor="pv-outreach-activo"
                    className="flex cursor-pointer items-start gap-3 rounded-lg border border-border/60 bg-muted/20 p-3"
                >
                    <Checkbox
                        id="pv-outreach-activo"
                        checked={activo}
                        onCheckedChange={(v) => setActivo(v === true)}
                        className="mt-0.5"
                    />
                    <span className="flex flex-col gap-0.5">
                        <span className="text-sm font-medium text-foreground">
                            Activar envío automático diario
                        </span>
                        <span className="text-xs text-muted-foreground">
                            Si lo desactivas, solo se envían mensajes cuando
                            tú lo hagas manualmente (botón individual o
                            "Enviar ahora").
                        </span>
                    </span>
                </label>

                {!settings.whatsapp_listo && (
                    <p className="flex items-start gap-1.5 rounded-md bg-amber-500/10 p-2.5 text-xs text-amber-700 dark:text-amber-400">
                        <Info className="mt-0.5 size-3.5 shrink-0" />
                        WhatsApp de plataforma (OpenWA) no está conectado
                        ahora mismo. Puedes guardar la configuración, pero
                        ningún mensaje saldrá hasta que se reconecte.
                    </p>
                )}
            </FormSection>

            <FormSection title="Cantidad y horario" icon={Clock} className="mt-4">
                <FormField
                    id="pv-outreach-cantidad"
                    label="Mensajes por corrida"
                    hint={`Recomendado: 5–10 al día para evitar bloqueos de WhatsApp (máx. ${MAX_MENSAJES}).`}
                >
                    <Input
                        id="pv-outreach-cantidad"
                        type="number"
                        min={MIN_MENSAJES}
                        max={MAX_MENSAJES}
                        value={cantidad}
                        onChange={(e) =>
                            setCantidad(
                                Math.min(
                                    MAX_MENSAJES,
                                    Math.max(
                                        MIN_MENSAJES,
                                        Number(e.target.value) || MIN_MENSAJES,
                                    ),
                                ),
                            )
                        }
                    />
                </FormField>

                <FormField
                    id="pv-outreach-hora"
                    label="Hora de la corrida"
                    hint="Hora local de Perú. El sistema revisa cada hora si ya toca enviar."
                >
                    <Input
                        id="pv-outreach-hora"
                        type="time"
                        value={hora}
                        onChange={(e) => setHora(e.target.value)}
                    />
                </FormField>
            </FormSection>

            <FormSection title="Imagen del primer mensaje" icon={ImagePlus} className="mt-4">
                <label
                    htmlFor="pv-outreach-con-imagen"
                    className="flex cursor-pointer items-start gap-3 rounded-lg border border-border/60 bg-muted/20 p-3"
                >
                    <Checkbox
                        id="pv-outreach-con-imagen"
                        checked={conImagen}
                        onCheckedChange={(v) => setConImagen(v === true)}
                        className="mt-0.5"
                    />
                    <span className="flex flex-col gap-0.5">
                        <span className="text-sm font-medium text-foreground">
                            Enviar con foto (recomendado)
                        </span>
                        <span className="text-xs text-muted-foreground">
                            El texto de la IA va como pie de la imagen, en
                            una sola burbuja de WhatsApp. Si la foto falla,
                            se manda solo el texto.
                        </span>
                    </span>
                </label>

                {conImagen && (
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <div className="overflow-hidden rounded-lg border border-border/60 bg-muted/20 sm:w-44">
                            <img
                                src={imagenVisible}
                                alt="Imagen que acompaña el primer mensaje"
                                className="aspect-4/3 h-full w-full object-cover"
                            />
                        </div>
                        <div className="flex min-w-0 flex-1 flex-col gap-2">
                            <FormField
                                id="pv-outreach-imagen"
                                label="Cambiar imagen"
                                hint="JPG o PNG, máximo 4 MB. Si no subes nada, se usa la foto de VetSaaS (perro y gato en consulta)."
                            >
                                <Input
                                    id="pv-outreach-imagen"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    onChange={(e) => {
                                        const file = e.target.files?.[0] ?? null;
                                        setImagenFile(file);
                                        setQuitarImagen(false);
                                    }}
                                />
                            </FormField>
                            {settings.imagen_personalizada && !quitarImagen && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="w-fit px-0 text-xs text-muted-foreground"
                                    onClick={() => {
                                        setImagenFile(null);
                                        setQuitarImagen(true);
                                    }}
                                >
                                    Volver a la imagen por defecto
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </FormSection>

            <div className="mt-4 flex items-start gap-2 rounded-lg border border-primary/20 bg-primary/5 p-3">
                <ShieldCheck className="mt-0.5 size-4 shrink-0 text-primary" />
                <div className="flex flex-col gap-0.5">
                    <p className="text-xs font-medium text-foreground">
                        El tiempo entre cada mensaje lo controla el sistema
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Cada mensaje se espacía automáticamente (~40–60s,
                        variable) para simular un envío humano y proteger el
                        número de WhatsApp. No es configurable a propósito.
                    </p>
                </div>
            </div>

            <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                <span className="flex items-center gap-1">
                    <MessageSquareText className="size-3.5" />
                    {settings.elegibles} prospecto(s) listos para recibir mensaje
                    {settings.filtros_aplicados && ' (con los filtros que tienes activos)'}
                </span>
                <span>Última corrida: {formatFechaHora(settings.ultima_corrida_at)}</span>
            </div>
        </FormModal>
    );
}
