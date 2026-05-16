<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminReservationController extends Controller
{
    public function __construct(private BookingService $bookingService)
    {
    }

    public function index(): View
    {
        return view('admin.reservations');
    }

    public function list(): JsonResponse
    {
        $reservations = Reservation::with(['user', 'service'])
            ->orderBy('starts_at', 'desc')
            ->get()
            ->map(fn($r) => [
                'id'           => $r->id,
                'user_name'    => $r->user->name,
                'user_plan'    => $r->user->plan,
                'service_name' => $r->service->name,
                'starts_at'    => $r->starts_at,
                'ends_at'      => $r->ends_at,
                'status'       => $r->status,
                'refund_amount'=> $r->refund_amount,
            ]);

        return response()->json(['data' => $reservations]);
    }

    public function destroy(Reservation $reservation): JsonResponse
    {
        if ($reservation->status === 'cancelled') {
            return response()->json(['message' => 'La reserva ya está cancelada.'], 422);
        }

        $cancelled = $this->bookingService->cancel($reservation);

        return response()->json([
            'message'       => 'Reserva cancelada exitosamente.',
            'refund_amount' => $cancelled->refund_amount,
        ]);
    }
}
