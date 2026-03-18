<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionAreaPestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'inspection_areas' => $this->inspectionAreas->map (fn ($item) => InspectionAreaResource::make($item)),
            'pests' => $this->pests->map (fn ($item) => PestResource::make($item))
        ];
    }
}
