<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RoomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || in_array($user->role, ['recepcion', 'limpieza'], true)
            && $user->branch_id !== null;
    }

    public function view(User $user, Room $room): Response|bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! in_array($user->role, ['recepcion', 'limpieza'], true) || $user->branch_id === null) {
            return false;
        }

        return (int) $user->branch_id === (int) $room->branch_id
            ? true
            : Response::denyAsNotFound();
    }

    public function update(User $user, Room $room): Response|bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->role !== 'limpieza' || $user->branch_id === null) {
            return false;
        }

        return (int) $user->branch_id === (int) $room->branch_id
            ? true
            : Response::denyAsNotFound();
    }

    public function report(User $user, Room $room): Response|bool
    {
        return $this->update($user, $room);
    }

    public function resolveReport(User $user, Room $room): Response|bool
    {
        return $user->isAdmin()
            || ($user->role === 'limpieza'
                && $user->branch_id !== null
                && (int) $user->branch_id === (int) $room->branch_id);
    }
}
