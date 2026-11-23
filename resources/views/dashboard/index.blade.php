@extends('layouts.app')

@section('title', auth()->user()->isAdmin() && $selectedBranchId === null ? 'Dashboard multi-sucursal' : 'Panel de recepción')
@section('subtitle', 'Operación hotelera, disponibilidad por fechas y movimientos en PEN')

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            @foreach([
                ['Habitaciones', $stats['total_rooms'], 'text-slate-900'],
                ['Limpias', $stats['clean_rooms'], 'text-emerald-700'],
                ['Sucias', $stats['dirty_rooms'], 'text-red-700'],
                ['En limpieza', $stats['cleaning_rooms'], 'text-amber-700'],
                ['Mantenimiento', $stats['maintenance_rooms'], 'text-orange-700'],
                ['Reservas activas', $stats['active_reservations'], 'text-blue-700'],
            ] as [$label, $value, $color])
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-bold uppercase text-slate-500">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-black {{ $color }}">{{ $value }}</p>
                </article>
            @endforeach
        </section>

        @if(auth()->user()->isAdmin())
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <form method="GET" action="{{ route('dashboard') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label class="text-xs font-bold text-slate-600">
                        Sucursal
                        <select name="branch_id" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="">Todas las sucursales</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((int) $selectedBranchId === (int) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-xs font-bold text-slate-600">
                        Desde
                        <input type="date" name="from" value="{{ $from }}" required class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </label>
                    <label class="text-xs font-bold text-slate-600">
                        Hasta
                        <input type="date" name="to" value="{{ $to }}" required class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </label>
                    <button class="self-end rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-700">Aplicar filtros</button>
                </form>

                <div class="mt-5 grid gap-4 xl:grid-cols-3">
                    <article class="rounded-xl bg-slate-50 p-4">
                        <h2 class="text-sm font-extrabold">Ingresos netos por sucursal (PEN)</h2>
                        @php
                            $maxRevenue = max(1, (float) ($charts['revenue']->max('revenue_pen') ?? 0));
                        @endphp
                        <div class="mt-4 space-y-3">
                            @forelse($charts['revenue'] as $metric)
                                <div>
                                    <div class="mb-1 flex justify-between gap-2 text-xs"><span>{{ $metric->branch_name }}</span><strong>S/ {{ number_format((float) $metric->revenue_pen, 2) }}</strong></div>
                                    <div class="h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-blue-600" style="width: {{ min(100, max(0, ((float) $metric->revenue_pen / $maxRevenue) * 100)) }}%"></div></div>
                                </div>
                            @empty
                                <p class="text-xs text-slate-500">Sin cobros registrados para este periodo.</p>
                            @endforelse
                        </div>
                    </article>

                    <article class="rounded-xl bg-slate-50 p-4">
                        <h2 class="text-sm font-extrabold">Tipos de habitación más usados</h2>
                        <div class="mt-4 space-y-2">
                            @forelse($charts['roomCategories'] as $metric)
                                <div class="flex justify-between gap-2 border-b border-slate-200 pb-2 text-xs">
                                    <span>{{ $metric->branch_name }} · {{ $metric->category_name }}</span>
                                    <strong>{{ $metric->rooms_used }}</strong>
                                </div>
                            @empty
                                <p class="text-xs text-slate-500">Sin estancias para este periodo.</p>
                            @endforelse
                        </div>
                    </article>

                    <article class="rounded-xl bg-slate-50 p-4">
                        <h2 class="text-sm font-extrabold">Servicios extra más solicitados</h2>
                        <div class="mt-4 space-y-2">
                            @forelse($charts['extraServices'] as $metric)
                                <div class="flex justify-between gap-2 border-b border-slate-200 pb-2 text-xs">
                                    <span>{{ $metric->branch_name }} · {{ $metric->service_name }}</span>
                                    <strong>{{ $metric->quantity }}</strong>
                                </div>
                            @empty
                                <p class="text-xs text-slate-500">Sin servicios extra para este periodo.</p>
                            @endforelse
                        </div>
                    </article>
                </div>
                <a href="{{ route('admin.index') }}" class="mt-4 inline-flex text-sm font-bold text-blue-700 hover:text-blue-900">Administración, tasas y auditoría →</a>
            </section>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-extrabold">Estado físico de habitaciones</h2>
                    <p class="mt-1 text-xs text-slate-500">La disponibilidad comercial se calcula por fechas; una reserva futura no marca la habitación como ocupada.</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('housekeeping.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold hover:bg-slate-50">Cola de limpieza</a>
                    <a href="{{ route('reservations.create', ['branch_id' => $selectedBranchId]) }}" class="rounded-lg bg-blue-700 px-3 py-2 text-xs font-bold text-white hover:bg-blue-800">Nueva reserva</a>
                </div>
            </div>
            <div class="flex flex-col gap-6">
                @foreach($rooms->groupBy('branch_id') as $branchRooms)
                    <div class="flex flex-col gap-3">
                        <h3 class="text-sm font-extrabold uppercase tracking-wide text-slate-700">
                            Habitaciones · {{ $branchRooms->first()->branch->name }}
                        </h3>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                            @foreach($branchRooms as $room)
                                @php
                                    $currentReservation = $room->currentStay?->reservation;
                                    $primaryGuest = $currentReservation?->primaryGuest->first();
                                @endphp
                                <article class="rounded-xl border p-3 {{ $room->physical_status === 'limpia' ? 'border-emerald-200 bg-emerald-50/50' : ($room->physical_status === 'mantenimiento' ? 'border-red-300 bg-red-50' : 'border-amber-200 bg-amber-50/60') }}">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-extrabold">#{{ $room->number }}</span>
                                        <span class="rounded-full bg-white px-2 py-1 text-[10px] font-bold uppercase">{{ str_replace('_', ' ', $room->physical_status) }}</span>
                                    </div>
                                    <p class="mt-1 truncate text-[11px] text-slate-600">{{ $room->category->name }}</p>
                                    @if($primaryGuest)
                                        <p class="mt-2 truncate text-xs font-semibold text-blue-800">{{ $primaryGuest->first_name }} {{ $primaryGuest->last_name }}</p>
                                    @endif
                                    @if($room->has_maintenance_alert)
                                        <p class="mt-2 text-[11px] font-semibold text-red-800">{{ $room->maintenance_incident }}</p>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4">
                <h2 class="text-lg font-extrabold">Pre-reservas, llegadas y huéspedes alojados</h2>
                <p class="mt-1 text-xs text-slate-500">Los cobros se registran aparte y guardan su tasa de cambio aplicada.</p>
            </div>
            <div class="space-y-4">
                @forelse($reservations as $reservation)
                    @php
                        $guest = $reservation->primaryGuest->first();
                        $collected = (float) $reservation->payments->where('type', 'cobro')->where('status', 'registrado')->sum('amount_pen')
                            - (float) $reservation->payments->where('type', 'reembolso')->where('status', 'registrado')->sum('amount_pen');
                    @endphp
                    <article class="rounded-xl border border-slate-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-extrabold">{{ $reservation->public_reference }}</span>
                                    <span class="rounded-full bg-blue-50 px-2 py-1 text-[10px] font-bold uppercase text-blue-800">{{ str_replace('_', ' ', $reservation->booking_status) }}</span>
                                </div>
                                <p class="mt-1 text-sm font-semibold">{{ $guest?->first_name }} {{ $guest?->last_name }} · {{ $reservation->branch->name }}</p>
                                <p class="mt-1 text-xs text-slate-600">{{ $reservation->check_in->format('d/m/Y') }} – {{ $reservation->check_out->format('d/m/Y') }} · {{ $reservation->roomRequests->count() }} habitación(es)</p>
                            </div>
                            <div class="text-right text-sm">
                                <p class="font-extrabold">Cotizado S/ {{ number_format((float) $reservation->total_amount_pen, 2) }}</p>
                                <p class="text-xs text-slate-600">Cobrado S/ {{ number_format(max(0, $collected), 2) }}</p>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            @if($reservation->booking_status === 'pre_reserva')
                                <form method="POST" action="{{ route('reservations.confirm', $reservation) }}">@csrf <button class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white">Confirmar pre-reserva</button></form>
                            @endif
                            @if($reservation->booking_status === 'confirmada')
                                <form method="POST" action="{{ route('reservations.check-in', $reservation) }}">@csrf <button class="rounded-lg bg-blue-700 px-3 py-2 text-xs font-bold text-white">Check-in</button></form>
                                @if(!$reservation->check_in->isFuture())
                                    <form method="POST" action="{{ route('reservations.no-show', $reservation) }}">@csrf <button class="rounded-lg bg-orange-700 px-3 py-2 text-xs font-bold text-white">Marcar no-show</button></form>
                                @endif
                            @endif
                            @if($reservation->booking_status === 'check_in')
                                <form method="POST" action="{{ route('reservations.check-out', $reservation) }}">@csrf <button class="rounded-lg bg-slate-800 px-3 py-2 text-xs font-bold text-white">Check-out</button></form>
                            @endif
                            @if(in_array($reservation->booking_status, ['pre_reserva', 'confirmada'], true))
                                <form method="POST" action="{{ route('reservations.cancel', $reservation) }}" class="flex flex-wrap gap-2">
                                    @csrf
                                    <input name="reason" required minlength="5" maxlength="255" placeholder="Motivo de cancelación" class="min-w-40 rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                    <button class="rounded-lg border border-red-300 px-3 py-2 text-xs font-bold text-red-800 hover:bg-red-50">Cancelar</button>
                                </form>
                            @endif
                        </div>

                        @if(in_array($reservation->booking_status, ['pre_reserva', 'confirmada', 'check_in'], true))
                            <form method="POST" action="{{ route('payments.store', $reservation) }}" class="mt-4 grid gap-2 border-t border-slate-100 pt-4 sm:grid-cols-2 lg:grid-cols-5">
                                @csrf
                                <input name="amount_original" type="number" step="0.01" min="0.01" required placeholder="Importe" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <select name="currency" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option>PEN</option><option>USD</option><option>EUR</option></select>
                                <select name="payment_method" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="efectivo">Efectivo</option><option value="tarjeta">Tarjeta</option><option value="transferencia">Transferencia</option><option value="otro">Otro</option></select>
                                <input name="reference" maxlength="100" placeholder="Referencia (opcional)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <button class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-bold text-white hover:bg-emerald-800">Registrar cobro</button>
                            </form>
                        @endif

                        @if($reservation->roomStays->isNotEmpty())
                            <div class="mt-4 space-y-2 border-t border-slate-100 pt-4">
                                @foreach($reservation->roomStays as $stay)
                                    <form method="POST" action="{{ route('reservations.relocate', $reservation) }}" class="grid gap-2 sm:grid-cols-3">
                                        @csrf
                                        <input type="hidden" name="stay_id" value="{{ $stay->id }}">
                                        <span class="self-center text-xs font-semibold">Habitación activa #{{ $stay->room->number }}</span>
                                        <select name="new_room_id" required class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs">
                                            <option value="">Habitación destino (mismo tipo)</option>
                                            @foreach($rooms->where('branch_id', $reservation->branch_id)->where('room_category_id', $stay->room->room_category_id)->where('physical_status', 'limpia') as $candidate)
                                                @if(!$candidate->currentStay)
                                                    <option value="{{ $candidate->id }}">#{{ $candidate->number }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <div class="flex gap-2">
                                            <input name="reason" required minlength="5" maxlength="255" placeholder="Motivo de avería" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-xs">
                                            <button class="rounded-lg border border-red-300 px-3 py-2 text-xs font-bold text-red-800">Reubicar</button>
                                        </div>
                                    </form>
                                @endforeach
                            </div>
                        @endif
                    </article>
                @empty
                    <p class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-500">No hay reservas activas en este filtro.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-amber-200 bg-amber-50/50 p-5 shadow-sm">
            <div class="mb-4">
                <h2 class="text-lg font-extrabold">Reembolsos y penalidades pendientes</h2>
                <p class="mt-1 text-xs text-slate-600">Confirma un movimiento solo después de ejecutarlo o cobrarlo. Las penalidades se registran como saldo adeudado en PEN.</p>
            </div>
            <div class="space-y-3">
                @forelse($pendingPayments as $payment)
                    @php
                        $guest = $payment->reservation->primaryGuest->first();
                    @endphp
                    <article class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-amber-200 bg-white p-4">
                        <div>
                            <p class="text-sm font-extrabold">{{ ucfirst($payment->type) }} · S/ {{ number_format((float) $payment->amount_pen, 2) }}</p>
                            <p class="mt-1 text-xs text-slate-600">{{ $payment->reservation->public_reference }} · {{ $payment->reservation->branch->name }} · {{ $guest?->first_name }} {{ $guest?->last_name }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $payment->reference }}</p>
                        </div>
                        <form method="POST" action="{{ route('payments.update', $payment) }}" class="flex flex-wrap gap-2">
                            @csrf
                            @method('PATCH')
                            <select name="status" required class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs">
                                <option value="registrado">{{ $payment->type === 'reembolso' ? 'Confirmar reembolso' : 'Confirmar cobro' }}</option>
                                <option value="anulado">Anular movimiento</option>
                            </select>
                            <select name="payment_method" required class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs">
                                <option value="efectivo">Efectivo</option>
                                <option value="tarjeta">Tarjeta</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="otro">Otro</option>
                            </select>
                            <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white">Guardar movimiento</button>
                        </form>
                    </article>
                @empty
                    <p class="rounded-xl bg-white p-4 text-sm text-slate-500">No hay movimientos pendientes en esta sucursal.</p>
                @endforelse
            </div>
        </section>
    </div>

    <script>
        let hasUnsavedChanges = false;
        document.querySelectorAll('form').forEach((form) => {
            form.addEventListener('input', () => {
                hasUnsavedChanges = true;
            });
            form.addEventListener('change', () => {
                hasUnsavedChanges = true;
            });
            form.addEventListener('submit', () => {
                hasUnsavedChanges = false;
            });
        });

        setInterval(() => {
            if (document.visibilityState === 'visible' && !hasUnsavedChanges) {
                window.location.reload();
            }
        }, 15000);
    </script>
@endsection
