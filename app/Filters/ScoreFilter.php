<?php

declare(strict_types=1);

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class ScoreFilter extends QueryFilters
{
    protected array $allowedFilters = ['score', 'rating'];

    protected array $columnSearch = ['score', 'rating'];
}
