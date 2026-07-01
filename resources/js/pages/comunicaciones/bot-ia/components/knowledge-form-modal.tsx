import { useForm } from '@inertiajs/react';
import {
    BookOpen,
    Clock,
    HelpCircle,
    Loader2,
    MapPin,
    Scissors,
    ShieldAlert,
} from 'lucide-react';
import { useEffect, useRef, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, FormSection } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { KnowledgeEntry, KnowledgeSection } from '../types';

const STORE_URL = '/comunicaciones/bot-ia/conocimiento';
const UPDATE_URL = (id: number) => `/comunicaciones/bot-ia/conocimiento/${id}`;

const SECTIONS: KnowledgeSection[] = [
    'faq',
    'horario',
    'politica',
    'servicio',
    'contacto',
    'general',
];

const SECTION_ICONS: Record<KnowledgeSection, React.ElementType> = {
    faq: HelpCircle,
    horario: Clock,
    politica: ShieldAlert,
    servicio: Scissors,
    contacto: MapPin,
    general: BookOpen,
};

export type KnowledgeFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    entry: KnowledgeEntry | null;
};

type FormData = {
    section: KnowledgeSection;
    slug: string;
    title: string;
    content: string;
    is_active: boolean;
};

const emptyForm: FormData = {
    section: 'faq',
    slug: '',
    title: '',
    content: '',
    is_active: true,
};

function slugify(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 100);
}

function buildSlug(section: string, title: string): string {
    const base = slugify(title);
    return base ? `${section}-${base}` : '';
}

export function KnowledgeFormModal({ open, onOpenChange, entry }: KnowledgeFormModalProps) {
    const { t } = useTranslation(['bot-ia', 'common']);
    const isEdit = entry !== null;
    const slugTouched = useRef(false);

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm<FormData>(
        emptyForm,
    );

    useEffect(() => {
        if (!open) return;

        slugTouched.current = false;
        clearErrors();

        if (entry) {
            reset({
                section: entry.section,
                slug: entry.slug,
                title: entry.title,
                content: entry.content,
                is_active: entry.is_active,
            });
        } else {
            reset(emptyForm);
        }
    }, [open, entry, reset, clearErrors]);

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();

        if (isEdit && entry) {
            put(UPDATE_URL(entry.id), {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        } else {
            post(STORE_URL, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        }
    };

    const SectionIcon = SECTION_ICONS[data.section];

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('knowledge.form.edit_title') : t('knowledge.form.create_title')}
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        {t('knowledge.form.cancel')}
                    </Button>
                    <Button type="submit" form="bot-ia-knowledge-form" disabled={processing} className="gap-2">
                        {processing && <Loader2 className="size-4 animate-spin" />}
                        {t('knowledge.form.save')}
                    </Button>
                </>
            }
        >
            <form id="bot-ia-knowledge-form" onSubmit={onSubmit} className="space-y-4">
                <FormSection>
                    <FormField
                        label={t('knowledge.form.section_label')}
                        error={errors.section}
                        htmlFor="knowledge-section"
                    >
                        <Select
                            value={data.section}
                            onValueChange={(value) => {
                                const section = value as KnowledgeSection;
                                setData('section', section);
                                if (!slugTouched.current) {
                                    setData('slug', buildSlug(section, data.title));
                                }
                            }}
                        >
                            <SelectTrigger id="knowledge-section">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {SECTIONS.map((section) => {
                                    const Icon = SECTION_ICONS[section];
                                    return (
                                        <SelectItem key={section} value={section}>
                                            <span className="flex items-center gap-2">
                                                <Icon className="size-3.5" />
                                                {t(`knowledge.sections.${section}`)}
                                            </span>
                                        </SelectItem>
                                    );
                                })}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField
                        label={t('knowledge.form.title_label')}
                        error={errors.title}
                        htmlFor="knowledge-title"
                    >
                        <Input
                            id="knowledge-title"
                            value={data.title}
                            onChange={(e) => {
                                const title = e.target.value;
                                setData((prev) => ({
                                    ...prev,
                                    title,
                                    slug: slugTouched.current ? prev.slug : buildSlug(prev.section, title),
                                }));
                            }}
                            placeholder={t('knowledge.form.title_placeholder')}
                        />
                    </FormField>

                    <FormField
                        label={t('knowledge.form.slug_label')}
                        hint={!isEdit ? t('knowledge.form.slug_hint') : undefined}
                        error={errors.slug}
                        htmlFor="knowledge-slug"
                    >
                        <Input
                            id="knowledge-slug"
                            value={data.slug}
                            onChange={(e) => {
                                slugTouched.current = true;
                                setData('slug', e.target.value);
                            }}
                            placeholder={t('knowledge.form.slug_placeholder')}
                        />
                    </FormField>

                    <FormField
                        label={t('knowledge.form.content_label')}
                        error={errors.content}
                        htmlFor="knowledge-content"
                    >
                        <Textarea
                            id="knowledge-content"
                            value={data.content}
                            onChange={(e) => setData('content', e.target.value)}
                            placeholder={t('knowledge.form.content_placeholder')}
                            rows={8}
                            className="min-h-32 resize-y"
                        />
                    </FormField>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="knowledge-active"
                            checked={data.is_active}
                            onCheckedChange={(checked) => setData('is_active', checked === true)}
                        />
                        <Label htmlFor="knowledge-active" className="text-sm font-normal">
                            {t('knowledge.form.is_active_label')}
                        </Label>
                    </div>

                    <p className="flex items-start gap-2 rounded-md border bg-muted/40 p-3 text-xs text-muted-foreground">
                        <SectionIcon className="mt-0.5 size-3.5 shrink-0" />
                        {t(`knowledge.section_hints.${data.section}`)}
                    </p>
                </FormSection>
            </form>
        </FormModal>
    );
}
