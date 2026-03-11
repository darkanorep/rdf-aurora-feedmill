<?php

namespace App\Models;

use App\Filters\FormFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Form extends Model
{
    use SoftDeletes, Filterable;

    protected $guarded = [];

    protected $default_filters = FormFilter::class;
}
