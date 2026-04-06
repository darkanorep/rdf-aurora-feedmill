<?php

namespace App\Models;

use App\Filters\UnitFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use SoftDeletes, Filterable;

    protected $guarded = [];
    protected string $default_filters = UnitFilter::class;

}
