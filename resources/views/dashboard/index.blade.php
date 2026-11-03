@extends('layouts.app')

@section('title', auth()->user()->isAdmin() && !$selectedBranchId ? 'Dashboard Gerencial Multi-Sede (Master Admin)' : 'Panel Operativo de Sede')
@section('subtitle', 'Matriz de ocupación en tiempo real, análisis financiero y parque habitacional')

@section('content')
    @if(auth()->user()->isAdmin() && !$selectedBranchId)
        <!-- KPI CARDS MASTER ADMIN -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <p class="text-xs font-bold text-slate-400 uppercase">Parque Total Habitaciones</p>
                <p class="text-4xl font-extrabold text-slate-900 mt-2">{{ $stats['total_rooms'] }}</p>
                <span class="text-xs text-emerald-600 font-semibold mt-1 block">● 3 Sucursales Activas</span>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-emerald-200 shadow-sm">
                <p class="text-xs font-bold text-emerald-600 uppercase">Habitaciones Disponibles</p>
                <p class="text-4xl font-extrabold text-emerald-700 mt-2">{{ $stats['available_clean'] }}</p>
                <span class="text-xs text-slate-500 font-semibold mt-1 block">Inspeccionadas y listas</span>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-blue-200 shadow-sm">
                <p class="text-xs font-bold text-blue-600 uppercase">Ocupación Actual</p>
                <p class="text-4xl font-extrabold text-blue-700 mt-2">{{ $stats['occupied'] }}</p>
                <span class="text-xs text-blue-500 font-semibold mt-1 block">Huéspedes In-House</span>
            </div>
            <div class="bg-[#0b192c] p-6 rounded-2xl text-white shadow-sm">
                <p class="text-xs font-bold text-slate-300 uppercase">Ingresos Consolidados</p>
                <p class="text-3xl font-extrabold mt-2">S/ {{ number_format($stats['revenue_active'], 2) }}</p>
                <span class="text-xs text-slate-400 font-semibold mt-1 block">Tarifas activas en red</span>
            </div>
        </div>

        <!-- GRÁFICO DE LÍNEAS MULTI-SEDE (Inspirado en la regla de negocio de ingresos por sucursal) -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-8">
            <h3 class="text-base font-bold text-slate-900 mb-1">Tendencia de Ingresos por Sucursal (Análisis Comparativo)</h3>
            <p class="text-xs text-slate-500 mb-4">Evolución de rendimiento comercial entre Centro Histórico, Magisterio y Pisac</p>
            <div style="height: 300px;">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    @endif

    <!-- MATRIZ DE OCUPACIÓN CORRELATIVA POR SEDE -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-lg font-bold text-slate-900">Matriz de Ocupación por Sede</h3>
                <p class="text-xs text-slate-500">Supervisa el estado operativo y los huéspedes actuales ordenados por piso</p>
            </div>
            @if(!auth()->user()->isAdmin() || $selectedBranchId)
                <a href="{{ route('reservations.create', ['branch_id' => $selectedBranchId]) }}"
                   class="px-4 py-2.5 bg-[#0b192c] hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition shadow">
                    + Registrar Nueva Reserva / Check-in
                </a>
            @endif
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
            @foreach($rooms as $room)
                @php
                    $activeRes = $room->reservations ? $room->reservations->where('status', 'activa')->first() : null;
                @endphp
                <div class="p-3 rounded-xl border transition hover:shadow-md text-center
                    {{ $room->status === 'ocupada' ? 'border-blue-300 bg-blue-50/70' : ($room->status === 'mantenimiento' ? 'border-red-300 bg-red-50/70' : 'border-slate-200 bg-slate-50/50') }}">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-xs font-extrabold text-slate-900">#{{ $room->number }}</span>
                        <span class="w-2 h-2 rounded-full {{ $room->status === 'disponible' ? 'bg-emerald-500' : ($room->status === 'ocupada' ? 'bg-blue-600 animate-pulse' : 'bg-red-500') }}"></span>
                    </div>
                    <p class="text-[10px] font-semibold text-slate-600 truncate">{{ $room->category->name }}</p>
                    
                    @if($activeRes)
                        <div class="mt-2 pt-1 border-t border-blue-200 text-[10px]">
                            <p class="font-bold text-blue-900 truncate">👤 {{ $activeRes->guest_name }}</p>
                            <p class="text-slate-500 text-[9px]">DNI: {{ $activeRes->guest_dni }}</p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    @if(auth()->user()->isAdmin() && !$selectedBranchId)
        <!-- Chart.js CDN Script para el Gráfico de Líneas Multi-Sede -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const ctx = document.getElementById('revenueChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Dic'],
                    datasets: [
                        {
                            label: '🏨 Centro Histórico',
                            data: [12000, 19000, 15000, 22000, 28000, 35000, 31000, 29000, 38000, 42000, 46000, 52000],
                            borderColor: '#2563eb',
                            backgroundColor: '#2563eb',
                            tension: 0.3
                        },
                        {
                            label: '🏨 Magisterio',
                            data: [8000, 12000, 10000, 15000, 18000, 24000, 21000, 19000, 26000, 31000, 34000, 39000],
                            borderColor: '#10b981',
                            backgroundColor: '#10b981',
                            tension: 0.3
                        },
                        {
                            label: '🏨 Pisac (Valle Sagrado)',
                            data: [15000, 22000, 18000, 25000, 32000, 41000, 38000, 35000, 44000, 49000, 53000, 61000],
                            borderColor: '#f59e0b',
                            backgroundColor: '#f59e0b',
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) { return 'S/ ' + value; }
                            }
                        }
                    }
                }
            });
        </script>
    @endif
@endsection