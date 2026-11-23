<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchRoomCategoryRate;
use App\Models\ExchangeRate;
use App\Models\ExtraService;
use App\Models\HousekeepingReport;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationRoomRequest;
use App\Models\ReservationRoomStay;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use App\Services\BookingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ProductionFlowSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pre_reservation_uses_server_price_and_normalized_relations(): void
    {
        $branch = $this->createBranch();
        $category = $this->createCategory();
        $room = $this->createRoom($branch, $category, '101');
        BranchRoomCategoryRate::create([
            'branch_id' => $branch->id,
            'room_category_id' => $category->id,
            'nightly_rate_pen' => 220,
            'is_active' => true,
        ]);
        $service = ExtraService::create([
            'code' => 'breakfast',
            'name' => 'Desayuno',
            'base_price_pen' => 30,
            'is_active' => true,
        ]);

        $response = $this->post(route('pre-reservations.store'), $this->reservationPayload($branch, $category, [
            'services' => [['extra_service_id' => $service->id, 'quantity' => 2]],
            'total_amount_pen' => 1,
        ]));

        $reservation = Reservation::query()->firstOrFail();
        $response->assertRedirect(route('pre-reservations.confirmation', $reservation->public_reference));
        $this->get(route('pre-reservations.confirmation', $reservation->public_reference))
            ->assertOk()
            ->assertSee($reservation->public_reference);
        $this->assertSame('pre_reserva', $reservation->booking_status);
        $this->assertSame('280.00', $reservation->total_amount_pen);
        $this->assertDatabaseHas('reservation_room_requests', [
            'reservation_id' => $reservation->id,
            'room_category_id' => $category->id,
            'nightly_rate_pen' => 220,
        ]);
        $this->assertDatabaseHas('reservation_guests', [
            'reservation_id' => $reservation->id,
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('reservation_extra_services', [
            'reservation_id' => $reservation->id,
            'extra_service_id' => $service->id,
            'quantity' => 2,
            'unit_price_pen' => 30,
            'subtotal_pen' => 60,
        ]);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'physical_status' => 'limpia']);
        $this->get(route('home', [
            'branch_id' => $branch->id,
            'check_in' => today()->addDays(3)->toDateString(),
            'check_out' => today()->addDays(4)->toDateString(),
        ]))->assertOk()->assertSee('0 disponibles para estas fechas');
    }

    public function test_booking_rejects_guest_count_above_selected_room_capacity(): void
    {
        $branch = $this->createBranch();
        $category = $this->createCategory(maxAdults: 1);
        $this->createRoom($branch, $category, '101');
        BranchRoomCategoryRate::create([
            'branch_id' => $branch->id,
            'room_category_id' => $category->id,
            'nightly_rate_pen' => 220,
            'is_active' => true,
        ]);
        $payload = $this->reservationPayload($branch, $category);
        $payload['companions'] = [[
            'document_type' => 'DNI',
            'document_number' => '87654321',
            'first_name' => 'Ana',
            'last_name' => 'Lopez',
            'guest_type' => 'adulto',
        ]];

        $response = $this->post(route('pre-reservations.store'), $payload);

        $response->assertSessionHasErrors('companions');
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_booking_rejects_the_same_guest_document_for_overlapping_reservations(): void
    {
        $branch = $this->createBranch();
        $category = $this->createCategory();
        $this->createRoom($branch, $category, '101');
        $this->createRoom($branch, $category, '102');
        BranchRoomCategoryRate::create([
            'branch_id' => $branch->id,
            'room_category_id' => $category->id,
            'nightly_rate_pen' => 220,
            'is_active' => true,
        ]);
        $firstStart = today()->addDays(3)->toDateString();
        $firstEnd = today()->addDays(5)->toDateString();

        $this->post(route('pre-reservations.store'), $this->reservationPayload(
            $branch,
            $category,
            documentNumber: '33333333',
            checkIn: $firstStart,
            checkOut: $firstEnd,
        ))->assertRedirect();
        $response = $this->post(route('pre-reservations.store'), $this->reservationPayload(
            $branch,
            $category,
            documentNumber: '33333333',
            checkIn: today()->addDays(4)->toDateString(),
            checkOut: today()->addDays(6)->toDateString(),
        ));

        $response->assertSessionHasErrors('primary_guest.document_number');
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_reception_can_view_housekeeping_but_cannot_write_and_cleaners_can_complete_the_queue(): void
    {
        $branch = $this->createBranch();
        $category = $this->createCategory();
        $room = $this->createRoom($branch, $category, '101', 'sucia');
        $supplyRoom = $this->createRoom($branch, $category, '102', 'sucia');
        $brokenRoom = $this->createRoom($branch, $category, '103', 'sucia');
        $reception = $this->createUser($branch, 'recepcion');
        $cleaner = $this->createUser($branch, 'limpieza');
        $otherBranch = $this->createBranch('BR02');
        $otherRoom = $this->createRoom($otherBranch, $category, '201', 'sucia');
        $otherBranchReservation = Reservation::create([
            'branch_id' => $otherBranch->id,
            'check_in' => today()->addDays(3)->toDateString(),
            'check_out' => today()->addDays(4)->toDateString(),
            'booking_status' => 'confirmada',
            'total_amount_pen' => 200,
            'public_reference' => 'OTHER-BRANCH-01',
        ]);

        $this->actingAs($reception)->get(route('housekeeping.index'))
            ->assertOk()
            ->assertSee('Habitación #101');
        $this->get(route('reservations.create'))->assertOk()->assertSee('Consultar disponibilidad');
        $this->post(route('reservations.confirm', $otherBranchReservation))->assertNotFound();
        $this->patch(route('housekeeping.update', $room), ['physical_status' => 'en_limpieza'])
            ->assertForbidden();
        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'physical_status' => 'sucia']);

        $this->actingAs($cleaner)->get(route('reservations.create'))->assertForbidden();
        $this->post(route('payments.store', $otherBranchReservation), [
            'currency' => 'PEN',
            'amount_original' => 10,
            'payment_method' => 'efectivo',
        ])->assertForbidden();
        $this->get(route('admin.index'))->assertForbidden();
        $this->patch(route('housekeeping.update', $otherRoom), ['physical_status' => 'en_limpieza'])
            ->assertNotFound();
        $this->patch(route('housekeeping.update', $room), ['physical_status' => 'en_limpieza'])
            ->assertRedirect();
        $this->patch(route('housekeeping.update', $room), ['physical_status' => 'limpia'])
            ->assertRedirect();
        $this->post(route('housekeeping.reports.store', $supplyRoom), [
            'report_type' => 'insumo',
            'description' => 'Falta detergente para el baño',
        ])->assertRedirect();
        $this->post(route('housekeeping.reports.store', $brokenRoom), [
            'report_type' => 'averia',
            'description' => 'Grifo pierde agua',
        ])->assertRedirect();

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'physical_status' => 'limpia',
            'last_cleaned_by' => $cleaner->id,
        ]);
        $this->assertDatabaseHas('housekeeping_reports', [
            'room_id' => $supplyRoom->id,
            'report_type' => 'insumo',
            'status' => 'abierto',
        ]);
        $this->assertDatabaseHas('rooms', [
            'id' => $brokenRoom->id,
            'physical_status' => 'mantenimiento',
            'has_maintenance_alert' => true,
        ]);
    }

    public function test_check_in_relocation_and_check_out_preserve_room_stay_history(): void
    {
        $this->travelTo('2026-04-10 10:00:00');
        $branch = $this->createBranch();
        $category = $this->createCategory();
        $originalRoom = $this->createRoom($branch, $category, '101');
        $replacementRoom = $this->createRoom($branch, $category, '102');
        BranchRoomCategoryRate::create([
            'branch_id' => $branch->id,
            'room_category_id' => $category->id,
            'nightly_rate_pen' => 220,
            'is_active' => true,
        ]);
        $reception = $this->createUser($branch, 'recepcion');
        $response = $this->post(route('pre-reservations.store'), $this->reservationPayload(
            $branch,
            $category,
            checkIn: '2026-04-10',
            checkOut: '2026-04-12',
        ));
        $reservation = Reservation::query()->firstOrFail();

        $response->assertRedirect();
        $this->actingAs($reception)
            ->post(route('reservations.confirm', $reservation))->assertRedirect();
        $this->post(route('reservations.check-in', $reservation))->assertRedirect();
        $activeStay = ReservationRoomStay::query()
            ->where('reservation_id', $reservation->id)
            ->whereNull('ended_at')
            ->firstOrFail();
        $this->post(route('reservations.relocate', $reservation), [
            'stay_id' => $activeStay->id,
            'new_room_id' => $replacementRoom->id,
            'reason' => 'Fuga de agua en baño',
        ])->assertRedirect();
        $admin = $this->createUser(null, 'admin');
        $this->actingAs($admin)->post(route('housekeeping.reports.store', $replacementRoom), [
            'report_type' => 'averia',
            'description' => 'Falla eléctrica pendiente',
        ])->assertRedirect();
        $pendingReport = HousekeepingReport::query()
            ->where('room_id', $replacementRoom->id)
            ->where('status', 'abierto')
            ->firstOrFail();
        $currentStay = ReservationRoomStay::query()
            ->where('reservation_id', $reservation->id)
            ->whereNull('ended_at')
            ->firstOrFail();

        $this->actingAs($reception)
            ->post(route('reservations.check-out', $reservation))
            ->assertSessionHasErrors([
                'reservation' => 'No se puede completar el check-out: la habitación #102 tiene una incidencia de mantenimiento pendiente de resolución.',
            ]);
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'booking_status' => 'check_in',
        ]);
        $this->assertDatabaseHas('reservation_room_stays', [
            'id' => $currentStay->id,
            'ended_at' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('housekeeping.reports.resolve', $pendingReport))
            ->assertRedirect();
        $this->post(route('reservations.check-out', $reservation))->assertRedirect();
        $inventory = app(BookingService::class)->availableInventory(
            (int) $branch->id,
            '2026-04-12',
            '2026-04-13',
        );
        $this->assertSame(1, (int) $inventory->firstWhere('id', $category->id)->available_count);

        $this->assertDatabaseHas('reservation_room_stays', [
            'id' => $activeStay->id,
            'room_id' => $originalRoom->id,
            'reason' => 'Reubicación: Fuga de agua en baño',
        ]);
        $this->assertDatabaseHas('reservation_room_stays', [
            'reservation_id' => $reservation->id,
            'room_id' => $replacementRoom->id,
            'ended_at' => '2026-04-10 10:00:00',
        ]);
        $this->assertDatabaseHas('rooms', [
            'id' => $originalRoom->id,
            'physical_status' => 'mantenimiento',
            'has_maintenance_alert' => true,
            'maintenance_incident' => 'Fuga de agua en baño',
        ]);
        $this->assertDatabaseHas('rooms', ['id' => $replacementRoom->id, 'physical_status' => 'sucia']);
        $this->assertDatabaseHas('housekeeping_reports', [
            'room_id' => $originalRoom->id,
            'report_type' => 'averia',
            'status' => 'abierto',
        ]);
    }

    public function test_early_cancellation_refunds_half_of_collected_payments_and_no_show_records_remaining_penalty(): void
    {
        $this->travelTo('2026-05-03 00:00:00');
        $branch = $this->createBranch();
        $category = $this->createCategory();
        $this->createRoom($branch, $category, '101');
        BranchRoomCategoryRate::create([
            'branch_id' => $branch->id,
            'room_category_id' => $category->id,
            'nightly_rate_pen' => 200,
            'is_active' => true,
        ]);
        $reception = $this->createUser($branch, 'recepcion');
        ExchangeRate::create([
            'currency' => 'USD',
            'effective_on' => '2026-05-03',
            'rate_to_pen' => 4,
        ]);

        $this->post(route('pre-reservations.store'), $this->reservationPayload(
            $branch,
            $category,
            documentNumber: '11111111',
            checkIn: '2026-05-04',
            checkOut: '2026-05-05',
        ));
        $earlyReservation = Reservation::query()->firstOrFail();
        $this->actingAs($reception)->post(route('reservations.confirm', $earlyReservation));
        $this->post(route('payments.store', $earlyReservation), [
            'currency' => 'USD',
            'amount_original' => 25,
            'amount_pen' => 1,
            'payment_method' => 'efectivo',
        ])->assertRedirect();
        $this->assertDatabaseHas('payments', [
            'reservation_id' => $earlyReservation->id,
            'currency' => 'USD',
            'amount_original' => 25,
            'exchange_rate_applied' => 4,
            'amount_pen' => 100,
        ]);
        $this->post(route('reservations.cancel', $earlyReservation), ['reason' => 'Cambio de itinerario'])
            ->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'reservation_id' => $earlyReservation->id,
            'type' => 'reembolso',
            'amount_pen' => 50,
            'status' => 'pendiente',
        ]);

        $this->travelTo('2026-05-05 08:00:00');
        $this->createRoom($branch, $category, '102');
        $this->post(route('pre-reservations.store'), $this->reservationPayload(
            $branch,
            $category,
            documentNumber: '22222222',
            checkIn: '2026-05-05',
            checkOut: '2026-05-06',
        ));
        $noShowReservation = Reservation::query()->where('id', '!=', $earlyReservation->id)->firstOrFail();
        $this->post(route('reservations.confirm', $noShowReservation));
        $this->post(route('payments.store', $noShowReservation), [
            'currency' => 'PEN',
            'amount_original' => 50,
            'payment_method' => 'tarjeta',
        ])->assertRedirect();
        $this->post(route('reservations.no-show', $noShowReservation))->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'reservation_id' => $noShowReservation->id,
            'type' => 'penalidad',
            'amount_pen' => 150,
            'status' => 'pendiente',
        ]);
        $this->assertDatabaseHas('reservations', ['id' => $noShowReservation->id, 'booking_status' => 'no_show']);

        $this->post(route('pre-reservations.store'), $this->reservationPayload(
            $branch,
            $category,
            documentNumber: '33333333',
            checkIn: '2026-05-06',
            checkOut: '2026-05-07',
        ));
        $lateReservation = Reservation::query()
            ->whereNotIn('id', [$earlyReservation->id, $noShowReservation->id])
            ->firstOrFail();
        $this->post(route('reservations.confirm', $lateReservation));
        $this->post(route('payments.store', $lateReservation), [
            'currency' => 'PEN',
            'amount_original' => 50,
            'payment_method' => 'tarjeta',
        ])->assertRedirect();
        $this->post(route('reservations.cancel', $lateReservation), ['reason' => 'Cambio de itinerario'])
            ->assertRedirect();
        $this->assertDatabaseHas('payments', [
            'reservation_id' => $lateReservation->id,
            'type' => 'penalidad',
            'amount_pen' => 150,
            'status' => 'pendiente',
        ]);
        $this->post(route('pre-reservations.store'), $this->reservationPayload(
            $branch,
            $category,
            documentNumber: '44444444',
            checkIn: '2026-05-06',
            checkOut: '2026-05-07',
        ))->assertRedirect();
    }

    public function test_payment_currencies_are_converted_using_server_exchange_rates(): void
    {
        $branch = $this->createBranch();
        $category = $this->createCategory();
        $this->createRoom($branch, $category, '101');
        BranchRoomCategoryRate::create([
            'branch_id' => $branch->id,
            'room_category_id' => $category->id,
            'nightly_rate_pen' => 200,
            'is_active' => true,
        ]);
        $reception = $this->createUser($branch, 'recepcion');
        ExchangeRate::create([
            'currency' => 'USD',
            'effective_on' => today(),
            'rate_to_pen' => 4,
        ]);
        ExchangeRate::create([
            'currency' => 'EUR',
            'effective_on' => today(),
            'rate_to_pen' => 4.5,
        ]);
        $this->post(route('pre-reservations.store'), $this->reservationPayload($branch, $category));
        $reservation = Reservation::query()->firstOrFail();

        $this->actingAs($reception);
        foreach ([
            ['currency' => 'PEN', 'rate' => 1, 'amount_pen' => 10],
            ['currency' => 'USD', 'rate' => 4, 'amount_pen' => 40],
            ['currency' => 'EUR', 'rate' => 4.5, 'amount_pen' => 45],
        ] as $payment) {
            $this->post(route('payments.store', $reservation), [
                'currency' => $payment['currency'],
                'amount_original' => 10,
                'amount_pen' => 0.01,
                'payment_method' => 'efectivo',
            ])->assertRedirect();
            $this->assertDatabaseHas('payments', [
                'reservation_id' => $reservation->id,
                'currency' => $payment['currency'],
                'amount_original' => 10,
                'exchange_rate_applied' => $payment['rate'],
                'amount_pen' => $payment['amount_pen'],
            ]);
        }
    }

    public function test_initial_admin_seeding_is_idempotent_with_a_valid_password(): void
    {
        config([
            'luxstay.initial_admin.email' => 'admin@example.test',
            'luxstay.initial_admin.password' => 'valid-password-12',
        ]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('branches', 3);
        $this->assertDatabaseCount('rooms', 72);
        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.test',
            'role' => 'admin',
        ]);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_initial_admin_seeding_rejects_passwords_with_fewer_than_twelve_characters(): void
    {
        config([
            'luxstay.initial_admin.email' => 'admin@example.test',
            'luxstay.initial_admin.password' => 'áááááá',
        ]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The initial administrator email must be valid and the password must contain at least 12 characters.');

        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_dashboard_reports_only_recorded_penned_revenue_and_real_hotel_metrics(): void
    {
        $this->travelTo('2026-06-15 12:00:00');
        $branch = $this->createBranch();
        $category = $this->createCategory();
        $room = $this->createRoom($branch, $category, '101');
        $service = ExtraService::create([
            'code' => 'test-parking',
            'name' => 'Estacionamiento',
            'base_price_pen' => 25,
            'is_active' => true,
        ]);
        $admin = $this->createUser(null, 'admin');
        $reservation = Reservation::create([
            'branch_id' => $branch->id,
            'check_in' => '2026-06-15',
            'check_out' => '2026-06-16',
            'booking_status' => 'check_in',
            'total_amount_pen' => 100,
            'public_reference' => 'ANALYTICS-01',
        ]);
        $roomRequest = ReservationRoomRequest::create([
            'reservation_id' => $reservation->id,
            'room_category_id' => $category->id,
            'nightly_rate_pen' => 100,
        ]);
        ReservationRoomStay::create([
            'reservation_id' => $reservation->id,
            'reservation_room_request_id' => $roomRequest->id,
            'room_id' => $room->id,
            'started_at' => now(),
            'reason' => 'Check-in',
        ]);
        $reservation->extraServices()->attach($service->id, [
            'quantity' => 2,
            'unit_price_pen' => 25,
            'subtotal_pen' => 50,
        ]);
        Payment::create([
            'reservation_id' => $reservation->id,
            'recorded_by_user_id' => $admin->id,
            'currency' => 'USD',
            'amount_original' => 25,
            'exchange_rate_applied' => 4,
            'amount_pen' => 100,
            'payment_method' => 'tarjeta',
            'type' => 'cobro',
            'status' => 'registrado',
        ]);
        Payment::create([
            'reservation_id' => $reservation->id,
            'recorded_by_user_id' => $admin->id,
            'currency' => 'PEN',
            'amount_original' => 10,
            'exchange_rate_applied' => 1,
            'amount_pen' => 10,
            'payment_method' => 'transferencia',
            'type' => 'reembolso',
            'status' => 'registrado',
        ]);
        $emptyBranch = $this->createBranch('BR02');
        $this->createRoom($emptyBranch, $category, '201');
        ExchangeRate::create([
            'currency' => 'USD',
            'effective_on' => '2026-06-15',
            'rate_to_pen' => 4,
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard', [
            'branch_id' => $branch->id,
            'from' => '2026-06-15',
            'to' => '2026-06-15',
        ]));

        $response->assertOk();
        $response->assertViewHas('charts', function (array $charts): bool {
            return (float) $charts['revenue']->first()->revenue_pen === 90.0
                && (int) $charts['roomCategories']->first()->rooms_used === 1
                && (int) $charts['extraServices']->first()->quantity === 2;
        });
        $this->get(route('dashboard', [
            'from' => '2026-06-15',
            'to' => '2026-06-15',
        ]))
            ->assertOk()
            ->assertSeeInOrder([
                'Habitaciones · Sucursal BR01',
                '#101',
                'Habitaciones · Sucursal BR02',
                '#201',
            ])
            ->assertViewHas('charts', function (array $charts) use ($emptyBranch): bool {
                return $charts['revenue']->count() === 2
                    && (float) $charts['revenue']->firstWhere('branch_id', $emptyBranch->id)->revenue_pen === 0.0;
            });
        $this->get(route('admin.index'))->assertOk()->assertSee('Tasas de cambio a PEN');
    }

    private function createBranch(string $code = 'BR01'): Branch
    {
        return Branch::create([
            'name' => 'Sucursal '.$code,
            'code' => $code,
            'address' => 'Calle Hotel 123',
            'phone' => '987654321',
            'is_active' => true,
        ]);
    }

    private function createCategory(int $maxAdults = 2): RoomCategory
    {
        return RoomCategory::create([
            'name' => 'Habitación doble',
            'max_adults' => $maxAdults,
            'base_price' => 200,
            'amenities' => 'Wi-Fi',
        ]);
    }

    private function createRoom(Branch $branch, RoomCategory $category, string $number, string $status = 'limpia'): Room
    {
        return Room::create([
            'branch_id' => $branch->id,
            'room_category_id' => $category->id,
            'number' => $number,
            'floor' => 1,
            'physical_status' => $status,
        ]);
    }

    private function createUser(?Branch $branch, string $role): User
    {
        return User::create([
            'branch_id' => $branch?->id,
            'name' => ucfirst($role).' Test',
            'email' => $role.'-'.uniqid().'@example.test',
            'role' => $role,
            'password' => 'secure-password',
            'is_active' => true,
        ]);
    }

    private function reservationPayload(
        Branch $branch,
        RoomCategory $category,
        array $overrides = [],
        string $documentNumber = '12345678',
        ?string $checkIn = null,
        ?string $checkOut = null,
    ): array {
        return [
            'branch_id' => $branch->id,
            'check_in' => $checkIn ?? today()->addDays(3)->toDateString(),
            'check_out' => $checkOut ?? today()->addDays(4)->toDateString(),
            'rooms' => [['room_category_id' => $category->id, 'quantity' => 1]],
            'primary_guest' => [
                'document_type' => 'DNI',
                'document_number' => $documentNumber,
                'first_name' => 'Pedro',
                'last_name' => 'Solis',
                'email' => 'pedro@example.test',
                'phone' => '987654321',
            ],
            ...$overrides,
        ];
    }
}
