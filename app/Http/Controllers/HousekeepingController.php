<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Room;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class HousekeepingController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('viewAny', Room::class);

        $filters = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'filter_status' => ['nullable', 'in:sucia,en_limpieza,limpia,mantenimiento'],
        ]);
        $branches = $user->isAdmin()
            ? Branch::query()->where('is_active', true)->orderBy('name')->get()
            : collect([$user->branch]);
        $selectedBranchId = $user->isAdmin()
            ? (isset($filters['branch_id']) ? (int) $filters['branch_id'] : null)
            : (int) $user->branch_id;

        $rooms = Room::query()
            ->with([
                'branch',
                'category',
                'cleaner',
                'housekeepingReports' => fn ($query) => $query->where('status', 'abierto')->with('reporter')->latest(),
            ])
            ->when($selectedBranchId, fn ($query) => $query->where('branch_id', $selectedBranchId))
            ->when(
                $user->role === 'limpieza',
                fn ($query) => $query->whereIn('physical_status', ['sucia', 'en_limpieza', 'mantenimiento']),
            )
            ->when(isset($filters['filter_status']), fn ($query) => $query->where('physical_status', $filters['filter_status']))
            ->orderByRaw("CASE physical_status WHEN 'sucia' THEN 0 WHEN 'en_limpieza' THEN 1 WHEN 'mantenimiento' THEN 2 ELSE 3 END")
            ->orderBy('branch_id')
            ->orderBy('number')
            ->paginate(30)
            ->withQueryString();

        $counts = Room::query()
            ->when($selectedBranchId, fn ($query) => $query->where('branch_id', $selectedBranchId))
            ->selectRaw('physical_status, COUNT(*) AS rooms_count')
            ->groupBy('physical_status')
            ->pluck('rooms_count', 'physical_status');

        return view('housekeeping.index', [
            'rooms' => $rooms,
            'branches' => $branches,
            'selectedBranchId' => $selectedBranchId,
            'metrics' => [
                'dirty' => (int) ($counts['sucia'] ?? 0),
                'in_progress' => (int) ($counts['en_limpieza'] ?? 0),
                'clean' => (int) ($counts['limpia'] ?? 0),
                'maintenance' => (int) ($counts['mantenimiento'] ?? 0),
            ],
        ]);
    }

    public function updateStatus(Request $request, Room $room, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'physical_status' => ['required', 'in:en_limpieza,limpia,mantenimiento'],
            'cleaning_notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $room, $validated, $auditLogger): void {
            $room = Room::query()->lockForUpdate()->findOrFail($room->id);
            Gate::authorize('update', $room);

            if (! $request->user()->isAdmin()
                && ! ($room->physical_status === 'sucia' && $validated['physical_status'] === 'en_limpieza')
                && ! ($room->physical_status === 'en_limpieza' && $validated['physical_status'] === 'limpia')) {
                abort(409, 'La tarea cambió de estado. Actualiza la cola antes de volver a intentarlo.');
            }

            if ($room->has_maintenance_alert && $validated['physical_status'] !== 'mantenimiento') {
                abort(409, 'El administrador debe resolver primero la incidencia de mantenimiento.');
            }

            $room->update([
                'physical_status' => $validated['physical_status'],
                'cleaning_notes' => $validated['cleaning_notes'] ?? $room->cleaning_notes,
                'last_cleaned_at' => $validated['physical_status'] === 'limpia' ? now() : $room->last_cleaned_at,
                'last_cleaned_by' => $validated['physical_status'] === 'limpia' ? $request->user()->id : $room->last_cleaned_by,
            ]);

            $auditLogger->record(
                $request->user(),
                (int) $room->branch_id,
                'housekeeping.status_updated',
                'room',
                (int) $room->id,
                'Habitación '.$room->number.' actualizada a '.$validated['physical_status'].'.',
            );
        });

        return back()->with('success', 'Estado físico de la habitación actualizado.');
    }
}
