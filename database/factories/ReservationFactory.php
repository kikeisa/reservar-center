<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationFactory extends Factory
{
    public function definition(): array
    {
        $service  = Service::factory()->create();
        $startsAt = now('America/Bogota')->next('Monday')->setHour(9)->setMinute(0)->setSecond(0);
        $endsAt   = $startsAt->copy()->addMinutes($service->duration_minutes);

        return [
            'user_id'       => User::factory(),
            'service_id'    => $service->id,
            'starts_at'     => $startsAt->utc(),
            'ends_at'       => $endsAt->utc(),
            'status'        => 'active',
            'refund_amount' => null,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled']);
    }
}
