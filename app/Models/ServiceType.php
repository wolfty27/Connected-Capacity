<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'category',
        'category_id',
        'default_duration_minutes',
        'description',
        'cost_code',
        'cost_driver',
        'cost_per_visit',
        'source',
        'active',
    ];

    protected $casts = [
        'default_duration_minutes' => 'integer',
        'cost_per_visit' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function category()
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
