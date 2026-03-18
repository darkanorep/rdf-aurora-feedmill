<?php

namespace App\Models;

use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Staudenmeir\EloquentJsonRelations\HasJsonRelationships;

class InspectionAreaInfestationLevel extends Model
{
    use SoftDeletes, Filterable, HasJsonRelationships;

    protected $table = 'inspection_area_infestation_level';

    protected $fillable = [
        'inspection_area_id',
        'infestation_level_id',
    ];

    protected $casts = [
        'inspection_area_id' => 'array',
        'infestation_level_id' => 'array',
    ];

    protected $hidden = [
        'inspection_area_id',
        'infestation_level_id',
    ];
}
