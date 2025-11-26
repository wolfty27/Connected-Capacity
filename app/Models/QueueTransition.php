<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * QueueTransition - Audit trail for patient queue status changes
 *
 * Records every status transition in the patient queue for audit purposes,
 * including who made the change, when, and why.
 */
class QueueTransition extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_queue_id',
        'from_status',
        'to_status',
        'transitioned_by',
        'transition_reason',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    /**
     * Get the queue entry this transition belongs to.
     */
    public function patientQueue()
    {
        return $this->belongsTo(PatientQueue::class);
    }

    /**
     * Get the user who made this transition.
     */
    public function transitionedBy()
    {
        return $this->belongsTo(User::class, 'transitioned_by');
    }

    /**
     * Get the patient through the queue entry.
     */
    public function patient()
    {
        return $this->hasOneThrough(
            Patient::class,
            PatientQueue::class,
            'id',           // Foreign key on patient_queue
            'id',           // Foreign key on patients
            'patient_queue_id', // Local key on queue_transitions
            'patient_id'    // Local key on patient_queue
        );
    }

    /**
     * Get the display label for the from status.
     */
    public function getFromStatusLabelAttribute(): string
    {
        return PatientQueue::STATUS_LABELS[$this->from_status] ?? $this->from_status;
    }

    /**
     * Get the display label for the to status.
     */
    public function getToStatusLabelAttribute(): string
    {
        return PatientQueue::STATUS_LABELS[$this->to_status] ?? $this->to_status;
    }
}
