import { Head, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, PenLine } from 'lucide-react';
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
    firmado_at,
    clinic,
    paciente_nombre,
    firmante_nombre_sugerido,
    firmante_documento_sugerido,
    submit_url,
}: Props) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const drawing = useRef(false);
    const flash = usePage().props.flash as { success?: string } | undefined;
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

    const cuerpoTieneLogo = cuerpo.includes('auth-doc-logo');
    const canSign = estado === 'pendiente' && !expirado;
    const firmado = estado === 'firmado' || Boolean(flash?.success);

    return (
        <>
            <Head title={titulo} />
            {firmado ? (
                <div className="mb-4 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                    <CheckCircle2 className="size-4 shrink-0" />
                    Documento firmado. Gracias.
                </div>
            ) : null}

            <article className="overflow-hidden rounded-sm bg-white text-[#111827] shadow-[0_18px_50px_-20px_rgba(15,23,42,0.45)] ring-1 ring-black/10">
                <div className="h-1.5 bg-primary" />
                <div className="px-5 py-6 sm:px-9 sm:py-8">
                    <header className="mb-6 border-b border-stone-200 pb-4 text-center">
                        {clinic.logo_url && !cuerpoTieneLogo ? (
                            <img
                                src={clinic.logo_url}
                                alt=""
                                className="mx-auto mb-3 h-12 w-auto max-w-36 object-contain"
                            />
                        ) : null}
                        <p className="text-[11px] font-semibold tracking-[0.18em] text-primary uppercase">
                            {clinic.nombre}
                        </p>
                        {paciente_nombre ? (
                            <p className="mt-1 text-xs text-stone-500">Paciente: {paciente_nombre}</p>
                        ) : null}
                    </header>

                    <div
                        className="auth-doc-body leading-relaxed text-stone-800"
                        dangerouslySetInnerHTML={{ __html: cuerpo }}
                    />

                    {!canSign ? (
                        expirado && !firmado ? (
                            <p className="mt-8 text-center text-sm font-medium text-amber-800">
                                Este enlace expiró o ya no está disponible.
                            </p>
                        ) : firmado_at ? (
                            <p className="mt-8 text-center text-xs text-stone-500">
                                Firmado el {new Date(firmado_at).toLocaleString('es-PE')}
                            </p>
                        ) : null
                    ) : (
                        <form
                            className="mt-8 space-y-4 border-t border-stone-200 pt-5"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post(submit_url, { preserveScroll: true });
                            }}
                        >
                            <p className="flex items-center gap-2 text-xs font-semibold tracking-wide text-stone-500 uppercase">
                                <PenLine className="size-3.5" />
                                Firma del titular
                            </p>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label htmlFor="fn" className="text-stone-700">
                                        Nombre de quien firma
                                    </Label>
                                    <Input
                                        id="fn"
                                        value={form.data.firmante_nombre}
                                        onChange={(e) => form.setData('firmante_nombre', e.target.value)}
                                        required
                                        className="bg-white text-stone-900"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="fd" className="text-stone-700">
                                        Documento (opcional)
                                    </Label>
                                    <Input
                                        id="fd"
                                        value={form.data.firmante_documento}
                                        onChange={(e) => form.setData('firmante_documento', e.target.value)}
                                        className="bg-white text-stone-900"
                                    />
                                </div>
                            </div>
                            <div className="space-y-1.5">
                                <Label className="text-stone-700">Firma (dedo o mouse)</Label>
                                <canvas
                                    ref={canvasRef}
                                    className="h-[140px] w-full touch-none rounded-md border border-stone-300 bg-white"
                                />
                                <Button type="button" variant="ghost" size="sm" onClick={clearFirma}>
                                    Borrar firma
                                </Button>
                            </div>
                            <label className="flex items-start gap-2 text-sm text-stone-700">
                                <Checkbox
                                    checked={form.data.acepto}
                                    onCheckedChange={(c) => form.setData('acepto', c === true)}
                                />
                                He leído este documento y firmo de forma voluntaria.
                            </label>
                            <Button
                                type="submit"
                                disabled={form.processing || !form.data.firma || !form.data.acepto}
                                className="w-full sm:w-auto"
                            >
                                Firmar documento
                            </Button>
                        </form>
                    )}
                </div>
            </article>
        </>
    );
}
