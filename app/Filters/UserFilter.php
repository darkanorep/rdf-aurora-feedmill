<?php

declare(strict_types=1);

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class UserFilter extends QueryFilters
{
    protected array $allowedFilters = ['first_name', 'last_name', 'employee_id', 'username'];

    protected array $columnSearch = ['first_name', 'last_name', 'employee_id', 'username'];
}
