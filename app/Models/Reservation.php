<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    protected $fillable = [
        'branch_id',
        'created_by_user_id',
        'check_in',
        'check_out',
        'booking_status',
        'total_amount_pen',
        'public_reference',
        'confirmed_at',
        'checked_in_at',
        'checked_out_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'total_amount_pen' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function guests(): BelongsToMany
    {
        return $this->belongsToMany(Guest::class, 'reservation_guests')
            ->withPivot(['reservation_room_request_id', 'is_primary', 'guest_type'])
            ->withTimestamps();
    }

    public function primaryGuest(): BelongsToMany
    {
        return $this->guests()->wherePivot('is_primary', true);
    }

    public function roomRequests(): HasMany
    {
        return $this->hasMany(ReservationRoomRequest::class);
    }

    public function extraServices(): BelongsToMany
    {
        return $this->belongsToMany(ExtraService::class, 'reservation_extra_services')
            ->withPivot(['quantity', 'unit_price_pen', 'subtotal_pen'])
            ->withTimestamps();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function roomStays(): HasMany
    {
        return $this->hasMany(ReservationRoomStay::class);
    }

    public function nights(): int
    {
        return max(1, (int) $this->check_in->diffInDays($this->check_out));
    }
}
