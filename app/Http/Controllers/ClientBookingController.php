<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Service;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientBookingController extends Controller
{
    public function __construct(private BookingService $bookingService)
    {
    }

    public function services(): JsonResponse
    {
        $services = Service::orderBy('name')->get(['id', 'name', 'duration_minutes', 'price', 'non_refundable']);
        return response()->json($services);
    }

    public function list(Request $request): JsonResponse
    {
        $user = $request->user();

        $reservations = Reservation::with('service')
            ->where('user_id', $user->id)
            ->orderBy('starts_at', 'desc')
            ->get()
            ->map(fn($r) => [
                'id'            => $r->id,
                'service_name'  => $r->service->name,
                'duration'      => $r->service->duration_minutes,
                'price'         => $r->service->price,
                'non_refundable'=> $r->service->non_refundable,
                'starts_at'     => $r->starts_at,
                'ends_at'       => $r->ends_at,
                'status'        => $r->status,
                'refund_amount' => $r->refund_amount,
                'is_future'     => $r->starts_at->isFuture(),
            ]);

        $activeCount = Reservation::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('starts_at', '>', now())
            ->count();

        return response()->json([
            'data'         => $reservations,
            'active_count' => $activeCount,
            'plan'         => $user->plan,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'starts_at'  => 'required|date',
        ]);

        $service = Service::findOrFail($validated['service_id']);

        try {
            $reservation = $this->bookingService->create(
                $request->user(),
                $service,
                $validated['starts_at']
            );

            return response()->json([
                'message'     => 'Reserva creada exitosamente.',
                'reservation' => $reservation->load('service'),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function refundPreview(Request $request, Reservation $reservation): JsonResponse
    {
        if ($reservation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $amount = $this->bookingService->calculateRefund($reservation);

        return response()->json([
            'refund_amount' => $amount,
            'price'         => $reservation->service->price,
        ]);
    }

    public function cancel(Request $request, Reservation $reservation): JsonResponse
    {
        if ($reservation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        if ($reservation->status === 'cancelled') {
            return response()->json(['message' => 'La reserva ya está cancelada.'], 422);
        }

        $cancelled = $this->bookingService->cancel($reservation);

        return response()->json([
            'message'       => 'Reserva cancelada.',
            'refund_amount' => $cancelled->refund_amount,
        ]);
    }
}
