<?php

namespace Database\Seeders;

use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(
            file_get_contents(database_path('seeders/seed.json')),
            true
        );

        // ── Usuarios ──────────────────────────────────────────────────────────
        $userMap = [];
        foreach ($data['users'] as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name'     => $userData['name'],
                    'password' => Hash::make($userData['password']),
                    'role'     => $userData['role'] ?? 'cliente',
                    'plan'     => $userData['plan'] ?? 'standard',  // valor por defecto si falta
                ]
            );
            $userMap[$userData['email']] = $user;
        }

        // ── Servicios ─────────────────────────────────────────────────────────
        $services = [];
        foreach ($data['services'] as $svcData) {
            $services[] = Service::firstOrCreate(
                ['name' => $svcData['name']],
                [
                    'duration_minutes' => (int) $svcData['duration_minutes'],
                    'price'            => (float) $svcData['price'],
                    'professional_id'  => $svcData['professional_id'],
                    'non_refundable'   => (bool) ($svcData['non_refundable'] ?? false),
                ]
            );
        }

        // ── Reservas ──────────────────────────────────────────────────────────
        foreach ($data['reservations'] as $resData) {
            $user    = $userMap[$resData['user_email']] ?? null;
            $service = $services[$resData['service_index']] ?? null;

            if (! $user || ! $service) {
                continue;
            }

            $startsAt = Carbon::parse($resData['starts_at'], 'America/Bogota')->utc();
            $endsAt   = $startsAt->copy()->addMinutes($service->duration_minutes);

            Reservation::firstOrCreate(
                [
                    'user_id'    => $user->id,
                    'service_id' => $service->id,
                    'starts_at'  => $startsAt,
                ],
                [
                    'ends_at'       => $endsAt,
                    'status'        => $resData['status'] ?? 'active',
                    'refund_amount' => $resData['refund_amount'] ?? null,
                ]
            );
        }
    }
}
