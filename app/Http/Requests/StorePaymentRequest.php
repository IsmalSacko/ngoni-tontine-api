<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
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
            'tontine_id' => 'required|exists:tontines,id',
            'tontine_member_id' => 'required|exists:tontine_members,id',
            'cycle_id' => 'nullable|sometimes|integer',
            'amount' => 'required|integer|min:1',
            'method' => 'required|in:cash,orange,wave',
        ];
    }
}
