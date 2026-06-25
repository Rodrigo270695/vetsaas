import { useForm } from '@inertiajs/react';
import { BookOpen, HelpCircle, Loader2, MessageSquareQuote, ShieldAlert } from 'lucide-react';
import { useEffect, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, FormSection } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import salesbotKnowledge from '@/routes/plataforma/salesbot-knowledge';
import type { KnowledgeEntry } from '../types';

const SECTION_DESCRIPTIONS: Record<string, { icon: React.ElementType; color: string; desc: string }> = {
    modulo:   { icon: BookOpen,           color: 'text-blue-600',   desc: 'Describe qué hace cada módulo (historial, citas, grooming…) y cómo funciona. El bot lo usa para conectar el dolor del prospecto con la feature correcta.' },
    faq:      { icon: HelpCircle,         color: 'text-violet-600', desc: 'Preguntas frecuentes con respuesta lista. Ej: "¿Puedo emitir facturas?", "¿Hay contrato?". El bot responde con esta info exacta.' },
    objecion: { icon: MessageSquareQuote, color: 'text-orange-600', desc: 'Objeciones típicas de venta y cómo rebatirlas. Ej: "Está muy caro", "Ya tengo sistema". El bot usa estos guiones cuando detecta resistencia.' },
    general:  { icon: ShieldAlert,        color: 'text-muted-foreground', desc: 'Información general del producto que no encaja en otra categoría.' },
};

export type KnowledgeFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    entry: KnowledgeEntry | null;
};

type FormData = {
    product: string;
    section: string;
    slug: string;
    title: string;
    content: string;
    is_active: boolean;
};

const emptyForm: FormData = {
    product: 'vetsaas',
    section: 'modulo',
    slug: '',
    title: '',
    content: '',
    is_active: true,
};

export function KnowledgeFormModal({ open, onOpenChange, entry }: KnowledgeFormModalProps) {
    const { t } = useTranslation(['salesbot-knowledge', 'common']);
    const isEdit = entry !== null;

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<FormData>(
            isEdit
                ? {
                      product:    entry.product,
                      section:    entry.section,
                      slug:       entry.slug,
                      title:      entry.title,
                      content:    entry.content,
                      sort_order: entry.sort_order,
                      is_active:  entry.is_active,
                  }
                : emptyForm,
        );

    // Sync form data when the entry changes (opening edit for a different row).
    useEffect(() => {
        if (open) {
            if (entry) {
                reset();
                setData({
                    product:   entry.product,
                    section:   entry.section,
                    slug:      entry.slug,
                    title:     entry.title,
                    content:   entry.content,
                    is_active: entry.is_active,
                });
            } else {
                reset();
                clearErrors();
            }
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, entry?.id]);

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (isEdit) {
            put(salesbotKnowledge.update(entry.id).url, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        } else {
            post(salesbotKnowledge.store().url, {
                preserveScroll: true,
                onSuccess: () => {
                    onOpenChange(false);
                    reset();
                },
            });
        }
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit
                ? t('salesbot-knowledge:form.edit_title')
                : t('salesbot-knowledge:form.create_title')}
            size="lg"
            onSubmit={handleSubmit}
            footer={
                <div className="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                        className="cursor-pointer"
                    >
                        {t('salesbot-knowledge:form.cancel')}
                    </Button>
                    <Button
                        type="submit"
                        disabled={processing}
                        className="cursor-pointer gap-2"
                    >
                        {processing && <Loader2 className="size-4 animate-spin" />}
                        {t('salesbot-knowledge:form.save')}
                    </Button>
                </div>
            }
        >
            <FormSection>
                {/* Sección — sin "Plan" (los precios vienen de Plataforma › Planes) */}
                <FormField
                    label={t('salesbot-knowledge:form.section_label')}
                    error={errors.section}
                    required
                >
                    <Select
                        value={data.section}
                        onValueChange={(v) => setData('section', v)}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="modulo">Módulo</SelectItem>
                            <SelectItem value="faq">FAQ</SelectItem>
                            <SelectItem value="objecion">Objeción</SelectItem>
                            <SelectItem value="general">General</SelectItem>
                        </SelectContent>
                    </Select>
                </FormField>

                {/* Leyenda dinámica según la sección seleccionada */}
                {SECTION_DESCRIPTIONS[data.section] && (() => {
                    const { icon: Icon, color, desc } = SECTION_DESCRIPTIONS[data.section];
                    return (
                        <div className="flex items-start gap-2 rounded-lg border border-border/60 bg-muted/40 px-3 py-2.5">
                            <Icon className={`mt-0.5 size-4 shrink-0 ${color}`} strokeWidth={2} />
                            <p className="text-xs leading-relaxed text-muted-foreground">{desc}</p>
                        </div>
                    );
                })()}

                {/* Slug */}
                <FormField
                    label={t('salesbot-knowledge:form.slug_label')}
                    error={errors.slug}
                    required
                >
                    <Input
                        value={data.slug}
                        onChange={(e) => setData('slug', e.target.value)}
                        placeholder={t('salesbot-knowledge:form.slug_placeholder')}
                        className="font-mono text-sm"
                    />
                </FormField>

                {/* Título */}
                <FormField
                    label={t('salesbot-knowledge:form.title_label')}
                    error={errors.title}
                    required
                >
                    <Input
                        value={data.title}
                        onChange={(e) => setData('title', e.target.value)}
                        placeholder={t('salesbot-knowledge:form.title_placeholder')}
                    />
                </FormField>

                {/* Contenido */}
                <FormField
                    label={t('salesbot-knowledge:form.content_label')}
                    error={errors.content}
                    required
                >
                    <Textarea
                        value={data.content}
                        onChange={(e) => setData('content', e.target.value)}
                        placeholder={t('salesbot-knowledge:form.content_placeholder')}
                        rows={10}
                        className="resize-y font-mono text-sm leading-relaxed"
                    />
                </FormField>

                {/* Activo */}
                <div className="flex items-center gap-3">
                    <Checkbox
                        id="is_active"
                        checked={data.is_active}
                        onCheckedChange={(v) => setData('is_active', Boolean(v))}
                    />
                    <Label htmlFor="is_active" className="cursor-pointer text-sm">
                        {t('salesbot-knowledge:form.is_active_label')}
                    </Label>
                </div>
            </FormSection>
        </FormModal>
    );
}
