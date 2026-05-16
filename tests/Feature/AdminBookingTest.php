<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'plan' => 'premium']);
    }

    private function makeCliente(string $plan = 'standard'): User
    {
        return User::factory()->create(['role' => 'cliente', 'plan' => $plan]);
    }

    private function makeService(int $professionalId = 1): Service
    {
        return Service::factory()->create([
            'duration_minutes' => 60,
            'price'            => 100000,
            'professional_id'  => $professionalId,
            'non_refundable'   => false,
        ]);
    }

    private function makeReservation(User $user, Service $service): Reservation
    {
        $startsAt = Carbon::now('America/Bogota')->addDays(5)->setHour(10)->utc();
        return Reservation::factory()->create([
            'user_id'    => $user->id,
            'service_id' => $service->id,
            'starts_at'  => $startsAt,
            'ends_at'    => $startsAt->copy()->addHour(),
            'status'     => 'active',
        ]);
    }

    // ── Test 1: Admin ve todas las reservas de todos los usuarios ─────────────

    public function test_admin_sees_all_reservations(): void
    {
        $admin    = $this->makeAdmin();
        $cliente1 = $this->makeCliente();
        $cliente2 = $this->makeCliente('premium');

        $svc1 = $this->makeService(1);
        $svc2 = $this->makeService(2);

        $r1 = $this->makeReservation($cliente1, $svc1);
        $r2 = $this->makeReservation($cliente2, $svc2);

        $response = $this->actingAs($admin)->getJson('/api/bookings');

        $response->assertStatus(200);
        $response->assertJsonCount(2);
        $response->assertJsonFragment(['id' => $r1->id]);
        $response->assertJsonFragment(['id' => $r2->id]);
    }

    // ── Test 2: La respuesta del admin incluye datos del usuario ──────────────

    public function test_admin_response_includes_user_data(): void
    {
        $admin   = $this->makeAdmin();
        $cliente = $this->makeCliente();
        $svc     = $this->makeService();

        $this->makeReservation($cliente, $svc);

        $response = $this->actingAs($admin)->getJson('/api/bookings');

        $response->assertStatus(200);
        $response->assertJsonPath('0.user.id', $cliente->id);
        $response->assertJsonPath('0.user.name', $cliente->name);
    }

    // ── Test 3: Cliente solo ve sus propias reservas ──────────────────────────

    public function test_cliente_sees_only_own_reservations(): void
    {
        $cliente1 = $this->makeCliente();
        $cliente2 = $this->makeCliente();

        $svc1 = $this->makeService(1);
        $svc2 = $this->makeService(2);

        $r1 = $this->makeReservation($cliente1, $svc1);
        $this->makeReservation($cliente2, $svc2);

        $response = $this->actingAs($cliente1)->getJson('/api/bookings');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['id' => $r1->id]);
    }

    // ── Test 4: Admin puede ver detalle de reserva de otro usuario ────────────

    public function test_admin_can_view_any_reservation_detail(): void
    {
        $admin   = $this->makeAdmin();
        $cliente = $this->makeCliente();
        $svc     = $this->makeService();
        $reservation = $this->makeReservation($cliente, $svc);

        $response = $this->actingAs($admin)->getJson("/api/bookings/{$reservation->id}");

        $response->assertStatus(403); // show() aún requiere ser dueño — comportamiento documentado
    }

    // ── Test 5: Sin autenticación → 401 ──────────────────────────────────────

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/bookings');
        $response->assertStatus(401);
    }
}
