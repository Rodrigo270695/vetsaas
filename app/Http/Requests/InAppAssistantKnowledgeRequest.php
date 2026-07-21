<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\InAppAssistantKnowledge;
use App\Services\InAppAssistant\InAppAssistantNavigation;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InAppAssistantKnowledgeRequest extends FormRequest
{
    private const TOUR_IDS = [
        'citas',
        'pacientes',
        'historias-clinicas',
    ];

    public function authorize(): bool
    {
        $permission = $this->isMethod('post')
            ? 'in-app-assistant-knowledge.create'
            : 'in-app-assistant-knowledge.update';

        return $this->user()?->can($permission) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $entry = $this->route('inAppAssistantKnowledge');
        $id = $entry instanceof InAppAssistantKnowledge ? $entry->getKey() : null;

        return [
            'slug' => [
                'required',
                'string',
                'max:160',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('in_app_assistant_knowledge', 'slug')->ignore($id),
            ],
            'scope' => ['required', 'string', Rule::in(InAppAssistantKnowledge::SCOPES)],
            'section' => ['required', 'string', Rule::in(InAppAssistantKnowledge::SECTIONS)],
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string', 'max:50000'],
            'keywords' => ['nullable', 'array', 'max:50'],
            'keywords.*' => ['string', 'max:100', 'distinct'],
            'url_patterns' => ['nullable', 'array', 'max:50'],
            'url_patterns.*' => ['string', 'max:500', $this->internalPatternValidator()],
            'component_patterns' => ['nullable', 'array', 'max:50'],
            'component_patterns.*' => ['string', 'max:250', 'regex:/^[A-Za-z0-9_./*-]+$/', 'distinct'],
            'required_permissions' => ['nullable', 'array', 'max:50'],
            'required_permissions.*' => ['string', 'max:160', 'regex:/^[a-z0-9-]+(?:\.[a-z0-9-]+)+$/', 'distinct'],
            'permission_mode' => ['required', 'string', Rule::in(InAppAssistantKnowledge::PERMISSION_MODES)],
            'allowed_roles' => ['nullable', 'array', 'max:30'],
            'allowed_roles.*' => ['string', 'max:100', 'regex:/^[a-z0-9_-]+$/', 'distinct'],
            'actions' => ['nullable', 'array', 'max:10'],
            'actions.*' => ['array:type,label,url,tour_id,required_permissions,permission_mode,allowed_roles'],
            'actions.*.type' => ['required', 'string', Rule::in(['navigate', 'start_tour'])],
            'actions.*.label' => ['required', 'string', 'max:120'],
            'actions.*.url' => [
                'required_if:actions.*.type,navigate',
                'prohibited_if:actions.*.type,start_tour',
                'string',
                'max:500',
                $this->internalUrlValidator(),
            ],
            'actions.*.tour_id' => [
                'required_if:actions.*.type,start_tour',
                'prohibited_if:actions.*.type,navigate',
                'string',
                Rule::in(self::TOUR_IDS),
            ],
            'actions.*.required_permissions' => ['nullable', 'array', 'max:20'],
            'actions.*.required_permissions.*' => [
                'string',
                'max:160',
                'regex:/^[a-z0-9-]+(?:\.[a-z0-9-]+)+$/',
                'distinct',
            ],
            'actions.*.permission_mode' => [
                'nullable',
                'string',
                Rule::in(InAppAssistantKnowledge::PERMISSION_MODES),
            ],
            'actions.*.allowed_roles' => ['nullable', 'array', 'max:20'],
            'actions.*.allowed_roles.*' => ['string', 'max:100', 'regex:/^[a-z0-9_-]+$/', 'distinct'],
            'priority' => ['required', 'integer', 'min:0', 'max:65535'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function internalUrlValidator(): Closure
    {
        $scope = (string) $this->input('scope', '');

        return static function (string $attribute, mixed $value, Closure $fail) use ($scope): void {
            if (! is_string($value)
                || preg_match('#^/(?!/)[A-Za-z0-9/_{}.*-]*(?:\?[A-Za-z0-9_=&%{}.*-]*)?$#', $value) !== 1) {
                $fail("El campo {$attribute} debe ser una URL interna relativa.");

                return;
            }

            if (! InAppAssistantNavigation::isKnownKnowledgeUrl($value, $scope)) {
                $fail("El campo {$attribute} debe apuntar a un destino permitido del catálogo {$scope}.");
            }
        };
    }

    private function internalPatternValidator(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)
                || preg_match('#^/(?!/)[A-Za-z0-9/_{}.*-]*(?:\?[A-Za-z0-9_=&%{}.*-]*)?$#', $value) !== 1) {
                $fail("El campo {$attribute} debe ser un patrón de URL interno relativo.");
            }
        };
    }
}
