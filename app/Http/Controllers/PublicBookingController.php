<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePreReservationRequest;
use App\Models\Branch;
use App\Models\ExtraService;
use App\Models\Reservation;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicBookingController extends Controller
{
    public function index(Request $request, BookingService $bookings): View
    {
        $branches = Branch::query()->where('is_active', true)->orderBy('name')->get();
        $dates = $request->validate([
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
            'check_in' => ['nullable', 'date', 'after_or_equal:today'],
            'check_out' => ['nullable', 'date', 'after:check_in'],
        ]);
        $branchId = (int) ($dates['branch_id'] ?? $branches->first()?->id);
        $checkIn = $dates['check_in'] ?? today()->toDateString();
        $checkOut = $dates['check_out'] ?? today()->addDay()->toDateString();
        $inventory = $branchId > 0
            ? $bookings->availableInventory($branchId, $checkIn, $checkOut)
            : collect();

        return view('public.home', [
            'branches' => $branches,
            'selectedBranchId' => $branchId,
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'inventory' => $inventory,
            'extraServices' => ExtraService::query()->where('is_active', true)->orderBy('name')->get(),
            'formAction' => route('pre-reservations.store'),
            'isPublic' => true,
        ]);
    }

    public function store(StorePreReservationRequest $request, BookingService $bookings): RedirectResponse
    {
        $reservation = $bookings->createReservation($request->validated(), null, 'pre_reserva');

        return redirect()->route('pre-reservations.confirmation', $reservation->public_reference);
    }

    public function confirmation(string $reference): View
    {
        $reservation = Reservation::query()
            ->with(['branch', 'roomRequests.category'])
            ->where('public_reference', $reference)
            ->firstOrFail();

        return view('public.confirmation', compact('reservation'));
    }
}
