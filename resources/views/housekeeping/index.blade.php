@extends('layouts.app')

@section('title', 'Centro de limpieza')
@section('subtitle', 'Cola compartida de la sucursal · Los cambios quedan auditados')

@section('content')
    @php
        $user = auth()->user();
    @endphp
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach([
                ['Sucias', $metrics['dirty'], 'text-red-700', 'bg-red-50'],
                ['En limpieza', $metrics['in_progress'], 'text-amber-700', 'bg-amber-50'],
                ['Limpias', $metrics['clean'], 'text-emerald-700', 'bg-emerald-50'],
                ['Mantenimiento', $metrics['maintenance'], 'text-orange-700', 'bg-orange-50'],
            ] as [$label, $value, $color, $background])
                <article class="rounded-2xl border border-slate-200 {{ $background }} p-4 shadow-sm">
                    <p class="text-xs font-bold uppercase text-slate-600">{{ $label }}</p>
                    <p class="mt-1 text-3xl font-black {{ $color }}">{{ $value }}</p>
                </article>
            @endforeach
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('housekeeping.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @if($user->isAdmin())
                    <label class="text-xs font-bold text-slate-600">
                        Sucursal
                        <select name="branch_id" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="">Todas las sucursales</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((int) $selectedBranchId === (int) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <label class="text-xs font-bold text-slate-600">
                    Estado
                    <select name="filter_status" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Toda la cola disponible</option>
                        @foreach(['sucia' => 'Sucia', 'en_limpieza' => 'En limpieza', 'mantenimiento' => 'Mantenimiento', 'limpia' => 'Limpia'] as $status => $label)
                            @if($user->isAdmin() || $user->role !== 'limpieza' || $status !== 'limpia')
                                <option value="{{ $status }}" @selected(request('filter_status') === $status)>{{ $label }}</option>
                            @endif
                        @endforeach
                    </select>
                </label>
                <button class="self-end rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-700">Filtrar cola</button>
                <p class="self-end text-xs text-slate-500">La cola es compartida por el equipo de esta sucursal; se registra quién completa cada tarea.</p>
            </form>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($rooms as $room)
                @php
                    $isDirty = $room->physical_status === 'sucia';
                    $isCleaning = $room->physical_status === 'en_limpieza';
                    $statusStyle = match ($room->physical_status) {
                        'limpia' => 'border-emerald-200 bg-emerald-50/40',
                        'en_limpieza' => 'border-amber-200 bg-amber-50/50',
                        'mantenimiento' => 'border-red-300 bg-red-50/60',
                        default => 'border-orange-200 bg-orange-50/40',
                    };
                @endphp
                <article class="flex flex-col rounded-2xl border-2 p-4 shadow-sm {{ $statusStyle }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold text-slate-500">{{ $room->branch->name }}</p>
                            <h2 class="mt-1 text-2xl font-black">Habitación #{{ $room->number }}</h2>
                            <p class="mt-1 text-xs font-semibold text-blue-800">{{ $room->category->name }} · Piso {{ $room->floor }}</p>
                        </div>
                        <span class="rounded-full bg-white px-2.5 py-1 text-[10px] font-black uppercase text-slate-700">{{ str_replace('_', ' ', $room->physical_status) }}</span>
                    </div>

                    @if($room->cleaning_notes)
                        <p class="mt-3 rounded-lg bg-white/80 p-3 text-xs text-slate-700">{{ $room->cleaning_notes }}</p>
                    @endif
                    @if($room->cleaner && $room->last_cleaned_at)
                        <p class="mt-2 text-[11px] text-slate-500">Última limpieza: {{ $room->cleaner->name }} · {{ $room->last_cleaned_at->format('d/m/Y H:i') }}</p>
                    @endif
                    @if($room->has_maintenance_alert)
                        <p class="mt-3 rounded-lg border border-red-200 bg-white p-3 text-xs font-semibold text-red-800">
                            Incidencia: {{ $room->maintenance_incident ?: 'Pendiente de diagnóstico' }}
                        </p>
                    @endif

                    @if($user->isAdmin() || $user->role === 'limpieza')
                        @if($isDirty || $isCleaning || ($user->isAdmin() && $room->physical_status === 'mantenimiento'))
                            <form method="POST" action="{{ route('housekeeping.update', $room) }}" class="mt-4 space-y-2">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="physical_status" value="{{ $isDirty ? 'en_limpieza' : ($isCleaning ? 'limpia' : 'mantenimiento') }}">
                                <label class="block text-xs font-bold text-slate-700">
                                    Nota de limpieza
                                    <textarea name="cleaning_notes" rows="2" maxlength="500" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs">{{ old('cleaning_notes', $room->cleaning_notes) }}</textarea>
                                </label>
                                <button class="w-full rounded-lg {{ $isCleaning ? 'bg-emerald-700 hover:bg-emerald-800' : 'bg-blue-700 hover:bg-blue-800' }} px-3 py-2.5 text-xs font-extrabold text-white">
                                    {{ $isDirty ? 'Iniciar limpieza' : ($isCleaning ? 'Marcar como limpia' : 'Mantener en mantenimiento') }}
                                </button>
                            </form>
                        @endif

                        @if($user->role === 'limpieza')
                            <form method="POST" action="{{ route('housekeeping.reports.store', $room) }}" class="mt-4 space-y-2 border-t border-slate-200/80 pt-4">
                                @csrf
                                <label class="block text-xs font-bold text-slate-700">
                                    Reportar falla o insumo
                                    <select name="report_type" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs">
                                        <option value="insumo">Falta de insumos</option>
                                        <option value="averia">Falla de mantenimiento</option>
                                    </select>
                                </label>
                                <textarea name="description" required minlength="3" maxlength="500" rows="2" placeholder="Describe el faltante o la falla" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs"></textarea>
                                <button class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold hover:bg-slate-50">Enviar reporte</button>
                            </form>
                        @endif
                    @endif

                    @if($room->housekeepingReports->isNotEmpty())
                        <div class="mt-4 space-y-2 border-t border-slate-200/80 pt-4">
                            @foreach($room->housekeepingReports as $report)
                                <div class="rounded-lg border border-slate-200 bg-white p-3">
                                    <p class="text-[10px] font-black uppercase text-slate-500">{{ $report->report_type }} · {{ $report->status }}</p>
                                    <p class="mt-1 text-xs text-slate-700">{{ $report->description }}</p>
                                    <p class="mt-1 text-[10px] text-slate-400">{{ $report->reporter?->name ?? 'Personal' }}</p>
                                    @if($report->status === 'abierto' && ($user->isAdmin() || ($user->role === 'limpieza' && $report->report_type === 'insumo')))
                                        <form method="POST" action="{{ route('housekeeping.reports.resolve', $report) }}" class="mt-2">
                                            @csrf
                                            <button class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-[11px] font-bold">Marcar resuelto</button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if(!$user->isAdmin() && $user->role === 'recepcion')
                        <p class="mt-4 border-t border-slate-200 pt-3 text-xs font-semibold text-slate-600">Solo lectura para recepción.</p>
                    @endif
                </article>
            @empty
                <p class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500 md:col-span-2 xl:col-span-3">No hay habitaciones que coincidan con el filtro de la cola.</p>
            @endforelse
        </section>

        <div>{{ $rooms->links() }}</div>
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
