<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionAreaInfestationLevelResource extends JsonResource
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
            'inspection_areas' => $this->inspectionAreas->map (fn ($item) => InspectionAreaResource::make($item)),
            'infestation_levels' => $this->infestationLevels->map (fn ($item) => InfestationLevelResource::make($item))
        ];
    }
}
