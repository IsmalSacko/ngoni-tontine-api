<?php

namespace App\Http\Requests;

use App\Models\Tontine;
use Illuminate\Foundation\Http\FormRequest;

class CreateTontineRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prépare les données pour la validation
     */
    protected function prepareForValidation(): void
    {
        // Convertir la fréquence française en anglais si nécessaire
        if ($this->has('frequency')) {
            $frequency = $this->input('frequency');

            // Si la fréquence est en français, on la convertit
            if (array_key_exists($frequency, Tontine::FREQUENCY_MAP)) {
                $this->merge([
                    'frequency' => Tontine::FREQUENCY_MAP[$frequency],
                ]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'sometimes|in:'.implode(',', Tontine::TYPES),
            'amount' => 'required_unless:type,fundraising|nullable|integer|min:1',
            // Une collecte ou une part fixe n'a pas de période.
            'frequency' => 'exclude_if:type,fundraising|exclude_if:type,share|required|in:daily,weekly,monthly,quarterly,yearly',
            'target_amount' => 'required_if:type,fundraising|nullable|integer|min:1',
            'description' => 'nullable|string|max:1000',
            'start_date' => 'nullable|date',
            'deadline' => 'nullable|date',
            'group_id' => 'nullable|integer|exists:groups,id',
            'currency' => 'nullable|in:'.implode(',', Tontine::CURRENCIES),
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de la tontine est requis.',
            'amount.required' => 'Le montant est requis.',
            'amount.min' => 'Le montant doit être supérieur à 0.',
            'frequency.required' => 'La fréquence est requise.',
            'frequency.in' => 'La fréquence sélectionnée n\'est pas valide.',
            'amount.required_unless' => 'Le montant de la cotisation est requis.',
            'frequency.required_unless' => 'La fréquence est requise.',
            'target_amount.required_if' => 'L\'objectif de la collecte est requis.',
        ];
    }
}
