<?php

namespace App\Services;

use App\Models\InspectionAreaInfestationLevel;

class InspectionAreaInfestationLevelService
{
    protected $inspectionAreaInfestationLevel;
    public function __construct(InspectionAreaInfestationLevel $inspectionAreaInfestationLevel)
    {
        $this->inspectionAreaInfestationLevel = $inspectionAreaInfestationLevel;
    }

    public function getSurveys() {
        return $this->inspectionAreaInfestationLevel->useFilters()->dynamicPaginate();
    }

    public function storeSurvey(array $data) {
        return $this->inspectionAreaInfestationLevel->create($data);
    }

    public function showSurvey(int $id) {
        return $this->inspectionAreaInfestationLevel->where('id', $id)->first();
    }

    public function updateSurvey(InspectionAreaInfestationLevel $inspectionAreaInfestationLevel, array $data): InspectionAreaInfestationLevel
    {
        $inspectionAreaInfestationLevel->update($data);

        return $inspectionAreaInfestationLevel->fresh()->load([
            'inspectionAreas:id,name',
            'InfestationLevels:id,name,type',
        ]);
    }

    public function destroySurvey(int $id) {
        $survey = $this->inspectionAreaInfestationLevel->withTrashed()->find($id);

        if (!$survey) {
            return null;
        }

        if ($survey->trashed()) {
            $survey->restore();
        } else {
            $survey->delete();
        }

        return $survey;
    }
}
