import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useMemo, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';

import { FormField, FormModal, FormSection } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

import type { InAppAnnouncementRecord } from '../types';

const ROUTE_URL = '/plataforma/configuracion/novedades';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    entry: InAppAnnouncementRecord | null;
};

type FormData = {
    title: string;
    body: string;
    features: [string, string, string, string];
    publish_now: boolean;
};

const emptyForm: FormData = {
    title: '',
    body: '',
    features: ['', '', '', ''],
    publish_now: true,
};

function padFeatures(features: string[] | undefined): [string, string, string, string] {
    const next = [...(features ?? [])].slice(0, 4);
    while (next.length < 4) {
        next.push('');
    }
    return [next[0] ?? '', next[1] ?? '', next[2] ?? '', next[3] ?? ''];
}

export function InAppAnnouncementFormModal({ open, onOpenChange, entry }: Props) {
    const { t } = useTranslation(['platform', 'common']);
    const isEdit = entry !== null;

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<FormData>(emptyForm);

    const canSave = useMemo(
        () => data.title.trim() !== '' && data.body.trim() !== '',
        [data.title, data.body],
    );

    useEffect(() => {
        if (!open) {
            return;
        }

        clearErrors();

        if (entry) {
            reset();
            setData({
                title: entry.title,
                body: entry.body,
                features: padFeatures(entry.features),
                publish_now: entry.is_active,
            });
        } else {
            reset();
            setData(emptyForm);
        }
    }, [open, entry, reset, setData, clearErrors]);

    const setFeature = (index: number, value: string) => {
        const features = [...data.features] as [string, string, string, string];
        features[index] = value;
        setData('features', features);
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!canSave) {
            return;
        }

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
                    ? t('platform:announcements.form.edit_title')
                    : t('platform:announcements.form.create_title')
            }
            description={t('platform:announcements.form.description')}
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
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" disabled={processing || !canSave} className="gap-2">
                        {processing ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : null}
                        {isEdit
                            ? t('common:actions.save')
                            : t('platform:announcements.form.create')}
                    </Button>
                </>
            }
        >
            <FormSection index={0} title="" columns={1} className="gap-4">
                <FormField
                    id="in-app-announcement-title"
                    label={t('platform:announcements.fields.title')}
                    error={errors.title}
                    required
                >
                    <Input
                        id="in-app-announcement-title"
                        value={data.title}
                        onChange={(e) => setData('title', e.target.value)}
                        maxLength={160}
                        autoFocus
                    />
                </FormField>

                <FormField
                    id="in-app-announcement-body"
                    label={t('platform:announcements.fields.body')}
                    error={errors.body}
                    hint={t('platform:announcements.fields.body_hint')}
                    required
                >
                    <Textarea
                        id="in-app-announcement-body"
                        value={data.body}
                        onChange={(e) => setData('body', e.target.value)}
                        rows={4}
                        maxLength={2000}
                        className="min-h-24 resize-y"
                    />
                </FormField>

                <div className="space-y-3">
                    <div className="space-y-1">
                        <Label className="text-sm font-medium">
                            {t('platform:announcements.fields.features')}
                        </Label>
                        <p className="text-xs text-muted-foreground">
                            {t('platform:announcements.fields.features_hint')}
                        </p>
                    </div>
                    {data.features.map((feature, index) => (
                        <FormField
                            key={`feature-${index}`}
                            id={`in-app-announcement-feature-${index}`}
                            label={t('platform:announcements.fields.feature', {
                                n: index + 1,
                            })}
                            error={errors[`features.${index}`]}
                        >
                            <Input
                                id={`in-app-announcement-feature-${index}`}
                                value={feature}
                                onChange={(e) => setFeature(index, e.target.value)}
                                maxLength={200}
                            />
                        </FormField>
                    ))}
                </div>

                <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-border/70 bg-muted/20 px-3.5 py-3">
                    <Checkbox
                        checked={data.publish_now}
                        onCheckedChange={(checked) =>
                            setData('publish_now', checked === true)
                        }
                        className="mt-0.5"
                    />
                    <span className="min-w-0 space-y-0.5">
                        <Label className="cursor-pointer text-sm font-medium">
                            {t('platform:announcements.fields.publish_now')}
                        </Label>
                        <p className="text-xs leading-relaxed text-muted-foreground">
                            {t('platform:announcements.fields.publish_now_hint')}
                        </p>
                    </span>
                </label>
            </FormSection>
        </FormModal>
    );
}
