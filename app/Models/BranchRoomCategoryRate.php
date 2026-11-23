<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchRoomCategoryRate extends Model
{
    protected $fillable = [
        'branch_id',
        'room_category_id',
        'nightly_rate_pen',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'nightly_rate_pen' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RoomCategory::class, 'room_category_id');
    }
}
