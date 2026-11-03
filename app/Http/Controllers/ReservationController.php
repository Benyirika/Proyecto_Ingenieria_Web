<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReservationController extends Controller
{
    public function create(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $roomsQuery = Room::with(['branch', 'category'])
            ->where('status', 'disponible')
            ->where('cleaning_status', 'limpia');

        if (!$user->isAdmin()) {
            $roomsQuery->where('branch_id', $user->branch_id);
        } elseif ($request->filled('branch_id')) {
            $roomsQuery->where('branch_id', $request->branch_id);
        }

        $availableRooms = $roomsQuery->get();

        return view('reservations.create', compact('availableRooms'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'guest_name' => ['required', 'string', 'min:3', 'max:120'],
            'guest_dni' => ['required', 'digits:8'],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
        ], [
            'room_id.required' => 'Seleccione una habitación habilitada.',
            'guest_name.required' => 'El nombre completo del huésped es obligatorio.',
            'guest_dni.digits' => 'El DNI debe contener exactamente 8 dígitos numéricos.',
            'check_in.after_or_equal' => 'La fecha de Check-in no puede estar en el pasado.',
            'check_out.after' => 'La fecha de Check-out debe ser posterior al Check-in.',
        ]);

        $room = Room::with('category')->findOrFail($validated['room_id']);

        // Seguridad extra de negocio
        if ($room->status !== 'disponible' || $room->cleaning_status !== 'limpia') {
            return back()->withErrors(['room_id' => 'La habitación seleccionada no está limpia o disponible para Check-in.'])->withInput();
        }

        $days = max(1, Carbon::parse($validated['check_in'])->diffInDays(Carbon::parse($validated['check_out'])));
        $totalPrice = $days * $room->category->base_price;

        Reservation::create([
            'branch_id' => $room->branch_id,
            'room_id' => $room->id,
            'user_id' => Auth::id(), // Usando Auth fachada para evitar errores de IDE
            'guest_name' => $validated['guest_name'],
            'guest_dni' => $validated['guest_dni'],
            'check_in' => $validated['check_in'],
            'check_out' => $validated['check_out'],
            'total_price' => $totalPrice,
            'status' => 'activa',
        ]);

        $room->update(['status' => 'ocupada']);

        return redirect()->route('dashboard', ['branch_id' => $room->branch_id])
            ->with('success', "¡Check-in registrado con éxito! Habitación #{$room->number} asignada a {$validated['guest_name']} (Total: S/ " . number_format($totalPrice, 2) . ").");
    }

    public function checkout(Reservation $reservation)
    {
        $reservation->update(['status' => 'finalizada']);

        // Al hacer Check-out, la habitación pasa automáticamente a disponible pero SUCIA y prioridad URGENTE para Limpieza
        $reservation->room->update([
            'status' => 'disponible',
            'cleaning_status' => 'sucia',
            'priority' => 'urgente',
            'cleaning_notes' => 'Check-out recién realizado por recepción. Requiere limpieza integral y reposición de blancos.',
        ]);

        return back()->with('success', "Check-out procesado. La Habitación #{$reservation->room->number} fue enviada a la cola de Housekeeping como SUCIA (Prioridad Urgente).");
    }
}