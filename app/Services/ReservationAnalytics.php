<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Reservation;
use App\Models\ReservationRoomStay;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

class ReservationAnalytics
{
    public function revenueByBranch(string $from, string $to, ?int $branchId = null): Collection
    {
        $periodStart = CarbonImmutable::parse($from)->startOfDay();
        $periodEnd = CarbonImmutable::parse($to)->endOfDay();

        return Branch::query()
            ->leftJoin('reservations', 'reservations.branch_id', '=', 'branches.id')
            ->leftJoin('payments', function (JoinClause $join) use ($periodStart, $periodEnd): void {
                $join->on('payments.reservation_id', '=', 'reservations.id')
                    ->where('payments.status', 'registrado')
                    ->whereIn('payments.type', ['cobro', 'reembolso', 'penalidad'])
                    ->whereBetween('payments.created_at', [$periodStart, $periodEnd]);
            })
            ->when($branchId, fn ($query) => $query->where('branches.id', $branchId))
            ->groupBy('branches.id', 'branches.name')
            ->orderBy('branches.name')
            ->selectRaw(
                "branches.id AS branch_id, branches.name AS branch_name, COALESCE(SUM(CASE WHEN payments.type = 'reembolso' THEN -payments.amount_pen ELSE payments.amount_pen END), 0) AS revenue_pen"
            )
            ->get();
    }

    public function roomCategoriesByBranch(string $from, string $to, ?int $branchId = null): Collection
    {
        $periodStart = CarbonImmutable::parse($from)->startOfDay();
        $periodEnd = CarbonImmutable::parse($to)->endOfDay();

        return ReservationRoomStay::query()
            ->join('rooms', 'rooms.id', '=', 'reservation_room_stays.room_id')
            ->join('branches', 'branches.id', '=', 'rooms.branch_id')
            ->join('room_categories', 'room_categories.id', '=', 'rooms.room_category_id')
            ->where('reservation_room_stays.started_at', '<=', $periodEnd)
            ->where(function ($query) use ($periodStart): void {
                $query->whereNull('reservation_room_stays.ended_at')
                    ->orWhere('reservation_room_stays.ended_at', '>=', $periodStart);
            })
            ->when($branchId, fn ($query) => $query->where('branches.id', $branchId))
            ->groupBy('branches.id', 'branches.name', 'room_categories.id', 'room_categories.name')
            ->orderBy('branches.name')
            ->orderByDesc('rooms_used')
            ->selectRaw(
                'branches.id AS branch_id, branches.name AS branch_name, room_categories.name AS category_name, COUNT(DISTINCT reservation_room_stays.reservation_room_request_id) AS rooms_used'
            )
            ->get();
    }

    public function extraServices(string $from, string $to, ?int $branchId = null): Collection
    {
        return Reservation::query()
            ->join('reservation_extra_services', 'reservation_extra_services.reservation_id', '=', 'reservations.id')
            ->join('extra_services', 'extra_services.id', '=', 'reservation_extra_services.extra_service_id')
            ->join('branches', 'branches.id', '=', 'reservations.branch_id')
            ->whereBetween('reservations.created_at', [
                CarbonImmutable::parse($from)->startOfDay(),
                CarbonImmutable::parse($to)->endOfDay(),
            ])
            ->when($branchId, fn ($query) => $query->where('branches.id', $branchId))
            ->groupBy('branches.id', 'branches.name', 'extra_services.id', 'extra_services.name')
            ->orderBy('branches.name')
            ->orderByDesc('quantity')
            ->selectRaw(
                'branches.id AS branch_id, branches.name AS branch_name, extra_services.name AS service_name, SUM(reservation_extra_services.quantity) AS quantity'
            )
            ->get();
    }

    public function operatingTotals(?int $branchId = null): array
    {
        $reservations = Reservation::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));
        $rooms = Room::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));

        return [
            'total_rooms' => (clone $rooms)->count(),
            'clean_rooms' => (clone $rooms)->where('physical_status', 'limpia')->count(),
            'dirty_rooms' => (clone $rooms)->where('physical_status', 'sucia')->count(),
            'cleaning_rooms' => (clone $rooms)->where('physical_status', 'en_limpieza')->count(),
            'maintenance_rooms' => (clone $rooms)->where('physical_status', 'mantenimiento')->count(),
            'active_reservations' => (clone $reservations)->whereIn('booking_status', ['pre_reserva', 'confirmada', 'check_in'])->count(),
        ];
    }
}
