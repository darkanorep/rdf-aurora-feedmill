<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Submission extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected $casts = ['start_at' => 'datetime', 'submitted_at' => 'datetime'];

    public function steps(): HasMany      {
        return $this->hasMany(SubmissionStep::class)->orderBy('step_order');
    }

    public function checklist(): BelongsTo {
        return $this->belongsTo(Checklist::class);
    }
    public function isDraft(): bool {
        return $this->submitted_at === null;
    }

    /** Derived from the steps. The earliest unfinished step names the status. */
    public function status(): string
    {
        if ($this->isDraft()) {
            return 'Draft';
        }

        return $this->steps->where('status', 'pending')->sortBy('step_order')->first()?->status_label ?? 'Done';
    }
}
