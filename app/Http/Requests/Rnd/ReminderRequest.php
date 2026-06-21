<?php

namespace App\Http\Requests\Rnd;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an RND creating a reminder for one of their clients.
 * client_id must be a client the RND has an ACTIVE relationship with —
 * that check happens in the controller (it needs the authenticated
 * RND's id, which isn't available to a static validation rule), not here.
 * created_by is set server-side to the authenticated RND.
 */
class ReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isRnd();
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required_without:id', 'integer', 'exists:users,id'],
            'title'     => ['required_without:id', 'string', 'max:255'],
            'message'   => ['nullable', 'string', 'max:2000'],
            'type'      => ['required_without:id', 'in:appointment,meal_log,medication,general'],
            'send_at'   => ['required_without:id', 'date', 'after:now'],
        ];
    }
}
