<?php

namespace App\Models;

use App\Filters\UnitFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Staudenmeir\EloquentJsonRelations\HasJsonRelationships;

class Unit extends Model
{
    use SoftDeletes, Filterable, HasJsonRelationships;

    protected $guarded = [];
    protected string $default_filters = UnitFilter::class;

    public function checkLists() {
        return $this->hasManyJson(Checklist::class, 'unit_ids')->withTrashed();
    }

}
