<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * STAFF-019: SSPO Service Capability Model
 *
 * Represents the service types an SSPO can provide along with
 * capacity, pricing, and quality metrics for marketplace matching.
 */
class SspoServiceCapability extends Model
{
    protected $table = 'sspo_service_capabilities';

    protected $fillable = [
        'sspo_id',
        'service_type_id',
        'is_active',
        'max_weekly_hours',
        'current_utilization_hours',
        'min_notice_hours',
        'hourly_rate',
        'visit_rate',
        'rate_modifiers',
        'service_areas',
        'available_days',
        'earliest_start_time',
        'latest_end_time',
        'acceptance_rate',
        'completion_rate',
        'quality_score',
        'staff_qualifications',
        'available_staff_count',
        'can_handle_complex_care',
        'can_handle_dementia',
        'can_handle_palliative',
        'bilingual_french',
        'languages_available',
        'capability_effective_date',
        'capability_expiry_date',
        'insurance_verified',
        'insurance_expiry_date',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'max_weekly_hours' => 'integer',
        'current_utilization_hours' => 'integer',
        'min_notice_hours' => 'integer',
        'hourly_rate' => 'decimal:2',
        'visit_rate' => 'decimal:2',
        'rate_modifiers' => 'array',
        'service_areas' => 'array',
        'available_days' => 'array',
        'earliest_start_time' => 'datetime:H:i',
        'latest_end_time' => 'datetime:H:i',
        'acceptance_rate' => 'decimal:2',
        'completion_rate' => 'decimal:2',
        'quality_score' => 'decimal:2',
        'staff_qualifications' => 'array',
        'available_staff_count' => 'integer',
        'can_handle_complex_care' => 'boolean',
        'can_handle_dementia' => 'boolean',
        'can_handle_palliative' => 'boolean',
        'bilingual_french' => 'boolean',
        'languages_available' => 'array',
        'capability_effective_date' => 'date',
        'capability_expiry_date' => 'date',
        'insurance_verified' => 'boolean',
        'insurance_expiry_date' => 'date',
    ];

    /**
     * SSPO Organization
     */
    public function sspo(): BelongsTo
    {
        return $this->belongsTo(ServiceProviderOrganization::class, 'sspo_id');
    }

    /**
     * Service Type
     */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    /**
     * Required skills for this capability (through service type)
     */
    public function requiredSkills(): BelongsToMany
    {
        return $this->serviceType->skills()->wherePivot('is_required', true);
    }

    // ==========================================
    // Scopes
    // ==========================================

    /**
     * Scope: Active capabilities
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('capability_expiry_date')
                  ->orWhere('capability_expiry_date', '>', Carbon::today());
            });
    }

    /**
     * Scope: Has available capacity
     */
    public function scopeWithAvailableCapacity($query, int $requiredHours = 1)
    {
        return $query->whereRaw('(max_weekly_hours - current_utilization_hours) >= ?', [$requiredHours]);
    }

    /**
     * Scope: For a specific service type
     */
    public function scopeForServiceType($query, int $serviceTypeId)
    {
        return $query->where('service_type_id', $serviceTypeId);
    }

    /**
     * Scope: Covers a specific area (postal prefix)
     */
    public function scopeCoversArea($query, string $postalPrefix)
    {
        return $query->whereJsonContains('service_areas', $postalPrefix);
    }

    /**
     * Scope: Available on a specific day
     */
    public function scopeAvailableOnDay($query, int $dayOfWeek)
    {
        return $query->whereJsonContains('available_days', $dayOfWeek);
    }

    /**
     * Scope: Can handle dementia care
     */
    public function scopeCanHandleDementia($query)
    {
        return $query->where('can_handle_dementia', true);
    }

    /**
     * Scope: Can handle palliative care
     */
    public function scopeCanHandlePalliative($query)
    {
        return $query->where('can_handle_palliative', true);
    }

    /**
     * Scope: Has minimum quality score
     */
    public function scopeMinQuality($query, float $minScore)
    {
        return $query->where('quality_score', '>=', $minScore);
    }

    /**
     * Scope: Order by marketplace ranking
     */
    public function scopeOrderByRanking($query)
    {
        return $query->orderByDesc('quality_score')
                     ->orderByDesc('acceptance_rate')
                     ->orderByDesc('completion_rate');
    }

    // ==========================================
    // Computed Properties
    // ==========================================

    /**
     * Get available hours this week
     */
    public function getAvailableHoursAttribute(): int
    {
        return max(0, ($this->max_weekly_hours ?? 0) - ($this->current_utilization_hours ?? 0));
    }

    /**
     * Get utilization rate
     */
    public function getUtilizationRateAttribute(): float
    {
        if (!$this->max_weekly_hours || $this->max_weekly_hours <= 0) {
            return 0;
        }
        return round(($this->current_utilization_hours / $this->max_weekly_hours) * 100, 1);
    }

    /**
     * Get overall capability score for ranking
     */
    public function getCapabilityScoreAttribute(): float
    {
        // Weighted average of quality metrics
        $qualityWeight = 0.4;
        $acceptanceWeight = 0.3;
        $completionWeight = 0.3;

        $score = (($this->quality_score ?? 0) * $qualityWeight)
               + (($this->acceptance_rate ?? 0) * $acceptanceWeight)
               + (($this->completion_rate ?? 0) * $completionWeight);

        return round($score, 2);
    }

    /**
     * Get effective rate for a visit
     */
    public function getEffectiveRate(?bool $isWeekend = false, ?bool $isHoliday = false): float
    {
        $baseRate = $this->visit_rate ?? ($this->hourly_rate ?? 0);

        if (!$this->rate_modifiers) {
            return $baseRate;
        }

        $modifier = 1.0;
        if ($isWeekend && isset($this->rate_modifiers['weekend'])) {
            $modifier = $this->rate_modifiers['weekend'];
        }
        if ($isHoliday && isset($this->rate_modifiers['holiday'])) {
            $modifier = max($modifier, $this->rate_modifiers['holiday']);
        }

        return $baseRate * $modifier;
    }

    /**
     * Check if capability is currently valid
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->capability_expiry_date && $this->capability_expiry_date->isPast()) {
            return false;
        }

        if (!$this->insurance_verified || ($this->insurance_expiry_date && $this->insurance_expiry_date->isPast())) {
            return false;
        }

        return true;
    }

    /**
     * Check if SSPO can service a specific time
     */
    public function canServiceAt(Carbon $datetime): bool
    {
        // Check day of week
        if ($this->available_days && !in_array($datetime->dayOfWeek, $this->available_days)) {
            return false;
        }

        // Check time range
        if ($this->earliest_start_time) {
            $earliest = Carbon::parse($this->earliest_start_time);
            if ($datetime->format('H:i') < $earliest->format('H:i')) {
                return false;
            }
        }

        if ($this->latest_end_time) {
            $latest = Carbon::parse($this->latest_end_time);
            if ($datetime->format('H:i') > $latest->format('H:i')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if SSPO has required qualifications for a skill
     */
    public function hasQualification(string $skillCode): bool
    {
        return $this->staff_qualifications && in_array($skillCode, $this->staff_qualifications);
    }

    /**
     * Check if meets notice requirement
     */
    public function meetsNoticeRequirement(Carbon $requestedStart): bool
    {
        $hoursUntilStart = Carbon::now()->diffInHours($requestedStart, false);
        return $hoursUntilStart >= ($this->min_notice_hours ?? 0);
    }
}
