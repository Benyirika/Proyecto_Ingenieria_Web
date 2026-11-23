<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Services\ReservationAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, ReservationAnalytics $analytics): View
    {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('viewAny', Reservation::class);

        $filters = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $branches = $user->isAdmin()
            ? Branch::query()->where('is_active', true)->orderBy('name')->get()
            : collect([$user->branch]);
        $selectedBranchId = $user->isAdmin()
            ? (isset($filters['branch_id']) ? (int) $filters['branch_id'] : null)
            : (int) $user->branch_id;
        $from = $filters['from'] ?? today()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? today()->toDateString();

        $rooms = Room::query()
            ->with(['branch', 'category', 'currentStay.reservation.primaryGuest'])
            ->when($selectedBranchId !== null, fn ($query) => $query->where('branch_id', $selectedBranchId))
            ->orderBy('branch_id')
            ->orderBy('number')
            ->get();

        $reservations = Reservation::query()
            ->with([
                'branch',
                'primaryGuest',
                'roomRequests.category',
                'roomStays' => fn ($query) => $query->whereNull('ended_at')->with('room'),
                'payments',
            ])
            ->whereIn('booking_status', ['pre_reserva', 'confirmada', 'check_in'])
            ->when($selectedBranchId !== null, fn ($query) => $query->where('branch_id', $selectedBranchId))
            ->orderBy('check_in')
            ->orderBy('id')
            ->get();

        $pendingPayments = Payment::query()
            ->with(['reservation.branch', 'reservation.primaryGuest'])
            ->where('status', 'pendiente')
            ->whereHas('reservation', fn ($query) => $query->when($selectedBranchId !== null, fn ($reservationQuery) => $reservationQuery->where('branch_id', $selectedBranchId)))
            ->orderBy('created_at')
            ->get();

        $charts = $user->isAdmin() ? [
            'revenue' => $analytics->revenueByBranch($from, $to, $selectedBranchId),
            'roomCategories' => $analytics->roomCategoriesByBranch($from, $to, $selectedBranchId),
            'extraServices' => $analytics->extraServices($from, $to, $selectedBranchId),
        ] : [];

        return view('dashboard.index', [
            'branches' => $branches,
            'selectedBranchId' => $selectedBranchId,
            'rooms' => $rooms,
            'reservations' => $reservations,
            'pendingPayments' => $pendingPayments,
            'stats' => $analytics->operatingTotals($selectedBranchId),
            'charts' => $charts,
            'from' => $from,
            'to' => $to,
        ]);
    }
}
