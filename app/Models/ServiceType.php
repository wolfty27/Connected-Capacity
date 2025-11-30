<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceType extends Model
{
    use HasFactory;

    /**
     * Scheduling mode constants.
     */
    public const SCHEDULING_MODE_WEEKLY = 'weekly';
    public const SCHEDULING_MODE_FIXED_VISITS = 'fixed_visits';

    /**
     * Preferred provider constants - which org type owns this service.
     */
    public const PROVIDER_SSPO = 'sspo';
    public const PROVIDER_SPO = 'spo';
    public const PROVIDER_EITHER = 'either';

    /**
     * Delivery mode constants.
     */
    public const DELIVERY_IN_PERSON = 'in_person';
    public const DELIVERY_REMOTE = 'remote';
    public const DELIVERY_EITHER = 'either';

    protected $fillable = [
        'name',
        'code',
        'category',
        'category_id',
        'default_duration_minutes',
        'min_gap_between_visits_minutes',
        'description',
        'cost_code',
        'cost_driver',
        'cost_per_visit',
        'source',
        'active',
        'scheduling_mode',
        'fixed_visits_per_plan',
        'fixed_visit_labels',
        'preferred_provider',
        'allowed_provider_types',
        'delivery_mode',
    ];

    protected $casts = [
        'default_duration_minutes' => 'integer',
        'min_gap_between_visits_minutes' => 'integer',
        'cost_per_visit' => 'decimal:2',
        'active' => 'boolean',
        'fixed_visits_per_plan' => 'integer',
        'fixed_visit_labels' => 'array',
        'allowed_provider_types' => 'array',
    ];

    /**
     * Check if this service type uses fixed visits per care plan.
     */
    public function isFixedVisits(): bool
    {
        return $this->scheduling_mode === self::SCHEDULING_MODE_FIXED_VISITS;
    }

    /**
     * Check if this service type uses weekly frequency scheduling.
     */
    public function isWeeklyScheduled(): bool
    {
        return $this->scheduling_mode === self::SCHEDULING_MODE_WEEKLY
            || $this->scheduling_mode === null;
    }

    /**
     * Check if this service type is primarily provided by SSPO.
     * Typically includes: Nursing, Allied Health (OT, PT, SLP, SW, RD)
     */
    public function isSspoOwned(): bool
    {
        if ($this->preferred_provider) {
            return $this->preferred_provider === self::PROVIDER_SSPO;
        }

        // Default based on category if not explicitly set
        $sspoCategories = ['nursing', 'rehab', 'therapy', 'allied_health'];
        $sspoCodes = ['RN', 'RPN', 'OT', 'PT', 'SLP', 'SW', 'RD', 'RT', 'RPM'];

        return in_array(strtolower($this->category ?? ''), $sspoCategories)
            || in_array(strtoupper($this->code ?? ''), $sspoCodes);
    }

    /**
     * Check if this service type is primarily provided by SPO.
     * Typically includes: PSW, Homemaking, Behaviour Support
     */
    public function isSpoOwned(): bool
    {
        if ($this->preferred_provider) {
            return $this->preferred_provider === self::PROVIDER_SPO;
        }

        // Default based on category if not explicitly set
        $spoCategories = ['psw', 'personal_support', 'homemaking', 'behaviour', 'behavioral'];
        $spoCodes = ['PSW', 'HM', 'BS'];

        return in_array(strtolower($this->category ?? ''), $spoCategories)
            || in_array(strtoupper($this->code ?? ''), $spoCodes);
    }

    /**
     * Check if this service allows the specified provider type.
     */
    public function allowsProviderType(string $providerType): bool
    {
        // If allowed_provider_types is set, check it
        if (!empty($this->allowed_provider_types)) {
            return in_array($providerType, $this->allowed_provider_types);
        }

        // Fall back to preferred_provider logic
        if ($this->preferred_provider === self::PROVIDER_EITHER) {
            return true;
        }

        return $this->preferred_provider === $providerType;
    }

    /**
     * Check if this service is delivered remotely.
     */
    public function isRemote(): bool
    {
        return $this->delivery_mode === self::DELIVERY_REMOTE;
    }

    /**
     * Check if this service can be delivered in person.
     */
    public function isInPerson(): bool
    {
        return $this->delivery_mode === self::DELIVERY_IN_PERSON
            || $this->delivery_mode === self::DELIVERY_EITHER
            || empty($this->delivery_mode);
    }

    /**
     * Get organizations that offer this service type.
     */
    public function organizations()
    {
        return $this->belongsToMany(ServiceProviderOrganization::class, 'organization_service_types')
            ->withPivot(['is_primary', 'metadata'])
            ->withTimestamps();
    }

    /**
     * Get the label for a specific visit number (1-indexed).
     *
     * @param int $visitNumber Visit number (1, 2, etc.)
     * @return string|null The label or null if not defined
     */
    public function getVisitLabel(int $visitNumber): ?string
    {
        if (!$this->fixed_visit_labels || !is_array($this->fixed_visit_labels)) {
            return null;
        }

        // Convert to 0-indexed
        return $this->fixed_visit_labels[$visitNumber - 1] ?? null;
    }

    public function serviceCategory()
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function careBundles()
    {
        return $this->belongsToMany(CareBundle::class)
            ->withPivot(['default_frequency_per_week', 'default_provider_org_id', 'assignment_type', 'role_required'])
            ->withTimestamps();
    }

    public function serviceAssignments()
    {
        return $this->hasMany(ServiceAssignment::class);
    }

    /**
     * Required skills for this service type (STAFF-011)
     */
    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'service_type_skills')
            ->withPivot(['is_required', 'minimum_proficiency'])
            ->withTimestamps();
    }

    /**
     * Get required skills only
     */
    public function requiredSkills()
    {
        return $this->skills()->wherePivot('is_required', true);
    }

    /**
     * Check if a user has all required skills for this service type
     */
    public function userHasRequiredSkills(User $user): bool
    {
        $requiredSkillIds = $this->requiredSkills()->pluck('skills.id');

        if ($requiredSkillIds->isEmpty()) {
            return true;
        }

        $userSkillIds = $user->skills()
            ->whereIn('skills.id', $requiredSkillIds)
            ->where(function ($q) {
                $q->whereNull('staff_skills.expires_at')
                    ->orWhere('staff_skills.expires_at', '>', now());
            })
            ->pluck('skills.id');

        return $requiredSkillIds->diff($userSkillIds)->isEmpty();
    }

    /**
     * Get the metadata entries for this service type.
     */
    public function metadata()
    {
        return $this->hasMany(ServiceMetadata::class);
    }

    /**
     * Get a specific metadata value.
     */
    public function getMeta(string $key, $default = null)
    {
        $meta = $this->metadata()->where('key', $key)->first();
        return $meta ? $meta->typed_value : $default;
    }

    /**
     * Set a metadata value.
     */
    public function setMeta(string $key, $value, string $valueType = 'string'): ServiceMetadata
    {
        return ServiceMetadata::updateOrCreate(
            ['service_type_id' => $this->id, 'key' => $key],
            ['value' => (string) $value, 'value_type' => $valueType]
        );
    }
}
