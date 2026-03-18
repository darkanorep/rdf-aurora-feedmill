<?php

namespace App\Models;

use App\Filters\InspectionAreaPestFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Staudenmeir\EloquentJsonRelations\HasJsonRelationships;

class InspectionAreaPest extends Model
{
    use SoftDeletes, HasJsonRelationships, Filterable;
    protected $table = 'inspection_area_pest';
    protected $default_filters = InspectionAreaPestFilter::class;

    protected $fillable = [
        'inspection_area_id',
        'pest_id',
    ];

    protected $casts = [
        'inspection_area_id' => 'array',
        'pest_id' => 'array',
    ];

    protected $hidden = [
        'inspection_area_id',
        'pest_id',
    ];

    public function pests() {
        return $this->belongsToJson(Pest::class, 'pest_id');
    }

    public function inspectionAreas() {
        return $this->belongsToJson(InspectionArea::class, 'inspection_area_id');
    }
}
