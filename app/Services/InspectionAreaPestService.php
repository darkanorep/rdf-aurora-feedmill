<?php

namespace App\Services;

use App\Models\InspectionAreaPest;

class InspectionAreaPestService
{
    protected $inspectionAreaPest;
    public function __construct(InspectionAreaPest  $inspectionAreaPest)
    {
        $this->inspectionAreaPest = $inspectionAreaPest;
    }

    public function getSheets() {
        return $this->inspectionAreaPest->useFilters()->dynamicPaginate();
    }

    public function storeSheet(array $data) {
        return $this->inspectionAreaPest->create($data);
    }

    public function showSheet(int $id) {
        return $this->inspectionAreaPest->where('id', $id)->first();
    }

    public function updateSheet(InspectionAreaPest $inspectionAreaPest, array $data): InspectionAreaPest
    {
        $inspectionAreaPest->update($data);

        // Return fresh model + relations expected by the resource
        return $inspectionAreaPest->fresh()->load([
            'inspectionAreas:id,name',
            'pests:id,name',
        ]);
    }

    public function destroySheet(int $id) {
        $sheet = $this->inspectionAreaPest->withTrashed()->find($id);

        if (!$sheet) {
            return null;
        }

        if ($sheet->trashed()) {
            $sheet->restore();
        } else {
            $sheet->delete();
        }

        return $sheet;
    }
}
