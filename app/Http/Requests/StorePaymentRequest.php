<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $tontineId = $this->integer('tontine_id');

        return [
            'tontine_id' => 'required|exists:tontines,id',
            // Le membre et le tour doivent être ceux de cette caisse : sans quoi
            // une cotisation pouvait atterrir dans la caisse d'un autre groupe.
            'tontine_member_id' => [
                'required',
                Rule::exists('tontine_members', 'id')->where('tontine_id', $tontineId),
            ],
            'cycle_id' => [
                'nullable',
                'integer',
                Rule::exists('cycles', 'id')->where('tontine_id', $tontineId),
            ],
            'amount' => 'required|integer|min:1',
            'method' => 'required|in:cash,orange,wave',
            'paid_at' => 'nullable|date|before_or_equal:today',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tontine_member_id.exists' => 'Ce membre ne fait pas partie de cette caisse.',
            'cycle_id.exists' => 'Ce tour n\'appartient pas à cette caisse.',
            'paid_at.before_or_equal' => 'La date du paiement ne peut pas être dans le futur.',
        ];
    }
}
