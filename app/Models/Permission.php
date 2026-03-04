<?php

namespace App\Models;

use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Filters\PermissionFilter;
use Staudenmeir\EloquentJsonRelations\HasJsonRelationships;

class Permission extends Model
{
    use SoftDeletes, Filterable, HasJsonRelationships;
    
    protected $default_filters = PermissionFilter::class;
    
    protected $guarded = [];
    
    public function roles() {
        return $this->hasManyJson(Role::class, 'permission_id');
    }
}
