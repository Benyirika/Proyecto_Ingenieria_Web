<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $branchesData = [
            ['name' => 'Centro Histórico', 'code' => 'CUS-CH', 'address' => 'Calle Plateros 324, Cusco', 'phone' => '084-231450'],
            ['name' => 'Magisterio', 'code' => 'CUS-MAG', 'address' => 'Av. La Cultura 1420, Urb. Magisterio', 'phone' => '084-248910'],
            ['name' => 'Pisac', 'code' => 'VAL-PIS', 'address' => 'Sector Valle Sagrado Km 32, Pisac', 'phone' => '084-203112'],
        ];

        $branches = [];
        foreach ($branchesData as $bData) {
            $branches[] = Branch::create($bData);
        }

        // Master Admin
        User::create([
            'branch_id' => null,
            'name' => 'Daniel Prado (Master Admin)',
            'email' => 'admin@luxstay.com',
            'role' => 'admin',
            'password' => Hash::make('password123'),
        ]);

        // Personal por Sede
        foreach ($branches as $index => $branch) {
            $codeLower = strtolower($index === 0 ? 'centro' : ($index === 1 ? 'magisterio' : 'pisac'));

            User::create([
                'branch_id' => $branch->id,
                'name' => "Recepción {$branch->name}",
                'email' => "recepcion.{$codeLower}@luxstay.com",
                'role' => 'recepcion',
                'password' => Hash::make('password123'),
            ]);

            for ($l = 1; $l <= 2; $l++) {
                User::create([
                    'branch_id' => $branch->id,
                    'name' => "Auxiliar Limpieza {$l} - {$branch->name}",
                    'email' => "limpieza{$l}.{$codeLower}@luxstay.com",
                    'role' => 'limpieza',
                    'password' => Hash::make('password123'),
                ]);
            }
        }

        // Categorías de Habitación (Corregido el error tipográfico)
        $catSingle = RoomCategory::create(['name' => 'Habitación Personal (Single)', 'max_adults' => 1, 'base_price' => 120.00, 'amenities' => 'Cama Individual, Wi-Fi']);
        $catDouble = RoomCategory::create(['name' => 'Habitación Doble (Double)', 'max_adults' => 2, 'base_price' => 200.00, 'amenities' => 'Cama Queen, Smart TV']);
        $catTriple = RoomCategory::create(['name' => 'Habitación Triple (Triple)', 'max_adults' => 3, 'base_price' => 310.00, 'amenities' => '3 Camas, Vista Exterior']);
        $catSuite = RoomCategory::create(['name' => 'Suite Ejecutiva (Suite)', 'max_adults' => 4, 'base_price' => 480.00, 'amenities' => 'Jacuzzi, Sala VIP, King Size']);
        
        $categories = [$catSingle, $catDouble, $catTriple, $catSuite];

        // Generar 24 habitaciones por sede con numeración correlativa limpia (101-108, 201-208, 301-308)
        foreach ($branches as $branch) {
            $recepUser = User::where('branch_id', $branch->id)->where('role', 'recepcion')->first();

            for ($floor = 1; $floor <= 3; $floor++) {
                for ($h = 1; $h <= 8; $h++) {
                    $roomNumber = ($floor * 100) + $h; // 101, 102... 201... 308
                    $category = $categories[array_rand($categories)];
                    
                    $isOccupied = ($h <= 2); // Dejar algunas ocupadas por defecto para generar ingresos reales
                    $status = $isOccupied ? 'ocupada' : 'disponible';
                    $cleaningStatus = $isOccupied ? 'sucia' : 'limpia';

                    $room = Room::create([
                        'branch_id' => $branch->id,
                        'room_category_id' => $category->id,
                        'number' => (string)$roomNumber,
                        'floor' => $floor,
                        'status' => $status,
                        'cleaning_status' => $cleaningStatus,
                        'cleaning_notes' => $isOccupied ? 'Ocupado por huésped recurrente.' : 'Listo para nuevo check-in.',
                    ]);

                    // Si está ocupada, crear una reserva activa para que sume ingresos reales en S/
                    if ($isOccupied) {
                        Reservation::create([
                            'branch_id' => $branch->id,
                            'room_id' => $room->id,
                            'user_id' => $recepUser->id ?? 1,
                            'guest_name' => 'Huésped Test ' . $roomNumber . ' (' . $branch->code . ')',
                            'guest_dni' => '40000' . $roomNumber,
                            'check_in' => now()->subDays(1)->toDateString(),
                            'check_out' => now()->addDays(2)->toDateString(),
                            'total_price' => $category->base_price * 3, // 3 noches aseguradas
                            'status' => 'activa',
                        ]);
                    }
                }
            }
        }
    }
}