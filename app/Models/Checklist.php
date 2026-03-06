<?php

namespace App\Models;

use App\Filters\ChecklistFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Checklist extends Model
{
    use SoftDeletes, Filterable;

    protected $default_filters = ChecklistFilter::class;

    protected $guarded = [];

    public function section() {
        return $this->belongsTo(Section::class);
    }
}
