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
    ];

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

    public function carePlans()
    {
        return $this->hasMany(CarePlan::class);
    }

    public function serviceAssignments()
    {
        return $this->hasMany(ServiceAssignment::class);
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
