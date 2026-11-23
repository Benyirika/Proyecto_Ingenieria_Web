<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReservationRoomRequest extends Model
{
    protected $fillable = [
        'reservation_id',
        'room_category_id',
        'nightly_rate_pen',
    ];

    protected function casts(): array
    {
        return [
            'nightly_rate_pen' => 'decimal:2',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RoomCategory::class, 'room_category_id');
    }

    public function stays(): HasMany
    {
        return $this->hasMany(ReservationRoomStay::class);
    }
}
