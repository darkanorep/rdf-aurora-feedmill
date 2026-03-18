<?php

declare(strict_types=1);

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class UserFilter extends QueryFilters
{
    protected array $allowedFilters = ['first_name', 'last_name', 'employee_id', 'username'];

    protected array $columnSearch = ['first_name', 'last_name', 'employee_id', 'username'];

    public function status($status)
    {
        return $this->builder->withTrashed()->when(
            $status === 'inactive',
            fn ($query) => $query->whereNotNull('deleted_at'),
            fn ($query) => $query->whereNull('deleted_at')
        );
    }
}
