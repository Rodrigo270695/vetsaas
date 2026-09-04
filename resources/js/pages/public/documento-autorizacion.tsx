import { Head, useForm } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    token: string;
    titulo: string;
    cuerpo: string;
    estado: string;
    expirado: boolean;
    firmado_at: string | null;
    clinic: { nombre: string; logo_url: string | null };
    paciente_nombre: string | null;
    propietario_nombre: string | null;
    firmante_nombre_sugerido: string | null;
    firmante_documento_sugerido: string | null;
    submit_url: string;
};

export default function PublicDocumentoAutorizacion({
    titulo,
    cuerpo,
    estado,
    expirado,
    clinic,
    paciente_nombre,
    firmante_nombre_sugerido,
    firmante_documento_sugerido,
    submit_url,
}: Props) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const drawing = useRef(false);
    const form = useForm({
        firmante_nombre: firmante_nombre_sugerido ?? '',
        firmante_documento: firmante_documento_sugerido ?? '',
        firma: '',
        acepto: false,
    });

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) {
            return;
        }
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }
        const ratio = window.devicePixelRatio || 1;
        const w = canvas.clientWidth;
        const h = 140;
        canvas.width = Math.floor(w * ratio);
        canvas.height = Math.floor(h * ratio);
        ctx.scale(ratio, ratio);
        ctx.strokeStyle = '#111827';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';

        const pos = (e: PointerEvent) => {
            const r = canvas.getBoundingClientRect();
            return { x: e.clientX - r.left, y: e.clientY - r.top };
        };

        const down = (e: PointerEvent) => {
            drawing.current = true;
            const p = pos(e);
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
            canvas.setPointerCapture(e.pointerId);
        };
        const move = (e: PointerEvent) => {
            if (!drawing.current) {
                return;
            }
            const p = pos(e);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
        };
        const up = () => {
            drawing.current = false;
            form.setData('firma', canvas.toDataURL('image/png'));
        };

        canvas.addEventListener('pointerdown', down);
        canvas.addEventListener('pointermove', move);
        canvas.addEventListener('pointerup', up);
        canvas.addEventListener('pointercancel', up);

        return () => {
            canvas.removeEventListener('pointerdown', down);
            canvas.removeEventListener('pointermove', move);
            canvas.removeEventListener('pointerup', up);
            canvas.removeEventListener('pointercancel', up);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const clearFirma = () => {
        const canvas = canvasRef.current;
        const ctx = canvas?.getContext('2d');
        if (canvas && ctx) {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
        form.setData('firma', '');
    };

    const canSign = estado === 'pendiente' && !expirado;

    return (
        <>
            <Head title={titulo} />
            <div className="rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-6">
                <div className="mb-4 flex items-center gap-3">
                    {clinic.logo_url ? (
                        <img src={clinic.logo_url} alt="" className="h-10 w-auto max-w-24 object-contain" />
                    ) : null}
                    <div>
                        <p className="text-sm text-muted-foreground">{clinic.nombre}</p>
                        <h1 className="text-lg font-semibold tracking-tight">{titulo}</h1>
                    </div>
                </div>
                {paciente_nombre ? (
                    <p className="mb-3 text-sm text-muted-foreground">Paciente: {paciente_nombre}</p>
                ) : null}
                <div
                    className="auth-doc-body max-h-[50vh] overflow-y-auto rounded-xl bg-muted/40 p-4 text-sm leading-relaxed"
                    dangerouslySetInnerHTML={{ __html: cuerpo }}
                />

                {!canSign ? (
                    <p className="mt-4 text-sm font-medium">
                        {estado === 'firmado'
                            ? 'Este documento ya fue firmado. Gracias.'
                            : 'Este enlace expiró o ya no está disponible.'}
                    </p>
                ) : (
                    <form
                        className="mt-5 space-y-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(submit_url, { preserveScroll: true });
                        }}
                    >
                        <div className="space-y-1.5">
                            <Label htmlFor="fn">Nombre de quien firma</Label>
                            <Input
                                id="fn"
                                value={form.data.firmante_nombre}
                                onChange={(e) => form.setData('firmante_nombre', e.target.value)}
                                required
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="fd">Documento (opcional)</Label>
                            <Input
                                id="fd"
                                value={form.data.firmante_documento}
                                onChange={(e) => form.setData('firmante_documento', e.target.value)}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Firma (dedo o mouse)</Label>
                            <canvas
                                ref={canvasRef}
                                className="h-[140px] w-full touch-none rounded-xl border border-border bg-white"
                            />
                            <Button type="button" variant="ghost" size="sm" onClick={clearFirma}>
                                Borrar firma
                            </Button>
                        </div>
                        <label className="flex items-start gap-2 text-sm">
                            <Checkbox
                                checked={form.data.acepto}
                                onCheckedChange={(c) => form.setData('acepto', c === true)}
                            />
                            He leído este documento y firmo de forma voluntaria.
                        </label>
                        <Button type="submit" disabled={form.processing || !form.data.firma || !form.data.acepto}>
                            Firmar documento
                        </Button>
                    </form>
                )}
            </div>
        </>
    );
}
