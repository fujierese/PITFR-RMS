<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\FacilityRequest::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'department' => ['required', 'string', 'max:100'],
            'name_of_activity' => ['required', 'string', 'max:200'],
            'expected_participants' => ['required', 'integer', 'min:1'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'venue' => ['nullable', 'array'],
            'equipment' => ['nullable', 'array'],
            'equipment_quantities' => ['nullable', 'array'],
            'other_venue' => ['nullable', 'string', 'max:200'],
            'priority' => ['nullable', 'in:regular,institutional'],
            'is_emergency' => ['nullable', 'boolean'],
        ];
    }
}
