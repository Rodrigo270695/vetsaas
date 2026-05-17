<?php

namespace App\Http\Requests\Concerns;

use App\Support\Plan\PlanLimits;
use Illuminate\Contracts\Validation\Validator;

trait ValidatesPlanIntLimits
{
    /**
     * @param  list<string>  $features  ej. ['max_propietarios']
     */
    protected function enforcePlanIntLimitsOnCreate(Validator $validator, array $features, int $adding = 1): void
    {
        if (! $this->isMethod('POST')) {
            return;
        }

        $validator->after(function (Validator $v) use ($features, $adding): void {
            if (PlanLimits::tenant() === null) {
                return;
            }

            foreach ($features as $feature) {
                if (PlanLimits::wouldExceed($feature, adding: $adding)) {
                    $v->errors()->add('plan_limit', PlanLimits::message($feature));
                }
            }
        });
    }
}
