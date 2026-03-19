<?php

namespace App\Models;

use App\Filters\RoleFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Staudenmeir\EloquentJsonRelations\HasJsonRelationships;

class Role extends Model
{
    use SoftDeletes, Filterable, HasJsonRelationships;
    const ADMIN = 'Admin';

    protected $default_filters = RoleFilter::class;

    protected $guarded = [];


    protected $casts = [
        'permission_id' => 'json',
    ];

    public function permissions() {
        return $this->belongsToJson(Permission::class, 'permission_id');
    }
}
