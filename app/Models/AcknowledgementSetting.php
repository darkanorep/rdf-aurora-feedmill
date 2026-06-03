<?php

namespace App\Models;

use App\Filters\AcknowledgementSettingFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Staudenmeir\EloquentJsonRelations\HasJsonRelationships;

class AcknowledgementSetting extends Model
{
    use SoftDeletes, HasJsonRelationships, Filterable;

    protected $guarded = [];
    protected $casts = [
        'hierarchy' => 'json',
    ];

    protected $default_filters = AcknowledgementSettingFilter::class;

    public function users() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function hierarchies() {
        return $this->belongsToJson(User::class, 'hierarchy');
    }
}
