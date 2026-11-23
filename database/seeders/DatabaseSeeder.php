<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchRoomCategoryRate;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $branchesData = [
            ['name' => 'Centro Histórico', 'code' => 'CUS-CH', 'address' => 'Calle Plateros 324, Cusco', 'phone' => '084-231450'],
            ['name' => 'Magisterio', 'code' => 'CUS-MAG', 'address' => 'Av. La Cultura 1420, Urb. Magisterio', 'phone' => '084-248910'],
            ['name' => 'Pisac', 'code' => 'VAL-PIS', 'address' => 'Sector Valle Sagrado Km 32, Pisac', 'phone' => '084-203112'],
        ];
        $categoriesData = [
            ['name' => 'Habitación Personal (Single)', 'max_adults' => 1, 'base_price' => 120.00, 'amenities' => 'Cama individual, Wi-Fi'],
            ['name' => 'Habitación Doble (Double)', 'max_adults' => 2, 'base_price' => 200.00, 'amenities' => 'Cama Queen, Smart TV'],
            ['name' => 'Habitación Triple (Triple)', 'max_adults' => 3, 'base_price' => 310.00, 'amenities' => 'Tres camas, vista exterior'],
            ['name' => 'Suite Ejecutiva (Suite)', 'max_adults' => 4, 'base_price' => 480.00, 'amenities' => 'Jacuzzi, sala VIP, cama King'],
        ];

        $branches = collect($branchesData)->map(
            fn (array $data): Branch => Branch::query()->firstOrCreate(['code' => $data['code']], $data + ['is_active' => true]),
        );
        $categories = collect($categoriesData)->map(
            fn (array $data): RoomCategory => RoomCategory::query()->firstOrCreate(['name' => $data['name']], $data),
        );

        foreach ($branches as $branch) {
            foreach ($categories as $category) {
                BranchRoomCategoryRate::query()->firstOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'room_category_id' => $category->id,
                    ],
                    [
                        'nightly_rate_pen' => $category->base_price,
                        'is_active' => true,
                    ],
                );
            }

            for ($floor = 1; $floor <= 3; $floor++) {
                for ($number = 1; $number <= 8; $number++) {
                    $category = $categories[($floor + $number - 2) % $categories->count()];
                    Room::query()->firstOrCreate(
                        [
                            'branch_id' => $branch->id,
                            'number' => (string) (($floor * 100) + $number),
                        ],
                        [
                            'room_category_id' => $category->id,
                            'floor' => $floor,
                            'physical_status' => 'limpia',
                            'cleaning_notes' => 'Habitación lista para operación.',
                        ],
                    );
                }
            }
        }

        $adminEmail = config('luxstay.initial_admin.email');
        $adminPassword = config('luxstay.initial_admin.password');

        if (($adminEmail === null) !== ($adminPassword === null)) {
            throw new RuntimeException('Configure both LUXSTAY_ADMIN_EMAIL and LUXSTAY_ADMIN_PASSWORD to seed the initial administrator.');
        }

        if ($adminEmail !== null && $adminPassword !== null) {
            if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false || mb_strlen($adminPassword) < 12) {
                throw new RuntimeException('The initial administrator email must be valid and the password must contain at least 12 characters.');
            }

            User::query()->updateOrCreate(
                ['email' => $adminEmail],
                [
                    'branch_id' => null,
                    'name' => 'Administrador LuxStay',
                    'role' => 'admin',
                    'password' => Hash::make($adminPassword),
                    'is_active' => true,
                ],
            );
        }
    }
}
