<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ExtraService extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'base_price_pen',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price_pen' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function reservations(): BelongsToMany
    {
        return $this->belongsToMany(Reservation::class, 'reservation_extra_services')
            ->withPivot(['quantity', 'unit_price_pen', 'subtotal_pen'])
            ->withTimestamps();
    }
}
