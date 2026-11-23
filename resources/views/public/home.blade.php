@extends('layouts.public')

@section('title', 'Hotel LuxStay | Reserva tu estadía')

@section('content')
    <section class="bg-slate-900 text-white">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:items-center lg:px-8 lg:py-24">
            <div class="space-y-5">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-blue-300">Tres destinos · Una estadía memorable</p>
                <h1 class="max-w-xl text-4xl font-black tracking-tight sm:text-5xl">Descubre Cusco y el Valle Sagrado con LuxStay.</h1>
                <p class="max-w-xl text-base leading-7 text-slate-300">Elige una de nuestras sucursales, consulta habitaciones por fecha y envía una pre-reserva segura. El pago se coordina con recepción.</p>
                <a href="#reservar" class="inline-flex rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold hover:bg-blue-500">Consultar disponibilidad</a>
            </div>
            <div id="sucursales" class="grid gap-3 sm:grid-cols-3">
                @foreach($branches as $branch)
                    <article class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-sm font-extrabold">{{ $branch->name }}</p>
                        <p class="mt-2 text-xs leading-5 text-slate-300">{{ $branch->address }}</p>
                        <p class="mt-2 text-xs text-blue-200">{{ $branch->phone }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="reservar" class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(280px,0.7fr)] lg:px-8">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
            <div class="mb-6">
                <h2 class="text-2xl font-black">Solicita tu pre-reserva</h2>
                <p class="mt-2 text-sm text-slate-600">Elige fechas, sucursal, habitaciones y servicios. El importe se calcula en el servidor y no se cobra en línea.</p>
            </div>
            <form method="GET" action="{{ route('home') }}" class="mb-6 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-4">
                <label class="text-xs font-bold text-slate-700">
                    Sucursal
                    <select name="branch_id" required class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((int) $selectedBranchId === (int) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-bold text-slate-700">
                    Check-in
                    <input type="date" name="check_in" min="{{ today()->toDateString() }}" value="{{ $checkIn }}" required class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </label>
                <label class="text-xs font-bold text-slate-700">
                    Check-out
                    <input type="date" name="check_out" min="{{ today()->addDay()->toDateString() }}" value="{{ $checkOut }}" required class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </label>
                <button class="self-end rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-700">Consultar disponibilidad</button>
            </form>
            @include('reservations.form')
        </div>
        <aside class="space-y-4">
            <div class="rounded-2xl bg-blue-950 p-6 text-white">
                <h2 class="text-lg font-extrabold">¿Cómo funciona?</h2>
                <ol class="mt-4 list-inside list-decimal space-y-3 text-sm text-blue-100">
                    <li>Selecciona sucursal y fechas.</li>
                    <li>Envía la solicitud; no se cobra automáticamente.</li>
                    <li>Recepción valida inventario y confirma el pago.</li>
                </ol>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6">
                <h2 class="text-sm font-extrabold">¿Ya tienes una reserva?</h2>
                <p class="mt-2 text-sm text-slate-600">Contacta directamente a la sucursal elegida para confirmar detalles de llegada.</p>
            </div>
        </aside>
    </section>
@endsection
