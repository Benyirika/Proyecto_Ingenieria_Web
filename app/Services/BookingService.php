<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchRoomCategoryRate;
use App\Models\ExtraService;
use App\Models\Guest;
use App\Models\HousekeepingReport;
use App\Models\Reservation;
use App\Models\ReservationRoomRequest;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    private const ACTIVE_STATUSES = ['pre_reserva', 'confirmada', 'check_in'];

    public function __construct(
        private PaymentService $payments,
        private AuditLogger $auditLogger,
    ) {}

    public function availableInventory(int $branchId, string $checkIn, string $checkOut): Collection
    {
        $branch = Branch::query()->where('is_active', true)->findOrFail($branchId);
        $start = CarbonImmutable::parse($checkIn)->toDateString();
        $end = CarbonImmutable::parse($checkOut)->toDateString();

        return RoomCategory::query()
            ->with(['branchRates' => fn ($query) => $query->where('branch_id', $branch->id)->where('is_active', true)])
            ->get()
            ->map(function (RoomCategory $category) use ($branch, $start, $end): RoomCategory {
                $rate = $category->branchRates->first();
                $inventory = Room::query()
                    ->where('branch_id', $branch->id)
                    ->where('room_category_id', $category->id)
                    ->where('physical_status', '!=', 'mantenimiento')
                    ->count();

                $reserved = ReservationRoomRequest::query()
                    ->where('room_category_id', $category->id)
                    ->whereHas('reservation', function ($query) use ($branch, $start, $end): void {
                        $query->where('branch_id', $branch->id)
                            ->whereIn('booking_status', self::ACTIVE_STATUSES)
                            ->where('check_in', '<', $end)
                            ->where('check_out', '>', $start);
                    })
                    ->count();

                $category->setAttribute('nightly_rate_pen', $rate?->nightly_rate_pen);
                $category->setAttribute('available_count', max(0, $inventory - $reserved));

                return $category;
            })
            ->filter(fn (RoomCategory $category): bool => $category->branchRates->isNotEmpty())
            ->values();
    }

    public function createReservation(array $data, ?User $creator, string $status): Reservation
    {
        return DB::transaction(function () use ($data, $creator, $status): Reservation {
            $branch = Branch::query()
                ->where('is_active', true)
                ->lockForUpdate()
                ->findOrFail((int) $data['branch_id']);

            if ($creator !== null && ! $creator->isAdmin() && (int) $creator->branch_id !== (int) $branch->id) {
                abort(404);
            }

            $checkIn = CarbonImmutable::parse($data['check_in'])->startOfDay();
            $checkOut = CarbonImmutable::parse($data['check_out'])->startOfDay();
            $nights = max(1, (int) $checkIn->diffInDays($checkOut));
            $roomSelections = collect($data['rooms'])->filter(fn (array $room): bool => (int) $room['quantity'] > 0);

            if ($roomSelections->isEmpty()) {
                throw ValidationException::withMessages([
                    'rooms' => 'Seleccione al menos una habitación.',
                ]);
            }

            $selectedCategories = RoomCategory::query()
                ->whereIn('id', $roomSelections->pluck('room_category_id')->map(fn ($id): int => (int) $id))
                ->get()
                ->keyBy('id');
            $requestsToCreate = [];
            $totalCapacity = 0;
            $roomSubtotal = 0.0;

            foreach ($roomSelections as $selection) {
                $categoryId = (int) $selection['room_category_id'];
                $quantity = (int) $selection['quantity'];
                $category = $selectedCategories->get($categoryId);
                $rate = BranchRoomCategoryRate::query()
                    ->where('branch_id', $branch->id)
                    ->where('room_category_id', $categoryId)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if ($category === null || $rate === null) {
                    throw ValidationException::withMessages([
                        'rooms' => 'Una de las categorías no está disponible en la sucursal elegida.',
                    ]);
                }

                $roomIds = Room::query()
                    ->where('branch_id', $branch->id)
                    ->where('room_category_id', $categoryId)
                    ->where('physical_status', '!=', 'mantenimiento')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->pluck('id');

                $reserved = ReservationRoomRequest::query()
                    ->where('room_category_id', $categoryId)
                    ->whereHas('reservation', function ($query) use ($branch, $checkIn, $checkOut): void {
                        $query->where('branch_id', $branch->id)
                            ->whereIn('booking_status', self::ACTIVE_STATUSES)
                            ->where('check_in', '<', $checkOut->toDateString())
                            ->where('check_out', '>', $checkIn->toDateString());
                    })
                    ->count();

                if ($quantity > max(0, $roomIds->count() - $reserved)) {
                    throw ValidationException::withMessages([
                        'rooms' => "No hay suficiente inventario de {$category->name} para esas fechas.",
                    ]);
                }

                $totalCapacity += $category->max_adults * $quantity;
                $roomSubtotal += (float) $rate->nightly_rate_pen * $nights * $quantity;

                for ($index = 0; $index < $quantity; $index++) {
                    $requestsToCreate[] = [
                        'room_category_id' => $categoryId,
                        'nightly_rate_pen' => (float) $rate->nightly_rate_pen,
                    ];
                }
            }

            $companions = $data['companions'] ?? [];
            $guestCount = 1 + count($companions);

            if ($guestCount > $totalCapacity) {
                throw ValidationException::withMessages([
                    'companions' => 'El número de huéspedes supera la capacidad total de las habitaciones seleccionadas.',
                ]);
            }

            $guestData = [$data['primary_guest'], ...$companions];
            $guestKeys = [];

            foreach ($guestData as $guest) {
                $key = strtoupper($guest['document_type']).':'.strtoupper(trim($guest['document_number']));

                if (isset($guestKeys[$key])) {
                    throw ValidationException::withMessages([
                        'companions' => 'No se puede registrar el mismo documento más de una vez en la reserva.',
                    ]);
                }

                $guestKeys[$key] = true;
            }

            $guests = [];

            foreach ($guestData as $index => $guest) {
                $identity = [
                    'document_type' => strtoupper($guest['document_type']),
                    'document_number' => strtoupper(trim($guest['document_number'])),
                ];
                $record = Guest::query()->firstOrCreate($identity, [
                    'first_name' => trim($guest['first_name']),
                    'last_name' => trim($guest['last_name']),
                    'email' => $guest['email'] ?? null,
                    'phone' => $guest['phone'] ?? null,
                ]);
                $record = Guest::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();

                if ($record->is_blacklisted) {
                    throw ValidationException::withMessages([
                        'primary_guest.document_number' => 'El documento pertenece a un huésped restringido; contacte a administración.',
                    ]);
                }

                $duplicate = Reservation::query()
                    ->whereIn('booking_status', self::ACTIVE_STATUSES)
                    ->where('check_in', '<', $checkOut->toDateString())
                    ->where('check_out', '>', $checkIn->toDateString())
                    ->whereHas('guests', fn ($query) => $query->where('guests.id', $record->id))
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'primary_guest.document_number' => 'Este huésped ya tiene una reserva activa que se cruza con las fechas solicitadas.',
                    ]);
                }

                $guests[] = [$record, $index === 0, $guest['guest_type'] ?? 'adulto'];
            }

            $services = collect($data['services'] ?? [])
                ->filter(fn (array $service): bool => (int) $service['quantity'] > 0)
                ->values();
            $serviceSubtotal = 0.0;

            if ($services->isNotEmpty()) {
                $serviceRecords = ExtraService::query()
                    ->where('is_active', true)
                    ->whereIn('id', $services->pluck('extra_service_id')->map(fn ($id): int => (int) $id))
                    ->get()
                    ->keyBy('id');

                foreach ($services as $serviceSelection) {
                    $service = $serviceRecords->get((int) $serviceSelection['extra_service_id']);

                    if ($service === null) {
                        throw ValidationException::withMessages([
                            'services' => 'Uno de los servicios seleccionados ya no está disponible.',
                        ]);
                    }

                    $serviceSubtotal += (float) $service->base_price_pen * (int) $serviceSelection['quantity'];
                }
            }

            $reservation = Reservation::create([
                'branch_id' => $branch->id,
                'created_by_user_id' => $creator?->id,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'booking_status' => $status,
                'total_amount_pen' => round($roomSubtotal + $serviceSubtotal, 2),
                'public_reference' => Str::upper(Str::random(12)),
                'confirmed_at' => $status === 'confirmada' ? now() : null,
            ]);

            foreach ($requestsToCreate as $roomRequest) {
                $reservation->roomRequests()->create($roomRequest);
            }

            foreach ($guests as [$guest, $isPrimary, $guestType]) {
                $reservation->guests()->attach($guest->id, [
                    'is_primary' => $isPrimary,
                    'guest_type' => $guestType,
                ]);
            }

            foreach ($services as $serviceSelection) {
                $service = $serviceRecords->get((int) $serviceSelection['extra_service_id']);
                $quantity = (int) $serviceSelection['quantity'];
                $unitPrice = (float) $service->base_price_pen;

                $reservation->extraServices()->attach($service->id, [
                    'quantity' => $quantity,
                    'unit_price_pen' => $unitPrice,
                    'subtotal_pen' => round($unitPrice * $quantity, 2),
                ]);
            }

            $this->auditLogger->record(
                $creator,
                (int) $branch->id,
                $creator === null ? 'reservation.pre_reservation' : 'reservation.created',
                'reservation',
                (int) $reservation->id,
                'Reserva '.$reservation->public_reference.' registrada como '.$status.'.',
            );

            return $reservation->load(['branch', 'guests', 'roomRequests.category', 'extraServices']);
        }, attempts: 3);
    }

    public function cancel(Reservation $reservation, User $actor, string $reason): Reservation
    {
        return DB::transaction(function () use ($reservation, $actor, $reason): Reservation {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if (! in_array($reservation->booking_status, ['pre_reserva', 'confirmada'], true)) {
                throw ValidationException::withMessages([
                    'reservation' => 'Solo se pueden cancelar reservas pendientes o confirmadas.',
                ]);
            }

            $checkInAt = $reservation->check_in->startOfDay();
            $hoursUntilCheckIn = ($checkInAt->getTimestamp() - now()->getTimestamp()) / 3600;

            if ($hoursUntilCheckIn >= 24) {
                $refund = round($this->payments->collectedAmountPen($reservation) * 0.5, 2);
                $this->payments->recordCancellationRefund($reservation, $actor, $refund);
            } else {
                $this->payments->recordNoShowPenalty($reservation, $actor);
            }

            $reservation->update([
                'booking_status' => 'cancelada',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            $this->auditLogger->record(
                $actor,
                (int) $reservation->branch_id,
                'reservation.cancelled',
                'reservation',
                (int) $reservation->id,
                'Reserva cancelada: '.$reason,
            );

            return $reservation->refresh();
        }, attempts: 3);
    }

    public function markNoShow(Reservation $reservation, User $actor): Reservation
    {
        return DB::transaction(function () use ($reservation, $actor): Reservation {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if ($reservation->booking_status !== 'confirmada' || $reservation->check_in->isFuture()) {
                throw ValidationException::withMessages([
                    'reservation' => 'Solo se puede marcar no-show a una reserva confirmada cuya fecha de llegada ya inició.',
                ]);
            }

            $this->payments->recordNoShowPenalty($reservation, $actor);
            $reservation->update([
                'booking_status' => 'no_show',
                'cancelled_at' => now(),
                'cancellation_reason' => 'No-show',
            ]);

            $this->auditLogger->record(
                $actor,
                (int) $reservation->branch_id,
                'reservation.no_show',
                'reservation',
                (int) $reservation->id,
                'Reserva marcada como no-show.',
            );

            return $reservation->refresh();
        }, attempts: 3);
    }

    public function confirm(Reservation $reservation, User $actor): Reservation
    {
        return DB::transaction(function () use ($reservation, $actor): Reservation {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if ($reservation->booking_status !== 'pre_reserva') {
                throw ValidationException::withMessages([
                    'reservation' => 'Solo se pueden confirmar pre-reservas.',
                ]);
            }

            $reservation->update([
                'booking_status' => 'confirmada',
                'confirmed_at' => now(),
            ]);

            $this->auditLogger->record($actor, (int) $reservation->branch_id, 'reservation.confirmed', 'reservation', (int) $reservation->id, 'Pre-reserva confirmada.');

            return $reservation->refresh();
        }, attempts: 3);
    }

    public function checkIn(Reservation $reservation, User $actor): Reservation
    {
        return DB::transaction(function () use ($reservation, $actor): Reservation {
            $reservation = Reservation::query()->with('roomRequests')->lockForUpdate()->findOrFail($reservation->id);

            if ($reservation->booking_status !== 'confirmada'
                || $reservation->check_in->isFuture()
                || $reservation->check_out->isPast()) {
                throw ValidationException::withMessages([
                    'reservation' => 'La reserva no está confirmada o está fuera de su periodo de alojamiento.',
                ]);
            }

            foreach ($reservation->roomRequests as $roomRequest) {
                $room = Room::query()
                    ->where('branch_id', $reservation->branch_id)
                    ->where('room_category_id', $roomRequest->room_category_id)
                    ->where('physical_status', 'limpia')
                    ->whereDoesntHave('currentStay')
                    ->orderBy('number')
                    ->lockForUpdate()
                    ->first();

                if ($room === null) {
                    throw ValidationException::withMessages([
                        'reservation' => 'No hay una habitación limpia disponible para completar el check-in.',
                    ]);
                }

                $room->reservationStays()->create([
                    'reservation_id' => $reservation->id,
                    'reservation_room_request_id' => $roomRequest->id,
                    'started_at' => now(),
                    'reason' => 'Check-in',
                    'moved_by_user_id' => $actor->id,
                ]);
            }

            $reservation->update([
                'booking_status' => 'check_in',
                'checked_in_at' => now(),
            ]);

            $this->auditLogger->record($actor, (int) $reservation->branch_id, 'reservation.check_in', 'reservation', (int) $reservation->id, 'Check-in completado.');

            return $reservation->refresh();
        }, attempts: 3);
    }

    public function checkOut(Reservation $reservation, User $actor): Reservation
    {
        return DB::transaction(function () use ($reservation, $actor): Reservation {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if ($reservation->booking_status !== 'check_in') {
                throw ValidationException::withMessages([
                    'reservation' => 'Solo se puede realizar check-out a huéspedes alojados.',
                ]);
            }

            $stays = $reservation->roomStays()->whereNull('ended_at')->lockForUpdate()->get();

            if ($stays->isEmpty()) {
                throw ValidationException::withMessages([
                    'reservation' => 'No hay habitaciones activas asociadas a esta estancia.',
                ]);
            }

            $rooms = Room::query()
                ->whereIn('id', $stays->pluck('room_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $roomsWithOpenIncidents = HousekeepingReport::query()
                ->whereIn('room_id', $rooms->modelKeys())
                ->where('report_type', 'averia')
                ->where('status', 'abierto')
                ->pluck('room_id');
            $roomWithPendingMaintenance = $rooms->first(
                fn (Room $room): bool => $room->has_maintenance_alert
                    || filled($room->maintenance_incident)
                    || $roomsWithOpenIncidents->contains(
                        fn ($roomId): bool => (int) $roomId === (int) $room->id,
                    ),
            );

            if ($roomWithPendingMaintenance !== null) {
                throw ValidationException::withMessages([
                    'reservation' => "No se puede completar el check-out: la habitación #{$roomWithPendingMaintenance->number} tiene una incidencia de mantenimiento pendiente de resolución.",
                ]);
            }

            foreach ($stays as $stay) {
                $stay->update(['ended_at' => now()]);
                $stay->room()->update([
                    'physical_status' => 'sucia',
                    'cleaning_notes' => 'Check-out completado. Requiere limpieza.',
                ]);
            }

            $reservation->update([
                'booking_status' => 'check_out',
                'checked_out_at' => now(),
            ]);

            $this->auditLogger->record($actor, (int) $reservation->branch_id, 'reservation.check_out', 'reservation', (int) $reservation->id, 'Check-out completado.');

            return $reservation->refresh();
        }, attempts: 3);
    }

    public function relocate(Reservation $reservation, int $stayId, Room $newRoom, User $actor, string $reason): Reservation
    {
        return DB::transaction(function () use ($reservation, $stayId, $newRoom, $actor, $reason): Reservation {
            $reservation = Reservation::query()->with('roomRequests')->lockForUpdate()->findOrFail($reservation->id);
            $newRoom = Room::query()->lockForUpdate()->findOrFail($newRoom->id);

            if ($reservation->booking_status !== 'check_in'
                || (int) $newRoom->branch_id !== (int) $reservation->branch_id
                || $newRoom->physical_status !== 'limpia'
                || $newRoom->currentStay()->exists()) {
                throw ValidationException::withMessages([
                    'new_room_id' => 'La habitación de destino debe estar limpia y libre en la misma sucursal.',
                ]);
            }

            $activeStay = $reservation->roomStays()
                ->whereKey($stayId)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if ($activeStay === null) {
                throw ValidationException::withMessages([
                    'reservation' => 'No se encontró una habitación activa para reubicar.',
                ]);
            }

            $roomRequest = $activeStay->roomRequest;

            if ((int) $newRoom->room_category_id !== (int) $roomRequest->room_category_id) {
                throw ValidationException::withMessages([
                    'new_room_id' => 'La habitación de destino debe ser del mismo tipo reservado.',
                ]);
            }

            $activeStay->update(['ended_at' => now(), 'reason' => 'Reubicación: '.$reason]);
            $activeStay->room()->update([
                'physical_status' => 'mantenimiento',
                'has_maintenance_alert' => true,
                'maintenance_incident' => $reason,
            ]);
            $newRoom->reservationStays()->create([
                'reservation_id' => $reservation->id,
                'reservation_room_request_id' => $roomRequest->id,
                'started_at' => now(),
                'reason' => $reason,
                'moved_by_user_id' => $actor->id,
            ]);
            HousekeepingReport::create([
                'room_id' => $activeStay->room_id,
                'reported_by_user_id' => $actor->id,
                'report_type' => 'averia',
                'description' => $reason,
                'status' => 'abierto',
            ]);

            $this->auditLogger->record($actor, (int) $reservation->branch_id, 'reservation.relocated', 'reservation', (int) $reservation->id, 'Huésped reubicado por incidencia: '.$reason);

            return $reservation->refresh();
        }, attempts: 3);
    }
}
