<?php

namespace App\Models;

use App\Filters\ChecklistFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Staudenmeir\EloquentJsonRelations\HasJsonRelationships;

class Checklist extends Model
{
    use SoftDeletes, Filterable, HasJsonRelationships;

    protected $default_filters = ChecklistFilter::class;

    protected $guarded = [];

    protected $casts = [
        'items' => 'json',
        'unit_ids' => 'array',
//        'inspection_area_ids' => 'array',
//        'infestation_level_ids' => 'array',
    ];

    public function section() {
        return $this->belongsTo(Section::class);
    }

    public function units() {
        return $this->belongsToJson(Unit::class, 'unit_ids')->withTrashed();
    }

    public function responses()
    {
        return $this->hasMany(Response::class, 'checklist_id');
    }

//    public function inspectionAreas() {
//        return $this->belongsToJson(InspectionArea::class, 'inspection_area_ids')->withTrashed();
//    }
//
//    public function infestationLevels() {
//        return $this->belongsToJson(InfestationLevel::class, 'infestation_level_ids')->withTrashed();
//    }
    /**
     * Count total number of sub_items across all items
     */
    public function countSubItems(): int
    {
        $count = 0;
        $items = $this->items ?? [];

        foreach ($items as $item) {
            $itemList = $item['items'] ?? [];
            foreach ($itemList as $subItem) {
                $subItems = $subItem['sub_items'] ?? [];
                $count += count($subItems);
            }
        }

        return $count;
    }

    /**
     * Get all sub_items as a flat array
     */
    public function getAllSubItems(): array
    {
        $allSubItems = [];
        $items = $this->items ?? [];

        foreach ($items as $item) {
            $itemList = $item['items'] ?? [];
            foreach ($itemList as $subItem) {
                $subItems = $subItem['sub_items'] ?? [];
                $allSubItems = array_merge($allSubItems, $subItems);
            }
        }

        return $allSubItems;
    }
}
