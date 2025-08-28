<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            "username" => $this->username,
            "profile_id" => $this->profile_id,
            "first_name" => $this->first_name,
            "last_name" => $this->last_name,
            "country_code" => $this->country_code,
            "phone" => $this->phone,
            "gender" => $this->gender,
            "dob" => $this->dob,
            "email" => $this->email,
            "status" => $this->status,
            "created_at" => $this->created_at->format('d M, Y'),
            "galleries" => $this->galleries->map(function ($child) {
                return [
                    'id' => $child->id,
                    'name' => $child->name,
                    'image_url' => \Storage::disk('user')->url($child->name),
                    'created_at' => $child->created_at->format('d M, Y'),
                    // 'tmp_image' => \Storage::disk('minio-temporaryurls')->temporaryUrl($child->name, now()->addMinutes(30))
                ];
            }),
            "tenants" => $this->tenants->map(function ($child) {
                return [
                    'tenant_id' => $child->id,
                    'tanent' => $child
                ];
            })
        ];
    }
}
