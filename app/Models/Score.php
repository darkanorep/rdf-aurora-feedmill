<?php

namespace App\Models;

use App\Filters\ScoreFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Score extends Model
{
    use SoftDeletes, Filterable;

    protected $default_filters = ScoreFilter::class;
    protected $guarded = [];
}
