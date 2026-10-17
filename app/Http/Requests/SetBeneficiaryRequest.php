<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetBeneficiaryRequest extends FormRequest
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
            'member_id' => [
                'required',
                'integer',
                Rule::exists('tontine_members', 'id')->where('tontine_id', $this->route('tontine')->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'member_id.required' => 'Choisissez le bénéficiaire.',
            'member_id.exists' => 'Ce membre ne fait pas partie de la tontine.',
        ];
    }
}
