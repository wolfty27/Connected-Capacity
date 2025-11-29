<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarePlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'care_bundle_template_id',
        'status',
        'version',
        'start_date',
        'end_date',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function careBundleTemplate(): BelongsTo
    {
        return $this->belongsTo(CareBundleTemplate::class);
    }

    public function serviceAssignments(): HasMany
    {
        return $this->hasMany(ServiceAssignment::class);
    }

    /**
     * Check if the care plan is currently active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Get all service assignments for a specific service type
     */
    public function getAssignmentsForServiceType(int $serviceTypeId)
    {
        return $this->serviceAssignments()
            ->where('service_type_id', $serviceTypeId)
            ->whereNotIn('status', [
                ServiceAssignment::STATUS_CANCELLED,
                ServiceAssignment::STATUS_MISSED,
            ])
            ->get();
    }

    /**
     * Count scheduled visits for a specific service type
     */
    public function countScheduledVisitsForServiceType(int $serviceTypeId): int
    {
        return $this->serviceAssignments()
            ->where('service_type_id', $serviceTypeId)
            ->whereNotIn('status', [
                ServiceAssignment::STATUS_CANCELLED,
                ServiceAssignment::STATUS_MISSED,
            ])
            ->count();
    }

    /**
     * Scope: only active care plans
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
