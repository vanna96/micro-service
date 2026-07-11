<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'foreign_name' => $this->foreign_name,
            'location' => $this->location,
            'sort_order' => (int) $this->sort_order,
            'status' => $this->status,
            'created_at' => $this->created_at?->format('d M, Y'),
        ];
    }
}
