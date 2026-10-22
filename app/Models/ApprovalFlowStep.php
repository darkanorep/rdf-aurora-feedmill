<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalFlowStep extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    public function type(): BelongsTo
    {
        return $this->belongsTo(ApprovalStepType::class, 'step_type_id');
    }
    public function assignees(): HasMany
    {
        return $this->hasMany(ApprovalFlowStepAssignee::class);
    }
}
