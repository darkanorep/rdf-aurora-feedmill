<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\InfestationLevelController;
use App\Http\Controllers\InspectionAreaController;
use App\Http\Controllers\MergeFormController;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\WastageController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PestController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\InspectionAreaPestController;
use App\Http\Controllers\InspectionAreaInfestationLevelController;
use App\Http\Controllers\ScoreController;
use App\Http\Controllers\ApprovalController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::group(['middleware' => 'can:admin'], function () {
        Route::resource('checklists', ChecklistController::class);
        Route::resource('sections', SectionController::class);
        Route::resource('permissions', PermissionController::class);
        Route::resource('roles', RoleController::class);
        Route::resource('users', UserController::class);
        Route::resource('inspection-areas', InspectionAreaController::class);
        Route::resource('pests', PestController::class);
        Route::resource('infestation-levels', InfestationLevelController::class);
        Route::resource('units', UnitController::class);
        Route::resource('scores', ScoreController::class);
        Route::resource('wastages', WastageController::class);

        // ❌ COBS QUESTIONNAIRES CONSOLIDATOR
        //Route::resource('merge-forms', MergeFormController::class);

        // ❌ COBS
//        Route::prefix('forms')->group(function () {
//            Route::get('by-checklist', [FormController::class, 'getFormByChecklistId']);
//            Route::put('by-checklist', [FormController::class, 'updateByChecklistId']);
//            Route::delete('by-checklist', [FormController::class, 'deleteByChecklistId']);
//            Route::resource('/', FormController::class)->only(['index', 'store']);
//            Route::post('upload', [FormController::class, 'upload']);
//        });

        //PESTS
        Route::resource('sheets', InspectionAreaPestController::class);
        //BIRDS
        Route::resource('surveys', InspectionAreaInfestationLevelController::class);
    });

    Route::resource('questionnaires', ChecklistController::class)->only(['show']);
    Route::get('responses/summary', [ResponseController::class, 'summaryReportByBatchNo']);
    Route::resource('responses', ResponseController::class);

    Route::put('approvals/approve', [ApprovalController::class, 'approve']);
    Route::resource('approvals', ApprovalController::class);

    Route::post('logout', [AuthController::class, 'logout']);
});
