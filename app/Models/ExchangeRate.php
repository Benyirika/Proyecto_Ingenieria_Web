<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    protected $fillable = [
        'currency',
        'effective_on',
        'rate_to_pen',
        'managed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'effective_on' => 'date',
            'rate_to_pen' => 'decimal:6',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'managed_by_user_id');
    }
}
