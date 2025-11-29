<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'date_of_birth',
        'email',
        'phone',
        'address',
        'postal_code',
        'status',
        'rug_category',
        'risk_flags',
        'organization_id',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'risk_flags' => 'array',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISCHARGED = 'discharged';
    public const STATUS_ON_HOLD = 'on_hold';

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function serviceAssignments(): HasMany
    {
        return $this->hasMany(ServiceAssignment::class);
    }

    public function carePlans(): HasMany
    {
        return $this->hasMany(CarePlan::class);
    }

    public function activeCarePlan(): HasOne
    {
        return $this->hasOne(CarePlan::class)
            ->where('status', CarePlan::STATUS_ACTIVE)
            ->latest();
    }

    /**
     * Check if patient has any high-risk flags
     */
    public function hasHighRiskFlags(): bool
    {
        $highRiskFlags = ['high_fall_risk', 'clinical_instability', 'wandering', 'ED_risk'];
        $patientFlags = $this->risk_flags ?? [];

        return count(array_intersect($highRiskFlags, $patientFlags)) > 0;
    }

    /**
     * Scope: only active patients
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope: patients with active care plans
     */
    public function scopeWithActiveCarePlan($query)
    {
        return $query->whereHas('carePlans', function ($q) {
            $q->where('status', CarePlan::STATUS_ACTIVE);
        });
    }
}
