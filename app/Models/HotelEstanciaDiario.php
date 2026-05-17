<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora diaria durante una estancia (hotel / guardería).
 *
 * @property int $id
 * @property string $hotel_estancia_id
 * @property string $fecha Y-m-d
 * @property ?string $notas
 * @property ?string $created_by_id
 */
class HotelEstanciaDiario extends Model
{
    protected $table = 'hotel_estancia_diarios';

    protected $fillable = [
        'hotel_estancia_id',
        'fecha',
        'notas',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function estancia(): BelongsTo
    {
        return $this->belongsTo(HotelEstancia::class, 'hotel_estancia_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
