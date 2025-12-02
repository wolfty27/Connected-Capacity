<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * QinRecord - Represents an officially issued Quality Improvement Notice from OHaH.
 *
 * QINs are compliance documents issued by Ontario Health at Home when an SPO
 * breaches performance band thresholds on Schedule 4 indicators:
 * - Referral Acceptance Rate
 * - Time-to-First-Service
 * - Missed Care Rate
 * - Direct Care FTE Compliance
 *
 * The SPO must respond with a Quality Improvement Plan (QIP) by the due date.
 *
 * Status workflow: open -> submitted -> under_review -> closed
 */
class QinRecord extends Model
{
    use HasFactory;

    protected $table = 'qin_records';

    // Status constants
    public const STATUS_OPEN = 'open';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_CLOSED = 'closed';

    // Source constants
    public const SOURCE_OHAH_WEBHOOK = 'ohah_webhook';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_SEEDED = 'seeded';

    // RFP-defined indicators that can trigger QINs
    public const INDICATORS = [
        'referral_acceptance' => 'Referral Acceptance Rate',
        'time_to_first_service' => 'Time-to-First-Service',
        'missed_care' => 'Missed Care Rate',
        'fte_compliance' => 'Direct Care FTE Compliance',
    ];

    // Band breach descriptions
    public const BAND_BREACHES = [
        'referral_acceptance' => [
            'B' => 'Band B (95-99.9%)',
            'C' => 'Band C (<95%)',
        ],
        'time_to_first_service' => [
            'B' => 'Band B (24-48h)',
            'C' => 'Band C (>48h)',
        ],
        'missed_care' => [
            'B' => 'Band B (0.01-0.5%)',
            'C' => 'Band C (>0.5%)',
        ],
        'fte_compliance' => [
            'B' => 'Band B (75-79%)',
            'C' => 'Band C (<75%)',
        ],
    ];

    protected $fillable = [
        'organization_id',
        'qin_number',
        'indicator',
        'band_breach',
        'metric_value',
        'evidence_period_start',
        'evidence_period_end',
        'evidence_service_assignment_id',
        'issued_date',
        'qip_due_date',
        'closed_at',
        'status',
        'ohah_contact',
        'notes',
        'source',
    ];

    protected $casts = [
        'issued_date' => 'date',
        'qip_due_date' => 'date',
        'closed_at' => 'datetime',
        'metric_value' => 'decimal:2',
        'evidence_period_start' => 'datetime',
        'evidence_period_end' => 'datetime',
    ];

    /**
     * Get the organization that this QIN was issued to.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(ServiceProviderOrganization::class, 'organization_id');
    }

    /**
     * Scope for active (non-closed) QINs.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_OPEN,
            self::STATUS_SUBMITTED,
            self::STATUS_UNDER_REVIEW,
        ]);
    }

    /**
     * Scope for open QINs requiring action.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Scope for submitted QINs pending OHaH review.
     */
    public function scopePendingReview($query)
    {
        return $query->whereIn('status', [
            self::STATUS_SUBMITTED,
            self::STATUS_UNDER_REVIEW,
        ]);
    }

    /**
     * Scope for closed QINs.
     */
    public function scopeClosed($query)
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    /**
     * Check if this QIN is overdue for QIP submission.
     */
    public function isOverdue(): bool
    {
        if ($this->status !== self::STATUS_OPEN || !$this->qip_due_date) {
            return false;
        }

        return $this->qip_due_date->isPast();
    }

    /**
     * Get days until QIP is due (negative if overdue).
     */
    public function daysUntilDue(): ?int
    {
        if (!$this->qip_due_date) {
            return null;
        }

        return now()->startOfDay()->diffInDays($this->qip_due_date, false);
    }

    /**
     * Get human-readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN => 'Open - Action Required',
            self::STATUS_SUBMITTED => 'QIP Submitted',
            self::STATUS_UNDER_REVIEW => 'Under OHaH Review',
            self::STATUS_CLOSED => 'Closed',
            default => $this->status,
        };
    }

    /**
     * Generate the next QIN number for an organization.
     */
    public static function generateQinNumber(int $organizationId): string
    {
        $year = now()->year;
        $count = static::where('organization_id', $organizationId)
            ->whereYear('created_at', $year)
            ->count();

        return sprintf('QIN-%d-%03d', $year, $count + 1);
    }
}
