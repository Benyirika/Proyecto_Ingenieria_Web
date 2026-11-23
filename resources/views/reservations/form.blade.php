@php
    $oldCompanions = old('companions', []);
    $oldCompanions = is_array($oldCompanions) ? array_values(array_filter($oldCompanions, 'is_array')) : [];
@endphp
<form action="{{ $formAction }}" method="POST" class="space-y-8" data-booking-form>
    @csrf
    <input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">
    <input type="hidden" name="check_in" value="{{ $checkIn }}">
    <input type="hidden" name="check_out" value="{{ $checkOut }}">

    <section>
        <div class="mb-3">
            <h3 class="text-base font-extrabold">Habitaciones por tipo</h3>
            <p class="mt-1 text-xs text-slate-500">La disponibilidad se valida nuevamente en el servidor al enviar la solicitud.</p>
        </div>
        <div class="grid gap-3 md:grid-cols-2">
            @foreach($inventory as $index => $category)
                <div class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 p-4">
                    <div class="min-w-0">
                        <input type="hidden" name="rooms[{{ $index }}][room_category_id]" value="{{ $category->id }}">
                        <p class="truncate text-sm font-bold">{{ $category->name }}</p>
                        <p class="mt-1 text-xs text-slate-500">Hasta {{ $category->max_adults }} huéspedes · S/ {{ number_format((float) $category->nightly_rate_pen, 2) }} / noche</p>
                        <p class="mt-1 text-xs {{ $category->available_count > 0 ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ $category->available_count }} disponibles para estas fechas
                        </p>
                    </div>
                    <label class="shrink-0 text-xs font-semibold text-slate-600">
                        Cantidad
                        <input type="number" name="rooms[{{ $index }}][quantity]" min="0" max="{{ min(10, $category->available_count) }}" value="{{ old("rooms.$index.quantity", 0) }}" class="mt-1 block w-20 rounded-lg border border-slate-300 px-2 py-2 text-center text-sm" @disabled($category->available_count < 1)>
                    </label>
                </div>
            @endforeach
        </div>
        @if($inventory->isEmpty())
            <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">No hay tipos de habitación habilitados para esta sucursal o periodo.</p>
        @endif
    </section>

    <section class="space-y-4">
        <div>
            <h3 class="text-base font-extrabold">Titular de la reserva</h3>
            <p class="mt-1 text-xs text-slate-500">El titular cuenta como un huésped dentro de la capacidad total.</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <select name="primary_guest[document_type]" required class="rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm">
                <option value="DNI" @selected(old('primary_guest.document_type') === 'DNI')>DNI</option>
                <option value="PASAPORTE" @selected(old('primary_guest.document_type') === 'PASAPORTE')>Pasaporte</option>
                <option value="CE" @selected(old('primary_guest.document_type') === 'CE')>Carnet de extranjería</option>
            </select>
            <input name="primary_guest[document_number]" value="{{ old('primary_guest.document_number') }}" required minlength="4" maxlength="40" placeholder="Número de documento" class="rounded-xl border border-slate-300 px-3 py-3 text-sm">
            <input name="primary_guest[first_name]" value="{{ old('primary_guest.first_name') }}" required minlength="2" maxlength="120" placeholder="Nombres" class="rounded-xl border border-slate-300 px-3 py-3 text-sm">
            <input name="primary_guest[last_name]" value="{{ old('primary_guest.last_name') }}" required minlength="2" maxlength="120" placeholder="Apellidos" class="rounded-xl border border-slate-300 px-3 py-3 text-sm">
            <input name="primary_guest[email]" type="email" value="{{ old('primary_guest.email') }}" placeholder="Correo (opcional)" class="rounded-xl border border-slate-300 px-3 py-3 text-sm">
            <input name="primary_guest[phone]" type="tel" value="{{ old('primary_guest.phone') }}" placeholder="Teléfono (opcional)" class="rounded-xl border border-slate-300 px-3 py-3 text-sm">
        </div>
    </section>

    <section>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-extrabold">Acompañantes</h3>
                <p class="mt-1 text-xs text-slate-500">La cantidad total debe caber en las habitaciones seleccionadas.</p>
            </div>
            <button type="button" data-add-companion class="rounded-lg border border-blue-200 px-3 py-2 text-xs font-bold text-blue-800 hover:bg-blue-50">Añadir acompañante</button>
        </div>
        <div data-companions class="grid gap-3">
            @foreach($oldCompanions as $index => $companion)
                <div data-companion-row data-index="{{ $index }}" class="grid gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 sm:grid-cols-2 lg:grid-cols-4">
                    <select name="companions[{{ $index }}][document_type]" required class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        @foreach(['DNI' => 'DNI', 'PASAPORTE' => 'Pasaporte', 'CE' => 'Carnet de extranjería'] as $type => $label)
                            <option value="{{ $type }}" @selected(($companion['document_type'] ?? '') === $type)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input name="companions[{{ $index }}][document_number]" value="{{ $companion['document_number'] ?? '' }}" required minlength="4" maxlength="40" placeholder="Documento" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="companions[{{ $index }}][first_name]" value="{{ $companion['first_name'] ?? '' }}" required minlength="2" maxlength="120" placeholder="Nombres" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="companions[{{ $index }}][last_name]" value="{{ $companion['last_name'] ?? '' }}" required minlength="2" maxlength="120" placeholder="Apellidos" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <select name="companions[{{ $index }}][guest_type]" required class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="adulto" @selected(($companion['guest_type'] ?? 'adulto') === 'adulto')>Adulto</option>
                        <option value="nino" @selected(($companion['guest_type'] ?? '') === 'nino')>Niño</option>
                    </select>
                    <input name="companions[{{ $index }}][email]" type="email" value="{{ $companion['email'] ?? '' }}" placeholder="Correo (opcional)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="companions[{{ $index }}][phone]" type="tel" value="{{ $companion['phone'] ?? '' }}" placeholder="Teléfono (opcional)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <button type="button" data-remove-companion class="rounded-lg px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Quitar</button>
                </div>
            @endforeach
        </div>
        <template data-companion-template>
            <div class="grid gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 sm:grid-cols-2 lg:grid-cols-4">
                <select data-field="document_type" required class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="DNI">DNI</option>
                    <option value="PASAPORTE">Pasaporte</option>
                    <option value="CE">Carnet de extranjería</option>
                </select>
                <input data-field="document_number" required minlength="4" maxlength="40" placeholder="Documento" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input data-field="first_name" required minlength="2" maxlength="120" placeholder="Nombres" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input data-field="last_name" required minlength="2" maxlength="120" placeholder="Apellidos" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select data-field="guest_type" required class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="adulto">Adulto</option>
                    <option value="nino">Niño</option>
                </select>
                <input data-field="email" type="email" placeholder="Correo (opcional)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input data-field="phone" type="tel" placeholder="Teléfono (opcional)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button type="button" data-remove-companion class="rounded-lg px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Quitar</button>
            </div>
        </template>
    </section>

    <section>
        <h3 class="mb-3 text-base font-extrabold">Servicios adicionales</h3>
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach($extraServices as $index => $service)
                <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 p-3">
                    <div>
                        <input type="hidden" name="services[{{ $index }}][extra_service_id]" value="{{ $service->id }}">
                        <p class="text-sm font-bold">{{ $service->name }}</p>
                        <p class="text-xs text-slate-500">S/ {{ number_format((float) $service->base_price_pen, 2) }} por unidad</p>
                    </div>
                    <label class="text-xs font-semibold text-slate-600">
                        Cantidad
                        <input type="number" name="services[{{ $index }}][quantity]" min="0" max="20" value="{{ old("services.$index.quantity", 0) }}" class="mt-1 block w-20 rounded-lg border border-slate-300 px-2 py-2 text-center text-sm">
                    </label>
                </div>
            @endforeach
        </div>
    </section>

    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
        @if($isPublic)
            Esta solicitud es una <strong>pre-reserva</strong>. No se realizará ningún cobro en línea; recepción confirmará disponibilidad y pago.
        @else
            Esta acción crea la reserva confirmada. Registra el cobro por separado y completa el check-in cuando llegue el huésped.
        @endif
    </div>

    @if($errors->any())
        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <ul class="list-inside list-disc space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <button type="submit" class="w-full rounded-xl bg-blue-700 px-5 py-3.5 text-sm font-extrabold text-white hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200">
        {{ $isPublic ? 'Enviar pre-reserva sin pago' : 'Crear reserva confirmada' }}
    </button>
</form>
