<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || ($user->role === 'recepcion' && $user->branch_id !== null);
    }

    public function view(User $user, Reservation $reservation): Response|bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->role !== 'recepcion' || $user->branch_id === null) {
            return false;
        }

        return (int) $user->branch_id === (int) $reservation->branch_id
            ? true
            : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || ($user->role === 'recepcion' && $user->branch_id !== null);
    }

    public function update(User $user, Reservation $reservation): Response|bool
    {
        return $this->view($user, $reservation);
    }

    public function takePayment(User $user, Reservation $reservation): bool
    {
        return $this->update($user, $reservation);
    }
}
