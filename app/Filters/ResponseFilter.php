<?php

declare(strict_types=1);

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class ResponseFilter extends QueryFilters
{
    protected array $allowedFilters = [];

    protected array $columnSearch = [];

    protected array $relationSearch = [
        'unit' => ['name']
    ];

    public function month($month)
    {
        return $this->builder->when($month, function ($query) use ($month) {
            $query->whereMonth('start_at', $month);
        });
    }

    public function year($year)
    {
        return $this->builder->when($year, function ($query) use ($year) {
            $query->whereYear('start_at', $year);
        });
    }

    public function section($section) {
        return $this->builder->when($section, function ($query) use ($section) {
            $query->whereHas('section', function ($query) use ($section) {
                $query->where('sections.name', $section);
            });
        });
    }
}
