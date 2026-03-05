<?php

namespace App\Models;

use App\Filters\SectionFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sections extends Model
{
    use SoftDeletes, Filterable;
    protected $guarded = [];
    protected $default_filters = SectionFilter::class;
}
