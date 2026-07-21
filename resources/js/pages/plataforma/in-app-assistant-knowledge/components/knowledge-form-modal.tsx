import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
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
import type {
    KnowledgeAction,
    KnowledgeEntry,
    KnowledgeScope,
    KnowledgeSection,
    PermissionMode,
} from '../types';

type FormData = {
    slug: string;
    scope: KnowledgeScope;
    section: KnowledgeSection;
    title: string;
    content: string;
    keywords: string;
    url_patterns: string;
    component_patterns: string;
    required_permissions: string;
    permission_mode: PermissionMode;
    allowed_roles: string;
    actions: string;
    priority: number;
    sort_order: number;
    is_active: boolean;
};

const emptyForm: FormData = {
    slug: '',
    scope: 'both',
    section: 'module',
    title: '',
    content: '',
    keywords: '',
    url_patterns: '',
    component_patterns: '',
    required_permissions: '',
    permission_mode: 'any',
    allowed_roles: '',
    actions: '[]',
    priority: 0,
    sort_order: 0,
    is_active: true,
};

function slugify(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 160);
}

function commaList(value: string): string[] {
    return value
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean);
}

function lineList(value: string): string[] {
    return value
        .split(/\r?\n/)
        .map((item) => item.trim())
        .filter(Boolean);
}

function formFromEntry(entry: KnowledgeEntry): FormData {
    return {
        slug: entry.slug,
        scope: entry.scope,
        section: entry.section,
        title: entry.title,
        content: entry.content,
        keywords: (entry.keywords ?? []).join(', '),
        url_patterns: (entry.url_patterns ?? []).join('\n'),
        component_patterns: (entry.component_patterns ?? []).join('\n'),
        required_permissions: (entry.required_permissions ?? []).join(', '),
        permission_mode: entry.permission_mode,
        allowed_roles: (entry.allowed_roles ?? []).join(', '),
        actions: JSON.stringify(entry.actions ?? [], null, 2),
        priority: entry.priority,
        sort_order: entry.sort_order,
        is_active: entry.is_active,
    };
}

export function KnowledgeFormModal({
    open,
    onOpenChange,
    entry,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    entry: KnowledgeEntry | null;
}) {
    const { t } = useTranslation('in-app-assistant-knowledge');
    const isEdit = entry !== null;
    const slugManuallyEdited = useRef(false);
    const [actionsError, setActionsError] = useState<string | null>(null);
    const form = useForm<FormData>(entry ? formFromEntry(entry) : emptyForm);
    const actionsServerError = Object.entries(form.errors).find(
        ([field]) => field === 'actions' || field.startsWith('actions.'),
    )?.[1];

    useEffect(() => {
        if (!open) {
            return;
        }

        form.clearErrors();
        slugManuallyEdited.current = Boolean(entry);
        form.setData(entry ? formFromEntry(entry) : emptyForm);
        // The form instance is intentionally synchronized only when opening another record.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, entry?.id]);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        let actions: KnowledgeAction[];

        try {
            const parsed: unknown = JSON.parse(form.data.actions || '[]');

            if (!Array.isArray(parsed)) {
                throw new Error(t('form.actions_array_error'));
            }

            actions = parsed as KnowledgeAction[];
        } catch (error) {
            setActionsError(
                error instanceof Error
                    ? error.message
                    : t('form.actions_json_error'),
            );

            return;
        }

        setActionsError(null);
        form.transform((data) => ({
            ...data,
            keywords: commaList(data.keywords),
            url_patterns: lineList(data.url_patterns),
            component_patterns: lineList(data.component_patterns),
            required_permissions: commaList(data.required_permissions),
            allowed_roles: commaList(data.allowed_roles),
            actions,
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setActionsError(null);
                onOpenChange(false);
            },
        };

        if (entry) {
            form.put(
                `/plataforma/in-app-assistant-knowledge/${entry.id}`,
                options,
            );
        } else {
            form.post('/plataforma/in-app-assistant-knowledge', options);
        }
    };

    return (
        <FormModal
            open={open}
            onOpenChange={(nextOpen) => {
                if (!nextOpen) {
                    setActionsError(null);
                }

                onOpenChange(nextOpen);
            }}
            title={t(isEdit ? 'form.edit_title' : 'form.create_title')}
            size="xl"
            onSubmit={submit}
            footer={
                <div className="flex w-full justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => {
                            setActionsError(null);
                            onOpenChange(false);
                        }}
                        disabled={form.processing}
                    >
                        {t('form.cancel')}
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && (
                            <Loader2 className="size-4 animate-spin" />
                        )}
                        {t('form.save')}
                    </Button>
                </div>
            }
        >
            <FormSection title={t('form.details')}>
                <div className="grid gap-4 sm:grid-cols-3">
                    <FormField
                        id="knowledge-scope"
                        label={t('form.scope')}
                        error={form.errors.scope}
                        required
                    >
                        <Select
                            value={form.data.scope}
                            onValueChange={(value: KnowledgeScope) =>
                                form.setData('scope', value)
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {(['clinic', 'platform', 'both'] as const).map(
                                    (scope) => (
                                        <SelectItem key={scope} value={scope}>
                                            {t(`scopes.${scope}`)}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        id="knowledge-section"
                        label={t('form.section')}
                        error={form.errors.section}
                        required
                    >
                        <Select
                            value={form.data.section}
                            onValueChange={(value: KnowledgeSection) =>
                                form.setData('section', value)
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {(
                                    [
                                        'module',
                                        'screen',
                                        'workflow',
                                        'role',
                                        'faq',
                                    ] as const
                                ).map((section) => (
                                    <SelectItem key={section} value={section}>
                                        {t(`sections.${section}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        id="knowledge-permission-mode"
                        label={t('form.permission_mode')}
                        error={form.errors.permission_mode}
                        required
                    >
                        <Select
                            value={form.data.permission_mode}
                            onValueChange={(value: PermissionMode) =>
                                form.setData('permission_mode', value)
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="any">
                                    {t('permission_modes.any')}
                                </SelectItem>
                                <SelectItem value="all">
                                    {t('permission_modes.all')}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>
                </div>

                <FormField
                    id="knowledge-title"
                    label={t('form.title')}
                    error={form.errors.title}
                    required
                >
                    <Input
                        value={form.data.title}
                        onChange={(event) => {
                            form.setData('title', event.target.value);

                            if (!isEdit && !slugManuallyEdited.current) {
                                form.setData(
                                    'slug',
                                    slugify(event.target.value),
                                );
                            }
                        }}
                    />
                </FormField>
                <FormField
                    id="knowledge-slug"
                    label={t('form.slug')}
                    hint={t('form.slug_hint')}
                    error={form.errors.slug}
                    required
                >
                    <Input
                        value={form.data.slug}
                        className="font-mono"
                        onChange={(event) => {
                            slugManuallyEdited.current = true;
                            form.setData('slug', event.target.value);
                        }}
                    />
                </FormField>
                <FormField
                    id="knowledge-content"
                    label={t('form.content')}
                    error={form.errors.content}
                    required
                >
                    <Textarea
                        value={form.data.content}
                        onChange={(event) =>
                            form.setData('content', event.target.value)
                        }
                        rows={8}
                    />
                </FormField>

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="knowledge-keywords"
                        label={t('form.keywords')}
                        hint={t('form.comma_hint')}
                        error={form.errors.keywords}
                    >
                        <Textarea
                            value={form.data.keywords}
                            onChange={(event) =>
                                form.setData('keywords', event.target.value)
                            }
                            rows={3}
                        />
                    </FormField>
                    <FormField
                        id="knowledge-required-permissions"
                        label={t('form.required_permissions')}
                        hint={t('form.comma_hint')}
                        error={form.errors.required_permissions}
                    >
                        <Textarea
                            value={form.data.required_permissions}
                            onChange={(event) =>
                                form.setData(
                                    'required_permissions',
                                    event.target.value,
                                )
                            }
                            rows={3}
                        />
                    </FormField>
                    <FormField
                        id="knowledge-url-patterns"
                        label={t('form.url_patterns')}
                        hint={t('form.lines_hint')}
                        error={form.errors.url_patterns}
                    >
                        <Textarea
                            value={form.data.url_patterns}
                            onChange={(event) =>
                                form.setData('url_patterns', event.target.value)
                            }
                            rows={3}
                            className="font-mono text-xs"
                        />
                    </FormField>
                    <FormField
                        id="knowledge-component-patterns"
                        label={t('form.component_patterns')}
                        hint={t('form.lines_hint')}
                        error={form.errors.component_patterns}
                    >
                        <Textarea
                            value={form.data.component_patterns}
                            onChange={(event) =>
                                form.setData(
                                    'component_patterns',
                                    event.target.value,
                                )
                            }
                            rows={3}
                            className="font-mono text-xs"
                        />
                    </FormField>
                    <FormField
                        id="knowledge-allowed-roles"
                        label={t('form.allowed_roles')}
                        hint={t('form.comma_hint')}
                        error={form.errors.allowed_roles}
                    >
                        <Input
                            value={form.data.allowed_roles}
                            onChange={(event) =>
                                form.setData(
                                    'allowed_roles',
                                    event.target.value,
                                )
                            }
                        />
                    </FormField>
                    <div className="grid grid-cols-2 gap-4">
                        <FormField
                            id="knowledge-priority"
                            label={t('form.priority')}
                            error={form.errors.priority}
                            required
                        >
                            <Input
                                type="number"
                                min={0}
                                max={65535}
                                value={form.data.priority}
                                onChange={(event) =>
                                    form.setData(
                                        'priority',
                                        Number(event.target.value),
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            id="knowledge-sort-order"
                            label={t('form.sort_order')}
                            error={form.errors.sort_order}
                            required
                        >
                            <Input
                                type="number"
                                min={0}
                                value={form.data.sort_order}
                                onChange={(event) =>
                                    form.setData(
                                        'sort_order',
                                        Number(event.target.value),
                                    )
                                }
                            />
                        </FormField>
                    </div>
                </div>

                <FormField
                    id="knowledge-actions"
                    label={t('form.actions')}
                    hint={t('form.actions_hint')}
                    error={actionsError ?? actionsServerError}
                >
                    <Textarea
                        value={form.data.actions}
                        onChange={(event) => {
                            setActionsError(null);
                            form.setData('actions', event.target.value);
                        }}
                        rows={8}
                        spellCheck={false}
                        className="font-mono text-xs"
                    />
                </FormField>

                <div className="flex items-center gap-3">
                    <Checkbox
                        id="knowledge-is-active"
                        checked={form.data.is_active}
                        onCheckedChange={(checked) =>
                            form.setData('is_active', Boolean(checked))
                        }
                    />
                    <Label htmlFor="knowledge-is-active">
                        {t('form.active')}
                    </Label>
                </div>
            </FormSection>
        </FormModal>
    );
}
