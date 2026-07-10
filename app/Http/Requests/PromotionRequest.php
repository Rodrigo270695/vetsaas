<?php

namespace App\Http\Requests;

use App\Models\Promotion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $promotion = $this->route('promotion');
        $promotionId = $promotion instanceof \App\Models\Promotion ? $promotion->id : null;

        $scope = (string) $this->input('scope', '');
        $allowedConditions = Promotion::conditionTypesForScope($scope);

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'nullable',
                'string',
                'max:30',
                'alpha_dash',
                Rule::unique('promotions', 'code')->ignore($promotionId),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'discount_type' => ['required', 'string', Rule::in(Promotion::DISCOUNT_TYPES)],
            'value' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'scope' => ['required', 'string', Rule::in(Promotion::SCOPES)],
            'condition_type' => ['required', 'string', Rule::in($allowedConditions)],
            'grooming_service_slug' => ['nullable', 'string', 'max:80'],
            'producto_id' => [
                'nullable',
                'uuid',
                Rule::exists('productos', 'id')->whereNull('deleted_at'),
            ],
            'auto_apply' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $code = trim((string) $this->input('code', ''));

        $this->merge([
            'code' => $code === '' ? null : strtoupper($code),
            'auto_apply' => $this->boolean('auto_apply'),
            'is_active' => $this->boolean('is_active'),
            'grooming_service_slug' => trim((string) $this->input('grooming_service_slug', '')) ?: null,
            'producto_id' => trim((string) $this->input('producto_id', '')) ?: null,
        ]);

        if ($this->input('max_uses') === '' || $this->input('max_uses') === null) {
            $this->merge(['max_uses' => null]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $discountType = (string) $this->input('discount_type', '');
            $value = (float) $this->input('value', 0);

            if (in_array($discountType, [Promotion::DISCOUNT_PCT_LINE, Promotion::DISCOUNT_PCT_SALE], true) && $value > 100) {
                $v->errors()->add('value', __('promotions.validation.pct_max'));
            }

            if ($this->input('condition_type') === Promotion::CONDITION_COUPON_CODE && blank($this->input('code'))) {
                $v->errors()->add('code', __('promotions.validation.code_required'));
            }

            $scope = (string) $this->input('scope', '');

            if ($scope !== Promotion::SCOPE_GROOMING && $this->filled('grooming_service_slug')) {
                $v->errors()->add('grooming_service_slug', __('promotions.validation.grooming_service_invalid_scope'));
            }

            if ($scope !== Promotion::SCOPE_PRODUCT && $this->filled('producto_id')) {
                $v->errors()->add('producto_id', __('promotions.validation.product_invalid_scope'));
            }
        });
    }
}
