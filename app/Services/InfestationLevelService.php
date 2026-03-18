<?php

namespace App\Services;

use App\Models\InfestationLevel;

class InfestationLevelService
{
    public function getInfestationLevels()
    {
        return InfestationLevel::useFilters()->dynamicPaginate();
    }

    public function createInfestationLevel($data)
    {
        return InfestationLevel::create($data);
    }

    public function getInfestationLevelById($id)
    {
        // Logic to retrieve an inspection area by ID
        return InfestationLevel::find($id);
    }

    public function updateInfestationLevel(InfestationLevel $infestationLevel, array $data): InfestationLevel
    {
        $infestationLevel->update($data);
        return $infestationLevel;
    }

    public function deleteInfestationLevel($id) {
        $infestationLevel = InfestationLevel::withTrashed()->find($id);

        if (!$infestationLevel) {
            return null;
        }

        if ($infestationLevel->trashed()) {
            $infestationLevel->restore();
        } else {
            $infestationLevel->delete();
        }

        return $infestationLevel;
    }
}
