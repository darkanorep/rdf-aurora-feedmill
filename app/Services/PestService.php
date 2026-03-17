<?php

namespace App\Services;

use App\Models\Pest;

class PestService
{
    
    protected $pest;
    public function __construct(Pest $pest)
    {
        $this->pest = $pest;
    }

    public function getPests()
    {
        return $this->pest::useFilters()->dynamicPaginate();
    }

    public function createPest(array $data)
    {
        return $this->pest::create($data);
    }

    public function getPestById($id)
    {
        return $this->pest::find($id);
    }

    public function updatePest(Pest $pest, array $data)
    {
        $pest->update($data);
        return $pest;
    }

    public function deletePest($id) {
        $pest = $this->pest::withTrashed()->find($id);
    
        if (!$pest) {
            return null;
        }
    
        if ($pest->trashed()) {
            $pest->restore();
        } else {
            $pest->delete();
        }
    
        return $pest;
    }
}
