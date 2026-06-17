<?php

namespace App\Http\Requests\Concerns;

trait AssignsAuthenticatedVeterinario
{
    protected function mergeAuthenticatedVeterinario(): void
    {
        $userId = $this->user()?->id;

        if ($userId !== null) {
            $this->merge(['veterinario_id' => $userId]);
        }
    }

    protected function stripVeterinarioFromUpdate(): void
    {
        $this->request->remove('veterinario_id');
    }
}
