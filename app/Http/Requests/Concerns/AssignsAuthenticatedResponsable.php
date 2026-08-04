<?php

namespace App\Http\Requests\Concerns;

trait AssignsAuthenticatedResponsable
{
    /**
     * Solo asigna el usuario autenticado si el cliente no envió un responsable.
     * No pisa un UUID elegido en el formulario.
     */
    protected function mergeAuthenticatedResponsable(): void
    {
        if ($this->filled('responsable_id')) {
            return;
        }

        $userId = $this->user()?->id;

        if ($userId !== null) {
            $this->merge(['responsable_id' => $userId]);
        }
    }
}
