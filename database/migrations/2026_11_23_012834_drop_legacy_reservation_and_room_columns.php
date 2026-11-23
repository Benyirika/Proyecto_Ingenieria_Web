<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $incompleteReservation = DB::table('reservations')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('reservation_room_requests')
                    ->whereColumn('reservation_room_requests.reservation_id', 'reservations.id');
            })
            ->exists();

        if ($incompleteReservation) {
            throw new RuntimeException('Legacy reservation data is not fully normalized; refusing to remove its source columns.');
        }

        $reservationWithoutGuest = DB::table('reservations')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('reservation_guests')
                    ->whereColumn('reservation_guests.reservation_id', 'reservations.id');
            })
            ->exists();

        if ($reservationWithoutGuest) {
            throw new RuntimeException('Legacy guest data is not fully normalized; refusing to remove its source columns.');
        }

        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropForeign(['room_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'room_id',
                'user_id',
                'guest_name',
                'guest_dni',
                'companions',
                'total_price',
                'currency',
                'exchange_rate_applied',
                'status',
                'checkout_reason',
                'refund_amount',
            ]);
        });

        Schema::table('rooms', function (Blueprint $table): void {
            $table->dropColumn(['status', 'cleaning_status']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Legacy reservation fields were removed after normalization and cannot be restored without a database backup.'
        );
    }
};
