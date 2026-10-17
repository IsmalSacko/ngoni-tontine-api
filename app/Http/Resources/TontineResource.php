<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TontineResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type ?? 'rotating',
            'group_id' => $this->group_id,
            'amount' => $this->amount,
            'currency' => $this->currency ?? 'XOF',
            'target_amount' => $this->target_amount,
            'deadline' => $this->deadline?->toDateString(),
            // Entrées, sorties et solde de la caisse. Pour une tontine rotative,
            // le pot est reversé au bénéficiaire : le solde n'y a pas de sens.
            'total_collected' => (int) ($this->payments_sum_amount ?? $this->payments()->sum('amount')),
            'total_spent' => (int) ($this->expenses_sum_amount ?? $this->expenses()->sum('amount')),
            'frequency' => $this->frequency,
            'description' => $this->description,
            'members_count' => $this->members_count, // Attribut virtuel défini dans le modèle
            'start_date' => $this->start_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'members' => TontineMemberResource::collection($this->whenLoaded('members')),
            'cycles' => $this->whenLoaded('cycles'),
        ];
    }
}
