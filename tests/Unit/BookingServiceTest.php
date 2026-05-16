<?php

namespace Tests\Unit;

use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $bookingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bookingService = new BookingService();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeUser(string $plan = 'standard'): User
    {
        return User::factory()->create(['plan' => $plan, 'role' => 'cliente']);
    }

    private function makeService(array $attrs = []): Service
    {
        return Service::factory()->create(array_merge([
            'duration_minutes' => 60,
            'price'            => 100000,
            'professional_id'  => 1,
            'non_refundable'   => false,
        ], $attrs));
    }

    /** Próximo lunes no festivo a las 09:00 Bogotá */
    private function validFutureSlot(): string
    {
        $holidays = [
            '2026-01-01','2026-01-12','2026-03-23','2026-04-02','2026-04-03',
            '2026-05-01','2026-05-18','2026-06-08','2026-06-15','2026-06-29',
            '2026-07-20','2026-08-07','2026-08-17','2026-10-12','2026-11-02',
            '2026-11-16','2026-12-08','2026-12-25',
        ];

        $slot = Carbon::now('America/Bogota')->next('Monday')->setHour(9)->setMinute(0)->setSecond(0);

        while (in_array($slot->toDateString(), $holidays, true)) {
            $slot->addWeek();
        }

        return $slot->toDateTimeString();
    }

    // ── Test 1: Reserva válida se registra ────────────────────────────────────

    public function test_create_valid_reservation(): void
    {
        $user    = $this->makeUser();
        $service = $this->makeService();
        $slot    = $this->validFutureSlot();

        $reservation = $this->bookingService->create($user, $service, $slot);

        $this->assertDatabaseHas('reservations', [
            'id'      => $reservation->id,
            'status'  => 'active',
        ]);
    }

    // ── Test 2: Domingo → rechazar ────────────────────────────────────────────

    public function test_reject_reservation_on_sunday(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('domingos');

        $user    = $this->makeUser();
        $service = $this->makeService();
        $sunday  = Carbon::now('America/Bogota')->next('Sunday')->setHour(10)->toDateTimeString();

        $this->bookingService->create($user, $service, $sunday);
    }

    // ── Test 3: Menos de 2h de anticipación → rechazar ────────────────────────

    public function test_reject_reservation_with_less_than_2h_advance(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('2 horas');

        $user    = $this->makeUser();
        $service = $this->makeService();
        // 1 hora en el futuro, dentro de horario laboral
        $soon = Carbon::now('America/Bogota')->addHour()->toDateTimeString();

        $this->bookingService->create($user, $service, $soon);
    }

    // ── Test 4: Reembolso 100% estándar con >24h ──────────────────────────────

    public function test_standard_user_gets_full_refund_over_24h(): void
    {
        $user    = $this->makeUser('standard');
        $service = $this->makeService(['price' => 200000]);

        $startsAt = Carbon::now('America/Bogota')->addHours(48)->utc();
        $endsAt   = $startsAt->copy()->addMinutes(60);

        $reservation = Reservation::factory()->create([
            'user_id'    => $user->id,
            'service_id' => $service->id,
            'starts_at'  => $startsAt,
            'ends_at'    => $endsAt,
            'status'     => 'active',
        ]);

        $refund = $this->bookingService->calculateRefund($reservation);

        $this->assertEquals(200000.0, $refund);
    }

    // ── Test 5: Usuario premium con 3h de anticipación → 50% ─────────────────

    public function test_premium_user_gets_50_percent_refund_between_1h_and_4h(): void
    {
        $user    = $this->makeUser('premium');
        $service = $this->makeService(['price' => 200000]);

        $startsAt = Carbon::now('America/Bogota')->addHours(3)->utc();
        $endsAt   = $startsAt->copy()->addMinutes(60);

        $reservation = Reservation::factory()->create([
            'user_id'    => $user->id,
            'service_id' => $service->id,
            'starts_at'  => $startsAt,
            'ends_at'    => $endsAt,
            'status'     => 'active',
        ]);

        $refund = $this->bookingService->calculateRefund($reservation);

        $this->assertEquals(100000.0, $refund);
    }

    // ── Test 6 (bonus): Solapamiento de profesional → rechazar ───────────────

    public function test_reject_overlapping_reservation_same_professional(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('profesional');

        $service = $this->makeService(['professional_id' => 5]);
        $user1   = $this->makeUser();
        $user2   = $this->makeUser();
        $slot    = $this->validFutureSlot();

        // Primera reserva OK
        $this->bookingService->create($user1, $service, $slot);

        // Segunda reserva mismo slot mismo profesional → debe fallar
        $this->bookingService->create($user2, $service, $slot);
    }

    // ── Test 7: Límite de 3 reservas activas ─────────────────────────────────

    public function test_reject_when_user_has_3_active_future_reservations(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('límite');

        $user = $this->makeUser();

        // Crear 3 reservas activas futuras directamente en BD
        for ($i = 1; $i <= 3; $i++) {
            $svc      = $this->makeService(['professional_id' => $i + 10]);
            $startsAt = Carbon::now('America/Bogota')->addDays($i + 5)->setHour(10)->utc();
            Reservation::factory()->create([
                'user_id'    => $user->id,
                'service_id' => $svc->id,
                'starts_at'  => $startsAt,
                'ends_at'    => $startsAt->copy()->addHour(),
                'status'     => 'active',
            ]);
        }

        $service = $this->makeService(['professional_id' => 99]);
        $slot    = $this->validFutureSlot();

        $this->bookingService->create($user, $service, $slot);
    }

    // ── Test 8: Servicio non_refundable → siempre 0% ─────────────────────────

    public function test_non_refundable_service_returns_zero_refund(): void
    {
        $user    = $this->makeUser('premium');
        $service = $this->makeService(['price' => 300000, 'non_refundable' => true]);

        $startsAt = Carbon::now('America/Bogota')->addDays(5)->utc();
        $reservation = Reservation::factory()->create([
            'user_id'    => $user->id,
            'service_id' => $service->id,
            'starts_at'  => $startsAt,
            'ends_at'    => $startsAt->copy()->addHour(),
            'status'     => 'active',
        ]);

        $refund = $this->bookingService->calculateRefund($reservation);

        $this->assertEquals(0.0, $refund);
    }
}
