<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phone' => [
                'required',
                'string',
                // Le même numéro, écrit autrement, a déjà un compte.
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (\App\Models\User::findByPhone((string) $value) !== null) {
                        $fail('Ce numéro a déjà un compte. Connectez-vous.');
                    }
                },
            ],
            'name' => 'required|string',
            'pin_code' => 'required|string|size:4',
        ];
    }
}
