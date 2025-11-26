<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PatientQueue - Manages patient workflow from intake to active care
 *
 * Implements a Workday-style queue management system where patients progress
 * through defined stages from initial intake through to having their care
 * bundle built and transitioning to an active patient profile.
 *
 * Queue Status Flow:
 * pending_intake -> triage_in_progress -> triage_complete -> tnp_in_progress
 *   -> tnp_complete -> bundle_building -> bundle_review -> bundle_approved -> transitioned
 */
class PatientQueue extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'patient_queue';

    protected $fillable = [
        'patient_id',
        'queue_status',
        'assigned_coordinator_id',
        'priority',
        'entered_queue_at',
        'triage_completed_at',
        'tnp_completed_at',
        'bundle_started_at',
        'bundle_completed_at',
        'transitioned_at',
        'queue_metadata',
        'notes',
    ];

    protected $casts = [
        'priority' => 'integer',
        'entered_queue_at' => 'datetime',
        'triage_completed_at' => 'datetime',
        'tnp_completed_at' => 'datetime',
        'bundle_started_at' => 'datetime',
        'bundle_completed_at' => 'datetime',
        'transitioned_at' => 'datetime',
        'queue_metadata' => 'array',
    ];

    /**
     * Valid queue statuses.
     */
    public const STATUSES = [
        'pending_intake',
        'triage_in_progress',
        'triage_complete',
        'tnp_in_progress',
        'tnp_complete',
        'bundle_building',
        'bundle_review',
        'bundle_approved',
        'transitioned',
    ];

    /**
     * Status display names.
     */
    public const STATUS_LABELS = [
        'pending_intake' => 'Pending Intake',
        'triage_in_progress' => 'Triage In Progress',
        'triage_complete' => 'Triage Complete',
        'tnp_in_progress' => 'TNP Assessment In Progress',
        'tnp_complete' => 'TNP Complete - Ready for Bundle',
        'bundle_building' => 'Building Care Bundle',
        'bundle_review' => 'Bundle Under Review',
        'bundle_approved' => 'Bundle Approved',
        'transitioned' => 'Transitioned to Active',
    ];

    /**
     * Valid status transitions (from => [valid next statuses]).
     */
    public const VALID_TRANSITIONS = [
        'pending_intake' => ['triage_in_progress'],
        'triage_in_progress' => ['triage_complete', 'pending_intake'],
        'triage_complete' => ['tnp_in_progress'],
        'tnp_in_progress' => ['tnp_complete', 'triage_complete'],
        'tnp_complete' => ['bundle_building'],
        'bundle_building' => ['bundle_review', 'tnp_complete'],
        'bundle_review' => ['bundle_approved', 'bundle_building'],
        'bundle_approved' => ['transitioned'],
        'transitioned' => [], // Terminal state
    ];

    /**
     * Get the patient this queue entry belongs to.
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the assigned coordinator.
     */
    public function assignedCoordinator()
    {
        return $this->belongsTo(User::class, 'assigned_coordinator_id');
    }

    /**
     * Get the transition history for this queue entry.
     */
    public function transitions()
    {
        return $this->hasMany(QueueTransition::class)->orderBy('created_at', 'desc');
    }

    /**
     * Check if a transition to the given status is valid.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $validTransitions = self::VALID_TRANSITIONS[$this->queue_status] ?? [];
        return in_array($newStatus, $validTransitions);
    }

    /**
     * Transition to a new status.
     *
     * @throws \InvalidArgumentException If the transition is not valid
     */
    public function transitionTo(string $newStatus, ?int $userId = null, ?string $reason = null, ?array $context = null): self
    {
        if (!$this->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(
                "Invalid transition from '{$this->queue_status}' to '{$newStatus}'"
            );
        }

        $oldStatus = $this->queue_status;

        // Create transition record
        $this->transitions()->create([
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'transitioned_by' => $userId,
            'transition_reason' => $reason,
            'context' => $context,
        ]);

        // Update status and relevant timestamps
        $this->queue_status = $newStatus;
        $this->updateStatusTimestamp($newStatus);
        $this->save();

        return $this;
    }

    /**
     * Update the appropriate timestamp based on status.
     */
    protected function updateStatusTimestamp(string $status): void
    {
        $now = now();

        match ($status) {
            'triage_complete' => $this->triage_completed_at = $now,
            'tnp_complete' => $this->tnp_completed_at = $now,
            'bundle_building' => $this->bundle_started_at = $now,
            'bundle_approved' => $this->bundle_completed_at = $now,
            'transitioned' => $this->transitioned_at = $now,
            default => null,
        };
    }

    /**
     * Get the display label for the current status.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->queue_status] ?? $this->queue_status;
    }

    /**
     * Check if the patient is still in queue (not transitioned).
     */
    public function isInQueue(): bool
    {
        return $this->queue_status !== 'transitioned';
    }

    /**
     * Check if the patient is ready for bundle building.
     */
    public function isReadyForBundle(): bool
    {
        return $this->queue_status === 'tnp_complete';
    }

    /**
     * Get the time spent in queue.
     */
    public function getTimeInQueueAttribute(): ?int
    {
        if (!$this->entered_queue_at) {
            return null;
        }

        $endTime = $this->transitioned_at ?? now();
        return $this->entered_queue_at->diffInMinutes($endTime);
    }

    /**
     * Scope to get patients in queue (not transitioned).
     */
    public function scopeInQueue($query)
    {
        return $query->where('queue_status', '!=', 'transitioned');
    }

    /**
     * Scope to get patients ready for bundle building.
     */
    public function scopeReadyForBundle($query)
    {
        return $query->where('queue_status', 'tnp_complete');
    }

    /**
     * Scope to get patients by status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('queue_status', $status);
    }

    /**
     * Scope to get patients assigned to a coordinator.
     */
    public function scopeAssignedTo($query, int $coordinatorId)
    {
        return $query->where('assigned_coordinator_id', $coordinatorId);
    }

    /**
     * Scope to order by priority (highest first).
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'asc')->orderBy('entered_queue_at', 'asc');
    }

    /**
     * Get metadata value.
     */
    public function getMeta(string $key, $default = null)
    {
        return data_get($this->queue_metadata ?? [], $key, $default);
    }

    /**
     * Set metadata value.
     */
    public function setMeta(string $key, $value): self
    {
        $metadata = $this->queue_metadata ?? [];
        data_set($metadata, $key, $value);
        $this->queue_metadata = $metadata;
        return $this;
    }
}
