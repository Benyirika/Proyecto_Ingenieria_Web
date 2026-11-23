<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table): void {
            $table->id();
            $table->string('document_type', 20);
            $table->string('document_number', 40);
            $table->string('first_name');
            $table->string('last_name')->default('');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_blacklisted')->default(false);
            $table->timestamps();
            $table->unique(['document_type', 'document_number']);
        });

        Schema::create('extra_services', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('base_price_pen', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('currency', 3);
            $table->date('effective_on');
            $table->decimal('rate_to_pen', 12, 6);
            $table->foreignId('managed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['currency', 'effective_on']);
        });

        Schema::create('branch_room_category_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_category_id')->constrained()->cascadeOnDelete();
            $table->decimal('nightly_rate_pen', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'room_category_id']);
        });

        Schema::create('reservation_room_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_category_id')->constrained()->restrictOnDelete();
            $table->decimal('nightly_rate_pen', 10, 2);
            $table->timestamps();
            $table->index(['room_category_id', 'reservation_id']);
        });

        Schema::create('reservation_guests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_room_request_id')->nullable()
                ->constrained('reservation_room_requests')->nullOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->string('guest_type', 16)->default('adulto');
            $table->timestamps();
            $table->unique(['reservation_id', 'guest_id']);
            $table->index(['reservation_id', 'is_primary']);
        });

        Schema::create('reservation_extra_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('extra_service_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->decimal('unit_price_pen', 10, 2);
            $table->decimal('subtotal_pen', 12, 2);
            $table->timestamps();
            $table->unique(
                ['reservation_id', 'extra_service_id'],
                'res_extra_services_reservation_service_unique',
            );
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('currency', 3);
            $table->decimal('amount_original', 12, 2);
            $table->decimal('exchange_rate_applied', 12, 6);
            $table->decimal('amount_pen', 12, 2);
            $table->string('payment_method', 32);
            $table->string('type', 16);
            $table->string('status', 16)->default('registrado');
            $table->string('reference', 100)->nullable();
            $table->timestamps();
            $table->index(['reservation_id', 'type', 'status']);
        });

        Schema::create('reservation_room_stays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_room_request_id')
                ->constrained('reservation_room_requests')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('reason', 255)->nullable();
            $table->foreignId('moved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['room_id', 'started_at', 'ended_at']);
            $table->index(['reservation_id', 'ended_at']);
        });

        Schema::create('housekeeping_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_type', 16);
            $table->string('description', 500);
            $table->string('status', 16)->default('abierto');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['room_id', 'status']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 60);
            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('summary', 500);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['branch_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::table('reservations', function (Blueprint $table): void {
            $table->string('booking_status', 20)->default('pre_reserva');
            $table->decimal('total_amount_pen', 12, 2)->default(0);
            $table->string('public_reference', 24)->nullable()->unique();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
        });

        Schema::table('rooms', function (Blueprint $table): void {
            $table->string('physical_status', 20)->default('limpia');
        });

        $this->seedCatalogs();
        $this->backfillBranchRoomRates();
        $this->backfillLegacyReservations();
        $this->backfillRoomPhysicalStatus();
    }

    public function down(): void
    {
        throw new RuntimeException(
            'This migration converts reservation history and is intentionally forward-only. Restore a database backup instead of rolling it back.'
        );
    }

    private function seedCatalogs(): void
    {
        $now = now();
        $services = [
            ['code' => 'champagne', 'name' => 'Botella de Champán de Bienvenida', 'base_price_pen' => 50.00],
            ['code' => 'massage', 'name' => 'Sesión de Masajes Relajantes (1h)', 'base_price_pen' => 100.00],
            ['code' => 'buffet', 'name' => 'Desayuno Buffet Ejecutivo por Día', 'base_price_pen' => 35.00],
            ['code' => 'parking', 'name' => 'Cochera Privada Vigilada', 'base_price_pen' => 25.00],
            ['code' => 'tour', 'name' => 'Excursión Guiada Valle Sagrado / City', 'base_price_pen' => 120.00],
            ['code' => 'extra_bed', 'name' => 'Cama Supletoria Adicional', 'base_price_pen' => 40.00],
        ];

        foreach ($services as $service) {
            DB::table('extra_services')->updateOrInsert(
                ['code' => $service['code']],
                [...$service, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }

    }

    private function backfillBranchRoomRates(): void
    {
        $now = now();

        foreach (DB::table('branches')->get() as $branch) {
            foreach (DB::table('room_categories')->get() as $category) {
                DB::table('branch_room_category_rates')->updateOrInsert(
                    [
                        'branch_id' => $branch->id,
                        'room_category_id' => $category->id,
                    ],
                    [
                        'nightly_rate_pen' => $category->base_price,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }
    }

    private function backfillLegacyReservations(): void
    {
        DB::table('reservations')->orderBy('id')->chunkById(100, function ($reservations): void {
            foreach ($reservations as $reservation) {
                DB::transaction(function () use ($reservation): void {
                    $room = DB::table('rooms')->where('id', $reservation->room_id)->first();

                    if ($room === null) {
                        throw new RuntimeException("Reservation {$reservation->id} references a missing room.");
                    }

                    $guest = $this->findOrCreateGuest(
                        $reservation->guest_dni,
                        $reservation->guest_name,
                        (int) $reservation->id,
                    );

                    $status = $this->mapStatus($reservation->status, $reservation->check_in, $reservation->check_out);
                    $exchangeRate = (float) ($reservation->exchange_rate_applied ?: 1);
                    $totalAmountPen = round((float) $reservation->total_price * $exchangeRate, 2);
                    $nights = max(1, (strtotime($reservation->check_out) - strtotime($reservation->check_in)) / 86400);
                    $requestId = DB::table('reservation_room_requests')
                        ->where('reservation_id', $reservation->id)
                        ->value('id');

                    if ($requestId === null) {
                        $requestId = DB::table('reservation_room_requests')->insertGetId([
                            'reservation_id' => $reservation->id,
                            'room_category_id' => $room->room_category_id,
                            'nightly_rate_pen' => round($totalAmountPen / $nights, 2),
                            'created_at' => $reservation->created_at,
                            'updated_at' => $reservation->updated_at,
                        ]);
                    }

                    $this->attachGuest($reservation->id, $guest, $requestId, true, 'adulto');
                    $this->backfillCompanions($reservation, $requestId);
                    $this->backfillServices($reservation);

                    DB::table('reservations')->where('id', $reservation->id)->update([
                        'booking_status' => $status,
                        'total_amount_pen' => $totalAmountPen,
                        'created_by_user_id' => $reservation->user_id,
                        'public_reference' => 'LEGACY-'.$reservation->id,
                        'confirmed_at' => $status === 'confirmada' ? $reservation->created_at : null,
                        'checked_in_at' => $status === 'check_in' ? $reservation->check_in.' 00:00:00' : null,
                        'checked_out_at' => $status === 'check_out' ? $reservation->check_out.' 00:00:00' : null,
                        'cancelled_at' => $status === 'cancelada' ? $reservation->updated_at : null,
                    ]);

                    if (in_array($status, ['check_in', 'check_out'], true)) {
                        $existingStay = DB::table('reservation_room_stays')
                            ->where('reservation_id', $reservation->id)
                            ->where('room_id', $room->id)
                            ->exists();

                        if (! $existingStay) {
                            DB::table('reservation_room_stays')->insert([
                                'reservation_id' => $reservation->id,
                                'reservation_room_request_id' => $requestId,
                                'room_id' => $room->id,
                                'started_at' => $reservation->check_in.' 00:00:00',
                                'ended_at' => $status === 'check_out' ? $reservation->check_out.' 00:00:00' : null,
                                'reason' => $reservation->checkout_reason ?: 'Migración de estancia existente',
                                'moved_by_user_id' => $reservation->user_id,
                                'created_at' => $reservation->created_at,
                                'updated_at' => $reservation->updated_at,
                            ]);
                        }
                    }

                    if ((float) $reservation->refund_amount > 0) {
                        DB::table('payments')->insert([
                            'reservation_id' => $reservation->id,
                            'recorded_by_user_id' => $reservation->user_id,
                            'currency' => $reservation->currency,
                            'amount_original' => $reservation->refund_amount,
                            'exchange_rate_applied' => $exchangeRate,
                            'amount_pen' => round((float) $reservation->refund_amount * $exchangeRate, 2),
                            'payment_method' => 'otro',
                            'type' => 'reembolso',
                            'status' => 'registrado',
                            'reference' => 'Migración de reembolso histórico',
                            'created_at' => $reservation->updated_at,
                            'updated_at' => $reservation->updated_at,
                        ]);
                    }
                });
            }
        });
    }

    private function findOrCreateGuest(?string $document, ?string $name, ?int $reservationId = null): int
    {
        $document = trim((string) $document);
        $documentType = 'DNI';

        if (preg_match('/^(DNI|PASAPORTE|CE)\s*:\s*(.+)$/iu', $document, $matches) === 1) {
            $documentType = strtoupper($matches[1]);
            $document = trim($matches[2]);
        }

        if ($document === '') {
            $documentType = 'LEGACY';
            $document = 'RES-'.($reservationId ?? throw new RuntimeException('Legacy guest identity is missing.'));
        }

        $existing = DB::table('guests')
            ->where('document_type', $documentType)
            ->where('document_number', $document)
            ->first();

        if ($existing !== null) {
            return (int) $existing->id;
        }

        $parts = preg_split('/\s+/u', trim((string) $name), 2) ?: [];
        $firstName = $parts[0] ?? 'Huésped';
        $lastName = $parts[1] ?? '';

        return (int) DB::table('guests')->insertGetId([
            'document_type' => $documentType,
            'document_number' => $document,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'is_blacklisted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function attachGuest(int $reservationId, int $guestId, int $requestId, bool $isPrimary, string $guestType): void
    {
        DB::table('reservation_guests')->updateOrInsert(
            ['reservation_id' => $reservationId, 'guest_id' => $guestId],
            [
                'reservation_room_request_id' => $requestId,
                'is_primary' => $isPrimary,
                'guest_type' => $guestType,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function backfillCompanions(object $reservation, int $requestId): void
    {
        $legacy = trim((string) $reservation->companions);

        if ($legacy === '') {
            return;
        }

        $parts = preg_split('/\s*\|\s*Servicios:\s*/u', $legacy, 2);
        $companions = trim($parts[0] ?? '');

        foreach (array_filter(array_map('trim', explode(';', $companions))) as $companion) {
            if (preg_match('/^\[(ADULTO|NINO|NIÑO)\s*\|\s*(DNI|PASAPORTE|CE):\s*([^\]]+)\]\s*(.+)$/iu', $companion, $matches) !== 1) {
                throw new RuntimeException("Reservation {$reservation->id} has companion data that cannot be normalized automatically.");
            }

            $guestId = $this->findOrCreateGuest($matches[2].': '.$matches[3], $matches[4]);
            $guestType = in_array(strtoupper($matches[1]), ['NINO', 'NIÑO'], true) ? 'nino' : 'adulto';
            $this->attachGuest((int) $reservation->id, $guestId, $requestId, false, $guestType);
        }
    }

    private function backfillServices(object $reservation): void
    {
        $legacy = trim((string) $reservation->companions);

        if (! str_contains($legacy, 'Servicios:')) {
            return;
        }

        $parts = preg_split('/\s*\|\s*Servicios:\s*/u', $legacy, 2);
        $serviceNames = array_filter(array_map('trim', explode(',', $parts[1] ?? '')));

        foreach ($serviceNames as $serviceName) {
            $service = DB::table('extra_services')->where('name', $serviceName)->first();

            if ($service === null) {
                throw new RuntimeException("Reservation {$reservation->id} references an unknown legacy service.");
            }

            DB::table('reservation_extra_services')->updateOrInsert(
                ['reservation_id' => $reservation->id, 'extra_service_id' => $service->id],
                [
                    'quantity' => 1,
                    'unit_price_pen' => $service->base_price_pen,
                    'subtotal_pen' => $service->base_price_pen,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    private function mapStatus(string $legacyStatus, string $checkIn, string $checkOut): string
    {
        if ($legacyStatus === 'activa') {
            $today = now()->toDateString();

            if ($checkIn > $today) {
                return 'confirmada';
            }

            return $checkOut <= $today ? 'check_out' : 'check_in';
        }

        return match ($legacyStatus) {
            'finalizada' => 'check_out',
            'cancelada' => 'cancelada',
            'no_show' => 'no_show',
            default => 'pre_reserva',
        };
    }

    private function backfillRoomPhysicalStatus(): void
    {
        DB::table('rooms')->orderBy('id')->chunkById(100, function ($rooms): void {
            foreach ($rooms as $room) {
                $physicalStatus = $room->status === 'mantenimiento'
                    ? 'mantenimiento'
                    : match ($room->cleaning_status) {
                        'sucia' => 'sucia',
                        'en_proceso' => 'en_limpieza',
                        default => 'limpia',
                    };

                DB::table('rooms')->where('id', $room->id)->update([
                    'physical_status' => $physicalStatus,
                ]);
            }
        });
    }
};
