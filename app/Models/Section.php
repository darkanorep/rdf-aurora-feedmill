<?php

namespace App\Models;

use App\Filters\SectionFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Section extends Model
{
    use SoftDeletes, Filterable;
    protected $guarded = [];
    protected $default_filters = SectionFilter::class;

    public function checklists() {
        return $this->hasMany(Checklist::class);
    }
}
