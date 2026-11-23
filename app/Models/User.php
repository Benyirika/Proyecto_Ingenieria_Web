<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['branch_id', 'name', 'email', 'role', 'password', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdReservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'created_by_user_id');
    }

    public function recordedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'recorded_by_user_id');
    }

    public function housekeepingReports(): HasMany
    {
        return $this->hasMany(HousekeepingReport::class, 'reported_by_user_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function belongsToBranch(int $branchId): bool
    {
        return $this->isAdmin() || $this->branch_id === $branchId;
    }
}
