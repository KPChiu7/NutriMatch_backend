<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class BookAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isClient();
    }

    public function rules(): array
    {
        return [
            'rnd_id'           => ['required', 'integer', 'exists:users,id'],
            'scheduled_at'     => ['required', 'date', 'after:now'],
            'type'             => ['required', 'in:in_person,video,chat'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:180'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ];
    }
}
