<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Room;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReservationWorkflowController extends Controller
{
    public function confirm(Reservation $reservation, BookingService $bookings): RedirectResponse
    {
        Gate::authorize('update', $reservation);

        return $this->backToBranch($bookings->confirm($reservation, request()->user()), 'Pre-reserva confirmada.');
    }

    public function checkIn(Reservation $reservation, BookingService $bookings): RedirectResponse
    {
        Gate::authorize('update', $reservation);

        return $this->backToBranch($bookings->checkIn($reservation, request()->user()), 'Check-in completado.');
    }

    public function checkOut(Reservation $reservation, BookingService $bookings): RedirectResponse
    {
        Gate::authorize('update', $reservation);

        return $this->backToBranch($bookings->checkOut($reservation, request()->user()), 'Check-out completado; habitaciones enviadas a limpieza.');
    }

    public function cancel(Request $request, Reservation $reservation, BookingService $bookings): RedirectResponse
    {
        Gate::authorize('update', $reservation);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        return $this->backToBranch($bookings->cancel($reservation, $request->user(), $validated['reason']), 'Reserva cancelada; se aplicó la política de reembolso o penalidad.');
    }

    public function noShow(Reservation $reservation, BookingService $bookings): RedirectResponse
    {
        Gate::authorize('update', $reservation);

        return $this->backToBranch($bookings->markNoShow($reservation, request()->user()), 'Reserva marcada no-show y penalidad registrada.');
    }

    public function relocate(Request $request, Reservation $reservation, BookingService $bookings): RedirectResponse
    {
        Gate::authorize('update', $reservation);
        $validated = $request->validate([
            'stay_id' => ['required', 'integer', Rule::exists('reservation_room_stays', 'id')->where('reservation_id', $reservation->id)],
            'new_room_id' => ['required', 'integer', 'exists:rooms,id'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);
        $newRoom = Room::query()->findOrFail($validated['new_room_id']);

        $updated = $bookings->relocate(
            $reservation,
            (int) $validated['stay_id'],
            $newRoom,
            $request->user(),
            $validated['reason'],
        );

        return $this->backToBranch($updated, 'Reubicación completada; se registró la estancia y la incidencia.');
    }

    private function backToBranch(Reservation $reservation, string $message): RedirectResponse
    {
        return redirect()->route('dashboard', ['branch_id' => $reservation->branch_id])->with('success', $message);
    }
}
