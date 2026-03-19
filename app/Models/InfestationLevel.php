<?php

namespace App\Models;

use App\Filters\InfestationLevelFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InfestationLevel extends Model
{
    use SoftDeletes, Filterable;

    protected $guarded = [];
    protected $default_filters = InfestationLevelFilter::class;

}
