<?php

namespace App\Models;

use App\Filters\ResponseFilter;
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

    public function approver() {
        return $this->belongsTo(User::class);
    }

    public function images() {
        return $this->hasMany(Image::class);
    }
}
