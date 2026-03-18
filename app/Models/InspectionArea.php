<?php

namespace App\Models;

use App\Filters\InspectionAreaFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Staudenmeir\EloquentJsonRelations\HasJsonRelationships;

class InspectionArea extends Model
{
    use SoftDeletes, Filterable, HasJsonRelationships;

    protected $guarded = [];

    protected $default_filters = InspectionAreaFilter::class;
}
