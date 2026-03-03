<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    use SoftDeletes;

    protected $guarded = [];
}
