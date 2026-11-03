<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Solo el Master Admin puede ver las 3 sucursales o filtrar entre ellas
        $branches = $user->isAdmin() ? Branch::withCount('rooms')->get() : collect([$user->branch]);

        // Determinar qué sucursal estamos visualizando
        $selectedBranchId = $user->isAdmin()
            ? $request->query('branch_id') // null = Vista Consolidada de los 3 locales
            : $user->branch_id;            // Fijo para Recepción

        $roomsQuery = Room::with(['branch', 'category', 'cleaner', 'reservations']);
        $reservationsQuery = Reservation::with(['branch', 'room.category', 'user'])->where('status', 'activa');

        if ($selectedBranchId) {
            $roomsQuery->where('branch_id', $selectedBranchId);
            $reservationsQuery->where('branch_id', $selectedBranchId);
        }

        $rooms = $roomsQuery->orderBy('branch_id')->orderBy('number')->get();
        $reservations = $reservationsQuery->latest()->get();

        // KPIs en tiempo real desde la Base de Datos
        $stats = [
            'total_rooms' => $rooms->count(),
            'available_clean' => $rooms->where('status', 'disponible')->where('cleaning_status', 'limpia')->count(),
            'occupied' => $rooms->where('status', 'ocupada')->count(),
            'dirty_or_process' => $rooms->whereIn('cleaning_status', ['sucia', 'en_proceso'])->count(),
            'revenue_active' => $reservations->sum('total_price'),
        ];

        return view('dashboard.index', compact('branches', 'selectedBranchId', 'rooms', 'reservations', 'stats'));
    }
}