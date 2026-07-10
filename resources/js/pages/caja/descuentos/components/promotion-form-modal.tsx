import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useMemo } from 'react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Combobox, type ComboboxOption } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { GroomingServiceOption, ProductOption, Promotion, PromotionMeta } from '../types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    promotion: Promotion | null;
    meta: PromotionMeta;
    groomingServiceOptions: readonly GroomingServiceOption[];
    productOptions: readonly ProductOption[];
};

type FormData = {
    name: string;
    code: string;
    description: string;
    discount_type: string;
    value: string;
    scope: string;
    condition_type: string;
    grooming_service_slug: string | null;
    producto_id: string | null;
    auto_apply: boolean;
    is_active: boolean;
    valid_from: string;
    valid_until: string;
    max_uses: string;
    priority: string;
};

const GROOMING_ONLY_CONDITIONS = new Set(['second_pet_grooming', 'second_grooming_line_in_cart']);

function defaultConditionForScope(scope: string): string {
    if (scope === 'grooming') {
        return 'second_pet_grooming';
    }

    return 'coupon_code';
}

const emptyForm = (meta: PromotionMeta): FormData => ({
    name: '',
    code: '',
    description: '',
    discount_type: meta.discount_types[0] ?? 'pct_line',
    value: '',
    scope: meta.scopes[0] ?? 'grooming',
    condition_type: defaultConditionForScope(meta.scopes[0] ?? 'grooming'),
    grooming_service_slug: null,
    producto_id: null,
    auto_apply: true,
    is_active: true,
    valid_from: '',
    valid_until: '',
    max_uses: '',
    priority: '100',
});

function toDatetimeLocal(value: string | null): string {
    if (!value) {
        return '';
    }
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) {
        return '';
    }
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function conditionTypesForScope(scope: string, all: readonly string[]): readonly string[] {
    if (scope === 'grooming') {
        return all;
    }

    return all.filter((c) => !GROOMING_ONLY_CONDITIONS.has(c));
}

export function PromotionFormModal({
    open,
    onOpenChange,
    promotion,
    meta,
    groomingServiceOptions,
    productOptions,
}: Props) {
    const { t } = useTranslation(['descuentos-promociones', 'common']);
    const isEdit = promotion !== null;

    const { data, setData, post, put, processing, errors, reset, clearErrors, transform } = useForm<FormData>(emptyForm(meta));

    const allowedConditions = useMemo(
        () => conditionTypesForScope(data.scope, meta.condition_types),
        [data.scope, meta.condition_types],
    );

    useEffect(() => {
        if (!open) {
            return;
        }

        if (!promotion) {
            reset();
            clearErrors();

            return;
        }

        setData({
            name: promotion.name,
            code: promotion.code ?? '',
            description: promotion.description ?? '',
            discount_type: promotion.discount_type,
            value: promotion.value,
            scope: promotion.scope,
            condition_type: promotion.condition_type,
            grooming_service_slug: promotion.grooming_service_slug,
            producto_id: promotion.producto_id,
            auto_apply: promotion.auto_apply,
            is_active: promotion.is_active,
            valid_from: toDatetimeLocal(promotion.valid_from),
            valid_until: toDatetimeLocal(promotion.valid_until),
            max_uses: promotion.max_uses != null ? String(promotion.max_uses) : '',
            priority: String(promotion.priority),
        });
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, promotion?.id]);

    useEffect(() => {
        if (!allowedConditions.includes(data.condition_type)) {
            setData('condition_type', defaultConditionForScope(data.scope));
        }
    }, [allowedConditions, data.condition_type, data.scope, setData]);

    const groomingOptions = useMemo<readonly ComboboxOption[]>(
        () => [
            { value: '', label: t('form.grooming_service_none') },
            ...groomingServiceOptions.map((o) => ({ value: o.value, label: o.label })),
        ],
        [groomingServiceOptions, t],
    );

    const productComboboxOptions = useMemo<readonly ComboboxOption[]>(
        () => [
            { value: '', label: t('form.producto_none') },
            ...productOptions.map((p) => ({
                value: p.id,
                label: p.sku ? `${p.nombre} (${p.sku})` : p.nombre,
            })),
        ],
        [productOptions, t],
    );

    const isPct = data.discount_type === 'pct_line' || data.discount_type === 'pct_sale';
    const showGroomingService = data.scope === 'grooming';
    const showProduct = data.scope === 'product';
    const selectTriggerClass = 'w-full min-w-0';
    const gridFieldClass = 'min-w-0';

    const handleScopeChange = (scope: string) => {
        const conditions = conditionTypesForScope(scope, meta.condition_types);
        const nextCondition = conditions.includes(data.condition_type)
            ? data.condition_type
            : defaultConditionForScope(scope);

        setData((prev) => ({
            ...prev,
            scope,
            condition_type: nextCondition,
            grooming_service_slug: scope === 'grooming' ? prev.grooming_service_slug : null,
            producto_id: scope === 'product' ? prev.producto_id : null,
        }));
    };

    const onSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        const onSuccess = () => {
            onOpenChange(false);
            reset();
            clearErrors();
        };

        transform((d) => ({
            ...d,
            code: d.code.trim() === '' ? null : d.code.trim(),
            description: d.description.trim() === '' ? null : d.description,
            grooming_service_slug: showGroomingService ? (d.grooming_service_slug || null) : null,
            producto_id: showProduct ? (d.producto_id || null) : null,
            valid_from: d.valid_from === '' ? null : d.valid_from,
            valid_until: d.valid_until === '' ? null : d.valid_until,
            max_uses: d.max_uses.trim() === '' ? null : d.max_uses,
        }));

        if (isEdit && promotion) {
            put(`/caja/descuentos/${promotion.id}`, { preserveScroll: true, onSuccess });

            return;
        }

        post('/caja/descuentos', { preserveScroll: true, onSuccess });
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('form.title_edit') : t('form.title_create')}
            description={t('description')}
            size="lg"
            onSubmit={onSubmit}
            footer={
                <>
                    <Button type="button" variant="outline" disabled={processing} onClick={() => onOpenChange(false)}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" disabled={processing || data.name.trim() === ''} className="gap-2">
                        {processing && <Loader2 className="size-4 animate-spin" />}
                        {isEdit ? t('form.submit_edit') : t('form.submit_create')}
                    </Button>
                </>
            }
        >
            <div className="grid gap-4">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <FormField id="promo-name" label={t('form.name')} error={errors.name} required className={gridFieldClass}>
                        <Input id="promo-name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                    </FormField>
                    <FormField id="promo-code" label={t('form.code')} error={errors.code} hint={t('form.code_hint')} className={gridFieldClass}>
                        <Input
                            id="promo-code"
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value.toUpperCase())}
                            className="font-mono uppercase"
                        />
                    </FormField>
                </div>

                <FormField id="promo-desc" label={t('form.description')} error={errors.description}>
                    <Textarea
                        id="promo-desc"
                        rows={2}
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                    />
                </FormField>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <FormField id="promo-discount-type" label={t('form.discount_type')} error={errors.discount_type} className={gridFieldClass}>
                        <Select value={data.discount_type} onValueChange={(v) => setData('discount_type', v)}>
                            <SelectTrigger id="promo-discount-type" className={selectTriggerClass}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {meta.discount_types.map((type) => (
                                    <SelectItem key={type} value={type}>
                                        {t(`discount_types.${type}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        id="promo-value"
                        label={t('form.value')}
                        error={errors.value}
                        hint={isPct ? t('form.value_pct_hint') : t('form.value_amount_hint')}
                        className={gridFieldClass}
                    >
                        <Input
                            id="promo-value"
                            type="number"
                            min={0}
                            max={isPct ? 100 : undefined}
                            step="0.01"
                            value={data.value}
                            onChange={(e) => setData('value', e.target.value)}
                        />
                    </FormField>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <FormField id="promo-scope" label={t('form.scope')} error={errors.scope} className={gridFieldClass}>
                        <Select value={data.scope} onValueChange={handleScopeChange}>
                            <SelectTrigger id="promo-scope" className={selectTriggerClass}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {meta.scopes.map((scope) => (
                                    <SelectItem key={scope} value={scope}>
                                        {t(`scopes.${scope}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField id="promo-condition" label={t('form.condition_type')} error={errors.condition_type} className={gridFieldClass}>
                        <Select value={data.condition_type} onValueChange={(v) => setData('condition_type', v)}>
                            <SelectTrigger id="promo-condition" className={selectTriggerClass}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {allowedConditions.map((cond) => (
                                    <SelectItem key={cond} value={cond}>
                                        {t(`conditions.${cond}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                </div>

                {showGroomingService ? (
                    <FormField
                        id="promo-grooming-svc"
                        label={t('form.grooming_service_slug')}
                        error={errors.grooming_service_slug}
                        className={gridFieldClass}
                    >
                        <Combobox
                            id="promo-grooming-svc"
                            options={groomingOptions}
                            value={data.grooming_service_slug ?? ''}
                            onChange={(v) => setData('grooming_service_slug', v === '' ? null : v)}
                            placeholder={t('form.grooming_service_none')}
                        />
                    </FormField>
                ) : null}

                {showProduct ? (
                    <FormField
                        id="promo-producto"
                        label={t('form.producto_id')}
                        error={errors.producto_id}
                        className={gridFieldClass}
                    >
                        <Combobox
                            id="promo-producto"
                            options={productComboboxOptions}
                            value={data.producto_id ?? ''}
                            onChange={(v) => setData('producto_id', v === '' ? null : v)}
                            placeholder={t('form.producto_none')}
                        />
                    </FormField>
                ) : null}

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <FormField id="promo-valid-from" label={t('form.valid_from')} error={errors.valid_from} className={gridFieldClass}>
                        <Input
                            id="promo-valid-from"
                            type="datetime-local"
                            value={data.valid_from}
                            onChange={(e) => setData('valid_from', e.target.value)}
                        />
                    </FormField>
                    <FormField id="promo-valid-until" label={t('form.valid_until')} error={errors.valid_until} className={gridFieldClass}>
                        <Input
                            id="promo-valid-until"
                            type="datetime-local"
                            value={data.valid_until}
                            onChange={(e) => setData('valid_until', e.target.value)}
                        />
                    </FormField>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <FormField
                        id="promo-max-uses"
                        label={t('form.max_uses')}
                        error={errors.max_uses}
                        hint={t('form.max_uses_hint')}
                        className={gridFieldClass}
                    >
                        <Input
                            id="promo-max-uses"
                            type="number"
                            min={1}
                            value={data.max_uses}
                            onChange={(e) => setData('max_uses', e.target.value)}
                        />
                    </FormField>
                    <FormField
                        id="promo-priority"
                        label={t('form.priority')}
                        error={errors.priority}
                        hint={t('form.priority_hint')}
                        className={gridFieldClass}
                    >
                        <Input
                            id="promo-priority"
                            type="number"
                            min={1}
                            value={data.priority}
                            onChange={(e) => setData('priority', e.target.value)}
                        />
                    </FormField>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <FormField id="promo-auto" label={t('form.auto_apply')} className={gridFieldClass}>
                        <label htmlFor="promo-auto" className="flex items-center gap-3 text-sm">
                            <Checkbox
                                id="promo-auto"
                                checked={data.auto_apply}
                                onCheckedChange={(checked) => setData('auto_apply', Boolean(checked))}
                            />
                            <span>{t('form.auto_apply_label')}</span>
                        </label>
                    </FormField>
                    <FormField id="promo-active" label={t('form.is_active')} className={gridFieldClass}>
                        <label htmlFor="promo-active" className="flex items-center gap-3 text-sm">
                            <Checkbox
                                id="promo-active"
                                checked={data.is_active}
                                onCheckedChange={(checked) => setData('is_active', Boolean(checked))}
                            />
                            <span>{t('form.is_active_label')}</span>
                        </label>
                    </FormField>
                </div>
            </div>
        </FormModal>
    );
}
