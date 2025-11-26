<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Skill extends Model
{
    use HasFactory, SoftDeletes;

    // Skill categories
    public const CATEGORY_CLINICAL = 'clinical';
    public const CATEGORY_PERSONAL_SUPPORT = 'personal_support';
    public const CATEGORY_SPECIALIZED = 'specialized';
    public const CATEGORY_ADMINISTRATIVE = 'administrative';
    public const CATEGORY_LANGUAGE = 'language';

    protected $fillable = [
        'name',
        'code',
        'category',
        'description',
        'requires_certification',
        'renewal_period_months',
        'is_active',
    ];

    protected $casts = [
        'requires_certification' => 'boolean',
        'renewal_period_months' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Staff members with this skill
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'staff_skills')
            ->withPivot([
                'proficiency_level',
                'certified_at',
                'expires_at',
                'verified_by',
                'verified_at',
                'certification_number',
                'certification_document_path',
                'notes'
            ])
            ->withTimestamps();
    }

    /**
     * Service types that require this skill
     */
    public function serviceTypes(): BelongsToMany
    {
        return $this->belongsToMany(ServiceType::class, 'service_type_skills')
            ->withPivot('is_required')
            ->withTimestamps();
    }

    /**
     * Scope: Only active skills
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by category
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope: Skills requiring certification
     */
    public function scopeRequiresCertification($query)
    {
        return $query->where('requires_certification', true);
    }

    /**
     * Scope: Clinical skills (RN-level)
     */
    public function scopeClinical($query)
    {
        return $query->where('category', self::CATEGORY_CLINICAL);
    }

    /**
     * Scope: Personal support skills (PSW-level)
     */
    public function scopePersonalSupport($query)
    {
        return $query->where('category', self::CATEGORY_PERSONAL_SUPPORT);
    }

    /**
     * Scope: Specialized certifications
     */
    public function scopeSpecialized($query)
    {
        return $query->where('category', self::CATEGORY_SPECIALIZED);
    }

    /**
     * Get category display name
     */
    public function getCategoryLabelAttribute(): string
    {
        return match($this->category) {
            self::CATEGORY_CLINICAL => 'Clinical',
            self::CATEGORY_PERSONAL_SUPPORT => 'Personal Support',
            self::CATEGORY_SPECIALIZED => 'Specialized Certification',
            self::CATEGORY_ADMINISTRATIVE => 'Administrative',
            self::CATEGORY_LANGUAGE => 'Language',
            default => ucfirst($this->category),
        };
    }

    /**
     * Check if skill has expiry tracking
     */
    public function hasExpiry(): bool
    {
        return $this->renewal_period_months !== null;
    }
}
