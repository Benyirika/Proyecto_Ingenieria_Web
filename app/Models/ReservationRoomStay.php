<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationRoomStay extends Model
{
    protected $fillable = [
        'reservation_id',
        'reservation_room_request_id',
        'room_id',
        'started_at',
        'ended_at',
        'reason',
        'moved_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function roomRequest(): BelongsTo
    {
        return $this->belongsTo(ReservationRoomRequest::class, 'reservation_room_request_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function movedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by_user_id');
    }
}
