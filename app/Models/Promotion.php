<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promotion extends Model
{
    use HasUuids;
    use SoftDeletes;

    public const DISCOUNT_PCT_LINE = 'pct_line';

    public const DISCOUNT_AMOUNT_LINE = 'amount_line';

    public const DISCOUNT_PCT_SALE = 'pct_sale';

    public const DISCOUNT_AMOUNT_SALE = 'amount_sale';

    public const SCOPE_GROOMING = 'grooming';

    public const SCOPE_HOTEL = 'hotel';

    public const SCOPE_PRODUCT = 'product';

    public const SCOPE_ENTIRE_SALE = 'entire_sale';

    public const CONDITION_NONE = 'none';

    public const CONDITION_COUPON_CODE = 'coupon_code';

    public const CONDITION_SECOND_PET_GROOMING = 'second_pet_grooming';

    public const CONDITION_SECOND_GROOMING_LINE_IN_CART = 'second_grooming_line_in_cart';

    /** @var list<string> */
    public const DISCOUNT_TYPES = [
        self::DISCOUNT_PCT_LINE,
        self::DISCOUNT_AMOUNT_LINE,
        self::DISCOUNT_PCT_SALE,
        self::DISCOUNT_AMOUNT_SALE,
    ];

    /** @var list<string> */
    public const SCOPES = [
        self::SCOPE_GROOMING,
        self::SCOPE_HOTEL,
        self::SCOPE_PRODUCT,
        self::SCOPE_ENTIRE_SALE,
    ];

    /** @var list<string> */
    public const CONDITION_TYPES = [
        self::CONDITION_NONE,
        self::CONDITION_COUPON_CODE,
        self::CONDITION_SECOND_PET_GROOMING,
        self::CONDITION_SECOND_GROOMING_LINE_IN_CART,
    ];

    protected $table = 'promotions';

    protected $fillable = [
        'name',
        'code',
        'description',
        'discount_type',
        'value',
        'scope',
        'condition_type',
        'grooming_service_slug',
        'producto_id',
        'auto_apply',
        'is_active',
        'valid_from',
        'valid_until',
        'max_uses',
        'uses_count',
        'priority',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'auto_apply' => 'boolean',
            'is_active' => 'boolean',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
            'priority' => 'integer',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * @return list<string>
     */
    public static function conditionTypesForScope(string $scope): array
    {
        if ($scope === self::SCOPE_GROOMING) {
            return self::CONDITION_TYPES;
        }

        return [
            self::CONDITION_NONE,
            self::CONDITION_COUPON_CODE,
        ];
    }

    public function isCurrentlyValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->valid_from !== null && $now->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until !== null && $now->gt($this->valid_until)) {
            return false;
        }

        if ($this->max_uses !== null && $this->uses_count >= $this->max_uses) {
            return false;
        }

        return true;
    }
}
