@extends('layouts.public')

@section('title', 'Pre-reserva recibida | LuxStay')

@section('content')
    <section class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
        <div class="rounded-2xl border border-emerald-200 bg-white p-6 shadow-sm sm:p-10">
            <div class="mb-6 inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-extrabold text-emerald-800">Solicitud recibida</div>
            <h1 class="text-3xl font-black">Tu pre-reserva está pendiente de confirmación.</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">No se realizó ningún cobro. La sucursal revisará la disponibilidad y coordinará el pago.</p>
            <dl class="mt-8 grid gap-4 rounded-xl bg-slate-50 p-5 text-sm sm:grid-cols-2">
                <div><dt class="text-xs font-semibold uppercase text-slate-500">Código</dt><dd class="mt-1 font-extrabold">{{ $reservation->public_reference }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase text-slate-500">Sucursal</dt><dd class="mt-1 font-bold">{{ $reservation->branch->name }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase text-slate-500">Fechas</dt><dd class="mt-1 font-bold">{{ $reservation->check_in->format('d/m/Y') }} – {{ $reservation->check_out->format('d/m/Y') }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase text-slate-500">Importe cotizado</dt><dd class="mt-1 font-bold">S/ {{ number_format((float) $reservation->total_amount_pen, 2) }}</dd></div>
            </dl>
            <ul class="mt-4 list-inside list-disc text-sm text-slate-600">
                @foreach($reservation->roomRequests as $request)
                    <li>{{ $request->category->name }} · {{ number_format((float) $request->nightly_rate_pen, 2) }} PEN / noche</li>
                @endforeach
            </ul>
            <a href="{{ route('home') }}" class="mt-8 inline-flex rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white hover:bg-blue-800">Volver al portal</a>
        </div>
    </section>
@endsection
