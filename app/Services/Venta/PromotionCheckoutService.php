<?php

namespace App\Services\Venta;

use App\Models\ConsultaCargoLinea;
use App\Models\GroomingTurno;
use App\Models\Promotion;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

final class PromotionCheckoutService
{
    /**
     * @param  array<string, mixed>  $context
     * @param  list<array<string, mixed>>  $lineasCalc
     */
    public function evaluate(
        array $context,
        array $lineasCalc,
        float $igvPct,
        bool $precioIncluyeIgv,
    ): PromotionCheckoutResult {
        if ($lineasCalc === []) {
            return new PromotionCheckoutResult($lineasCalc, '0.00', null, null);
        }

        $lineasCalc = $this->enrichLines($lineasCalc, $context);

        $promotionCode = isset($context['promotion_code']) && is_string($context['promotion_code'])
            ? strtoupper(trim($context['promotion_code']))
            : '';

        $candidates = Promotion::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('name')
            ->get()
            ->filter(fn (Promotion $p): bool => $p->isCurrentlyValid());

        $best = null;
        $bestDiscount = 0.0;
        $bestLines = $lineasCalc;

        foreach ($candidates as $promo) {
            if ($promotionCode !== '') {
                if ($promo->condition_type !== Promotion::CONDITION_COUPON_CODE) {
                    continue;
                }
                if (strtoupper((string) ($promo->code ?? '')) !== $promotionCode) {
                    continue;
                }
            } elseif (! $promo->auto_apply || $promo->condition_type === Promotion::CONDITION_COUPON_CODE) {
                continue;
            }

            if (! $this->matchesCondition($promo, $context, $lineasCalc)) {
                continue;
            }

            $simulation = $this->simulateApplication($promo, $lineasCalc, $igvPct, $precioIncluyeIgv);
            $discount = (float) $simulation['discount_amount'];

            if ($discount <= 0) {
                continue;
            }

            if ($promotionCode !== '' || $discount > $bestDiscount + 0.0001) {
                $best = $promo;
                $bestDiscount = $discount;
                $bestLines = $simulation['lineas'];

                if ($promotionCode !== '') {
                    break;
                }
            }
        }

        if ($best === null) {
            return new PromotionCheckoutResult($lineasCalc, '0.00', null, null);
        }

        return new PromotionCheckoutResult(
            $bestLines,
            number_format($bestDiscount, 2, '.', ''),
            $best->id,
            $best->name,
            [[
                'id' => $best->id,
                'name' => $best->name,
                'discount_amount' => number_format($bestDiscount, 2, '.', ''),
            ]],
        );
    }

    public function recordUse(?string $promotionId): void
    {
        if ($promotionId === null || $promotionId === '') {
            return;
        }

        Promotion::query()->whereKey($promotionId)->increment('uses_count');
    }

    /**
     * @param  list<array<string, mixed>>  $lineasCalc
     * @return list<array<string, mixed>>
     */
    private function enrichLines(array $lineasCalc, array $context): array
    {
        $groomingSlug = $context['grooming_service_slug'] ?? null;
        $isGroomingSale = is_string($context['grooming_turno_id'] ?? null)
            && $context['grooming_turno_id'] !== '';

        foreach ($lineasCalc as $idx => $line) {
            $tipo = (string) ($line['tipo_linea'] ?? '');
            $isService = $tipo === ConsultaCargoLinea::TIPO_SERVICIO || $line['producto_id'] === null;
            $lineasCalc[$idx]['is_grooming'] = $isGroomingSale && $isService && $line['producto_id'] === null;
            $lineasCalc[$idx]['is_hotel'] = is_string($context['hotel_estancia_id'] ?? null)
                && $context['hotel_estancia_id'] !== ''
                && $isService
                && $line['producto_id'] === null;
            $lineasCalc[$idx]['grooming_service_slug'] = $lineasCalc[$idx]['is_grooming'] ? $groomingSlug : null;
        }

        return $lineasCalc;
    }

    /**
     * @param  list<array<string, mixed>>  $lineasCalc
     */
    private function matchesCondition(Promotion $promo, array $context, array $lineasCalc): bool
    {
        if (! $this->hasLinesInScope($promo, $lineasCalc)) {
            return false;
        }

        return match ($promo->condition_type) {
            Promotion::CONDITION_NONE, Promotion::CONDITION_COUPON_CODE => true,
            Promotion::CONDITION_SECOND_PET_GROOMING => $this->isSecondPetGroomingToday($promo, $context),
            Promotion::CONDITION_SECOND_GROOMING_LINE_IN_CART => $this->hasSecondGroomingLineInCart($lineasCalc),
            default => false,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $lineasCalc
     */
    private function hasLinesInScope(Promotion $promo, array $lineasCalc): bool
    {
        foreach ($lineasCalc as $line) {
            if ($this->lineMatchesScope($promo, $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function lineMatchesScope(Promotion $promo, array $line): bool
    {
        return match ($promo->scope) {
            Promotion::SCOPE_GROOMING => (bool) ($line['is_grooming'] ?? false)
                && ($promo->grooming_service_slug === null
                    || (string) ($line['grooming_service_slug'] ?? '') === (string) $promo->grooming_service_slug),
            Promotion::SCOPE_HOTEL => (bool) ($line['is_hotel'] ?? false),
            Promotion::SCOPE_PRODUCT => $line['producto_id'] !== null,
            Promotion::SCOPE_ENTIRE_SALE => true,
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function isSecondPetGroomingToday(Promotion $promo, array $context): bool
    {
        $ownerId = (string) ($context['propietario_id'] ?? '');
        if ($ownerId === '') {
            return false;
        }

        $patientId = $context['paciente_id'] ?? null;
        if (! is_string($patientId) || $patientId === '') {
            $turnoId = $context['grooming_turno_id'] ?? null;
            if (is_string($turnoId) && $turnoId !== '') {
                $patientId = GroomingTurno::query()->whereKey($turnoId)->value('paciente_id');
            }
        }

        if (! is_string($patientId) || $patientId === '') {
            return false;
        }

        $query = Venta::query()
            ->where('propietario_id', $ownerId)
            ->where('estado', Venta::ESTADO_PAGADO)
            ->whereDate('fecha_pago', today())
            ->where('paciente_id', '!=', $patientId)
            ->whereNotNull('paciente_id')
            ->whereExists(function ($q): void {
                $q->select(DB::raw('1'))
                    ->from('grooming_turnos')
                    ->whereColumn('grooming_turnos.venta_id', 'ventas.id');
            });

        if ($promo->grooming_service_slug !== null) {
            $slug = $promo->grooming_service_slug;
            $query->whereExists(function ($q) use ($slug): void {
                $q->select(DB::raw('1'))
                    ->from('grooming_turnos')
                    ->whereColumn('grooming_turnos.venta_id', 'ventas.id')
                    ->where('grooming_turnos.servicio', $slug);
            });
        }

        return $query->exists();
    }

    /**
     * @param  list<array<string, mixed>>  $lineasCalc
     */
    private function hasSecondGroomingLineInCart(array $lineasCalc): bool
    {
        $count = 0;
        foreach ($lineasCalc as $line) {
            if ($line['is_grooming'] ?? false) {
                $count++;
            }
        }

        return $count >= 2;
    }

    /**
     * @param  list<array<string, mixed>>  $lineasCalc
     * @return array{lineas: list<array<string, mixed>>, discount_amount: float}
     */
    private function simulateApplication(
        Promotion $promo,
        array $lineasCalc,
        float $igvPct,
        bool $precioIncluyeIgv,
    ): array {
        $lineas = $lineasCalc;
        $discountTotal = 0.0;
        $divisor = 1 + ($igvPct / 100);

        $eligibleIndices = [];
        foreach ($lineas as $idx => $line) {
            if ($this->lineMatchesScope($promo, $line)) {
                $eligibleIndices[] = $idx;
            }
        }

        if ($promo->condition_type === Promotion::CONDITION_SECOND_GROOMING_LINE_IN_CART && count($eligibleIndices) >= 2) {
            $eligibleIndices = array_slice($eligibleIndices, 1);
        }

        if ($eligibleIndices === []) {
            return ['lineas' => $lineasCalc, 'discount_amount' => 0.0];
        }

        foreach ($eligibleIndices as $idx) {
            $line = $lineas[$idx];

            $lineDiscount = match ($promo->discount_type) {
                Promotion::DISCOUNT_PCT_LINE => $this->pctLineDiscount($line, (float) $promo->value, $precioIncluyeIgv, $divisor),
                Promotion::DISCOUNT_AMOUNT_LINE => min((float) $promo->value, $this->lineGrossAmount($line, $precioIncluyeIgv, $divisor)),
                default => 0.0,
            };

            if ($lineDiscount <= 0) {
                continue;
            }

            if ($promo->discount_type === Promotion::DISCOUNT_PCT_LINE) {
                $lineas[$idx] = $this->applyPctDiscountToLine(
                    $line,
                    (float) $promo->value,
                    $precioIncluyeIgv,
                    $divisor,
                    $promo->id,
                );
            } else {
                $lineas[$idx] = $this->applyAmountDiscountToLine(
                    $line,
                    $lineDiscount,
                    $precioIncluyeIgv,
                    $divisor,
                    $promo->id,
                );
            }

            $discountTotal += $lineDiscount;
        }

        if (in_array($promo->discount_type, [Promotion::DISCOUNT_PCT_SALE, Promotion::DISCOUNT_AMOUNT_SALE], true)) {
            $currentSubtotal = array_sum(array_map(
                fn (array $l): float => (float) ($l['subtotal'] ?? 0),
                $lineas,
            ));

            $saleDiscount = $promo->discount_type === Promotion::DISCOUNT_PCT_SALE
                ? round($currentSubtotal * ((float) $promo->value / 100), 2)
                : min((float) $promo->value, $currentSubtotal);

            if ($saleDiscount > 0 && $currentSubtotal > 0) {
                $factor = max(0, ($currentSubtotal - $saleDiscount) / $currentSubtotal);
                foreach ($lineas as $idx => $line) {
                    $qty = (float) ($line['cantidad'] ?? 0);
                    $newSub = round((float) $line['subtotal'] * $factor, 2);
                    $lineas[$idx]['subtotal'] = $newSub;
                    $lineas[$idx]['precio_unitario'] = $qty > 0 ? round($newSub / $qty, 4) : 0.0;
                    $lineas[$idx]['promotion_id'] = $promo->id;
                }
                $discountTotal += $saleDiscount;
            }
        }

        return [
            'lineas' => $lineas,
            'discount_amount' => round($discountTotal, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function pctLineDiscount(array $line, float $pct, bool $precioIncluyeIgv, float $divisor): float
    {
        return round($this->lineGrossAmount($line, $precioIncluyeIgv, $divisor) * ($pct / 100), 2);
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function lineGrossAmount(array $line, bool $precioIncluyeIgv, float $divisor): float
    {
        $qty = (float) ($line['cantidad'] ?? 0);
        $listPrice = (float) (string) ($line['precio_lista'] ?? 0);

        if ($precioIncluyeIgv) {
            return round($qty * $listPrice, 2);
        }

        $sub = (float) ($line['subtotal'] ?? 0);

        return round($sub * $divisor, 2);
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function applyPctDiscountToLine(
        array $line,
        float $pct,
        bool $precioIncluyeIgv,
        float $divisor,
        string $promotionId,
    ): array {
        $qty = (float) ($line['cantidad'] ?? 0);

        if ($precioIncluyeIgv) {
            $listPrice = (float) (string) ($line['precio_lista'] ?? 0);
            $grossOriginal = round($qty * $listPrice, 2);
            $grossNew = round($grossOriginal * (1 - ($pct / 100)), 2);
            $subNew = $divisor > 0 ? round($grossNew / $divisor, 2) : $grossNew;
            $line['subtotal'] = $subNew;
            $line['precio_unitario'] = $qty > 0 ? round($subNew / $qty, 4) : 0.0;
        } else {
            $subOriginal = (float) ($line['subtotal'] ?? 0);
            $subNew = round($subOriginal * (1 - ($pct / 100)), 2);
            $line['subtotal'] = $subNew;
            $line['precio_unitario'] = $qty > 0 ? round($subNew / $qty, 4) : 0.0;
        }

        $line['descuento_pct'] = $pct;
        $line['promotion_id'] = $promotionId;

        return $line;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function applyAmountDiscountToLine(
        array $line,
        float $discount,
        bool $precioIncluyeIgv,
        float $divisor,
        string $promotionId,
    ): array {
        $qty = (float) ($line['cantidad'] ?? 0);

        if ($precioIncluyeIgv) {
            $gross = $this->lineGrossAmount($line, true, $divisor);
            $grossNew = max(0, round($gross - $discount, 2));
            $subNew = $divisor > 0 ? round($grossNew / $divisor, 2) : $grossNew;
            $line['subtotal'] = $subNew;
            $line['precio_unitario'] = $qty > 0 ? round($subNew / $qty, 4) : 0.0;
            $line['descuento_pct'] = $gross > 0 ? round(($discount / $gross) * 100, 2) : 0.0;
        } else {
            $subOriginal = (float) ($line['subtotal'] ?? 0);
            $subNew = max(0, round($subOriginal - $discount, 2));
            $line['subtotal'] = $subNew;
            $line['precio_unitario'] = $qty > 0 ? round($subNew / $qty, 4) : 0.0;
            $line['descuento_pct'] = $subOriginal > 0 ? round(($discount / $subOriginal) * 100, 2) : 0.0;
        }

        $line['promotion_id'] = $promotionId;

        return $line;
    }
}
