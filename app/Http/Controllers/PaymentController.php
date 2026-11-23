<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reservation;
use App\Services\AuditLogger;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function store(Request $request, Reservation $reservation, PaymentService $payments): RedirectResponse
    {
        Gate::authorize('takePayment', $reservation);

        $validated = $request->validate([
            'currency' => ['required', 'in:PEN,USD,EUR'],
            'amount_original' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'payment_method' => ['required', 'in:efectivo,tarjeta,transferencia,otro'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $payments->recordCharge(
            $reservation,
            $request->user(),
            $validated['currency'],
            (float) $validated['amount_original'],
            $validated['payment_method'],
            $validated['reference'] ?? null,
        );

        return redirect()->route('dashboard', ['branch_id' => $reservation->branch_id])
            ->with('success', 'Cobro registrado y convertido a PEN con la tasa vigente.');
    }

    public function updateStatus(Request $request, Payment $payment, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:registrado,anulado'],
            'payment_method' => ['nullable', 'required_if:status,registrado', 'in:efectivo,tarjeta,transferencia,otro'],
        ]);

        DB::transaction(function () use ($request, $payment, $validated, $auditLogger): void {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            Gate::authorize('takePayment', $payment->reservation);

            if ($payment->status !== 'pendiente') {
                abort(409, 'El movimiento ya no está pendiente.');
            }

            $payment->update([
                'status' => $validated['status'],
                ...($validated['status'] === 'registrado' ? ['payment_method' => $validated['payment_method']] : []),
            ]);

            $auditLogger->record(
                $request->user(),
                (int) $payment->reservation->branch_id,
                'payment.status_updated',
                'payment',
                (int) $payment->id,
                'Movimiento financiero actualizado a '.$validated['status'].'.',
            );
        });

        return redirect()->route('dashboard', ['branch_id' => $payment->reservation->branch_id])
            ->with('success', 'Movimiento financiero actualizado.');
    }
}
