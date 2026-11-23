<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Guest extends Model
{
    protected $fillable = [
        'document_type',
        'document_number',
        'first_name',
        'last_name',
        'email',
        'phone',
        'is_blacklisted',
    ];

    protected function casts(): array
    {
        return [
            'is_blacklisted' => 'boolean',
        ];
    }

    public function reservations(): BelongsToMany
    {
        return $this->belongsToMany(Reservation::class, 'reservation_guests')
            ->withPivot(['reservation_room_request_id', 'is_primary', 'guest_type'])
            ->withTimestamps();
    }
}
