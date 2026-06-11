<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcknowledgementSettingResource extends JsonResource
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
            'hierarchy' => $this->hierarchies->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->getFullNameAttribute(),
                'role' => $user->role->name,
            ]),
            'user' => [
                'id' => $this->users->id,
                'name' => $this->users->getFullNameAttribute()
            ],
            'sections' => [
                'id' => $this->sections->id,
                'name' => $this->sections->name,
            ]
        ];
    }
}
