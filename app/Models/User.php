<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_HOSPITAL = 'hospital';
    public const ROLE_RETIREMENT_HOME = 'retirement-home';
    public const ROLE_SPO_ADMIN = 'SPO_ADMIN';
    public const ROLE_FIELD_STAFF = 'FIELD_STAFF';
    public const ROLE_PATIENT = 'patient';
    public const ROLE_SPO_COORDINATOR = 'SPO_COORDINATOR';
    public const ROLE_SSPO_ADMIN = 'SSPO_ADMIN';
    public const ROLE_SSPO_COORDINATOR = 'SSPO_COORDINATOR';
    public const ROLE_ORG_ADMIN = 'ORG_ADMIN';
    public const ROLE_MASTER = 'MASTER';

    // Staff status constants
    public const STAFF_STATUS_ACTIVE = 'active';
    public const STAFF_STATUS_INACTIVE = 'inactive';
    public const STAFF_STATUS_ON_LEAVE = 'on_leave';
    public const STAFF_STATUS_TERMINATED = 'terminated';

    public function isMaster(): bool
    {
        return $this->role === self::ROLE_MASTER;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone_number',
        'country',
        'image',
        'address',
        'city',
        'state',
        'timezone',
        'zipcode',
        'latitude',
        'longitude',
        'calendly_status',
        'calendly_username',
        'organization_id',
        'organization_role',
        'employment_type',
        'fte_value',
        // Staff-specific fields (STAFF-003)
        'external_id',
        'staff_status',
        'hire_date',
        'termination_date',
        'max_weekly_hours',
        // Workforce management fields (WORKFORCE-003)
        'staff_role_id',
        'employment_type_id',
        'job_satisfaction',
        'job_satisfaction_recorded_at',
        'external_staff_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'hire_date' => 'date',
        'termination_date' => 'date',
        'max_weekly_hours' => 'decimal:2',
        'fte_value' => 'decimal:2',
        'job_satisfaction_recorded_at' => 'date',
    ];

    public function hospitals()
    {
        return $this->hasOne(Hospital::class,'user_id');
    }

    public function organization()
    {
        return $this->belongsTo(ServiceProviderOrganization::class, 'organization_id');
    }

    /**
     * Staff role (RN, RPN, PSW, OT, PT, SLP, SW, etc.)
     */
    public function staffRole(): BelongsTo
    {
        return $this->belongsTo(StaffRole::class);
    }

    /**
     * Employment type (Full-Time, Part-Time, Casual, SSPO)
     */
    public function employmentTypeModel(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class, 'employment_type_id');
    }

    public function memberships()
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function coordinatedPatients()
    {
        return $this->hasMany(Patient::class, 'primary_coordinator_id');
    }

    public function assignedServiceAssignments()
    {
        return $this->hasMany(ServiceAssignment::class, 'assigned_user_id');
    }

    public function handledRpmAlerts()
    {
        return $this->hasMany(RpmAlert::class, 'handled_by');
    }

    public function careAssignments()
    {
        return $this->hasMany(CareAssignment::class, 'assigned_user_id');
    }

    public function assignedTasks()
    {
        return $this->hasMany(Task::class, 'assigned_to_user_id');
    }

    // ==========================================
    // Staff Skills & Availability Relationships
    // ==========================================

    /**
     * Skills associated with this staff member
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'staff_skills')
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
     * Availability windows for this staff member
     */
    public function availabilities(): HasMany
    {
        return $this->hasMany(StaffAvailability::class);
    }

    /**
     * Unavailability periods for this staff member
     */
    public function unavailabilities(): HasMany
    {
        return $this->hasMany(StaffUnavailability::class);
    }

    // ==========================================
    // Staff-Related Scopes (STAFF-007)
    // ==========================================

    /**
     * Scope: Only field staff
     */
    public function scopeFieldStaff($query)
    {
        return $query->where('role', self::ROLE_FIELD_STAFF);
    }

    /**
     * Scope: Active staff members
     */
    public function scopeActiveStaff($query)
    {
        return $query->where('staff_status', self::STAFF_STATUS_ACTIVE);
    }

    /**
     * Scope: Staff with a specific skill
     */
    public function scopeWithSkill($query, string $skillCode)
    {
        return $query->whereHas('skills', function ($q) use ($skillCode) {
            $q->where('code', $skillCode);
        });
    }

    /**
     * Scope: Staff with valid (non-expired) skill
     */
    public function scopeWithValidSkill($query, string $skillCode)
    {
        return $query->whereHas('skills', function ($q) use ($skillCode) {
            $q->where('code', $skillCode)
              ->where(function ($subQ) {
                  $subQ->whereNull('staff_skills.expires_at')
                       ->orWhere('staff_skills.expires_at', '>', Carbon::today());
              });
        });
    }

    /**
     * Scope: Staff available on a specific date/time
     */
    public function scopeAvailableOn($query, Carbon $date, ?string $startTime = null, ?string $endTime = null)
    {
        return $query->whereHas('availabilities', function ($q) use ($date, $startTime, $endTime) {
            $q->activeOn($date);
            if ($startTime && $endTime) {
                $q->coveringTime($startTime, $endTime);
            }
        })->whereDoesntHave('unavailabilities', function ($q) use ($date) {
            $q->approved()
              ->onDate($date);
        });
    }

    /**
     * Scope: Staff in a specific organization
     */
    public function scopeInOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * Scope: Staff with available capacity (hours)
     */
    public function scopeWithAvailableCapacity($query, float $minHours = 0)
    {
        // This is a simplified version - full implementation in FteComplianceService
        return $query->where('staff_status', self::STAFF_STATUS_ACTIVE)
                     ->where('max_weekly_hours', '>', $minHours);
    }

    // ==========================================
    // Staff Capacity Computed Properties
    // ==========================================

    /**
     * Get current weekly assigned hours
     */
    public function getCurrentWeeklyHoursAttribute(): float
    {
        return $this->assignedServiceAssignments()
            ->whereIn('status', ['in_progress', 'planned'])
            ->whereBetween('scheduled_start', [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek()
            ])
            ->get()
            ->sum(function ($assignment) {
                if ($assignment->scheduled_start && $assignment->scheduled_end) {
                    return $assignment->scheduled_start->diffInMinutes($assignment->scheduled_end) / 60;
                }
                return $assignment->estimated_duration_hours ?? 0;
            });
    }

    /**
     * Get available hours for this week
     */
    public function getAvailableHoursAttribute(): float
    {
        return max(0, ($this->max_weekly_hours ?? 40) - $this->current_weekly_hours);
    }

    /**
     * Get FTE utilization percentage
     */
    public function getFteUtilizationAttribute(): float
    {
        $maxHours = $this->max_weekly_hours ?? 40;
        if ($maxHours <= 0) {
            return 0;
        }
        return min(100, ($this->current_weekly_hours / $maxHours) * 100);
    }

    // ==========================================
    // Staff Skill Helper Methods
    // ==========================================

    /**
     * Check if staff has a specific skill
     */
    public function hasSkill(string $skillCode): bool
    {
        return $this->skills()->where('code', $skillCode)->exists();
    }

    /**
     * Check if staff has valid (non-expired) skill
     */
    public function hasValidSkill(string $skillCode): bool
    {
        return $this->skills()
            ->where('code', $skillCode)
            ->where(function ($q) {
                $q->whereNull('staff_skills.expires_at')
                  ->orWhere('staff_skills.expires_at', '>', Carbon::today());
            })
            ->exists();
    }

    /**
     * Get skills expiring within N days
     */
    public function getExpiringSkills(int $days = 30)
    {
        return $this->skills()
            ->wherePivot('expires_at', '<=', Carbon::today()->addDays($days))
            ->wherePivot('expires_at', '>', Carbon::today())
            ->get();
    }

    /**
     * Get expired skills
     */
    public function getExpiredSkills()
    {
        return $this->skills()
            ->wherePivot('expires_at', '<', Carbon::today())
            ->get();
    }

    // ==========================================
    // Staff Availability Helper Methods
    // ==========================================

    /**
     * Check if staff is available at a specific datetime
     */
    public function isAvailableAt(Carbon $datetime): bool
    {
        // Check if has availability window
        $hasAvailability = $this->availabilities()
            ->activeOn($datetime)
            ->where('start_time', '<=', $datetime->format('H:i:s'))
            ->where('end_time', '>=', $datetime->format('H:i:s'))
            ->exists();

        if (!$hasAvailability) {
            return false;
        }

        // Check for approved unavailability
        $hasUnavailability = $this->unavailabilities()
            ->approved()
            ->where('start_datetime', '<=', $datetime)
            ->where('end_datetime', '>=', $datetime)
            ->exists();

        return !$hasUnavailability;
    }

    /**
     * Check if staff is on leave
     */
    public function isOnLeave(): bool
    {
        return $this->staff_status === self::STAFF_STATUS_ON_LEAVE
            || $this->unavailabilities()
                ->approved()
                ->current()
                ->exists();
    }

    /**
     * Check if staff is active
     */
    public function isActiveStaff(): bool
    {
        return $this->staff_status === self::STAFF_STATUS_ACTIVE;
    }

    /**
     * Get weekly availability hours (from availability windows)
     */
    public function getWeeklyAvailabilityHoursAttribute(): float
    {
        return $this->availabilities()
            ->currentlyEffective()
            ->get()
            ->sum('duration_hours');
    }
}
