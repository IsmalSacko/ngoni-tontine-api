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
    public function rules() : array
    {
        return [
            'name' => 'required|string|max:255',
            'amount' => 'required|integer|min:1',
            'frequency' => 'required|in:daily,weekly,monthly,quarterly,yearly',
            'description' => 'nullable|string|max:1000',
            'start_date' => 'nullable|date',
        ];
    }

    /**
     * Messages de validation personnalisés
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de la tontine est requis.',
            'amount.required' => 'Le montant est requis.',
            'amount.min' => 'Le montant doit être supérieur à 0.',
            'frequency.required' => 'La fréquence est requise.',
            'frequency.in' => 'La fréquence sélectionnée n\'est pas valide.',
        ];
    }
}
