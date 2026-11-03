<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomCategory extends Model
{
    protected $fillable = ['name', 'base_price', 'capacity', 'amenities'];

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }
}