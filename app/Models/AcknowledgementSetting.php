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
    protected $casts = ['hierarchy' => 'array', 'evaluator_ids' => 'array'];

    protected $default_filters = AcknowledgementSettingFilter::class;

    protected static function booted(): void
    {
        $sync = fn ($setting) => app(\App\Services\ApprovalFlowSync::class)->sync($setting);

        static::saved($sync);
        static::deleted($sync);
        static::restored($sync);
    }

    public function hierarchies() {
        return $this->belongsToJson(User::class, 'hierarchy');
    }

    public function evaluators() {
        return $this->belongsToJson(User::class, 'evaluator_ids');
    }

    public function sections() {
        return $this->belongsTo(Section::class, 'section_id');
    }
}
