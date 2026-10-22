<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class SubmissionStepAssignee extends Model
{
    protected $guarded = [];
    protected $casts = ['acted_at' => 'datetime'];

    public function step(): BelongsTo {
        return $this->belongsTo(SubmissionStep::class, 'submission_step_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Not acted yet, the step is open, and every earlier step is done. */
    public function scopeActionable(Builder $q): Builder
    {
        return $q->whereNull('submission_step_assignees.decision')->whereHas('step', function ($s) {
            $s->where('status', 'pending')->whereNotExists(function ($e) {
                $e->selectRaw(1)->from('submission_steps as earlier')
                    ->whereColumn('earlier.submission_id', 'submission_steps.submission_id')
                    ->whereColumn('earlier.step_order', '<', 'submission_steps.step_order')
                    ->where('earlier.status', '!=', 'done');
            });
        });
    }
}
