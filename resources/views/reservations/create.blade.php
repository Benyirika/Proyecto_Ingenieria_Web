@extends('layouts.app')

@section('title', 'Crear reserva')
@section('subtitle', 'Reserva multi-habitación con tarifa calculada en el servidor')

@section('content')
    <div class="mx-auto max-w-5xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
        <form method="GET" action="{{ route('reservations.create') }}" class="mb-6 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-4">
            @if(auth()->user()->isAdmin())
                <label class="text-xs font-bold text-slate-700">
                    Sucursal
                    <select name="branch_id" required class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((int) $selectedBranchId === (int) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </label>
            @else
                <input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">
                <div class="self-end rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold">{{ $branches->first()?->name }}</div>
            @endif
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
@endsection
