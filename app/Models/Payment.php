<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'reservation_id',
        'recorded_by_user_id',
        'currency',
        'amount_original',
        'exchange_rate_applied',
        'amount_pen',
        'payment_method',
        'type',
        'status',
        'reference',
    ];

    protected function casts(): array
    {
        return [
            'amount_original' => 'decimal:2',
            'exchange_rate_applied' => 'decimal:6',
            'amount_pen' => 'decimal:2',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
