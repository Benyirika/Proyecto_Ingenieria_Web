<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchRoomCategoryRate;
use App\Models\ExchangeRate;
use App\Models\RoomCategory;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(): View
    {
        return view('admin.index', [
            'branches' => Branch::query()->with('roomCategoryRates.category')->orderBy('name')->get(),
            'users' => User::query()->with('branch')->orderBy('role')->orderBy('name')->get(),
            'categories' => RoomCategory::query()->orderBy('name')->get(),
            'rates' => ExchangeRate::query()->with('manager')->orderByDesc('effective_on')->orderBy('currency')->limit(30)->get(),
            'auditLogs' => AuditLog::query()->with(['actor', 'branch'])->latest('created_at')->limit(50)->get(),
        ]);
    }

    public function storeBranch(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:10', 'unique:branches,code'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $branch = DB::transaction(function () use ($validated, $request, $auditLogger): Branch {
            $branch = Branch::create([...$validated, 'is_active' => true]);

            foreach (RoomCategory::query()->get() as $category) {
                BranchRoomCategoryRate::create([
                    'branch_id' => $branch->id,
                    'room_category_id' => $category->id,
                    'nightly_rate_pen' => $category->base_price,
                    'is_active' => true,
                ]);
            }

            $auditLogger->record($request->user(), (int) $branch->id, 'branch.created', 'branch', (int) $branch->id, 'Sucursal creada.');

            return $branch;
        });

        return back()->with('success', 'Sucursal '.$branch->name.' creada con tarifas iniciales para las categorías existentes.');
    }

    public function updateBranch(Request $request, Branch $branch, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:10', Rule::unique('branches', 'code')->ignore($branch->id)],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'is_active' => ['required', 'boolean'],
        ]);

        $branch->update($validated);
        $auditLogger->record($request->user(), (int) $branch->id, 'branch.updated', 'branch', (int) $branch->id, 'Sucursal actualizada.');

        return back()->with('success', 'Sucursal actualizada.');
    }

    public function updateRoomRate(
        Request $request,
        Branch $branch,
        RoomCategory $category,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'nightly_rate_pen' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'is_active' => ['required', 'boolean'],
        ]);

        $rate = BranchRoomCategoryRate::query()->updateOrCreate(
            [
                'branch_id' => $branch->id,
                'room_category_id' => $category->id,
            ],
            [
                'nightly_rate_pen' => $validated['nightly_rate_pen'],
                'is_active' => $validated['is_active'],
            ],
        );

        $auditLogger->record(
            $request->user(),
            (int) $branch->id,
            'branch_room_rate.updated',
            'branch_room_category_rate',
            (int) $rate->id,
            'Tarifa de '.$category->name.' actualizada.',
        );

        return back()->with('success', 'Tarifa de habitación guardada.');
    }

    public function storeUser(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['admin', 'recepcion', 'limpieza'])],
            'branch_id' => [
                Rule::requiredIf(fn (): bool => in_array($request->input('role'), ['recepcion', 'limpieza'], true)),
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('is_active', true),
            ],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'branch_id' => $validated['role'] === 'admin' ? null : $validated['branch_id'],
            'password' => $validated['password'],
            'is_active' => true,
        ]);

        $auditLogger->record($request->user(), $user->branch_id, 'user.created', 'user', (int) $user->id, 'Usuario creado con rol '.$user->role.'.');

        return back()->with('success', 'Usuario creado.');
    }

    public function updateUser(Request $request, User $user, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(['admin', 'recepcion', 'limpieza'])],
            'branch_id' => [
                Rule::requiredIf(fn (): bool => in_array($request->input('role'), ['recepcion', 'limpieza'], true)),
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('is_active', true),
            ],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
        ]);

        if ((int) $request->user()->id === (int) $user->id
            && ($validated['role'] !== 'admin' || ! $validated['is_active'])) {
            return back()->withErrors(['user' => 'No puedes desactivar ni quitar el rol admin a tu propia cuenta.'])->withInput();
        }

        $attributes = [
            'name' => $validated['name'],
            'role' => $validated['role'],
            'branch_id' => $validated['role'] === 'admin' ? null : $validated['branch_id'],
            'is_active' => $validated['is_active'],
        ];

        if (! empty($validated['password'])) {
            $attributes['password'] = $validated['password'];
        }

        $user->update($attributes);
        $auditLogger->record($request->user(), $user->branch_id, 'user.updated', 'user', (int) $user->id, 'Usuario actualizado.');

        return back()->with('success', 'Usuario actualizado.');
    }

    public function storeExchangeRate(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'currency' => ['required', 'in:USD,EUR'],
            'effective_on' => ['required', 'date'],
            'rate_to_pen' => ['required', 'numeric', 'gt:0', 'max:999999'],
        ]);

        $rate = ExchangeRate::updateOrCreate(
            [
                'currency' => $validated['currency'],
                'effective_on' => $validated['effective_on'],
            ],
            [
                'rate_to_pen' => $validated['rate_to_pen'],
                'managed_by_user_id' => $request->user()->id,
            ],
        );

        $auditLogger->record(
            $request->user(),
            null,
            'exchange_rate.updated',
            'exchange_rate',
            (int) $rate->id,
            'Tasa '.$rate->currency.' actualizada para '.$rate->effective_on->toDateString().'.',
        );

        return back()->with('success', 'Tasa de cambio guardada.');
    }
}
