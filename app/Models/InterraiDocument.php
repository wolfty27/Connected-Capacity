<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * InterraiDocument Model
 *
 * Represents documents attached to InterRAI assessments including:
 * - Uploaded PDF assessment forms
 * - External IAR document ID links
 * - Other supporting attachments
 *
 * @property int $id
 * @property int $interrai_assessment_id
 * @property string $document_type
 * @property string|null $file_path
 * @property string|null $original_filename
 * @property string|null $mime_type
 * @property int|null $file_size
 * @property string|null $external_iar_id
 * @property int|null $uploaded_by
 * @property \Carbon\Carbon|null $uploaded_at
 * @property array|null $metadata
 */
class InterraiDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'interrai_assessment_id',
        'document_type',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'external_iar_id',
        'uploaded_by',
        'uploaded_at',
        'metadata',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'uploaded_at' => 'datetime',
        'metadata' => 'array',
    ];

    // Document types
    public const TYPE_PDF = 'pdf';
    public const TYPE_EXTERNAL_IAR = 'external_iar_id';
    public const TYPE_ATTACHMENT = 'attachment';

    // Allowed MIME types for uploads
    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
    ];

    // Maximum file size (10MB)
    public const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(InterraiAssessment::class, 'interrai_assessment_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get the full URL for the document file.
     */
    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        return Storage::url($this->file_path);
    }

    /**
     * Get human-readable file size.
     */
    public function getFormattedFileSizeAttribute(): ?string
    {
        if (!$this->file_size) {
            return null;
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 2) . ' ' . $units[$index];
    }

    /**
     * Check if this is an external IAR link.
     */
    public function getIsExternalLinkAttribute(): bool
    {
        return $this->document_type === self::TYPE_EXTERNAL_IAR;
    }

    /**
     * Check if this is an uploaded file.
     */
    public function getIsUploadedFileAttribute(): bool
    {
        return in_array($this->document_type, [self::TYPE_PDF, self::TYPE_ATTACHMENT]);
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Delete the associated file from storage.
     */
    public function deleteFile(): bool
    {
        if ($this->file_path && Storage::exists($this->file_path)) {
            return Storage::delete($this->file_path);
        }

        return false;
    }

    /**
     * Get storage path for a patient's documents.
     */
    public static function getStoragePath(int $patientId): string
    {
        return "interrai-documents/{$patientId}";
    }

    /**
     * Convert to API response array.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'assessment_id' => $this->interrai_assessment_id,
            'document_type' => $this->document_type,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'formatted_file_size' => $this->formatted_file_size,
            'external_iar_id' => $this->external_iar_id,
            'is_external_link' => $this->is_external_link,
            'uploaded_by' => $this->uploader?->name,
            'uploaded_at' => $this->uploaded_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
