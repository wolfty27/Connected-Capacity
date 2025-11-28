<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PatientNote Model
 *
 * Stores clinical notes and narrative summaries for patients.
 * Replaces the legacy TransitionNeedsProfile narrative functionality
 * with a more flexible note-based system.
 *
 * @property int $id
 * @property int $patient_id
 * @property int|null $author_id
 * @property string $source
 * @property string $note_type
 * @property string $content
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PatientNote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'author_id',
        'source',
        'note_type',
        'content',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Note types
    public const TYPE_SUMMARY = 'summary';
    public const TYPE_UPDATE = 'update';
    public const TYPE_CONTACT = 'contact';
    public const TYPE_CLINICAL = 'clinical';
    public const TYPE_GENERAL = 'general';

    // Common sources
    public const SOURCE_OHAH = 'Ontario Health atHome';
    public const SOURCE_OHAH_INTAKE = 'Ontario Health atHome Intake';

    /**
     * Get the patient that owns the note.
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the author (user) who created the note.
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Scope to get summary notes.
     */
    public function scopeSummaries($query)
    {
        return $query->where('note_type', self::TYPE_SUMMARY);
    }

    /**
     * Scope to get non-summary notes.
     */
    public function scopeUpdates($query)
    {
        return $query->where('note_type', '!=', self::TYPE_SUMMARY);
    }

    /**
     * Check if this is a summary note.
     */
    public function isSummary(): bool
    {
        return $this->note_type === self::TYPE_SUMMARY;
    }

    /**
     * Get a formatted representation for API responses.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'author_id' => $this->author_id,
            'author_name' => $this->author?->name,
            'source' => $this->source,
            'note_type' => $this->note_type,
            'content' => $this->content,
            'is_summary' => $this->isSummary(),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_formatted' => $this->created_at?->format('M j, Y g:i A'),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
