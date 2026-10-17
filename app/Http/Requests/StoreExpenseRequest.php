<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => 'required|integer|min:1',
            'label' => 'required|string|max:255',
            'note' => 'nullable|string|max:2000',
            'spent_at' => 'nullable|date',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Le montant de la dépense est requis.',
            'amount.min' => 'Le montant doit être supérieur à 0.',
            'label.required' => 'Dites à quoi a servi cet argent.',
        ];
    }
}
