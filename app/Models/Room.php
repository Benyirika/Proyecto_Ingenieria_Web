<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Room extends Model
{
    protected $fillable = [
        'branch_id',
        'room_category_id',
        'number',
        'floor',
        'physical_status',
        'priority',
        'cleaning_notes',
        'has_maintenance_alert',
        'maintenance_incident',
        'last_cleaned_at',
        'last_cleaned_by',
    ];

    protected $casts = [
        'last_cleaned_at' => 'datetime',
        'has_maintenance_alert' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RoomCategory::class, 'room_category_id');
    }

    public function cleaner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_cleaned_by');
    }

    public function reservationStays(): HasMany
    {
        return $this->hasMany(ReservationRoomStay::class);
    }

    public function currentStay(): HasOne
    {
        return $this->hasOne(ReservationRoomStay::class)->whereNull('ended_at');
    }

    public function housekeepingReports(): HasMany
    {
        return $this->hasMany(HousekeepingReport::class);
    }
}
