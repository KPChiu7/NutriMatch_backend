<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a client creating a reminder for themselves.
 * client_id and created_by are both set server-side to the authenticated
 * user — never taken from the request body.
 */
class ReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isClient();
    }

    public function rules(): array
    {
        return [
            'title'   => ['required_without:id', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
            'type'    => ['required_without:id', 'in:appointment,meal_log,medication,general'],
            'send_at' => ['required_without:id', 'date', 'after:now'],
        ];
    }
}
