@extends('layouts.app')

@section('title', 'Centro de Control de Housekeeping')
@section('subtitle', 'Gestión operativa de higienización y reporte inmediato de averías técnicas')

@section('content')
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-red-50 border border-red-200 p-5 rounded-2xl">
            <p class="text-xs font-bold text-red-700 uppercase">🔴 Habitaciones Sucias</p>
            <p class="text-3xl font-extrabold text-red-800 mt-1">{{ $metrics['dirty'] }}</p>
        </div>
        <div class="bg-emerald-50 border border-emerald-200 p-5 rounded-2xl">
            <p class="text-xs font-bold text-emerald-700 uppercase">🟢 Limpias e Inspeccionadas</p>
            <p class="text-3xl font-extrabold text-emerald-800 mt-1">{{ $metrics['clean'] }}</p>
        </div>
        <div class="bg-slate-800 text-white p-5 rounded-2xl">
            <p class="text-xs font-bold text-slate-300 uppercase">🛠️ Mantenimiento Técnico</p>
            <p class="text-3xl font-extrabold mt-1">{{ $metrics['maintenance'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($rooms as $room)
            <div class="bg-white rounded-2xl border-2 shadow-sm p-6 flex flex-col justify-between
                {{ $room->has_maintenance_alert ? 'border-red-600 bg-red-50/30' : ($room->cleaning_status === 'sucia' ? 'border-red-300' : 'border-emerald-200') }}">
                
                <div>
                    @if($room->has_maintenance_alert)
                        <div class="mb-3 p-2.5 bg-red-600 text-white rounded-xl text-xs font-extrabold animate-bounce flex items-center gap-2">
                            <span>🚨 ALERTA MÁXIMA: BLOQUEAR SALIDA DE HUÉSPED EN RECEPCIÓN</span>
                        </div>
                    @endif

                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-slate-500">🏨 {{ $room->branch->name }}</span>
                        <span class="px-2.5 py-1 rounded-md text-xs font-bold uppercase {{ $room->cleaning_status === 'limpia' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                            {{ $room->cleaning_status }}
                        </span>
                    </div>

                    <h3 class="text-2xl font-extrabold text-slate-900 mb-1">Habitación #{{ $room->number }}</h3>
                    <p class="text-xs font-semibold text-blue-700 mb-4">{{ $room->category->name }}</p>
                </div>

                <!-- Formulario simplificado de Housekeeping (Regla #17: Sin nivel de prioridad) -->
                <form action="{{ route('housekeeping.update', $room) }}" method="POST" class="space-y-3 pt-4 border-t border-slate-100">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Cambiar Estado Operativo</label>
                        <select name="cleaning_status" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs font-semibold">
                            <option value="sucia" {{ $room->cleaning_status === 'sucia' ? 'selected' : '' }}>🔴 Sucia (Requiere Limpieza)</option>
                            <option value="limpia" {{ $room->cleaning_status === 'limpia' ? 'selected' : '' }}>🟢 Limpia / Lista para Uso</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Bitácora / Observación</label>
                        <textarea name="cleaning_notes" rows="2" placeholder="Detalles de limpieza..." class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs">{{ $room->cleaning_notes }}</textarea>
                    </div>

                    <!-- Reporte de Avería con Alerta Máxima (Regla #6) -->
                    <div class="p-3 bg-red-50 rounded-xl border border-red-200 space-y-2">
                        <label class="flex items-center gap-2 text-xs text-red-700 font-bold cursor-pointer">
                            <input type="checkbox" name="report_maintenance" value="1" {{ $room->has_maintenance_alert ? 'checked' : '' }}>
                            <span>⚠️ Reportar Avería (Manchas, Destrozos, Olores)</span>
                        </label>
                        <input type="text" name="maintenance_incident" value="{{ $room->maintenance_incident }}" placeholder="Describa el incidente exacto..." class="w-full px-2.5 py-1.5 rounded border border-red-300 text-xs bg-white">
                    </div>

                    <button type="submit" class="w-full py-2.5 bg-[#0b192c] hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition shadow">
                        Actualizar Parte de Housekeeping
                    </button>
                </form>
            </div>
        @endforeach
    </div>
@endsection