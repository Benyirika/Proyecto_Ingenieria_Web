<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Sucursales
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Centro Histórico, Magisterio, Pisac
            $table->string('code', 10)->unique();
            $table->string('address');
            $table->string('phone', 20);
            $table->timestamps();
        });

        // 2. Modificar usuarios (Credenciales estrictas por sede y roles)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->nullOnDelete();
            $table->enum('role', ['admin', 'recepcion', 'limpieza'])->default('recepcion')->after('email');
        });

        // 3. Categorías de Habitación (Siguiendo el estándar internacional de capacidad)
        Schema::create('room_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Single, Double / Twin, Triple, Quad, Suite, etc.
            $table->integer('max_adults'); // 1, 2, 3, 4 según regla de negocio
            $table->decimal('base_price', 8, 2);
            $table->string('amenities');
            $table->timestamps();
        });

        // 4. Habitaciones (20 a 30 por sede, mínimo 4 tipos diferentes)
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('room_category_id')->constrained('room_categories')->restrictOnDelete();
            $table->string('number', 10); // Ej. 101, 102... 305
            $table->integer('floor');
            $table->enum('status', ['disponible', 'ocupada', 'mantenimiento', 'no_show'])->default('disponible');
            
            // Housekeeping simplificado (Sucia / Limpia) + Reporte de Averías con Alerta Máxima
            $table->enum('cleaning_status', ['limpia', 'sucia'])->default('limpia');
            $table->text('cleaning_notes')->nullable();
            $table->boolean('has_maintenance_alert')->default(false); // Alerta máxima para recepción
            $table->text('maintenance_incident')->nullable(); // Manchas, olores, destrozos
            $table->timestamp('last_cleaned_at')->nullable();
            $table->foreignId('last_cleaned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'number']);
        });

        // 5. Reservas con soporte para Multidivisa, Acompañantes y Tipos de Check-out
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('guest_name');
            $table->string('guest_dni', 15);
            $table->json('companions')->nullable(); // Acompañantes adicionales
            $table->date('check_in');
            $table->date('check_out');
            $table->decimal('total_price', 10, 2);
            $table->string('currency', 5)->default('PEN'); // PEN, USD, EUR
            $table->decimal('exchange_rate_applied', 8, 2)->default(1.00);
            
            // Estados y Motivos de Finalización
            $table->enum('status', ['activa', 'finalizada', 'cancelada', 'no_show'])->default('activa');
            $table->string('checkout_reason')->nullable(); // 'normal', 'urgencia_emergencia'
            $table->decimal('refund_amount', 10, 2)->default(0.00); // Para devoluciones del 50% o proporcionales
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('room_categories');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['branch_id', 'role']);
        });
        Schema::dropIfExists('branches');
    }
};