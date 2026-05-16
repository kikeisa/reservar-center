<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BookingService
{
    private const TIMEZONE = 'America/Bogota';

    // Festivos Colombia 2026
    private const HOLIDAYS_2026 = [
        '2026-01-01', // Año Nuevo
        '2026-01-12', // Reyes Magos
        '2026-03-23', // San José
        '2026-04-02', // Jueves Santo
        '2026-04-03', // Viernes Santo
        '2026-05-01', // Día del Trabajo
        '2026-05-18', // Ascensión del Señor
        '2026-06-08', // Corpus Christi
        '2026-06-15', // Sagrado Corazón
        '2026-06-29', // San Pedro y San Pablo
        '2026-07-20', // Día de la Independencia
        '2026-08-07', // Batalla de Boyacá
        '2026-08-17', // Asunción de la Virgen
        '2026-10-12', // Día de la Raza
        '2026-11-02', // Todos los Santos
        '2026-11-16', // Independencia de Cartagena
        '2026-12-08', // Inmaculada Concepción
        '2026-12-25', // Navidad
    ];

    public function create(User $user, Service $service, string $startsAt): Reservation
    {
        $start = Carbon::parse($startsAt, self::TIMEZONE);
        $end   = $start->copy()->addMinutes($service->duration_minutes);
        $now   = Carbon::now(self::TIMEZONE);

        $this->validateBusinessHours($start);
        $this->validateAdvanceNotice($start, $now);
        $this->validateActiveReservationsLimit($user);
        $this->validateNoOverlap($service, $start, $end);

        return Reservation::create([
            'user_id'    => $user->id,
            'service_id' => $service->id,
            'starts_at'  => $start->utc(),
            'ends_at'    => $end->utc(),
            'status'     => 'active',
        ]);
    }

    public function cancel(Reservation $reservation): Reservation
    {
        if ($reservation->status === 'cancelled') {
            throw new \LogicException('La reserva ya está cancelada.');
        }

        $refund = $this->calculateRefund($reservation);

        $reservation->update([
            'status'        => 'cancelled',
            'refund_amount' => $refund,
        ]);

        return $reservation->fresh();
    }

    public function calculateRefund(Reservation $reservation): float
    {
        $service = $reservation->service;
        $user    = $reservation->user;

        if ($service->non_refundable) {
            return 0.0;
        }

        $now      = Carbon::now(self::TIMEZONE);
        $startsAt = Carbon::parse($reservation->starts_at)->setTimezone(self::TIMEZONE);
        $hoursUntilStart = $now->diffInMinutes($startsAt, false) / 60;

        if ($hoursUntilStart < 0) {
            // Reserva ya pasó
            return 0.0;
        }

        $price = (float) $service->price;

        if ($user->plan === 'premium') {
            if ($hoursUntilStart >= 4)  return $price;        // 100%
            if ($hoursUntilStart >= 1)  return $price * 0.5;  // 50%
            return 0.0;                                         // <1h → 0%
        }

        // standard
        if ($hoursUntilStart >= 24) return $price;        // 100%
        if ($hoursUntilStart >= 4)  return $price * 0.5;  // 50%
        return 0.0;                                         // <4h → 0%
    }

    // ─── Validaciones privadas ────────────────────────────────────────────────

    private function validateBusinessHours(Carbon $start): void
    {
        // Sin domingos
        if ($start->dayOfWeek === Carbon::SUNDAY) {
            throw new \InvalidArgumentException('No se puede reservar los domingos.');
        }

        // Sin festivos
        if (in_array($start->toDateString(), self::HOLIDAYS_2026, true)) {
            throw new \InvalidArgumentException('No se puede reservar en días festivos.');
        }

        // Lunes–Sábado 7:00–19:00
        $hour = $start->hour + $start->minute / 60;
        if ($hour < 7 || $hour >= 19) {
            throw new \InvalidArgumentException('Solo se puede reservar entre 7:00 y 19:00 hora Bogotá.');
        }
    }

    private function validateAdvanceNotice(Carbon $start, Carbon $now): void
    {
        if ($now->diffInMinutes($start, false) < 120) {
            throw new \InvalidArgumentException('La reserva debe hacerse con al menos 2 horas de anticipación.');
        }
    }

    private function validateActiveReservationsLimit(User $user): void
    {
        $active = Reservation::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('starts_at', '>', now())
            ->count();

        if ($active >= 3) {
            throw new \InvalidArgumentException('El usuario ya tiene 3 reservas activas futuras (límite máximo).');
        }
    }

    private function validateNoOverlap(Service $service, Carbon $start, Carbon $end): void
    {
        $overlap = Reservation::where('service_id', $service->id)
            ->where('status', 'active')
            ->where('starts_at', '<', $end->utc())
            ->where('ends_at', '>', $start->utc())
            ->exists();

        if ($overlap) {
            throw new \InvalidArgumentException('El profesional ya tiene una reserva en ese horario.');
        }
    }
}
