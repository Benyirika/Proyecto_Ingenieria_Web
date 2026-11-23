@extends('layouts.app')

@section('title', 'Administración')
@section('subtitle', 'Sucursales, usuarios, tarifas, tipos de cambio y auditoría')

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="grid gap-6 xl:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-extrabold">Agregar sucursal</h2>
                <form method="POST" action="{{ route('admin.branches.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                    @csrf
                    <input name="name" required maxlength="120" placeholder="Nombre" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="code" required maxlength="10" placeholder="Código único" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="address" required maxlength="255" placeholder="Dirección" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="phone" required maxlength="30" placeholder="Teléfono" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white sm:col-span-2">Crear sucursal</button>
                </form>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-extrabold">Crear usuario</h2>
                <form method="POST" action="{{ route('admin.users.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                    @csrf
                    <input name="name" required maxlength="255" placeholder="Nombre completo" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="email" type="email" required maxlength="255" placeholder="Correo" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <select name="role" required class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="recepcion">Recepción</option>
                        <option value="limpieza">Limpieza</option>
                        <option value="admin">Admin</option>
                    </select>
                    <select name="branch_id" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Sin sucursal (admin)</option>
                        @foreach($branches as $branch)
                            @if($branch->is_active)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endif
                        @endforeach
                    </select>
                    <input name="password" type="password" required minlength="12" autocomplete="new-password" placeholder="Contraseña temporal (12+)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="password_confirmation" type="password" required minlength="12" autocomplete="new-password" placeholder="Confirmar contraseña" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white sm:col-span-2">Crear usuario</button>
                </form>
            </article>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-extrabold">Sucursales y tarifas por tipo de habitación</h2>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                @foreach($branches as $branch)
                    <article class="rounded-xl border border-slate-200 p-4">
                        <form method="POST" action="{{ route('admin.branches.update', $branch) }}" class="grid gap-2 sm:grid-cols-2">
                            @csrf
                            @method('PATCH')
                            <input name="name" required maxlength="120" value="{{ $branch->name }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <input name="code" required maxlength="10" value="{{ $branch->code }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <input name="address" required maxlength="255" value="{{ $branch->address }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <input name="phone" required maxlength="30" value="{{ $branch->phone }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <label class="flex items-center gap-2 text-sm font-semibold">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked($branch->is_active) class="rounded border-slate-300">
                                Sucursal activa
                            </label>
                            <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold hover:bg-slate-50">Guardar sucursal</button>
                        </form>

                        <div class="mt-4 divide-y divide-slate-100">
                            @foreach($branch->roomCategoryRates as $rate)
                                <form method="POST" action="{{ route('admin.branches.rates.update', [$branch, $rate->category]) }}" class="grid grid-cols-[minmax(0,1fr)_110px_auto] items-center gap-2 py-2">
                                    @csrf
                                    @method('PATCH')
                                    <span class="truncate text-xs font-semibold">{{ $rate->category->name }}</span>
                                    <input name="nightly_rate_pen" type="number" step="0.01" min="0.01" required value="{{ number_format((float) $rate->nightly_rate_pen, 2, '.', '') }}" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                    <div class="flex items-center gap-2">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" @checked($rate->is_active) aria-label="Tarifa activa" class="rounded border-slate-300">
                                        <button class="rounded-lg bg-slate-900 px-2 py-1.5 text-[11px] font-bold text-white">Guardar</button>
                                    </div>
                                </form>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-extrabold">Personal y permisos</h2>
                <div class="mt-4 space-y-3">
                    @foreach($users as $user)
                        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="grid gap-2 rounded-xl border border-slate-200 p-3 sm:grid-cols-2">
                            @csrf
                            @method('PATCH')
                            <p class="self-center text-xs font-bold">{{ $user->email }}</p>
                            <input name="name" required maxlength="255" value="{{ $user->name }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs">
                            <select name="role" required class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs">
                                @foreach(['admin' => 'Admin', 'recepcion' => 'Recepción', 'limpieza' => 'Limpieza'] as $role => $label)
                                    <option value="{{ $role }}" @selected($user->role === $role)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <select name="branch_id" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs">
                                <option value="">Sin sucursal (admin)</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected((int) $user->branch_id === (int) $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                            <label class="flex items-center gap-2 text-xs font-semibold">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked($user->is_active) class="rounded border-slate-300">
                                Cuenta activa
                            </label>
                            <input name="password" type="password" minlength="12" autocomplete="new-password" placeholder="Nueva contraseña (opcional)" class="rounded-lg border border-slate-300 px-3 py-2 text-xs">
                            <input name="password_confirmation" type="password" minlength="12" autocomplete="new-password" placeholder="Confirmar nueva contraseña" class="rounded-lg border border-slate-300 px-3 py-2 text-xs">
                            <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white sm:col-span-2">Guardar permisos</button>
                        </form>
                    @endforeach
                </div>
            </article>

            <div class="space-y-6">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-extrabold">Tasas de cambio a PEN</h2>
                    <form method="POST" action="{{ route('admin.exchange-rates.store') }}" class="mt-4 grid gap-2 sm:grid-cols-2">
                        @csrf
                        <select name="currency" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option>USD</option><option>EUR</option></select>
                        <input name="effective_on" type="date" required value="{{ today()->toDateString() }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <input name="rate_to_pen" type="number" step="0.000001" min="0.000001" required placeholder="Soles por unidad" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white">Guardar tasa</button>
                    </form>
                    <div class="mt-4 space-y-1">
                        @foreach($rates as $rate)
                            <p class="flex justify-between gap-2 border-b border-slate-100 py-1 text-xs">
                                <span>{{ $rate->currency }} · {{ $rate->effective_on->format('d/m/Y') }}</span>
                                <strong>{{ number_format((float) $rate->rate_to_pen, 6) }} PEN</strong>
                            </p>
                        @endforeach
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-extrabold">Auditoría reciente</h2>
                    <div class="mt-4 max-h-96 space-y-2 overflow-auto">
                        @forelse($auditLogs as $log)
                            <div class="border-b border-slate-100 pb-2">
                                <p class="text-xs font-bold">{{ $log->event }} · {{ $log->branch?->name ?? 'Global' }}</p>
                                <p class="mt-1 text-xs text-slate-600">{{ $log->summary }}</p>
                                <p class="mt-1 text-[10px] text-slate-400">{{ $log->actor?->name ?? 'Sistema' }} · {{ $log->created_at->format('d/m/Y H:i') }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Sin eventos de auditoría.</p>
                        @endforelse
                    </div>
                </article>
            </div>
        </section>
    </div>
@endsection
