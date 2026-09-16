<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Models\Paciente;
use App\Models\Propietario;
use App\Models\WhatsAppCampana;
use App\Models\WhatsAppCampanaDestinatario;
use App\Support\WhatsApp\PeruMobilePhone;
use App\Support\WhatsApp\WhatsAppCampaignMessageRenderer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class WhatsAppCampaignAudience
{
    /**
     * @return Builder<Propietario>
     */
    public function eligibleQuery(?string $search = null): Builder
    {
        $query = Propietario::query()
            ->where('activo', true)
            ->where(function ($q): void {
                $q->whereRaw(PeruMobilePhone::sqlCelularExpr('telefono'))
                    ->orWhereRaw(PeruMobilePhone::sqlCelularExpr('telefono_alt'));
            });

        $term = trim((string) $search);
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('nombres', 'ilike', $like)
                    ->orWhere('apellidos', 'ilike', $like)
                    ->orWhere('razon_social', 'ilike', $like)
                    ->orWhere('telefono', 'ilike', $like)
                    ->orWhere('telefono_alt', 'ilike', $like);
            });
        }

        return $query->orderBy('nombres');
    }

    /**
     * @return LengthAwarePaginator<int, Propietario>
     */
    public function paginateEligible(WhatsAppCampana $campana, string $search, int $perPage): LengthAwarePaginator
    {
        $taken = $campana->destinatarios()->pluck('propietario_id');

        return $this->eligibleQuery($search)
            ->whereNotIn('id', $taken)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  list<string>  $propietarioIds
     * @return array{added: int, skipped: int}
     */
    public function attachIds(WhatsAppCampana $campana, array $propietarioIds): array
    {
        $ids = array_values(array_unique(array_filter($propietarioIds)));
        if ($ids === []) {
            return ['added' => 0, 'skipped' => 0];
        }

        $owners = $this->eligibleQuery()
            ->whereIn('id', $ids)
            ->with(['pacientes' => fn ($q) => $q->where('activo', true)->orderBy('nombre')])
            ->get();

        return $this->insertOwners($campana, $owners);
    }

    /**
     * @return array{added: int, skipped: int}
     */
    public function attachMatching(WhatsAppCampana $campana, string $search): array
    {
        $taken = $campana->destinatarios()->pluck('propietario_id');
        $owners = $this->eligibleQuery($search)
            ->whereNotIn('id', $taken)
            ->with(['pacientes' => fn ($q) => $q->where('activo', true)->orderBy('nombre')])
            ->limit(2000)
            ->get();

        return $this->insertOwners($campana, $owners);
    }

    /**
     * @param  Collection<int, Propietario>  $owners
     * @return array{added: int, skipped: int}
     */
    private function insertOwners(WhatsAppCampana $campana, Collection $owners): array
    {
        $existingPhones = $campana->destinatarios()->pluck('telefono_normalizado')->all();
        $phoneSet = array_fill_keys($existingPhones, true);
        $existingOwners = array_fill_keys(
            $campana->destinatarios()->pluck('propietario_id')->all(),
            true,
        );
        $added = 0;
        $skipped = 0;

        foreach ($owners as $owner) {
            if (isset($existingOwners[$owner->id])) {
                $skipped++;

                continue;
            }
            $phone = PeruMobilePhone::pickFromOwner($owner->telefono, $owner->telefono_alt);
            if ($phone === null) {
                $skipped++;

                continue;
            }
            if (isset($phoneSet[$phone])) {
                $skipped++;

                continue;
            }

            $pets = $owner->pacientes
                ->map(fn (Paciente $p): string => (string) $p->nombre)
                ->all();

            WhatsAppCampanaDestinatario::query()->create([
                'campana_id' => $campana->id,
                'propietario_id' => $owner->id,
                'telefono_normalizado' => $phone,
                'nombre_snapshot' => $owner->displayName(),
                'mascota_nombres' => WhatsAppCampaignMessageRenderer::joinPetNames($pets) ?: null,
                'estado' => WhatsAppCampanaDestinatario::ESTADO_PENDIENTE,
            ]);

            $phoneSet[$phone] = true;
            $existingOwners[$owner->id] = true;
            $added++;
        }

        return ['added' => $added, 'skipped' => $skipped];
    }
}
