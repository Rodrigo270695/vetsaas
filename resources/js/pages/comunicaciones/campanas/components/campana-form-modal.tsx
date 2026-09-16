import { router, usePage } from '@inertiajs/react';
import { Clock, ImagePlus, Loader2, MessageSquareText, ShieldCheck } from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { FormField, FormModal, FormSection } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

const ROUTE_URL = '/comunicaciones/campanas';

const VARIABLES = [
    { token: '{nombre}', label: 'Nombre' },
    { token: '{nombre_completo}', label: 'Nombre completo' },
    { token: '{mascota}', label: 'Mascota' },
    { token: '{clinica}', label: 'Clínica' },
] as const;

export type CampanaFormValues = {
    id: string;
    nombre: string;
    cuerpo: string;
    tope_diario: number;
    intervalo_minutos: number;
    hora_inicio: string;
    hora_fin: string;
    imagen_url: string | null;
};

function insertToken(
    textarea: HTMLTextAreaElement | null,
    value: string,
    token: string,
    setValue: (next: string) => void,
): void {
    if (!textarea) {
        setValue(`${value}${token}`);

        return;
    }

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const next = value.slice(0, start) + token + value.slice(end);
    setValue(next);

    requestAnimationFrame(() => {
        textarea.focus();
        const pos = start + token.length;
        textarea.setSelectionRange(pos, pos);
    });
}

export function CampanaFormModal({
    open,
    onOpenChange,
    campana = null,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    campana?: CampanaFormValues | null;
}) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const cuerpoRef = useRef<HTMLTextAreaElement>(null);
    const [nombre, setNombre] = useState('');
    const [cuerpo, setCuerpo] = useState('');
    const [tope, setTope] = useState('50');
    const [intervalo, setIntervalo] = useState('12');
    const [horaInicio, setHoraInicio] = useState('09:00');
    const [horaFin, setHoraFin] = useState('23:59');
    const [imagen, setImagen] = useState<File | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [clearImagen, setClearImagen] = useState(false);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        setNombre(campana?.nombre ?? '');
        setCuerpo(campana?.cuerpo ?? '');
        setTope(String(campana?.tope_diario ?? 50));
        setIntervalo(String(campana?.intervalo_minutos ?? 12));
        setHoraInicio(String(campana?.hora_inicio ?? '09:00').slice(0, 5));
        setHoraFin(String(campana?.hora_fin ?? '23:59').slice(0, 5));
        setImagen(null);
        setClearImagen(false);
        setPreviewUrl(null);
    }, [open, campana]);

    useEffect(() => {
        if (!imagen) {
            return;
        }

        const url = URL.createObjectURL(imagen);
        setPreviewUrl(url);

        return () => URL.revokeObjectURL(url);
    }, [imagen]);

    const imagenVisible = previewUrl
        ?? (clearImagen ? null : campana?.imagen_url ?? null);

    const handleSubmit = (event?: FormEvent) => {
        event?.preventDefault();
        setSaving(true);

        const payload: Record<string, unknown> = {
            nombre,
            cuerpo,
            tope_diario: Number(tope),
            intervalo_minutos: Number(intervalo),
            hora_inicio: horaInicio,
            hora_fin: horaFin,
            clear_imagen: clearImagen ? 1 : 0,
        };
        if (imagen) {
            payload.imagen = imagen;
        }

        router.post(
            campana ? `${ROUTE_URL}/${campana.id}` : ROUTE_URL,
            payload,
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
            title={campana ? 'Editar campaña' : 'Nueva campaña'}
            description="Un mensaje. Tocá una variable para insertarla donde está el cursor. El envío sale de a uno, con tope diario."
            size="md"
            onSubmit={handleSubmit}
            footer={
                <div className="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button
                        type="button"
                        variant="outline"
                        className="cursor-pointer"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={saving} className="cursor-pointer gap-2">
                        {saving ? <Loader2 className="size-4 animate-spin" /> : null}
                        {saving ? 'Guardando…' : 'Guardar campaña'}
                    </Button>
                </div>
            }
        >
            <FormSection title="Mensaje" icon={MessageSquareText}>
                <FormField id="campana-nombre" label="Nombre interno" required error={errors.nombre}>
                    <Input
                        id="campana-nombre"
                        value={nombre}
                        onChange={(e) => setNombre(e.target.value)}
                        placeholder="Ej. Desparasitación setiembre"
                    />
                </FormField>

                <FormField
                    id="campana-cuerpo"
                    label="Texto"
                    required
                    error={errors.cuerpo}
                    hint="Clic en una pastilla para insertarla en el mensaje."
                >
                    <div className="mb-2 flex flex-wrap gap-1.5">
                        {VARIABLES.map((item) => (
                            <button
                                key={item.token}
                                type="button"
                                className="cursor-pointer rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-800 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
                                onClick={() =>
                                    insertToken(
                                        cuerpoRef.current,
                                        cuerpo,
                                        item.token,
                                        setCuerpo,
                                    )
                                }
                            >
                                {item.label}
                            </button>
                        ))}
                    </div>
                    <Textarea
                        ref={cuerpoRef}
                        id="campana-cuerpo"
                        value={cuerpo}
                        onChange={(e) => setCuerpo(e.target.value)}
                        rows={6}
                        placeholder="Hola {nombre}, te escribimos de {clinica} por la campaña de {mascota}…"
                    />
                </FormField>
            </FormSection>

            <FormSection title="Imagen (opcional)" icon={ImagePlus} className="mt-4">
                <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-border/60 bg-muted/20 p-3">
                    <Input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="cursor-pointer"
                        onChange={(e) => {
                            setImagen(e.target.files?.[0] ?? null);
                            setClearImagen(false);
                        }}
                    />
                </label>
                <p className="text-xs text-muted-foreground">
                    JPG o PNG, máximo 4 MB. Si hay foto, el texto va como pie.
                </p>
                {imagenVisible ? (
                    <div className="flex items-start gap-3">
                        <img
                            src={imagenVisible}
                            alt=""
                            className="h-24 w-24 rounded-lg object-cover ring-1 ring-border"
                        />
                        {campana?.imagen_url && !imagen ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="cursor-pointer"
                                onClick={() => setClearImagen(true)}
                            >
                                Quitar imagen
                            </Button>
                        ) : null}
                    </div>
                ) : null}
            </FormSection>

            <FormSection title="Ritmo de envío" icon={Clock} className="mt-4">
                <div className="grid gap-3 sm:grid-cols-2">
                    <FormField id="campana-tope" label="Tope por día" required error={errors.tope_diario}>
                        <Input
                            id="campana-tope"
                            type="number"
                            min={50}
                            max={100}
                            value={tope}
                            onChange={(e) => setTope(e.target.value)}
                        />
                    </FormField>
                    <FormField
                        id="campana-intervalo"
                        label="Minutos entre mensajes"
                        required
                        error={errors.intervalo_minutos}
                    >
                        <Input
                            id="campana-intervalo"
                            type="number"
                            min={1}
                            max={30}
                            value={intervalo}
                            onChange={(e) => setIntervalo(e.target.value)}
                        />
                    </FormField>
                    <FormField id="campana-desde" label="Desde" required error={errors.hora_inicio}>
                        <Input
                            id="campana-desde"
                            type="time"
                            value={horaInicio}
                            onChange={(e) => setHoraInicio(e.target.value)}
                        />
                    </FormField>
                    <FormField id="campana-hasta" label="Hasta" required error={errors.hora_fin}>
                        <Input
                            id="campana-hasta"
                            type="time"
                            value={horaFin}
                            onChange={(e) => setHoraFin(e.target.value)}
                        />
                    </FormField>
                </div>
                <p className="flex items-start gap-1.5 rounded-md bg-emerald-500/10 p-2.5 text-xs text-emerald-800 dark:text-emerald-300">
                    <ShieldCheck className="mt-0.5 size-3.5 shrink-0" />
                    Sale 1 mensaje a la vez, dentro del horario (Desde–Hasta), hasta el tope del día.
                    Si lanzás fuera de ese horario, espera. El intervalo puede ser de 1 a 30 minutos.
                </p>
            </FormSection>
        </FormModal>
    );
}
