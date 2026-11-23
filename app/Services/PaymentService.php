<?php

namespace App\Services;

use App\Models\ExchangeRate;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function recordCharge(
        Reservation $reservation,
        User $actor,
        string $currency,
        float $amountOriginal,
        string $paymentMethod,
        ?string $reference = null,
    ): Payment {
        return DB::transaction(function () use ($reservation, $actor, $currency, $amountOriginal, $paymentMethod, $reference): Payment {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if (! in_array($reservation->booking_status, ['pre_reserva', 'confirmada', 'check_in'], true)) {
                throw ValidationException::withMessages([
                    'reservation' => 'No se pueden registrar cobros para una reserva cerrada.',
                ]);
            }

            if ($amountOriginal <= 0) {
                throw ValidationException::withMessages([
                    'amount_original' => 'El monto debe ser mayor que cero.',
                ]);
            }

            $rate = $this->rateFor($currency);
            $amountPen = round($amountOriginal * $rate, 2);
            $balance = max(0, (float) $reservation->total_amount_pen - $this->collectedAmountPen($reservation));

            if ($amountPen > $balance) {
                throw ValidationException::withMessages([
                    'amount_original' => 'El cobro excede el saldo pendiente de la reserva.',
                ]);
            }

            $payment = Payment::create([
                'reservation_id' => $reservation->id,
                'recorded_by_user_id' => $actor->id,
                'currency' => $currency,
                'amount_original' => round($amountOriginal, 2),
                'exchange_rate_applied' => $rate,
                'amount_pen' => $amountPen,
                'payment_method' => $paymentMethod,
                'type' => 'cobro',
                'status' => 'registrado',
                'reference' => $reference,
            ]);

            $this->auditLogger->record(
                $actor,
                (int) $reservation->branch_id,
                'payment.charge',
                'reservation',
                (int) $reservation->id,
                'Cobro registrado en '.$currency.' por S/ '.number_format($amountPen, 2, '.', ''),
            );

            return $payment;
        });
    }

    public function recordCancellationRefund(Reservation $reservation, User $actor, float $amountPen): ?Payment
    {
        if ($amountPen <= 0) {
            return null;
        }

        $payment = Payment::create([
            'reservation_id' => $reservation->id,
            'recorded_by_user_id' => $actor->id,
            'currency' => 'PEN',
            'amount_original' => round($amountPen, 2),
            'exchange_rate_applied' => 1,
            'amount_pen' => round($amountPen, 2),
            'payment_method' => 'otro',
            'type' => 'reembolso',
            'status' => 'pendiente',
            'reference' => 'Reembolso de cancelación pendiente de ejecución',
        ]);

        $this->auditLogger->record(
            $actor,
            (int) $reservation->branch_id,
            'payment.refund_pending',
            'reservation',
            (int) $reservation->id,
            'Reembolso pendiente por S/ '.number_format($amountPen, 2, '.', ''),
        );

        return $payment;
    }

    public function recordNoShowPenalty(Reservation $reservation, User $actor): ?Payment
    {
        $collected = $this->collectedAmountPen($reservation);
        $total = (float) $reservation->total_amount_pen;
        $penaltyDue = round(max(0, $total - $collected), 2);

        if ($penaltyDue <= 0) {
            return null;
        }

        $payment = Payment::create([
            'reservation_id' => $reservation->id,
            'recorded_by_user_id' => $actor->id,
            'currency' => 'PEN',
            'amount_original' => $penaltyDue,
            'exchange_rate_applied' => 1,
            'amount_pen' => $penaltyDue,
            'payment_method' => 'otro',
            'type' => 'penalidad',
            'status' => 'pendiente',
            'reference' => 'Penalidad del 100% por cancelación tardía o no-show',
        ]);

        $this->auditLogger->record(
            $actor,
            (int) $reservation->branch_id,
            'payment.penalty',
            'reservation',
            (int) $reservation->id,
            'Saldo pendiente de penalidad por S/ '.number_format($penaltyDue, 2, '.', ''),
        );

        return $payment;
    }

    public function collectedAmountPen(Reservation $reservation): float
    {
        $charges = (float) $reservation->payments()
            ->where('type', 'cobro')
            ->where('status', 'registrado')
            ->sum('amount_pen');
        $refunds = (float) $reservation->payments()
            ->where('type', 'reembolso')
            ->where('status', 'registrado')
            ->sum('amount_pen');

        return max(0, $charges - $refunds);
    }

    private function rateFor(string $currency): float
    {
        if ($currency === 'PEN') {
            return 1.0;
        }

        $rate = ExchangeRate::query()
            ->where('currency', $currency)
            ->whereDate('effective_on', '<=', today())
            ->orderByDesc('effective_on')
            ->first();

        if ($rate === null || (float) $rate->rate_to_pen <= 0) {
            throw ValidationException::withMessages([
                'currency' => 'No hay una tasa de cambio vigente para la moneda seleccionada.',
            ]);
        }

        return (float) $rate->rate_to_pen;
    }
}
