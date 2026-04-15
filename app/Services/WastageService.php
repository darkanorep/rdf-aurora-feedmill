<?php

namespace App\Services;

use App\Models\Wastage;

class WastageService
{
    protected $wastage;
    public function __construct(Wastage $wastage)
    {
        $this->wastage = $wastage;
    }

    public function getWastage()
    {
        return $this->wastage->useFilters()->dynamicPaginate();
    }

    public function createWastage(array $data)
    {
        return $this->wastage->create($data);
    }

    public function getWastageById($id)
    {
        return $this->wastage->find($id);
    }

    public function updateWastage(Wastage $wastage, array $data)
    {
        $wastage->update($data);
        return $wastage;
    }

    public function deleteWastage($id) {
        $wastage = $this->wastage->withTrashed()->find($id);

        if (!$wastage) {
            return null;
        }

        if ($wastage->trashed()) {
            $wastage->restore();
        } else {
            $wastage->delete();
        }

        return $wastage;
    }
}
