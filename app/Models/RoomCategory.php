<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomCategory extends Model
{
    protected $fillable = ['name', 'max_adults', 'base_price', 'amenities'];

    protected $casts = [
        'max_adults' => 'integer',
        'base_price' => 'decimal:2',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function branchRates(): HasMany
    {
        return $this->hasMany(BranchRoomCategoryRate::class);
    }
}
