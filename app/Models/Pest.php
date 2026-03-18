<?php

namespace App\Models;

use App\Filters\PestFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Staudenmeir\EloquentJsonRelations\HasJsonRelationships;

class Pest extends Model
{
    use SoftDeletes, Filterable, HasJsonRelationships;

    protected $guarded = [];

    protected $default_filters = PestFilter::class;
}
