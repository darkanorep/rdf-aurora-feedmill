<?php

declare(strict_types=1);

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class InspectionAreaPestFilter extends QueryFilters
{

    protected array $relationSearch = [
        'pests' => ['name'],
        'inspectionAreas' => ['name']
    ];

    public function status($status)
    {
        return $this->builder->withTrashed()->when(!$status, function ($query) {
            $query->whereNotNull('deleted_at');
        }, function ($query) use ($status) {
            $query->when($status, function ($query){
                $query->whereNull('deleted_at');
            });
        });
    }
}
