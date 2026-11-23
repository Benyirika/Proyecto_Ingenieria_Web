<?php

namespace App\Http\Controllers;

use App\Models\HousekeepingReport;
use App\Models\Room;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class HousekeepingReportController extends Controller
{
    public function store(Request $request, Room $room, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'report_type' => ['required', 'in:averia,insumo'],
            'description' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $room, $validated, $auditLogger): void {
            $room = Room::query()->lockForUpdate()->findOrFail($room->id);
            Gate::authorize('report', $room);

            if (! $request->user()->isAdmin()
                && ! in_array($room->physical_status, ['sucia', 'en_limpieza', 'mantenimiento'], true)) {
                abort(403);
            }

            $report = HousekeepingReport::create([
                'room_id' => $room->id,
                'reported_by_user_id' => $request->user()->id,
                'report_type' => $validated['report_type'],
                'description' => $validated['description'],
                'status' => 'abierto',
            ]);

            if ($report->report_type === 'averia') {
                $room->update([
                    'physical_status' => 'mantenimiento',
                    'has_maintenance_alert' => true,
                    'maintenance_incident' => $report->description,
                ]);
            }

            $auditLogger->record(
                $request->user(),
                (int) $room->branch_id,
                'housekeeping.reported',
                'housekeeping_report',
                (int) $report->id,
                'Reporte de '.$report->report_type.' para habitación '.$room->number.'.',
            );
        });

        return back()->with('success', 'Reporte registrado en la cola de la sucursal.');
    }

    public function resolve(Request $request, HousekeepingReport $report, AuditLogger $auditLogger): RedirectResponse
    {
        DB::transaction(function () use ($request, $report, $auditLogger): void {
            $report = HousekeepingReport::query()->lockForUpdate()->findOrFail($report->id);
            $room = Room::query()->lockForUpdate()->findOrFail($report->room_id);
            Gate::authorize('resolveReport', $room);

            if ($report->report_type === 'averia' && ! $request->user()->isAdmin()) {
                abort(403);
            }

            if ($report->status !== 'abierto') {
                abort(409, 'El reporte ya está cerrado.');
            }

            $report->update([
                'status' => 'resuelto',
                'resolved_by_user_id' => $request->user()->id,
                'resolved_at' => now(),
            ]);

            if ($report->report_type === 'averia') {
                $room->update([
                    'has_maintenance_alert' => false,
                    'maintenance_incident' => null,
                    'physical_status' => 'sucia',
                ]);
            }

            $auditLogger->record(
                $request->user(),
                (int) $room->branch_id,
                'housekeeping.report_resolved',
                'housekeeping_report',
                (int) $report->id,
                'Reporte operativo marcado como resuelto.',
            );
        });

        return back()->with('success', 'Reporte cerrado.');
    }
}
