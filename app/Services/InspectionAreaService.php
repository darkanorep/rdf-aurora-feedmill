<?php

namespace App\Services;

use App\Models\InspectionArea;

class InspectionAreaService
{

    public function getInspectionAreas()
    {
        return InspectionArea::useFilters()->dynamicPaginate();
    }

    public function createInspectionArea($data)
    {
        return InspectionArea::create($data);
    }

    public function getInspectionAreasById($id)
    {
        // Logic to retrieve an inspection area by ID
        return InspectionArea::find($id);
    }

    public function updateInspectionArea(InspectionArea $inspectionArea, array $data)
    {
        $inspectionArea->update($data);
        return $inspectionArea;
    }

    public function deleteInspectionArea($id) {
        $inspectionArea = InspectionArea::withTrashed()->find($id);
    
        if (!$inspectionArea) {
            return null;
        }
    
        if ($inspectionArea->trashed()) {
            $inspectionArea->restore();
        } else {
            $inspectionArea->delete();
        }
    
        return $inspectionArea;
    }
}
    

