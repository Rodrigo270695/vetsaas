<?php

namespace App\Http\Requests\Concerns;

trait AssignsAuthenticatedResponsable
{
    protected function mergeAuthenticatedResponsable(): void
    {
        $userId = $this->user()?->id;

        if ($userId !== null) {
            $this->merge(['responsable_id' => $userId]);
        }
    }

    protected function stripResponsableFromUpdate(): void
    {
        $this->request->remove('responsable_id');
    }
}
