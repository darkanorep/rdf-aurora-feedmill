<?php

namespace App\Models;

use App\Filters\WastageFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wastage extends Model
{
    use SoftDeletes, Filterable;

    protected $guarded = [];
    protected $default_filters = WastageFilter::class;
}
