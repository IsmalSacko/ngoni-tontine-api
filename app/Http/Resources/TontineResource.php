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
            'amount' => $this->amount,
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
