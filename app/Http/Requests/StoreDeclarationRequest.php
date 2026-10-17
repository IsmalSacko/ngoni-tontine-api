<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeclarationRequest extends FormRequest
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
            'method' => 'required|in:cash,orange,wave',
            'paid_at' => 'nullable|date|before_or_equal:today',
            'reference' => 'nullable|string|max:100',
            'cycle_id' => [
                'nullable',
                'integer',
                Rule::exists('cycles', 'id')->where('tontine_id', $this->route('tontine')->id),
            ],
            'proof' => 'nullable|image|max:5120',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Indiquez le montant payé.',
            'paid_at.before_or_equal' => 'La date ne peut pas être dans le futur.',
            'proof.image' => 'Le justificatif doit être une photo.',
            'proof.max' => 'La photo est trop lourde (5 Mo au plus).',
        ];
    }
}
