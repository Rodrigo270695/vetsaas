import { useForm, usePage } from '@inertiajs/react';
import { Quote, Star } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

export type ProductReviewPrompt = {
    clinic_name: string;
    role_label: string;
    author_name: string;
    role_line: string;
};

const PLACEHOLDER =
    'Desde que unificamos la agenda y el historial clínico, el equipo atiende con más orden y menos retrabajo. WhatsApp nos ayuda a confirmar citas sin saturar la recepción.';

function StarPicker({
    value,
    onChange,
    disabled,
}: {
    value: number;
    onChange: (n: number) => void;
    disabled?: boolean;
}) {
    const [hover, setHover] = useState(0);
    const shown = hover || value;

    return (
        <div
            className="flex items-center gap-1"
            onMouseLeave={() => setHover(0)}
            role="radiogroup"
            aria-label="Calificación"
        >
            {[1, 2, 3, 4, 5].map((n) => (
                <button
                    key={n}
                    type="button"
                    role="radio"
                    aria-checked={value === n}
                    disabled={disabled}
                    className="rounded-md p-0.5 transition hover:scale-110 disabled:opacity-50"
                    onMouseEnter={() => setHover(n)}
                    onClick={() => onChange(n)}
                >
                    <Star
                        className={cn(
                            'size-8 stroke-[1.5]',
                            shown >= n
                                ? 'fill-amber-400 text-amber-500'
                                : 'fill-transparent text-muted-foreground/40',
                        )}
                    />
                </button>
            ))}
        </div>
    );
}

export function TenantProductReviewModal() {
    const page = usePage<{
        product_review_prompt?: ProductReviewPrompt | null;
        clinic_location_gate?: {
            needs_sede?: boolean;
            needs_gps?: boolean;
        } | null;
        tenant_impersonation?: unknown;
        tenant?: { is_demo?: boolean } | null;
    }>();

    const prompt = page.props.product_review_prompt ?? null;
    const gate = page.props.clinic_location_gate;
    const blockedByGate = Boolean(gate?.needs_sede || gate?.needs_gps);
    const impersonating = Boolean(page.props.tenant_impersonation);
    const isDemo = Boolean(page.props.tenant?.is_demo);

    const canShow = Boolean(prompt) && !blockedByGate && !impersonating && !isDemo;

    const [open, setOpen] = useState(false);

    const form = useForm({
        rating: 5,
        comment: '',
    });

    useEffect(() => {
        if (!canShow) {
            setOpen(false);
            return;
        }
        const t = window.setTimeout(() => setOpen(true), 600);
        return () => window.clearTimeout(t);
    }, [canShow, prompt?.role_line]);

    const previewLine = useMemo(() => prompt?.role_line ?? '', [prompt]);

    const closeWithoutSaving = () => {
        if (form.processing) {
            return;
        }
        setOpen(false);
        form.post('/tenant/product-review/dismiss', {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/tenant/product-review', {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                toast.success('Gracias. Tu reseña aparecerá en Orvae.');
            },
        });
    };

    if (!prompt) {
        return null;
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    closeWithoutSaving();
                }
            }}
        >
            <DialogContent className="max-w-lg gap-0 overflow-hidden p-0 sm:max-w-lg">
                <div className="border-b border-border/70 bg-linear-to-br from-primary/8 via-background to-background px-6 pb-5 pt-6">
                    <DialogHeader className="space-y-2 text-left">
                        <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-primary">
                            Valoración VetSaaS
                        </p>
                        <DialogTitle className="font-display text-xl leading-snug sm:text-2xl">
                            ¿Cómo está siendo VetSaaS en {prompt.clinic_name}?
                        </DialogTitle>
                        <DialogDescription className="text-sm leading-relaxed">
                            Puedes cerrar ahora; te lo volveremos a pedir en un par de semanas hasta que publiques.
                            La reseña se verá en orvae.pe como{' '}
                            <span className="font-medium text-foreground">{previewLine}</span>
                            {prompt.author_name ? (
                                <>
                                    {' '}
                                    — {prompt.author_name}
                                </>
                            ) : null}
                            .
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={submit} className="space-y-5 px-6 py-5">
                    <div className="space-y-2">
                        <Label>Estrellas</Label>
                        <StarPicker
                            value={form.data.rating}
                            disabled={form.processing}
                            onChange={(n) => form.setData('rating', n)}
                        />
                        {form.errors.rating ? (
                            <p className="text-sm text-destructive">{form.errors.rating}</p>
                        ) : null}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="product-review-comment">Comentario profesional</Label>
                        <div className="relative">
                            <Quote className="pointer-events-none absolute left-3 top-3 size-4 text-muted-foreground/50" />
                            <Textarea
                                id="product-review-comment"
                                value={form.data.comment}
                                disabled={form.processing}
                                placeholder={PLACEHOLDER}
                                className="min-h-28 pl-9"
                                maxLength={600}
                                onChange={(e) => form.setData('comment', e.target.value)}
                            />
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Mínimo 40 caracteres. Evita datos de pacientes; habla de la
                            operación de la clínica.
                        </p>
                        {form.errors.comment ? (
                            <p className="text-sm text-destructive">{form.errors.comment}</p>
                        ) : null}
                    </div>

                    <DialogFooter className="flex-col-reverse gap-2 sm:flex-row sm:justify-between">
                        <Button
                            type="button"
                            variant="ghost"
                            disabled={form.processing}
                            onClick={closeWithoutSaving}
                        >
                            Ahora no
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Publicar reseña
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
