<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePreReservationRequest;
use App\Models\Branch;
use App\Models\ExtraService;
use App\Models\Reservation;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function create(Request $request, BookingService $bookings): View
    {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('create', Reservation::class);

        $branches = $user->isAdmin()
            ? Branch::query()->where('is_active', true)->orderBy('name')->get()
            : collect([$user->branch]);
        $selectedBranchId = $user->isAdmin()
            ? (int) request('branch_id', $branches->first()?->id)
            : (int) $user->branch_id;
        $dates = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'check_in' => ['nullable', 'date', 'after_or_equal:today'],
            'check_out' => ['nullable', 'date', 'after:check_in'],
        ]);
        if ($user->isAdmin() && $selectedBranchId > 0) {
            $selectedBranchId = (int) Branch::query()->where('is_active', true)->findOrFail($selectedBranchId)->id;
        }
        $checkIn = $dates['check_in'] ?? today()->toDateString();
        $checkOut = $dates['check_out'] ?? today()->addDay()->toDateString();
        $inventory = $selectedBranchId
            ? $bookings->availableInventory($selectedBranchId, $checkIn, $checkOut)
            : collect();

        return view('reservations.create', [
            'branches' => $branches,
            'selectedBranchId' => $selectedBranchId,
            'inventory' => $inventory,
            'extraServices' => ExtraService::query()->where('is_active', true)->orderBy('name')->get(),
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'formAction' => route('reservations.store'),
            'isPublic' => false,
        ]);
    }

    public function store(StorePreReservationRequest $request, BookingService $bookings): RedirectResponse
    {
        Gate::authorize('create', Reservation::class);

        $reservation = $bookings->createReservation($request->validated(), $request->user(), 'confirmada');

        return redirect()->route('dashboard', ['branch_id' => $reservation->branch_id])
            ->with('success', 'Reserva '.$reservation->public_reference.' confirmada. Registre el pago y realice el check-in al llegar el huésped.');
    }
}
