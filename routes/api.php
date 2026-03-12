<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\InspectionAreaController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\UserController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::resource('checklists', ChecklistController::class);
    Route::resource('sections', SectionController::class);
    Route::resource('permissions', PermissionController::class);
    Route::resource('roles', RoleController::class);
    Route::resource('users', UserController::class);
    Route::resource('inspection-areas', InspectionAreaController::class);

    Route::prefix('forms')->group(function () {
        Route::get('by-checklist', [FormController::class, 'getFormByChecklistId']);
        Route::put('by-checklist', [FormController::class, 'updateByChecklistId']);
        Route::delete('by-checklist', [FormController::class, 'deleteByChecklistId']);
        Route::resource('/', FormController::class)->only(['index', 'store']);
    });

    Route::post('logout', [AuthController::class, 'logout']);
});