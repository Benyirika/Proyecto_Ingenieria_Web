<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = [
        'branch_id', 'room_category_id', 'number', 'floor',
        'status', 'cleaning_status', 'priority',
        'cleaning_notes', 'last_cleaned_at', 'last_cleaned_by'
    ];

    protected $casts = [
        'last_cleaned_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function category()
    {
        return $this->belongsTo(RoomCategory::class, 'room_category_id');
    }

    public function cleaner()
    {
        return $this->belongsTo(User::class, 'last_cleaned_by');
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}