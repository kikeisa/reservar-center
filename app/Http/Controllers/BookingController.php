<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Service;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private BookingService $bookingService)
    {
    }

    // GET /api/bookings
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Reservation::with('service')->orderBy('starts_at');

        if ($user->isAdmin()) {
            $query->with('user');
        } else {
            $query->where('user_id', $user->id);
        }

        return response()->json($query->get());
    }

    // POST /api/bookings
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

            return response()->json($reservation->load('service'), 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // GET /api/bookings/{reservation}
    public function show(Request $request, Reservation $reservation): JsonResponse
    {
        if ($reservation->user_id !== $request->user()->id) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        return response()->json($reservation->load('service'));
    }

    // DELETE /api/bookings/{reservation}
    public function destroy(Request $request, Reservation $reservation): JsonResponse
    {
        if ($reservation->user_id !== $request->user()->id) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        try {
            $cancelled = $this->bookingService->cancel($reservation);

            return response()->json([
                'message'       => 'Reserva cancelada exitosamente.',
                'refund_amount' => $cancelled->refund_amount,
                'reservation'   => $cancelled,
            ]);
        } catch (\LogicException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // GET /api/bookings/{reservation}/refund
    public function refundPreview(Request $request, Reservation $reservation): JsonResponse
    {
        if ($reservation->user_id !== $request->user()->id) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        $amount = $this->bookingService->calculateRefund($reservation);

        return response()->json([
            'reservation_id' => $reservation->id,
            'refund_amount'  => $amount,
        ]);
    }
}
