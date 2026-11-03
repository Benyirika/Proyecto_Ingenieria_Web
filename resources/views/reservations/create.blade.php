@extends('layouts.app')

@section('title', 'Proceso Guiado de Reserva')
@section('subtitle', 'Motor de cotización y validación de ocupantes según estándar hotelero internacional')

@section('content')
    <div class="max-w-4xl mx-auto bg-white rounded-2xl border border-slate-200 shadow-sm p-8">
        
        <!-- BARRA DE PROGRESO DE 5 PASOS -->
        <div class="mb-8 border-b border-slate-200 pb-6">
            <div class="flex items-center justify-between relative">
                <div class="absolute left-0 right-0 top-1/2 -translate-y-1/2 h-1 bg-slate-200 z-0"></div>
                
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full bg-blue-600 text-white font-bold flex items-center justify-center shadow">1</div>
                    <span class="text-xs font-bold text-slate-700 mt-2">Fechas y Sede</span>
                </div>
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full bg-[#0b192c] text-white font-bold flex items-center justify-center shadow">2</div>
                    <span class="text-xs font-bold text-slate-700 mt-2">Habitación</span>
                </div>
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full bg-slate-300 text-slate-600 font-bold flex items-center justify-center">3</div>
                    <span class="text-xs font-medium text-slate-500 mt-2">Titular y Acompañantes</span>
                </div>
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full bg-slate-300 text-slate-600 font-bold flex items-center justify-center">4</div>
                    <span class="text-xs font-medium text-slate-500 mt-2">Servicios / Ocupantes</span>
                </div>
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full bg-slate-300 text-slate-600 font-bold flex items-center justify-center">5</div>
                    <span class="text-xs font-medium text-slate-500 mt-2">Pago y Divisa</span>
                </div>
            </div>
        </div>

        <form action="{{ route('reservations.store') }}" method="POST" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-700 mb-1.5">Habitación Seleccionada (Capacidad Estricta)</label>
                    <select name="room_id" required class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm">
                        <option value="">-- Seleccionar Habitación Disponible --</option>
                        @foreach($availableRooms as $room)
                            <option value="{{ $room->id }}">
                                Hab. #{{ $room->number }} - {{ $room->category->name }} (Máx. {{ $room->category->max_adults }} Pax) - S/ {{ number_format($room->category->base_price, 2) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-slate-700 mb-1.5">Selección de Moneda</label>
                    <select name="currency" id="currencySelect" class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm font-bold text-blue-800">
                        <option value="PEN">Soles Peruanos (PEN)</option>
                        <option value="USD">Dólares Americanos (USD)</option>
                        <option value="EUR">Euros (EUR)</option>
                    </select>
                    <!-- Texto dinámico en negrita para el tipo de cambio -->
                    <p id="exchangeRateText" class="text-xs text-slate-600 mt-2 font-bold hidden"></p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-700 mb-1.5">Nombre Completo del Titular</label>
                    <input type="text" name="guest_name" required placeholder="Ej. Carlos Mendoza Rivera"
                           class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-700 mb-1.5">DNI o Pasaporte (Titular)</label>
                    <input type="text" name="guest_dni" required maxlength="15" placeholder="Ej. 45678912"
                           class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-700 mb-1.5">Fecha de Check-in</label>
                    <input type="date" name="check_in" value="{{ date('Y-m-d') }}" required
                           class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-700 mb-1.5">Fecha de Check-out</label>
                    <input type="date" name="check_out" value="{{ date('Y-m-d', strtotime('+2 days')) }}" required
                           class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm">
                </div>
            </div>

            <div class="pt-4 flex items-center justify-between border-t border-slate-200">
                <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">← Cancelar y Volver</a>
                <button type="submit" class="px-8 py-3.5 bg-[#0b192c] hover:bg-slate-800 text-white rounded-xl text-sm font-bold shadow-lg transition">
                    Confirmar Reserva y Pasarela →
                </button>
            </div>
        </form>
    </div>

    <!-- Script para mostrar dinámicamente el tipo de cambio comercial debajo del selector -->
    <script>
        const currencySelect = document.getElementById('currencySelect');
        const exchangeRateText = document.getElementById('exchangeRateText');

        function updateExchangeRate() {
            const val = currencySelect.value;
            if (val === 'USD') {
                exchangeRateText.textContent = 'Tipo de cambio aplicado: Compra S/ 3.51 / Venta S/ 3.60 (Incluye margen comercial)';
                exchangeRateText.classList.remove('hidden');
            } else if (val === 'EUR') {
                exchangeRateText.textContent = 'Tipo de cambio aplicado: Compra S/ 3.80 / Venta S/ 3.92 (Incluye margen comercial)';
                exchangeRateText.classList.remove('hidden');
            } else {
                exchangeRateText.textContent = '';
                exchangeRateText.classList.add('hidden');
            }
        }

        currencySelect.addEventListener('change', updateExchangeRate);
        // Ejecutar al cargar por si hay valor por defecto
        updateExchangeRate();
    </script>
@endsection