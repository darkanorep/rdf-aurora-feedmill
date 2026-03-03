<?php

namespace App\Models;

use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Filters\PermissionFilter;

class Permission extends Model
{
    use SoftDeletes, Filterable;
    
    protected $default_filters = PermissionFilter::class;
    
    protected $fillable = [
        "name",
    ];
}
