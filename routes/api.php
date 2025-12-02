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

// Public routes (no auth required)
Route::post('/client-errors', [\App\Http\Controllers\Api\ClientErrorController::class, 'store'])
    ->middleware('throttle:10,1'); // Rate limit: 10 per minute

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

    // Patient overview with InterRAI-driven summary (replaces TNP)
    Route::get('/v2/patients/{patient}/overview', [\App\Http\Controllers\Api\PatientController::class, 'overview']);

    // Patient assessment history with RUG classifications
    Route::get('/v2/patients/{patient}/assessments', [\App\Http\Controllers\Api\PatientController::class, 'assessments']);

    // Patient Notes API (replaces TNP narrative)
    Route::get('/v2/patients/{patient}/notes', [\App\Http\Controllers\Api\V2\PatientNotesController::class, 'index']);
    Route::post('/v2/patients/{patient}/notes', [\App\Http\Controllers\Api\V2\PatientNotesController::class, 'store']);
    Route::put('/v2/patient-notes/{note}', [\App\Http\Controllers\Api\V2\PatientNotesController::class, 'update']);
    Route::delete('/v2/patient-notes/{note}', [\App\Http\Controllers\Api\V2\PatientNotesController::class, 'destroy']);

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

    // Care Bundle Builder (Metadata-driven) - Legacy endpoints
    Route::prefix('v2/care-builder')->group(function () {
        Route::get('/{patientId}/bundles', [\App\Http\Controllers\Api\V2\CareBundleBuilderController::class, 'getBundles']);
        Route::get('/{patientId}/bundles/{bundleId}', [\App\Http\Controllers\Api\V2\CareBundleBuilderController::class, 'getBundle']);
        Route::post('/{patientId}/bundles/preview', [\App\Http\Controllers\Api\V2\CareBundleBuilderController::class, 'previewBundle']);
        Route::post('/{patientId}/plans', [\App\Http\Controllers\Api\V2\CareBundleBuilderController::class, 'buildPlan']);
        Route::get('/{patientId}/plans', [\App\Http\Controllers\Api\V2\CareBundleBuilderController::class, 'getPlanHistory']);
        Route::post('/{patientId}/plans/{carePlanId}/publish', [\App\Http\Controllers\Api\V2\CareBundleBuilderController::class, 'publishPlan']);

        // RUG-based Bundle Builder (CC2.1 Architecture)
        Route::get('/templates', [\App\Http\Controllers\Api\V2\CareBundleBuilderController::class, 'getAllTemplates']);
        Route::get('/{patientId}/rug-bundles', [\App\Http\Controllers\Api\V2\CareBundleBuilderController::class, 'getRugBundles']);
        Route::get('/{patientId}/rug-bundles/{templateId}', [\App\Http\Controllers\Api\V2\CareBundleBuilderController::class, 'getRugBundle']);
        Route::get('/{patientId}/rug-recommendation', [\App\Http\Controllers\Api\V2\CareBundleBuilderController::class, 'getTemplateRecommendation']);
        Route::post('/{patientId}/rug-plans', [\App\Http\Controllers\Api\V2\CareBundleBuilderController::class, 'buildPlanFromTemplate']);
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

    // Staff Scheduling Dashboard API (SCHED-001)
    Route::prefix('v2/scheduling')->group(function () {
        // Unscheduled care requirements (from CareBundleAssignmentPlanner)
        Route::get('/requirements', [\App\Http\Controllers\Api\V2\SchedulingController::class, 'requirements']);
        // Scheduling grid data (staff + assignments for week)
        Route::get('/grid', [\App\Http\Controllers\Api\V2\SchedulingController::class, 'grid']);
        // Staff eligible for a service at a specific time
        Route::get('/eligible-staff', [\App\Http\Controllers\Api\V2\SchedulingController::class, 'eligibleStaff']);
        // Navigation examples for deep links
        Route::get('/navigation-examples', [\App\Http\Controllers\Api\V2\SchedulingController::class, 'navigationExamples']);
        // Assignment CRUD
        Route::post('/assignments', [\App\Http\Controllers\Api\V2\SchedulingController::class, 'createAssignment']);
        Route::patch('/assignments/{id}', [\App\Http\Controllers\Api\V2\SchedulingController::class, 'updateAssignment']);
        Route::delete('/assignments/{id}', [\App\Http\Controllers\Api\V2\SchedulingController::class, 'deleteAssignment']);
    });

    // Jeopardy Board API (Visit Verification & Missed Care Risk)
    Route::prefix('v2/jeopardy')->group(function () {
        Route::get('/alerts', [\App\Http\Controllers\Api\V2\JeopardyBoardController::class, 'index']);
        Route::get('/summary', [\App\Http\Controllers\Api\V2\JeopardyBoardController::class, 'summary']);
        Route::post('/alerts/{id}/resolve', [\App\Http\Controllers\Api\V2\JeopardyBoardController::class, 'resolve']);
        Route::post('/alerts/{id}/mark-missed', [\App\Http\Controllers\Api\V2\JeopardyBoardController::class, 'markMissed']);
        Route::post('/alerts/bulk-resolve', [\App\Http\Controllers\Api\V2\JeopardyBoardController::class, 'bulkResolve']);
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

        // IR-003: External assessment and IAR linking
        Route::post('/patients/{patient}/assessments/external', [\App\Http\Controllers\Api\V2\InterraiController::class, 'storeExternal']);
        Route::post('/patients/{patient}/link-external', [\App\Http\Controllers\Api\V2\InterraiController::class, 'linkExternal']);

        // IR-005: Reassessment triggers
        Route::post('/patients/{patient}/request-reassessment', [\App\Http\Controllers\Api\V2\InterraiController::class, 'requestReassessment']);
        Route::get('/reassessment-triggers', [\App\Http\Controllers\Api\V2\InterraiController::class, 'reassessmentTriggers']);
        Route::post('/reassessment-triggers/{trigger}/resolve', [\App\Http\Controllers\Api\V2\InterraiController::class, 'resolveReassessmentTrigger']);
        Route::get('/reassessment-trigger-options', [\App\Http\Controllers\Api\V2\InterraiController::class, 'reassessmentTriggerOptions']);

        // IR-006: Full assessment workflow
        Route::post('/patients/{patient}/assessments/start', [\App\Http\Controllers\Api\V2\InterraiController::class, 'startAssessment']);

        // Assessment details and management
        Route::get('/assessments/{assessment}', [\App\Http\Controllers\Api\V2\InterraiController::class, 'show']);
        Route::patch('/assessments/{assessment}/progress', [\App\Http\Controllers\Api\V2\InterraiController::class, 'saveProgress']);
        Route::post('/assessments/{assessment}/calculate-scores', [\App\Http\Controllers\Api\V2\InterraiController::class, 'calculateScores']);
        Route::post('/assessments/{assessment}/complete', [\App\Http\Controllers\Api\V2\InterraiController::class, 'completeAssessment']);
        Route::post('/assessments/{assessment}/retry-iar', [\App\Http\Controllers\Api\V2\InterraiController::class, 'retryIarUpload']);

        // IR-004: Document endpoints
        Route::post('/assessments/{assessment}/documents', [\App\Http\Controllers\Api\V2\InterraiController::class, 'uploadDocument']);
        Route::get('/assessments/{assessment}/documents', [\App\Http\Controllers\Api\V2\InterraiController::class, 'listDocuments']);
        Route::delete('/assessments/{assessment}/documents/{document}', [\App\Http\Controllers\Api\V2\InterraiController::class, 'deleteDocument']);

        // Form schema and utilities
        Route::get('/form-schema', [\App\Http\Controllers\Api\V2\InterraiController::class, 'formSchema']);
        Route::get('/full-form-schema', [\App\Http\Controllers\Api\V2\InterraiController::class, 'fullFormSchema']);

        // IAR upload monitoring
        Route::get('/pending-iar-uploads', [\App\Http\Controllers\Api\V2\InterraiController::class, 'pendingIarUploads']);
        Route::get('/failed-iar-uploads', [\App\Http\Controllers\Api\V2\InterraiController::class, 'failedIarUploads']);
    });

    // IR-006: Admin InterRAI Dashboard API
    Route::prefix('v2/admin/interrai')->group(function () {
        Route::get('/dashboard-stats', [\App\Http\Controllers\Api\V2\Admin\InterraiDashboardController::class, 'stats']);
        Route::get('/stale-assessments', [\App\Http\Controllers\Api\V2\Admin\InterraiDashboardController::class, 'staleAssessments']);
        Route::get('/missing-assessments', [\App\Http\Controllers\Api\V2\Admin\InterraiDashboardController::class, 'missingAssessments']);
        Route::get('/failed-uploads', [\App\Http\Controllers\Api\V2\Admin\InterraiDashboardController::class, 'failedUploads']);
        Route::post('/bulk-retry-iar', [\App\Http\Controllers\Api\V2\Admin\InterraiDashboardController::class, 'bulkRetryIar']);
        Route::post('/sync-statuses', [\App\Http\Controllers\Api\V2\Admin\InterraiDashboardController::class, 'syncStatuses']);
        Route::get('/pending-triggers', [\App\Http\Controllers\Api\V2\Admin\InterraiDashboardController::class, 'pendingTriggers']);
        Route::get('/compliance-report', [\App\Http\Controllers\Api\V2\Admin\InterraiDashboardController::class, 'complianceReport']);
    });

    // SSPO Capability Management API (STAFF-019, STAFF-020, STAFF-021)
    Route::prefix('v2/sspo-capabilities')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V2\SspoCapabilityController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\V2\SspoCapabilityController::class, 'store']);
        Route::get('/coverage', [\App\Http\Controllers\Api\V2\SspoCapabilityController::class, 'serviceTypeCoverage']);
        Route::get('/rankings/{serviceTypeId}', [\App\Http\Controllers\Api\V2\SspoCapabilityController::class, 'rankings']);
        Route::post('/find-matches', [\App\Http\Controllers\Api\V2\SspoCapabilityController::class, 'findMatches']);
        Route::get('/{id}', [\App\Http\Controllers\Api\V2\SspoCapabilityController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Api\V2\SspoCapabilityController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\V2\SspoCapabilityController::class, 'destroy']);
    });

    // Service Rate Card Admin API (Rate Card Management)
    Route::prefix('v2/admin/service-rates')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V2\Admin\ServiceRateController::class, 'index']);
        Route::get('/system-defaults', [\App\Http\Controllers\Api\V2\Admin\ServiceRateController::class, 'systemDefaults']);
        Route::get('/organization/{organizationId?}', [\App\Http\Controllers\Api\V2\Admin\ServiceRateController::class, 'organizationRates']);
        Route::get('/history/{serviceTypeId}', [\App\Http\Controllers\Api\V2\Admin\ServiceRateController::class, 'history']);
        Route::post('/', [\App\Http\Controllers\Api\V2\Admin\ServiceRateController::class, 'store']);
        Route::post('/bulk', [\App\Http\Controllers\Api\V2\Admin\ServiceRateController::class, 'bulkStore']);
        Route::get('/{id}', [\App\Http\Controllers\Api\V2\Admin\ServiceRateController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Api\V2\Admin\ServiceRateController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\V2\Admin\ServiceRateController::class, 'destroy']);
    });

    // Staff Management API (STAFF-008)
    Route::prefix('v2/staff')->group(function () {
        // Staff CRUD
        Route::get('/', [\App\Http\Controllers\Api\V2\StaffController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\V2\StaffController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\Api\V2\StaffController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Api\V2\StaffController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\V2\StaffController::class, 'destroy']);

        // Skills management
        Route::get('/skills/catalog', [\App\Http\Controllers\Api\V2\StaffController::class, 'listSkills']);
        Route::get('/{staffId}/skills', [\App\Http\Controllers\Api\V2\StaffController::class, 'getStaffSkills']);
        Route::post('/{staffId}/skills', [\App\Http\Controllers\Api\V2\StaffController::class, 'assignSkill']);
        Route::delete('/{staffId}/skills/{skillId}', [\App\Http\Controllers\Api\V2\StaffController::class, 'removeSkill']);

        // Availability management
        Route::get('/{staffId}/availability', [\App\Http\Controllers\Api\V2\StaffController::class, 'getAvailability']);
        Route::put('/{staffId}/availability', [\App\Http\Controllers\Api\V2\StaffController::class, 'setAvailability']);

        // Unavailability (time-off) management
        Route::get('/{staffId}/unavailabilities', [\App\Http\Controllers\Api\V2\StaffController::class, 'getUnavailabilities']);
        Route::post('/{staffId}/time-off', [\App\Http\Controllers\Api\V2\StaffController::class, 'requestTimeOff']);
        Route::post('/time-off/{unavailabilityId}/process', [\App\Http\Controllers\Api\V2\StaffController::class, 'processTimeOffRequest']);

        // FTE Compliance & Analytics
        Route::get('/analytics/fte-compliance', [\App\Http\Controllers\Api\V2\StaffController::class, 'getFteCompliance']);
        Route::get('/analytics/fte-trend', [\App\Http\Controllers\Api\V2\StaffController::class, 'getFteComplianceTrend']);
        Route::get('/analytics/utilization', [\App\Http\Controllers\Api\V2\StaffController::class, 'getStaffUtilization']);
        Route::post('/analytics/hire-projection', [\App\Http\Controllers\Api\V2\StaffController::class, 'getHireProjection']);
    });

    // Workforce Management API (FTE Compliance, HHR Complement, Satisfaction)
    Route::prefix('v2/workforce')->group(function () {
        // Main workforce summary (combines FTE, HHR, satisfaction)
        Route::get('/summary', [\App\Http\Controllers\Api\V2\WorkforceController::class, 'summary']);

        // Capacity vs Required Care Hours
        Route::get('/capacity', [\App\Http\Controllers\Api\V2\WorkforceController::class, 'capacity']);

        // FTE Compliance
        Route::get('/fte-trend', [\App\Http\Controllers\Api\V2\WorkforceController::class, 'fteTrend']);
        Route::get('/compliance-gap', [\App\Http\Controllers\Api\V2\WorkforceController::class, 'complianceGap']);
        Route::get('/hire-projection', [\App\Http\Controllers\Api\V2\WorkforceController::class, 'hireProjection']);

        // HHR Complement (by role and employment type)
        Route::get('/hhr-complement', [\App\Http\Controllers\Api\V2\WorkforceController::class, 'hhrComplement']);

        // Staff Satisfaction
        Route::get('/satisfaction', [\App\Http\Controllers\Api\V2\WorkforceController::class, 'satisfaction']);

        // Staff listing with role/employment type
        Route::get('/staff', [\App\Http\Controllers\Api\V2\WorkforceController::class, 'staff']);

        // Utilization
        Route::get('/utilization', [\App\Http\Controllers\Api\V2\WorkforceController::class, 'utilization']);

        // Assignment summary (internal vs SSPO hours)
        Route::get('/assignment-summary', [\App\Http\Controllers\Api\V2\WorkforceController::class, 'assignmentSummary']);

        // Metadata for forms/filters
        Route::get('/metadata/roles', [\App\Http\Controllers\Api\V2\WorkforceController::class, 'metadataRoles']);
        Route::get('/metadata/employment-types', [\App\Http\Controllers\Api\V2\WorkforceController::class, 'metadataEmploymentTypes']);
    });

    // SSPO Marketplace API - Browse and view SSPO organizations
    Route::prefix('v2/sspo-marketplace')->group(function () {
        // List SSPOs with filtering
        Route::get('/', [\App\Http\Controllers\Api\V2\SspoMarketplaceController::class, 'index']);

        // Get filter options (service types, regions, statuses)
        Route::get('/filters', [\App\Http\Controllers\Api\V2\SspoMarketplaceController::class, 'filters']);

        // Get marketplace statistics
        Route::get('/stats', [\App\Http\Controllers\Api\V2\SspoMarketplaceController::class, 'stats']);

        // Get SSPO profile details
        Route::get('/{id}', [\App\Http\Controllers\Api\V2\SspoMarketplaceController::class, 'show'])->where('id', '[0-9]+');
    });

    // QIN (Quality Improvement Notice) API - Compliance management

    // TFS (Time-to-First-Service) API - Compliance metrics
    Route::prefix('v2/metrics/tfs')->group(function () {
        // Get TFS summary metrics
        Route::get('/summary', [\App\Http\Controllers\Api\V2\TfsController::class, 'summary']);
        
        // Get detailed patient data for TFS calculation
        Route::get('/details', [\App\Http\Controllers\Api\V2\TfsController::class, 'details']);
    });

    Route::prefix('v2/qin')->group(function () {
        // Get active (officially issued) QINs
        Route::get('/active', [\App\Http\Controllers\Api\V2\QinController::class, 'active']);
        
        // Get potential QINs based on metric breaches
        Route::get('/potential', [\App\Http\Controllers\Api\V2\QinController::class, 'potential']);
        
        // Get comprehensive QIN metrics for dashboard
        Route::get('/metrics', [\App\Http\Controllers\Api\V2\QinController::class, 'metrics']);
        
        // Get all QIN records for manager page
        Route::get('/all', [\App\Http\Controllers\Api\V2\QinController::class, 'all']);
        
        // Submit QIP for a QIN
        Route::post('/{id}/submit-qip', [\App\Http\Controllers\Api\V2\QinController::class, 'submitQip'])->where('id', '[0-9]+');
    });
    
    // OHaH Webhook endpoints (stubs for future integration)
    Route::prefix('v2/ohah')->group(function () {
        // QIN webhook - receive QINs from Ontario Health
        Route::post('/qin-webhook', [\App\Http\Controllers\Api\V2\QinController::class, 'webhook']);
    });
});
    // ====================================================
    // STAFF PROFILE ROUTES (STAFF-PROFILE)
    // ====================================================
    Route::prefix('v2/staff/{id}')->where(['id' => '[0-9]+'])->group(function () {
        // Profile
        Route::get('/profile', [\App\Http\Controllers\Api\V2\StaffProfileController::class, 'show']);
        
        // Status management
        Route::patch('/status', [\App\Http\Controllers\Api\V2\StaffProfileController::class, 'updateStatus']);
        
        // Scheduling lock
        Route::post('/scheduling-lock', [\App\Http\Controllers\Api\V2\StaffProfileController::class, 'lockScheduling']);
        Route::delete('/scheduling-lock', [\App\Http\Controllers\Api\V2\StaffProfileController::class, 'unlockScheduling']);
        
        // Schedule
        Route::get('/schedule', [\App\Http\Controllers\Api\V2\StaffProfileController::class, 'schedule']);
        
        // Availability
        Route::get('/availability', [\App\Http\Controllers\Api\V2\StaffProfileController::class, 'availability']);
        Route::post('/availability', [\App\Http\Controllers\Api\V2\StaffProfileController::class, 'storeAvailability']);
        Route::delete('/availability/{availabilityId}', [\App\Http\Controllers\Api\V2\StaffProfileController::class, 'destroyAvailability'])->where(['availabilityId' => '[0-9]+']);
        
        // Unavailabilities (Time Off)
        Route::get('/unavailabilities', [\App\Http\Controllers\Api\V2\StaffProfileController::class, 'unavailabilities']);
        Route::post('/unavailabilities', [\App\Http\Controllers\Api\V2\StaffProfileController::class, 'storeUnavailability']);
        Route::patch('/unavailabilities/{unavailabilityId}', [\App\Http\Controllers\Api\V2\StaffProfileController::class, 'updateUnavailability'])->where(['unavailabilityId' => '[0-9]+']);
        Route::delete('/unavailabilities/{unavailabilityId}', [\App\Http\Controllers\Api\V2\StaffProfileController::class, 'destroyUnavailability'])->where(['unavailabilityId' => '[0-9]+']);
        
        // Skills
        Route::get('/skills', [\App\Http\Controllers\Api\V2\StaffProfileController::class, 'skills']);
        Route::post('/skills', [\App\Http\Controllers\Api\V2\StaffProfileController::class, 'storeSkill']);
        Route::delete('/skills/{skillId}', [\App\Http\Controllers\Api\V2\StaffProfileController::class, 'destroySkill'])->where(['skillId' => '[0-9]+']);
        
        // Satisfaction
        Route::get('/satisfaction', [\App\Http\Controllers\Api\V2\StaffProfileController::class, 'satisfaction']);
        
        // Travel
        Route::get('/travel', [\App\Http\Controllers\Api\V2\StaffProfileController::class, 'travel']);
        
        // Account actions
        Route::post('/send-password-reset', [\App\Http\Controllers\Api\V2\StaffProfileController::class, 'sendPasswordReset']);
        Route::delete('/', [\App\Http\Controllers\Api\V2\StaffProfileController::class, 'destroy']);
    });
    
    // Skills metadata (for skill assignment dropdown)
    Route::get('v2/skills', [\App\Http\Controllers\Api\V2\StaffProfileController::class, 'availableSkills']);

    // ====================================================
    // AUTO ASSIGN / AI SCHEDULING ROUTES
    // ====================================================
    Route::prefix('v2/scheduling')->middleware(['auth:sanctum'])->group(function () {
        // Generate suggestions for unscheduled care
        Route::get('/suggestions', [\App\Http\Controllers\Api\V2\AutoAssignController::class, 'suggestions']);
        
        // Get summary statistics
        Route::get('/suggestions/summary', [\App\Http\Controllers\Api\V2\AutoAssignController::class, 'summary']);
        
        // Get explanation for a specific suggestion
        Route::get('/suggestions/{patient_id}/{service_type_id}/explain', [\App\Http\Controllers\Api\V2\AutoAssignController::class, 'explain'])
            ->where(['patient_id' => '[0-9]+', 'service_type_id' => '[0-9]+']);
        
        // Accept a single suggestion
        Route::post('/suggestions/accept', [\App\Http\Controllers\Api\V2\AutoAssignController::class, 'accept']);
        
        // Accept multiple suggestions in batch
        Route::post('/suggestions/accept-batch', [\App\Http\Controllers\Api\V2\AutoAssignController::class, 'acceptBatch']);
    });
