<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "foreign_name" => $this->foreign_name, 
            "status" => $this->status,
            "created_at" => $this->created_at?->format('d M, Y'),
            "parent" => $this->parent
                        ? implode(' / ', array_filter([$this->parent->name, $this->parent->foreign_name]))
                        : null,
            "galleries" => $this->galleries->map(function ($child) {
                return [
                    'id' => $child->id,
                    'name' => $child->name,
                    'image_url' => \Storage::disk('user')->url($child->name),
                    'created_at' => $child->created_at->format('d M, Y'),
                    // 'tmp_image' => \Storage::disk('minio-temporaryurls')->temporaryUrl($child->name, now()->addMinutes(30))
                ];
            })
        ];
    }
}
