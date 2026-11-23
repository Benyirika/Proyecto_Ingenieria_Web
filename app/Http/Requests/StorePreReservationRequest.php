<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user();

        if ($user !== null && ! $user->isAdmin()) {
            $this->merge(['branch_id' => $user->branch_id]);
        }
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'rooms' => ['required', 'array', 'min:1', 'max:8'],
            'rooms.*.room_category_id' => ['required', 'integer', 'distinct', 'exists:room_categories,id'],
            'rooms.*.quantity' => ['required', 'integer', 'min:0', 'max:10'],
            'primary_guest' => ['required', 'array'],
            'primary_guest.document_type' => ['required', Rule::in(['DNI', 'PASAPORTE', 'CE'])],
            'primary_guest.document_number' => ['required', 'string', 'min:4', 'max:40'],
            'primary_guest.first_name' => ['required', 'string', 'min:2', 'max:120'],
            'primary_guest.last_name' => ['required', 'string', 'min:2', 'max:120'],
            'primary_guest.email' => ['nullable', 'email', 'max:255'],
            'primary_guest.phone' => ['nullable', 'string', 'max:30'],
            'companions' => ['nullable', 'array', 'max:39'],
            'companions.*.document_type' => ['required', Rule::in(['DNI', 'PASAPORTE', 'CE'])],
            'companions.*.document_number' => ['required', 'string', 'min:4', 'max:40'],
            'companions.*.first_name' => ['required', 'string', 'min:2', 'max:120'],
            'companions.*.last_name' => ['required', 'string', 'min:2', 'max:120'],
            'companions.*.email' => ['nullable', 'email', 'max:255'],
            'companions.*.phone' => ['nullable', 'string', 'max:30'],
            'companions.*.guest_type' => ['required', Rule::in(['adulto', 'nino'])],
            'services' => ['nullable', 'array', 'max:20'],
            'services.*.extra_service_id' => ['required', 'integer', 'distinct', 'exists:extra_services,id'],
            'services.*.quantity' => ['required', 'integer', 'min:0', 'max:20'],
        ];
    }
}
