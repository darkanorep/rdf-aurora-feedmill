<?php

declare(strict_types=1);

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class InspectionAreaInfestationLevelFilter extends QueryFilters
{
    protected array $relationSearch = [
        'infestationLevels' => ['name'],
        'inspectionAreas' => ['name']
    ];

    public function status($status)
    {
        return $this->builder->withTrashed()->when(
            $status === 'inactive',
            fn ($query) => $query->whereNotNull('deleted_at'),
            fn ($query) => $query->whereNull('deleted_at')
        );
    }
}
