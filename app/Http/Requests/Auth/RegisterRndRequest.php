<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRndRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'          => ['required', 'string', 'max:100'],
            'last_name'           => ['required', 'string', 'max:100'],
            'email'               => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'            => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'phone'               => ['nullable', 'string', 'max:20'],
            'prc_license_number'  => ['required', 'string', 'max:50', 'unique:rnd_profiles,prc_license_number'],
            'prc_expiry_date'     => ['required', 'date', 'after:today'],
            'specialization'      => ['nullable', 'string', 'max:255'],
            'consultation_fee'    => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'bio'                 => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'prc_license_number.unique'  => 'This PRC license number is already registered.',
            'prc_expiry_date.after'      => 'PRC license must not be expired.',
        ];
    }
}
