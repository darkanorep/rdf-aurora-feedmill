<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubmissionStep extends Model
{
    protected $guarded = [];
    protected $casts = ['completed_at' => 'datetime'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(ApprovalStepType::class, 'step_type_id');
    }
    public function assignees(): HasMany
    {
        return $this->hasMany(SubmissionStepAssignee::class);
    }
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function refreshStatus(): void
    {
        $this->load('assignees');
        $a = $this->assignees;

        $status = match (true) {
            $a->contains('decision', 'rejected') => 'rejected',
            $a->isEmpty()                        => 'pending',
            $this->mode === 'any'                => $a->contains('decision', 'approved') ? 'done' : 'pending',
            default                              => $a->every(fn ($x) => $x->decision === 'approved') ? 'done' : 'pending',
        };

        $this->update(['status' => $status, 'completed_at' => $status === 'pending' ? null : now()]);
    }
}
