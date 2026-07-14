<?php

namespace App\Models;

use App\Filters\ResponseFilter;
use Carbon\Carbon;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Response extends Model
{
    use SoftDeletes, Filterable;
    protected $guarded = [];
    protected $default_filters = ResponseFilter::class;

    protected $casts = [
        'response' => 'json',
        'evaluate' => 'json',
        'approve' => 'json',
        'assess' => 'json',
    ];

    public function checklist() {
        return $this->belongsTo(Checklist::class);
    }

    public function unit() {
        return $this->belongsTo(Unit::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function evaluator() {
        return $this->belongsTo(User::class);
    }

    public function approver() {
        return $this->belongsTo(User::class);
    }

    public function assessor() {
        return $this->belongsTo(User::class);
    }

    public function images() {
        return $this->hasMany(Image::class);
    }

    public function scopeCobs($query)
    {
        return $query->whereHas('checklist.section', fn ($q) => $q->where('name', 'COBS'));
    }

    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    /**
     * "Third week" = calendar days 15–21 of the given month/year.
     */
    public function scopeInThirdWeekOf($query, int $month, int $year)
    {
        return $query->whereBetween('start_at', [
            Carbon::create($year, $month, 15)->startOfDay(),
            Carbon::create($year, $month, 21)->endOfDay(),
        ]);
    }

    public function section() {
        return $this->hasOneThrough(
            Section::class,
            Checklist::class,
            'id',
            'id',
            'checklist_id',
            'section_id'
        );
    }
}
