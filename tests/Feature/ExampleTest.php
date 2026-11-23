<?php

namespace Tests\Feature;

use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_portal_is_available_without_authentication(): void
    {
        Branch::create([
            'name' => 'Sucursal Centro',
            'code' => 'CENTRO',
            'address' => 'Calle Centro 123',
            'phone' => '987654321',
            'is_active' => true,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Solicita tu pre-reserva');
        $this->assertGuest();
    }
}
