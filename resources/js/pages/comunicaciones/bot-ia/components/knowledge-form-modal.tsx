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
import { useEffect, useMemo, useRef, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal } from '@/components/forms';
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

const SECTION_DESCRIPTIONS: Record<
    KnowledgeSection,
    { icon: React.ElementType; color: string }
> = {
    faq: { icon: HelpCircle, color: 'text-violet-600' },
    horario: { icon: Clock, color: 'text-blue-600' },
    politica: { icon: ShieldAlert, color: 'text-orange-600' },
    servicio: { icon: Scissors, color: 'text-emerald-600' },
    contacto: { icon: MapPin, color: 'text-rose-600' },
    general: { icon: BookOpen, color: 'text-muted-foreground' },
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
        .slice(0, 80);
}

function buildKnowledgeSlug(section: string, title: string): string {
    const base = slugify(title);
    if (base === '') {
        return section;
    }

    return `${section}-${base}`.slice(0, 100);
}

function isFormComplete(data: FormData): boolean {
    return (
        data.section.trim() !== '' &&
        data.title.trim() !== '' &&
        data.slug.trim() !== '' &&
        data.content.trim() !== ''
    );
}

export function KnowledgeFormModal({ open, onOpenChange, entry }: KnowledgeFormModalProps) {
    const { t } = useTranslation(['bot-ia', 'common']);
    const isEdit = entry !== null;
    const slugManuallyEdited = useRef(false);

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<FormData>(emptyForm);

    useEffect(() => {
        if (!open) {
            return;
        }

        if (entry) {
            slugManuallyEdited.current = true;
            reset();
            setData({
                section: entry.section,
                slug: entry.slug,
                title: entry.title,
                content: entry.content,
                is_active: entry.is_active,
            });
        } else {
            reset();
            clearErrors();
            slugManuallyEdited.current = false;
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, entry?.id]);

    const canSubmit = useMemo(() => isFormComplete(data), [data]);

    const updateAutoSlug = (section: string, title: string) => {
        if (isEdit || slugManuallyEdited.current) {
            return;
        }

        setData('slug', buildKnowledgeSlug(section, title));
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!canSubmit || processing) {
            return;
        }

        if (isEdit && entry) {
            put(UPDATE_URL(entry.id), {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        } else {
            post(STORE_URL, {
                preserveScroll: true,
                onSuccess: () => {
                    onOpenChange(false);
                    reset();
                },
            });
        }
    };

    const sectionMeta = SECTION_DESCRIPTIONS[data.section];
    const SectionIcon = sectionMeta.icon;

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('knowledge.form.edit_title') : t('knowledge.form.create_title')}
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
                        {t('knowledge.form.cancel')}
                    </Button>
                    <Button
                        type="submit"
                        disabled={processing || !canSubmit}
                        className="cursor-pointer gap-2"
                    >
                        {processing && <Loader2 className="size-4 animate-spin" />}
                        {t('knowledge.form.save')}
                    </Button>
                </div>
            }
        >
            <div className="flex flex-col gap-4">
                <FormField
                    id="knowledge-section"
                    label={t('knowledge.form.section_label')}
                    error={errors.section}
                    required
                >
                    <Select
                        value={data.section}
                        onValueChange={(value) => {
                            const section = value as KnowledgeSection;
                            setData('section', section);
                            updateAutoSlug(section, data.title);
                        }}
                    >
                        <SelectTrigger id="knowledge-section" className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {SECTIONS.map((section) => {
                                const Icon = SECTION_DESCRIPTIONS[section].icon;
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

                <div className="flex items-start gap-2 rounded-lg border border-border/60 bg-muted/40 px-3 py-2.5">
                    <SectionIcon
                        className={`mt-0.5 size-4 shrink-0 ${sectionMeta.color}`}
                        strokeWidth={2}
                    />
                    <p className="text-xs leading-relaxed text-muted-foreground">
                        {t(`knowledge.section_hints.${data.section}`)}
                    </p>
                </div>

                <FormField
                    id="knowledge-title"
                    label={t('knowledge.form.title_label')}
                    error={errors.title}
                    required
                >
                    <Input
                        id="knowledge-title"
                        value={data.title}
                        onChange={(e) => {
                            const title = e.target.value;
                            setData('title', title);
                            updateAutoSlug(data.section, title);
                        }}
                        placeholder={t('knowledge.form.title_placeholder')}
                    />
                </FormField>

                <FormField
                    id="knowledge-slug"
                    label={t('knowledge.form.slug_label')}
                    hint={!isEdit ? t('knowledge.form.slug_hint') : undefined}
                    error={errors.slug}
                    required
                >
                    <Input
                        id="knowledge-slug"
                        value={data.slug}
                        onChange={(e) => {
                            slugManuallyEdited.current = true;
                            setData('slug', e.target.value);
                        }}
                        placeholder={t('knowledge.form.slug_placeholder')}
                        className="font-mono text-sm"
                    />
                </FormField>

                <FormField
                    id="knowledge-content"
                    label={t('knowledge.form.content_label')}
                    error={errors.content}
                    required
                >
                    <Textarea
                        id="knowledge-content"
                        value={data.content}
                        onChange={(e) => setData('content', e.target.value)}
                        placeholder={t('knowledge.form.content_placeholder')}
                        rows={10}
                        className="resize-y font-mono text-sm leading-relaxed"
                    />
                </FormField>

                <div className="flex items-center gap-3">
                    <Checkbox
                        id="knowledge-active"
                        checked={data.is_active}
                        onCheckedChange={(checked) => setData('is_active', checked === true)}
                    />
                    <Label htmlFor="knowledge-active" className="cursor-pointer text-sm">
                        {t('knowledge.form.is_active_label')}
                    </Label>
                </div>
            </div>
        </FormModal>
    );
}
