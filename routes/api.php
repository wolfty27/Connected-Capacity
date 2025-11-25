<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Define API Rate Limiter
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/v2/dashboard', [\App\Http\Controllers\Api\V2\DashboardController::class, 'index']);
    Route::get('/v2/dashboards/spo', [\App\Http\Controllers\Api\V2\SpoDashboardController::class, 'index']);
    Route::get('/v2/staffing/fte', [\App\Http\Controllers\Api\V2\CareOps\FteComplianceController::class, 'current']);
    Route::post('/v2/staffing/fte-project', [\App\Http\Controllers\Api\V2\CareOps\FteComplianceController::class, 'project']);
    Route::post('/v2/assignments/sspo-estimate', [\App\Http\Controllers\Api\V2\CareOps\AssignmentEstimationController::class, 'estimate']);
    Route::post('/v2/finance/shadow-billing', [\App\Http\Controllers\Api\V2\Finance\ShadowBillingController::class, 'generate']);
    Route::post('/v2/ai/forecast', [\App\Http\Controllers\Api\V2\AiForecastController::class, 'forecast']);

    Route::get('/patients/{patient}/tnp', [\App\Http\Controllers\Api\V2\TnpController::class, 'show']);
    Route::post('/patients/{patient}/tnp', [\App\Http\Controllers\Api\V2\TnpController::class, 'store']);
    Route::put('/tnp/{tnp}', [\App\Http\Controllers\Api\V2\TnpController::class, 'update']);
    Route::post('/tnp/{tnp}/analyze', [\App\Http\Controllers\Api\V2\TnpController::class, 'analyze']);

    Route::get('/care-assignments', [\App\Http\Controllers\Api\V2\CareOpsController::class, 'index']);
    Route::get('/care-assignments/{assignment}', [\App\Http\Controllers\Api\V2\CareOpsController::class, 'show']);
    Route::post('/care-assignments', [\App\Http\Controllers\Api\V2\CareOpsController::class, 'store']);
    Route::put('/care-assignments/{assignment}', [\App\Http\Controllers\Api\V2\CareOpsController::class, 'update']);

    Route::apiResource('v2/care-plans', \App\Http\Controllers\Api\V2\CarePlanController::class);
    Route::get('v2/bundle-templates', [\App\Http\Controllers\Api\V2\BundleTemplateController::class, 'index']);
    Route::get('v2/bundle-templates/{id}', [\App\Http\Controllers\Api\V2\BundleTemplateController::class, 'show']);

    Route::apiResource('patients', \App\Http\Controllers\Api\PatientController::class);

    Route::get('/organization', [\App\Http\Controllers\Api\OrganizationController::class, 'show']);
    Route::put('/organization', [\App\Http\Controllers\Api\OrganizationController::class, 'update']);

    // Patient Queue Management (Workday-style workflow)
    Route::prefix('v2/patient-queue')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V2\PatientQueueController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\V2\PatientQueueController::class, 'store']);
        Route::get('/ready-for-bundle', [\App\Http\Controllers\Api\V2\PatientQueueController::class, 'readyForBundle']);
        Route::get('/{id}', [\App\Http\Controllers\Api\V2\PatientQueueController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Api\V2\PatientQueueController::class, 'update']);
        Route::post('/{id}/transition', [\App\Http\Controllers\Api\V2\PatientQueueController::class, 'transition']);
        Route::get('/{id}/transitions', [\App\Http\Controllers\Api\V2\PatientQueueController::class, 'transitions']);
        Route::post('/{id}/start-bundle', [\App\Http\Controllers\Api\V2\PatientQueueController::class, 'startBundleBuilding']);
    });

    // Care Bundle Builder (Metadata-driven)
    Route::prefix('v2/care-builder')->group(function () {
        Route::get('/{patientId}/bundles', [\App\Http\Controllers\Api\V2\CareBundleBuilderController::class, 'getBundles']);
        Route::get('/{patientId}/bundles/{bundleId}', [\App\Http\Controllers\Api\V2\CareBundleBuilderController::class, 'getBundle']);
        Route::post('/{patientId}/bundles/preview', [\App\Http\Controllers\Api\V2\CareBundleBuilderController::class, 'previewBundle']);
        Route::post('/{patientId}/plans', [\App\Http\Controllers\Api\V2\CareBundleBuilderController::class, 'buildPlan']);
        Route::get('/{patientId}/plans', [\App\Http\Controllers\Api\V2\CareBundleBuilderController::class, 'getPlanHistory']);
        Route::post('/{patientId}/plans/{carePlanId}/publish', [\App\Http\Controllers\Api\V2\CareBundleBuilderController::class, 'publishPlan']);
    });

    // Service Types API (SC-002)
    Route::prefix('v2/service-types')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V2\ServiceTypeController::class, 'index']);
        Route::get('/by-category', [\App\Http\Controllers\Api\V2\ServiceTypeController::class, 'byCategory']);
        Route::get('/categories', [\App\Http\Controllers\Api\V2\ServiceTypeController::class, 'categories']);
        Route::get('/{id}', [\App\Http\Controllers\Api\V2\ServiceTypeController::class, 'show']);
        Route::post('/', [\App\Http\Controllers\Api\V2\ServiceTypeController::class, 'store']);
        Route::put('/{id}', [\App\Http\Controllers\Api\V2\ServiceTypeController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\V2\ServiceTypeController::class, 'destroy']);
        Route::post('/{id}/toggle-active', [\App\Http\Controllers\Api\V2\ServiceTypeController::class, 'toggleActive']);
    });

    // SSPO Assignment Acceptance API (SSPO-002)
    Route::prefix('v2/assignments')->group(function () {
        Route::get('/pending-sspo', [\App\Http\Controllers\Api\V2\SspoAssignmentController::class, 'pendingAcceptance']);
        Route::post('/{id}/accept', [\App\Http\Controllers\Api\V2\SspoAssignmentController::class, 'accept']);
        Route::post('/{id}/decline', [\App\Http\Controllers\Api\V2\SspoAssignmentController::class, 'decline']);
        Route::get('/{id}/sspo-status', [\App\Http\Controllers\Api\V2\SspoAssignmentController::class, 'sspoStatus']);
        Route::get('/sspo-metrics', [\App\Http\Controllers\Api\V2\SspoAssignmentController::class, 'metrics']);
    });

    // SLA Compliance Dashboard API (SLA-005)
    Route::prefix('v2/sla')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Api\V2\SlaComplianceController::class, 'dashboard']);
        Route::get('/status', [\App\Http\Controllers\Api\V2\SlaComplianceController::class, 'status']);
        Route::get('/hpg-response', [\App\Http\Controllers\Api\V2\SlaComplianceController::class, 'hpgResponse']);
        Route::get('/missed-care', [\App\Http\Controllers\Api\V2\SlaComplianceController::class, 'missedCare']);
        Route::get('/missed-assignments', [\App\Http\Controllers\Api\V2\SlaComplianceController::class, 'missedAssignments']);
        Route::get('/sspo-performance', [\App\Http\Controllers\Api\V2\SlaComplianceController::class, 'sspoPerformance']);
        Route::get('/intake-metrics', [\App\Http\Controllers\Api\V2\SlaComplianceController::class, 'intakeMetrics']);
        Route::get('/pending-interrai', [\App\Http\Controllers\Api\V2\SlaComplianceController::class, 'pendingInterrai']);
        Route::post('/check', [\App\Http\Controllers\Api\V2\SlaComplianceController::class, 'runCheck']);
        Route::post('/huddle-report', [\App\Http\Controllers\Api\V2\SlaComplianceController::class, 'generateHuddleReport']);
    });

    // SSPO Performance Metrics API (SSPO-004)
    Route::prefix('v2/sspo')->group(function () {
        Route::get('/rankings', [\App\Http\Controllers\Api\V2\SspoPerformanceController::class, 'rankings']);
        Route::get('/{id}/performance', [\App\Http\Controllers\Api\V2\SspoPerformanceController::class, 'show']);
        Route::get('/{id}/dashboard', [\App\Http\Controllers\Api\V2\SspoPerformanceController::class, 'dashboard']);
        Route::get('/{id}/acceptance', [\App\Http\Controllers\Api\V2\SspoPerformanceController::class, 'acceptance']);
        Route::get('/{id}/response-time', [\App\Http\Controllers\Api\V2\SspoPerformanceController::class, 'responseTime']);
        Route::get('/{id}/trend', [\App\Http\Controllers\Api\V2\SspoPerformanceController::class, 'trend']);
        Route::get('/{id}/service-types', [\App\Http\Controllers\Api\V2\SspoPerformanceController::class, 'serviceTypes']);
        Route::get('/{id}/decline-reasons', [\App\Http\Controllers\Api\V2\SspoPerformanceController::class, 'declineReasons']);
    });

    // InterRAI Assessment API (IR-006)
    Route::prefix('v2/interrai')->group(function () {
        // Patient assessment needs
        Route::get('/patients-needing-assessment', [\App\Http\Controllers\Api\V2\InterraiController::class, 'patientsNeedingAssessment']);
        Route::get('/patients/{patient}/status', [\App\Http\Controllers\Api\V2\InterraiController::class, 'patientStatus']);
        Route::get('/patients/{patient}/assessments', [\App\Http\Controllers\Api\V2\InterraiController::class, 'patientAssessments']);
        Route::post('/patients/{patient}/assessments', [\App\Http\Controllers\Api\V2\InterraiController::class, 'store']);

        // Assessment details and management
        Route::get('/assessments/{assessment}', [\App\Http\Controllers\Api\V2\InterraiController::class, 'show']);
        Route::post('/assessments/{assessment}/retry-iar', [\App\Http\Controllers\Api\V2\InterraiController::class, 'retryIarUpload']);

        // Form schema and utilities
        Route::get('/form-schema', [\App\Http\Controllers\Api\V2\InterraiController::class, 'formSchema']);

        // IAR upload monitoring
        Route::get('/pending-iar-uploads', [\App\Http\Controllers\Api\V2\InterraiController::class, 'pendingIarUploads']);
        Route::get('/failed-iar-uploads', [\App\Http\Controllers\Api\V2\InterraiController::class, 'failedIarUploads']);
    });
});