<?php

namespace App\Services;

use App\Models\Unit;

class UnitService
{
    /**
     * Create a new class instance.
     */
    protected $unit;
    public function __construct(Unit $unit)
    {
        $this->unit = $unit;
    }

    public function getUnits()
    {
        return $this->unit->useFilters()->dynamicPaginate();
    }

    public function createUnit(array $data)
    {
        return $this->unit->create($data);
    }

    public function getUnitById($id)
    {
        return $this->unit->find($id);
    }

    public function updateUnit(Unit $unit, array $data)
    {
        $unit->update($data);
        return $unit;
    }

    public function deleteUnit($id) {
        $unit = $this->unit->withTrashed()->find($id);

        if (!$unit) {
            return null;
        }

        if ($unit->trashed()) {
            $unit->restore();
        } else {
            $unit->delete();
        }

        return $unit;
    }
}
