<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HousekeepingController extends Controller
{
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $branches = $user->isAdmin() ? Branch::all() : collect([$user->branch]);

        $selectedBranchId = $user->isAdmin()
            ? $request->query('branch_id')
            : $user->branch_id;

        $query = Room::with(['branch', 'category', 'cleaner']);

        if ($selectedBranchId) {
            $query->where('branch_id', $selectedBranchId);
        }

        if ($request->filled('filter_cleaning')) {
            $query->where('cleaning_status', $request->filter_cleaning);
        }

        // Ordenar correctamente sin la columna priority eliminada
        $rooms = $query->orderByRaw("FIELD(cleaning_status, 'sucia', 'limpia')")
                       ->orderBy('number')
                       ->get();

        $allBranchRooms = Room::when($selectedBranchId, fn($q) => $q->where('branch_id', $selectedBranchId))->get();

        $metrics = [
            'dirty' => $allBranchRooms->where('cleaning_status', 'sucia')->count(),
            'clean' => $allBranchRooms->where('cleaning_status', 'limpia')->count(),
            'maintenance' => $allBranchRooms->where('status', 'mantenimiento')->count(),
        ];

        return view('housekeeping.index', compact('rooms', 'branches', 'selectedBranchId', 'metrics'));
    }

    public function updateStatus(Request $request, Room $room)
    {
        $validated = $request->validate([
            'cleaning_status' => ['required', 'in:sucia,en_proceso,limpia'],
            'priority' => ['required', 'in:normal,alta,urgente'],
            'cleaning_notes' => ['nullable', 'string', 'max:500'],
            'report_maintenance' => ['nullable', 'boolean'],
        ]);

        $updateData = [
            'cleaning_status' => $validated['cleaning_status'],
            'priority' => $validated['cleaning_status'] === 'limpia' ? 'normal' : $validated['priority'],
            'cleaning_notes' => $validated['cleaning_notes'],
        ];

        if ($validated['cleaning_status'] === 'limpia') {
            $updateData['last_cleaned_at'] = now();
            $updateData['last_cleaned_by'] = Auth::id(); // Usando Auth fachada
            if ($room->status === 'mantenimiento' && !$request->boolean('report_maintenance')) {
                $updateData['status'] = 'disponible';
            }
        }

        if ($request->boolean('report_maintenance')) {
            $updateData['status'] = 'mantenimiento';
            $updateData['priority'] = 'urgente';
        }

        $room->update($updateData);

        return back()->with('success', "Habitación #{$room->number} ({$room->branch->name}) actualizada a estado: " . strtoupper($validated['cleaning_status']) . ".");
    }
}