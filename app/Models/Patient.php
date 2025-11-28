<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'ohip',
        'hospital_id',
        'retirement_home_id',
        'date_of_birth',
        'status',
        'gender',
        'triage_summary',
        'maple_score',
        'rai_cha_score',
        'interrai_status',
        'interrai_status_updated_at',
        'risk_flags',
        'primary_coordinator_id',
        'is_in_queue',
        'activated_at',
        'activated_by',
    ];

    protected $casts = [
        'status' => 'string',
        'triage_summary' => 'array',
        'risk_flags' => 'array',
        'is_in_queue' => 'boolean',
        'activated_at' => 'datetime',
        'interrai_status_updated_at' => 'datetime',
    ];

    // InterRAI status values
    public const INTERRAI_STATUS_CURRENT = 'current';
    public const INTERRAI_STATUS_STALE = 'stale';
    public const INTERRAI_STATUS_MISSING = 'missing';
    public const INTERRAI_STATUS_PENDING_UPLOAD = 'pending_upload';
    public const INTERRAI_STATUS_UPLOAD_FAILED = 'upload_failed';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }

    public function retirementHome()
    {
        return $this->belongsTo(RetirementHome::class);
    }

    public function triageResult()
    {
        return $this->hasOne(TriageResult::class);
    }

    public function interraiAssessments()
    {
        return $this->hasMany(InterraiAssessment::class);
    }

    /**
     * Get the most recent InterRAI assessment.
     */
    public function latestInterraiAssessment()
    {
        return $this->hasOne(InterraiAssessment::class)->latestOfMany('assessment_date');
    }

    /**
     * Check if patient has a current (non-stale) InterRAI assessment.
     */
    public function hasCurrentInterraiAssessment(): bool
    {
        return $this->interraiAssessments()
            ->where('assessment_date', '>=', now()->subMonths(InterraiAssessment::STALENESS_MONTHS))
            ->exists();
    }

    /**
     * Check if patient needs a new InterRAI assessment.
     */
    public function needsInterraiAssessment(): bool
    {
        return !$this->hasCurrentInterraiAssessment();
    }

    /**
     * Get all RUG classifications for this patient.
     */
    public function rugClassifications()
    {
        return $this->hasMany(RUGClassification::class);
    }

    /**
     * Get the latest/current RUG classification.
     */
    public function latestRugClassification()
    {
        return $this->hasOne(RUGClassification::class)
            ->where('is_current', true)
            ->latestOfMany();
    }

    /**
     * Check if patient has a current RUG classification.
     */
    public function hasCurrentRugClassification(): bool
    {
        return $this->rugClassifications()->where('is_current', true)->exists();
    }

    /**
     * Get reassessment triggers for this patient.
     */
    public function reassessmentTriggers()
    {
        return $this->hasMany(ReassessmentTrigger::class);
    }

    /**
     * Get pending reassessment triggers.
     */
    public function pendingReassessmentTriggers()
    {
        return $this->reassessmentTriggers()->pending();
    }

    /**
     * Check if patient has pending reassessment requests.
     */
    public function hasPendingReassessment(): bool
    {
        return $this->reassessmentTriggers()->pending()->exists();
    }

    /**
     * Get the computed InterRAI status based on latest assessment.
     */
    public function computeInterraiStatus(): string
    {
        $latest = $this->latestInterraiAssessment;

        if (!$latest) {
            return self::INTERRAI_STATUS_MISSING;
        }

        if ($latest->iar_upload_status === InterraiAssessment::IAR_FAILED) {
            return self::INTERRAI_STATUS_UPLOAD_FAILED;
        }

        if ($latest->iar_upload_status === InterraiAssessment::IAR_PENDING) {
            return self::INTERRAI_STATUS_PENDING_UPLOAD;
        }

        if ($latest->isStale()) {
            return self::INTERRAI_STATUS_STALE;
        }

        return self::INTERRAI_STATUS_CURRENT;
    }

    /**
     * Update the cached interrai_status field.
     */
    public function syncInterraiStatus(): self
    {
        $this->update([
            'interrai_status' => $this->computeInterraiStatus(),
            'interrai_status_updated_at' => now(),
        ]);

        return $this;
    }

    public function carePlans()
    {
        return $this->hasMany(CarePlan::class);
    }

    public function serviceAssignments()
    {
        return $this->hasMany(ServiceAssignment::class);
    }

    /**
     * Get upcoming service assignments (scheduled in the future).
     */
    public function upcomingServiceAssignments()
    {
        return $this->serviceAssignments()->upcoming();
    }

    /**
     * Get past/completed service assignments.
     */
    public function pastServiceAssignments()
    {
        return $this->serviceAssignments()->past();
    }

    /**
     * Get service assignments for the current week.
     */
    public function thisWeekServiceAssignments()
    {
        return $this->serviceAssignments()->thisWeek();
    }

    /**
     * Get today's service assignments.
     */
    public function todayServiceAssignments()
    {
        return $this->serviceAssignments()->today();
    }

    /**
     * Get the active care plan with bundle and service types.
     */
    public function activeCarePlan()
    {
        return $this->hasOne(CarePlan::class)
            ->where('status', 'active')
            ->with('careBundle.serviceTypes');
    }

    /**
     * Get care schedule summary for this patient.
     * Returns service types with their weekly frequency from the active bundle.
     */
    public function getCareScheduleSummaryAttribute(): array
    {
        $carePlan = $this->activeCarePlan;
        if (!$carePlan || !$carePlan->careBundle) {
            return [];
        }

        return $carePlan->careBundle->serviceTypes->map(function ($serviceType) {
            return [
                'service_type' => $serviceType->name,
                'code' => $serviceType->code,
                'frequency_per_week' => $serviceType->pivot->default_frequency_per_week ?? 0,
                'duration_minutes' => $serviceType->default_duration_minutes ?? 60,
                'assignment_type' => $serviceType->pivot->assignment_type ?? 'Either',
                'weekly_hours' => round(
                    (($serviceType->pivot->default_frequency_per_week ?? 0) *
                    ($serviceType->default_duration_minutes ?? 60)) / 60,
                    2
                ),
            ];
        })->toArray();
    }

    public function interdisciplinaryNotes()
    {
        return $this->hasMany(InterdisciplinaryNote::class);
    }

    public function rpmDevices()
    {
        return $this->hasMany(RpmDevice::class);
    }

    public function rpmAlerts()
    {
        return $this->hasMany(RpmAlert::class);
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class);
    }

    public function primaryCoordinator()
    {
        return $this->belongsTo(User::class, 'primary_coordinator_id');
    }

    public function transitionNeedsProfile()
    {
        return $this->hasOne(TransitionNeedsProfile::class);
    }

    public function careAssignments()
    {
        return $this->hasMany(CareAssignment::class);
    }

    public function visits()
    {
        return $this->hasMany(Visit::class);
    }

    /**
     * Get the patient's queue entry.
     */
    public function queueEntry()
    {
        return $this->hasOne(PatientQueue::class);
    }

    /**
     * Check if patient is currently in the queue.
     */
    public function isInQueue(): bool
    {
        return $this->is_in_queue ||
               $this->queueEntry()->whereNotIn('queue_status', ['transitioned'])->exists();
    }

    /**
     * Get the patient's current queue status.
     */
    public function getQueueStatusAttribute(): ?string
    {
        return $this->queueEntry?->queue_status;
    }

    /**
     * Scope to get patients currently in queue.
     */
    public function scopeInQueue($query)
    {
        return $query->where('is_in_queue', true);
    }

    /**
     * Scope to get active patients (not in queue).
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'Active')->where('is_in_queue', false);
    }
}
