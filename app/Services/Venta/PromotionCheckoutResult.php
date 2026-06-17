<?php

namespace App\Services\Venta;

final class PromotionCheckoutResult
{
    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  list<array{id: string, name: string, discount_amount: string}>  $promotions_applied
     */
    public function __construct(
        public array $lineas,
        public string $discount_amount,
        public ?string $promotion_id,
        public ?string $promotion_name,
        public array $promotions_applied = [],
    ) {}
}
