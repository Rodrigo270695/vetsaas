import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';

import { FormField, FormModal, FormSection } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

import type { AnnouncementEntry } from '../types';

const ROUTE_URL = '/plataforma/bot-ia-announcements';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    entry: AnnouncementEntry | null;
};

type FormData = {
    title: string;
    bullet_1: string;
    bullet_2: string;
    bullet_3: string;
    guide_title: string;
    guide_body: string;
    guide_tip_1: string;
    guide_tip_2: string;
    guide_tip_3: string;
    is_active: boolean;
    published_at: string;
    expires_at: string;
};

const emptyForm: FormData = {
    title: '',
    bullet_1: '',
    bullet_2: '',
    bullet_3: '',
    guide_title: '',
    guide_body: '',
    guide_tip_1: '',
    guide_tip_2: '',
    guide_tip_3: '',
    is_active: true,
    published_at: '',
    expires_at: '',
};

function toDatetimeLocal(value: string | null): string {
    if (!value) {
        return '';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function AnnouncementFormModal({ open, onOpenChange, entry }: Props) {
    const { t } = useTranslation(['bot-ia-announcements', 'common']);
    const isEdit = entry !== null;

    const { data, setData, post, put, processing, errors, reset, clearErrors, transform } =
        useForm<FormData>(emptyForm);

    transform((formData) => ({
        ...formData,
        bullet_2: formData.bullet_2 || null,
        bullet_3: formData.bullet_3 || null,
        guide_title: formData.guide_title || null,
        guide_body: formData.guide_body || null,
        guide_tip_1: formData.guide_tip_1 || null,
        guide_tip_2: formData.guide_tip_2 || null,
        guide_tip_3: formData.guide_tip_3 || null,
        published_at: formData.published_at || null,
        expires_at: formData.expires_at || null,
    }));

    useEffect(() => {
        if (!open) {
            return;
        }

        clearErrors();

        if (entry) {
            reset();
            setData({
                title: entry.title,
                bullet_1: entry.bullet_1,
                bullet_2: entry.bullet_2 ?? '',
                bullet_3: entry.bullet_3 ?? '',
                guide_title: entry.guide_title ?? '',
                guide_body: entry.guide_body ?? '',
                guide_tip_1: entry.guide_tip_1 ?? '',
                guide_tip_2: entry.guide_tip_2 ?? '',
                guide_tip_3: entry.guide_tip_3 ?? '',
                is_active: entry.is_active,
                published_at: toDatetimeLocal(entry.published_at),
                expires_at: toDatetimeLocal(entry.expires_at),
            });
        } else {
            reset();
            setData(emptyForm);
        }
    }, [open, entry, reset, setData, clearErrors]);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (isEdit && entry) {
            put(`${ROUTE_URL}/${entry.id}`, options);
            return;
        }

        post(ROUTE_URL, {
            ...options,
            onSuccess: () => {
                onOpenChange(false);
                reset();
            },
        });
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={
                isEdit
                    ? t('bot-ia-announcements:form.edit_title')
                    : t('bot-ia-announcements:form.create_title')
            }
            size="lg"
            onSubmit={submit}
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        {t('bot-ia-announcements:form.cancel')}
                    </Button>
                    <Button type="submit" disabled={processing} className="gap-2">
                        {processing ? <Loader2 className="size-4 animate-spin" /> : null}
                        {t('bot-ia-announcements:form.save')}
                    </Button>
                </>
            }
        >
            <FormSection>
                    <FormField label={t('bot-ia-announcements:form.title_label')} error={errors.title}>
                        <Input
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            placeholder={t('bot-ia-announcements:form.title_placeholder')}
                        />
                    </FormField>

                    <FormField label={t('bot-ia-announcements:form.bullet_1_label')} error={errors.bullet_1}>
                        <Textarea
                            value={data.bullet_1}
                            onChange={(e) => setData('bullet_1', e.target.value)}
                            placeholder={t('bot-ia-announcements:form.bullet_placeholder')}
                            rows={2}
                        />
                    </FormField>

                    <FormField label={t('bot-ia-announcements:form.bullet_2_label')} error={errors.bullet_2}>
                        <Textarea
                            value={data.bullet_2}
                            onChange={(e) => setData('bullet_2', e.target.value)}
                            placeholder={t('bot-ia-announcements:form.bullet_placeholder')}
                            rows={2}
                        />
                    </FormField>

                    <FormField label={t('bot-ia-announcements:form.bullet_3_label')} error={errors.bullet_3}>
                        <Textarea
                            value={data.bullet_3}
                            onChange={(e) => setData('bullet_3', e.target.value)}
                            placeholder={t('bot-ia-announcements:form.bullet_placeholder')}
                            rows={2}
                        />
                    </FormField>
                </FormSection>

                <FormSection title={t('bot-ia-announcements:form.guide_title_label')}>
                    <FormField label={t('bot-ia-announcements:form.guide_title_label')} error={errors.guide_title}>
                        <Input
                            value={data.guide_title}
                            onChange={(e) => setData('guide_title', e.target.value)}
                            placeholder={t('bot-ia-announcements:form.guide_title_placeholder')}
                        />
                    </FormField>

                    <FormField label={t('bot-ia-announcements:form.guide_body_label')} error={errors.guide_body}>
                        <Textarea
                            value={data.guide_body}
                            onChange={(e) => setData('guide_body', e.target.value)}
                            placeholder={t('bot-ia-announcements:form.guide_body_placeholder')}
                            rows={3}
                        />
                    </FormField>

                    <FormField label={t('bot-ia-announcements:form.guide_tip_1_label')} error={errors.guide_tip_1}>
                        <Input
                            value={data.guide_tip_1}
                            onChange={(e) => setData('guide_tip_1', e.target.value)}
                            placeholder={t('bot-ia-announcements:form.guide_tip_placeholder')}
                        />
                    </FormField>

                    <FormField label={t('bot-ia-announcements:form.guide_tip_2_label')} error={errors.guide_tip_2}>
                        <Input
                            value={data.guide_tip_2}
                            onChange={(e) => setData('guide_tip_2', e.target.value)}
                            placeholder={t('bot-ia-announcements:form.guide_tip_placeholder')}
                        />
                    </FormField>

                    <FormField label={t('bot-ia-announcements:form.guide_tip_3_label')} error={errors.guide_tip_3}>
                        <Input
                            value={data.guide_tip_3}
                            onChange={(e) => setData('guide_tip_3', e.target.value)}
                            placeholder={t('bot-ia-announcements:form.guide_tip_placeholder')}
                        />
                    </FormField>
                </FormSection>

                <FormSection>
                    <div className="flex items-start gap-3 rounded-lg border bg-muted/30 p-3">
                        <Checkbox
                            id="announcement-is-active"
                            checked={data.is_active}
                            onCheckedChange={(checked) => setData('is_active', checked === true)}
                        />
                        <div className="space-y-1">
                            <Label htmlFor="announcement-is-active" className="cursor-pointer font-medium">
                                {t('bot-ia-announcements:form.is_active_label')}
                            </Label>
                            <p className="text-xs text-muted-foreground">
                                {t('bot-ia-announcements:form.is_active_hint')}
                            </p>
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label={t('bot-ia-announcements:form.published_at_label')}
                            error={errors.published_at}
                        >
                            <Input
                                type="datetime-local"
                                value={data.published_at}
                                onChange={(e) => setData('published_at', e.target.value)}
                            />
                        </FormField>

                        <FormField label={t('bot-ia-announcements:form.expires_at_label')} error={errors.expires_at}>
                            <Input
                                type="datetime-local"
                                value={data.expires_at}
                                onChange={(e) => setData('expires_at', e.target.value)}
                            />
                        </FormField>
                    </div>
                </FormSection>
        </FormModal>
    );
}
