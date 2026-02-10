<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TontineMemberResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
            'order_position' => $this->order_position,
        ];
    }
}
