<?php

namespace App\Models;

use App\Filters\PestFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pest extends Model
{
    use SoftDeletes, Filterable;

    protected $guarded = [];

    protected $default_filters = PestFilter::class;
}
